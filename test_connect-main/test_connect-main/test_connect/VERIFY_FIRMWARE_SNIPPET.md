# Adding walk-up 3-factor verification to CFMS_V3.ino

The PHP backend already has `verify_user.php` deployed at:
```
http://192.168.1.23/test_connect/verify_user.php
```

This document is the Arduino-side change needed in `CFMS_V3.ino` to call
that endpoint. After this change, tapping a card on the cabinet will:

1. Read the RFID UID
2. Search the fingerprint sensor for the matching template
3. Read the keypad PIN
4. POST all three to `verify_user.php`
5. On `GRANTED`, the server arms the relay and the existing
   `checkRelay()` polling loop fires the solenoid

No changes are required to the existing `checkRelay()` /
`activateRelay1()` / `activateRelay2()` functions — they keep doing
exactly what they already do.

---

## 1. Add the new function

Paste this anywhere below `loop()` in `CFMS_V3.ino`:

```cpp
// =====================================================================
// 3-FACTOR VERIFICATION (called from loop() when not enrolling)
// =====================================================================
// Reads RFID -> fingerprint -> keypad, POSTs to verify_user.php, and
// lets the existing checkRelay() loop fire the solenoid on GRANTED.
void verifyAtCabinet() {
    lcd.clear();
    lcd.print("Tap RFID Card");

    // 1) Wait for an RFID card (3 sec timeout — keeps loop responsive)
    uint8_t uid[7];
    uint8_t uidLength;
    if (!nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength, 3000)) {
        return; // no card this cycle
    }
    String rfidUID = formatUID(uid, uidLength);

    // 2) Place finger -> fingerSearch returns the matching slot id
    lcd.clear();
    lcd.print("Place Finger");
    int fpSlot = -1;
    uint32_t fpStart = millis();
    while (millis() - fpStart < 6000) {
        uint8_t p = finger.getImage();
        if (p == FINGERPRINT_OK) {
            if (finger.image2Tz() != FINGERPRINT_OK) break;
            if (finger.fingerSearch() != FINGERPRINT_OK) break;
            fpSlot = finger.fingerID;
            break;
        }
        delay(120);
    }
    if (fpSlot < 0) {
        lcd.clear();
        lcd.print("Finger Failed");
        delay(1500);
        return;
    }

    // 3) Read keypad PIN (6-8 digits, # confirms, * clears)
    lcd.clear();
    lcd.print("Enter Passcode");
    String pin = "";
    while (true) {
        char k = keypad.getKey();
        if (!k) { delay(20); continue; }
        if (k == '#' && pin.length() >= 6) break;
        if (k == '*') { pin = ""; lcd.setCursor(0,1); lcd.print("                "); lcd.setCursor(0,1); continue; }
        if (isDigit(k) && pin.length() < 8) {
            pin += k;
            lcd.setCursor(pin.length()-1, 1);
            lcd.print('*');
        }
    }

    // 4) POST to verify_user.php
    lcd.clear();
    lcd.print("Verifying...");
    HTTPClient http;
    http.begin(server + "verify_user.php");
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    String body = "rfid_uid=" + rfidUID +
                  "&fingerprint_id=" + String(fpSlot) +
                  "&passcode=" + pin +
                  "&relay=1"; // arm cabinet 1 on grant
    int code = http.POST(body);
    String resp = (code > 0) ? http.getString() : "";
    http.end();

    lcd.clear();
    if (resp == "GRANTED") {
        lcd.print("ACCESS GRANTED");
        // No need to drive the relay here — verify_user.php armed it
        // server-side and the existing checkRelay() poll will fire it.
    } else if (resp == "DENIED_RFID") {
        lcd.print("Unknown Card");
    } else if (resp == "DENIED_FINGER") {
        lcd.print("Wrong Finger");
    } else if (resp == "DENIED_PIN") {
        lcd.print("Wrong PIN");
    } else if (resp == "DENIED_NOT_ENROLLED") {
        lcd.print("Not Enrolled");
    } else {
        lcd.print("Verify Error");
    }
    delay(2500);
}
```

---

## 2. Wire it into `loop()`

The existing `loop()` looks like this:

```cpp
void loop(){
    checkRegisterState();
    if(active==1){
        if(step==1 && RFID_ENABLED) scanRFID();
        if(step==2 && FINGER_ENABLED) enrollFingerprint();
        if(step==3 && PASSCODE_ENABLED) enterPasscode();
    }
    delay(1000);
    checkRelay();
}
```

Add a single `else` branch so verification runs whenever there's no
admin-driven enrollment in progress:

```cpp
void loop(){
    checkRegisterState();
    if(active==1){
        if(step==1 && RFID_ENABLED) scanRFID();
        if(step==2 && FINGER_ENABLED) enrollFingerprint();
        if(step==3 && PASSCODE_ENABLED) enterPasscode();
    } else {
        verifyAtCabinet();   // <-- NEW: walk-up 3-factor
    }
    delay(1000);
    checkRelay();
}
```

That's it. No other changes.

---

## 3. Test workflow after flashing

1. **Enroll a user via the web UI**
   - Open `http://192.168.1.23/test_connect/register.html`
   - Click "Start Registration"
   - Walk to the cabinet, tap your card → place finger → enter PIN → press `#`
   - LCD shows `User Saved`

2. **Verify a user at the cabinet**
   - LCD now shows `Tap RFID Card` (the new standby state)
   - Tap your enrolled card → LCD shows `Place Finger`
   - Place the finger you enrolled → LCD shows `Enter Passcode`
   - Type the PIN you set, press `#`
   - LCD shows `ACCESS GRANTED` and the relay clicks within ~1 second

3. **Failure cases**
   - Tap an unknown card → `Unknown Card`
   - Wrong finger → `Wrong Finger`
   - Wrong PIN → `Wrong PIN`

---

## What the server does on each call

| Firmware sends | `verify_user.php` returns | DB side effect |
|---|---|---|
| Correct R+F+P | `GRANTED` | `relay.active = 1` for the chosen channel |
| Wrong PIN | `DENIED_PIN` | none |
| Wrong finger | `DENIED_FINGER` | none |
| Unknown RFID | `DENIED_RFID` | none |
| User has incomplete enrollment | `DENIED_NOT_ENROLLED` | none |
| Missing form field | `MISSING_DATA` | none |

The relay is **only ever armed on GRANTED** — denied attempts touch nothing.

---

## Summary of all server-side files in `test_connect/` (after the fix sweep)

| File | Purpose | Hardened? |
|---|---|---|
| `db.php` | DB connection + ASCII error handler | ✅ |
| `test_connection.php` | Sanity check + table verification | ✅ |
| `register.html` | Admin UI (start enrollment + remote unlock) | ✅ + UTF-8 |
| `register_start.php` | Arms enrollment | ✅ |
| `get_register_state.php` | Polled by ESP32 every loop | ✅ |
| `update_step.php` | ESP32 reports current enrollment step | ✅ allowlist |
| `get_next_fingerid.php` | Allocates next free fingerprint slot | ✅ null guard |
| `store_user.php` | Saves enrolled user (now bcrypts PIN) | ✅ prepared + bcrypt |
| `finish_register.php` | Clears enrollment flag | ✅ idempotent |
| `get_users.php` | Lists enrolled users | ✅ richer schema |
| `get_rfids.php` | Lists enrolled RFID UIDs | ✅ |
| `activate_relay.php` | Admin-driven remote unlock | ✅ pre-check |
| `check_relay.php` | Polled by ESP32 every loop | ✅ |
| `reset_relay.php` | ESP32 clears the flag after firing | ✅ selective |
| **`verify_user.php`** | **NEW: walk-up 3-factor verification** | ✅ |
