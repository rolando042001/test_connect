// =============================================================================
// CFMS_V3 — patched for walk-up 3-factor verification
//
// Original sketch by the project team. Patched additions:
//   - WiFi credentials + server URL set for the local XAMPP host
//   - Non-blocking WiFi connect (15s timeout, then proceeds anyway)
//   - One-time fingerprint sensor wipe on first boot (NVS-gated) to clear
//     any stale templates from earlier testing — enrollments persist after
//   - verifyAtCabinet() walk-up flow that POSTs to verify_user.php
//   - loop() runs verifyAtCabinet() whenever no admin enrollment is armed
//
// Pin map and existing flow are UNCHANGED from the team's original V3.
// The relay path is unchanged: verify_user.php arms the relay table on
// GRANTED, and the existing checkRelay() poll fires GPIO 4 / GPIO 2.
// =============================================================================

#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <Keypad.h>
#include <SPI.h>
#include <Adafruit_PN532.h>
#include <Adafruit_Fingerprint.h>
#include <Preferences.h>

// WIFI
const char* ssid = "ZTE_2.4G_AkYeRj";
const char* password = "P4RgQRAu";

bool RFID_ENABLED = true;
bool FINGER_ENABLED = true;
bool PASSCODE_ENABLED = true;

// Server — this PC running XAMPP. The cabinet must be on the same LAN.
String server = "http://192.168.1.23/test_connect/";

// RELAY (GPIO outputs that drive the 2-channel relay module)
#define RELAY1 4
#define RELAY2 2

// LCD
LiquidCrystal_I2C lcd(0x27,16,2);

// FINGERPRINT
HardwareSerial mySerial(2);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);

// PN532 SPI (hardware SPI: SCK=18 MISO=19 MOSI=23 CS=5)
#define PN532_SS 5
Adafruit_PN532 nfc(PN532_SS);

// KEYPAD
const byte ROWS = 4;
const byte COLS = 4;

char keys[ROWS][COLS] = {
{'1','2','3','A'},
{'4','5','6','B'},
{'7','8','9','C'},
{'*','0','#','D'}
};

byte rowPins[ROWS] = {26,25,33,32};
byte colPins[COLS] = {13,12,14,27};

Keypad keypad = Keypad(makeKeymap(keys), rowPins, colPins, ROWS, COLS);

// REGISTRATION VARIABLES
String rfidUID="";
String passcode="";
int fingerprintID=0;

int active=0;
int step=0;

// ============================================================================
// SETUP
// ============================================================================
void setup(){

  Serial.begin(115200);

  lcd.init();
  lcd.backlight();
  lcd.setCursor(0,0);
  lcd.print("Test Hardware");

  pinMode(RELAY1,OUTPUT);
  pinMode(RELAY2,OUTPUT);

  digitalWrite(RELAY1,LOW);
  digitalWrite(RELAY2,LOW);

  // -------------------- WiFi (NON-BLOCKING) -----------------------
  // Original sketch did `while(WiFi.status()!=WL_CONNECTED)` which
  // hangs forever if the AP rejects the join. We give it 15 seconds
  // and then proceed anyway so the device stays usable.
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid,password);

  lcd.setCursor(0,1);
  lcd.print("Connecting WiFi");

  uint32_t t0 = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - t0 < 15000) {
    delay(500);
    Serial.print(".");
  }

  lcd.clear();
  if (WiFi.status() == WL_CONNECTED) {
    lcd.print("WiFi OK");
    lcd.setCursor(0,1);
    lcd.print(WiFi.localIP().toString());
    Serial.print("\n[WiFi] OK ip=");
    Serial.println(WiFi.localIP());
  } else {
    lcd.print("WiFi Fail");
    lcd.setCursor(0,1);
    lcd.print("(continuing)");
    Serial.println("\n[WiFi] timeout — continuing anyway");
  }
  delay(1500);

  // -------------------- FINGERPRINT -------------------------------
  mySerial.begin(57600, SERIAL_8N1, 16, 17);
  finger.begin(57600);

  // One-time wipe of the fingerprint sensor flash. Reason: the sensor
  // may carry stale templates from earlier testing that cause
  // fingerSearch() to return wrong slot ids during verification. We
  // wipe ONCE on the first boot of this firmware, write a sentinel to
  // NVS, and skip the wipe on every subsequent boot so enrollments
  // persist across resets/power cycles.
  Preferences prefs;
  prefs.begin("cfms", false);
  if (prefs.getUInt("fp_wiped", 0) != 0xCAFE2026) {
    lcd.clear();
    lcd.print("Wiping FP DB");
    if (finger.emptyDatabase() == FINGERPRINT_OK) {
      Serial.println("[FP] database wiped");
    } else {
      Serial.println("[FP] wipe FAILED — sensor not responding?");
    }
    prefs.putUInt("fp_wiped", 0xCAFE2026);
    delay(1000);
  }
  prefs.end();

  // -------------------- NFC ---------------------------------------
  SPI.begin();
  nfc.begin();
  nfc.SAMConfig();

  lcd.clear();
  lcd.print("System Ready");
}

// ============================================================================
// LOOP
// ============================================================================
void loop(){

  checkRegisterState();

  if (active == 1) {

    if (step == 1 && RFID_ENABLED)     scanRFID();
    if (step == 2 && FINGER_ENABLED)   enrollFingerprint();
    if (step == 3 && PASSCODE_ENABLED) enterPasscode();

  } else {
    // No admin enrollment armed → run walk-up 3-factor verification.
    // Returns immediately if no card is tapped within 3 seconds, so
    // checkRelay() still gets a chance to run on every cycle.
    verifyAtCabinet();
  }

  delay(500);
  checkRelay();
}

// ============================================================================
// HELPERS
// ============================================================================
String formatUID(uint8_t *uid, uint8_t length){
  String uidString = "";
  for(int i = 0; i < length; i++){
    if(uid[i] < 0x10) uidString += "0";
    uidString += String(uid[i], HEX);
  }
  uidString.toUpperCase();
  return uidString;
}

// GET REGISTER STATE
void checkRegisterState(){
  if (WiFi.status() != WL_CONNECTED) return;
  HTTPClient http;
  String url = server + "get_register_state.php";
  http.begin(url);
  int code = http.GET();
  if(code>0){
    String payload = http.getString();
    int comma = payload.indexOf(',');
    if (comma >= 0) {
      active = payload.substring(0,comma).toInt();
      step   = payload.substring(comma+1).toInt();
    }
  }
  http.end();
}

// ============================================================================
// ENROLLMENT (admin-armed via register.html)
// ============================================================================

// STEP 1 — RFID
void scanRFID(){
  lcd.clear();
  lcd.print("Scan RFID");

  uint8_t uid[7];
  uint8_t uidLength;

  if (nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength)) {
    rfidUID = formatUID(uid,uidLength);
    lcd.clear();
    lcd.print("RFID OK");
    delay(1500);
    updateStep(2);
  }
}

// STEP 2 — FINGERPRINT
void enrollFingerprint(){
  lcd.clear();
  lcd.print("Place Finger");

  while(finger.getImage()!=FINGERPRINT_OK);
  if(finger.image2Tz(1)!=FINGERPRINT_OK) return;

  lcd.clear();
  lcd.print("Remove Finger");
  delay(2000);

  while(finger.getImage()!=FINGERPRINT_NOFINGER);

  lcd.clear();
  lcd.print("Place Again");

  while(finger.getImage()!=FINGERPRINT_OK);
  if(finger.image2Tz(2)!=FINGERPRINT_OK) return;

  if(finger.createModel()!=FINGERPRINT_OK){
    lcd.clear();
    lcd.print("Mismatch");
    delay(1500);
    return;
  }

  fingerprintID = getNextFingerID();

  if(finger.storeModel(fingerprintID)==FINGERPRINT_OK){
    lcd.clear();
    lcd.print("Finger Saved");
    updateStep(3);
  }else{
    lcd.clear();
    lcd.print("Save Failed");
  }
  delay(2000);
}

// STEP 3 — PASSCODE
void enterPasscode(){
  lcd.clear();
  lcd.print("Enter Passcode");

  passcode="";

  while(true){
    char key = keypad.getKey();
    if(key){
      if(isDigit(key) && passcode.length()<8){
        passcode += key;
        lcd.setCursor(passcode.length()-1,1);
        lcd.print("*");
      }
      if(key=='*' && passcode.length()>0){
        passcode.remove(passcode.length()-1);
        lcd.setCursor(passcode.length(),1);
        lcd.print(" ");
        lcd.setCursor(passcode.length(),1);
      }
      if(key=='#' && passcode.length()>=6){
        break;
      }
    }
  }

  storeUser();
}

// UPDATE STEP
void updateStep(int s){
  if (WiFi.status() != WL_CONNECTED) return;
  HTTPClient http;
  String url = server + "update_step.php?step="+String(s);
  http.begin(url);
  http.GET();
  http.end();
}

// STORE USER
void storeUser(){
  if (WiFi.status() != WL_CONNECTED) return;
  HTTPClient http;
  String url = server + "store_user.php";
  http.begin(url);
  http.addHeader("Content-Type","application/x-www-form-urlencoded");

  String data =
    "id="+String(fingerprintID)+
    "&rfid_uid="+rfidUID+
    "&passcode="+passcode;

  http.POST(data);
  String response = http.getString();
  Serial.println(response);
  http.end();

  finishRegister();
}

int getNextFingerID(){
  if (WiFi.status() != WL_CONNECTED) return 1;
  HTTPClient http;
  String url = server + "get_next_fingerid.php";
  http.begin(url);
  int code = http.GET();
  int id = 1;
  if(code>0){
    id = http.getString().toInt();
  }
  http.end();
  return id;
}

// FINISH REGISTER
void finishRegister(){
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    String url = server + "finish_register.php";
    http.begin(url);
    http.GET();
    http.end();
  }
  lcd.clear();
  lcd.print("User Saved");
  delay(2500);
}

// ============================================================================
// RELAY POLLING (admin override path — unchanged from original V3)
// ============================================================================
void checkRelay(){
  if (WiFi.status() != WL_CONNECTED) return;
  HTTPClient http;
  String url = server + "check_relay.php";
  http.begin(url);
  int code = http.GET();
  if(code>0){
    int relay = http.getString().toInt();
    if(relay==1) activateRelay1();
    if(relay==2) activateRelay2();
  }
  http.end();
}

void activateRelay1(){
  lcd.clear();
  lcd.print("Unlock Relay 1");
  digitalWrite(RELAY1,HIGH);
  delay(5000);
  digitalWrite(RELAY1,LOW);
  resetRelay();
}

void activateRelay2(){
  lcd.clear();
  lcd.print("Unlock Relay 2");
  digitalWrite(RELAY2,HIGH);
  delay(5000);
  digitalWrite(RELAY2,LOW);
  resetRelay();
}

void resetRelay(){
  if (WiFi.status() != WL_CONNECTED) return;
  HTTPClient http;
  String url = server + "reset_relay.php";
  http.begin(url);
  http.GET();
  http.end();
}

// ============================================================================
// WALK-UP 3-FACTOR VERIFICATION
// ============================================================================
// Reads RFID -> fingerprint -> keypad PIN, POSTs to verify_user.php, and
// lets the existing checkRelay() poll fire the solenoid on GRANTED. The
// server arms the relay table for us when verification passes.
//
// Returns immediately if no card is tapped (so the rest of loop() can run).
void verifyAtCabinet() {
  // Idle prompt
  lcd.clear();
  lcd.print("Tap RFID Card");

  // 1) Wait for an RFID card (3 sec timeout — keeps loop responsive)
  uint8_t uid[7];
  uint8_t uidLength;
  if (!nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength, 3000)) {
    return; // no card this cycle
  }
  String tappedUID = formatUID(uid, uidLength);
  Serial.println("[VERIFY] RFID tapped: " + tappedUID);

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
      Serial.print("[VERIFY] fingerSearch returned slot ");
      Serial.println(fpSlot);
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

  // 3) Read keypad PIN (6-8 digits, # confirms, * deletes)
  lcd.clear();
  lcd.print("Enter Passcode");
  String pin = "";
  uint32_t pinStart = millis();
  while (true) {
    if (millis() - pinStart > 30000) {
      lcd.clear(); lcd.print("Timeout");
      delay(1500);
      return;
    }
    char k = keypad.getKey();
    if (!k) { delay(20); continue; }
    if (k == '#' && pin.length() >= 6) break;
    if (k == '*') {
      if (pin.length() > 0) {
        pin.remove(pin.length() - 1);
        lcd.setCursor(pin.length(), 1);
        lcd.print(" ");
        lcd.setCursor(pin.length(), 1);
      }
      continue;
    }
    if (isDigit(k) && pin.length() < 8) {
      pin += k;
      lcd.setCursor(pin.length() - 1, 1);
      lcd.print('*');
    }
  }

  // 4) POST to verify_user.php
  lcd.clear();
  lcd.print("Verifying...");
  if (WiFi.status() != WL_CONNECTED) {
    lcd.clear(); lcd.print("No WiFi");
    delay(1500);
    return;
  }

  HTTPClient http;
  http.begin(server + "verify_user.php");
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  String body = "rfid_uid=" + tappedUID +
                "&fingerprint_id=" + String(fpSlot) +
                "&passcode=" + pin +
                "&relay=1";  // arm cabinet 1 on grant
  int code = http.POST(body);
  String resp = (code > 0) ? http.getString() : "";
  Serial.print("[VERIFY] response: ");
  Serial.println(resp);
  http.end();

  lcd.clear();
  if (resp == "GRANTED") {
    lcd.print("ACCESS GRANTED");
    // Relay armed server-side; existing checkRelay() poll will fire it.
  } else if (resp == "DENIED_RFID") {
    lcd.print("Unknown Card");
  } else if (resp == "DENIED_FINGER") {
    lcd.print("Wrong Finger");
  } else if (resp == "DENIED_PIN") {
    lcd.print("Wrong PIN");
  } else if (resp == "DENIED_NOT_ENROLLED") {
    lcd.print("Not Enrolled");
  } else if (resp == "MISSING_DATA") {
    lcd.print("Missing Data");
  } else {
    lcd.print("Verify Error");
  }
  delay(2500);
}
