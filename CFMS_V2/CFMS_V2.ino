/* ============================================================================
 * CFMS v3 — ESP32 Physical Cabinet controller (with ArduinoOTA)
 *
 * 3-factor authentication (matches the architecture flowchart):
 *    1) RFID  (PN532)
 *    2) Fingerprint (Adafruit Fingerprint sensor on Serial2)
 *    3) Keypad passcode
 *
 * Two modes:
 *    A) Standby — polls /CFM/Backend/check_enroll.php for an admin-armed
 *       enrollment, then walks the user through RFID -> Fingerprint ->
 *       Passcode and posts to /CFM/Backend/esp_enroll_api.php.
 *    B) Verification — when no enrollment is pending, reading any RFID card
 *       triggers a 3-factor check against /CFM/Backend/hardware_verify.php.
 *
 * OTA: After the FIRST flash over USB, subsequent uploads can use the
 * network port (default mDNS hostname: cfms-cabinet.local). The OTA
 * password is set below — change it to taste.
 *
 * Required libraries:
 *    - WiFi, HTTPClient, SPI, Wire, ESPmDNS, ArduinoOTA   (built-in / core)
 *    - Adafruit_PN532
 *    - Adafruit_Fingerprint
 *    - Keypad
 *    - LiquidCrystal_I2C
 * ==========================================================================*/

#include <WiFi.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <Wire.h>
#include <HardwareSerial.h>
#include <ESPmDNS.h>
#include <WiFiUdp.h>
#include <ArduinoOTA.h>
#include <Adafruit_PN532.h>
#include <Adafruit_Fingerprint.h>
#include <Keypad.h>
#include <LiquidCrystal_I2C.h>

// secrets.h is gitignored. Copy secrets.example.h to secrets.h and fill in
// real WiFi + OTA credentials before flashing.
#include "secrets.h"

// ---------------- WIFI ------------------------------------------------------
const char* ssid     = CFM_WIFI_SSID;
const char* password = CFM_WIFI_PASSWORD;

// ---------------- OTA -------------------------------------------------------
// mDNS hostname — the device will appear as "<OTA_HOSTNAME>.local" on the LAN.
const char* OTA_HOSTNAME = CFM_OTA_HOSTNAME;
const char* OTA_PASSWORD = CFM_OTA_PASSWORD;

// ---------------- SERVER ENDPOINTS (CFM project) ----------------------------
const String BASE_URL          = "http://192.168.1.23/CFM/Backend";
const String URL_CHECK_ENROLL  = BASE_URL + "/check_enroll.php";
const String URL_ENROLL_API    = BASE_URL + "/esp_enroll_api.php";
const String URL_RESET_ENROLL  = BASE_URL + "/reset_enroll_request.php";
const String URL_UPDATE_STEP   = BASE_URL + "/update_step.php";
const String URL_VERIFY        = BASE_URL + "/hardware_verify.php";
const String URL_CHECK_RELAY   = BASE_URL + "/check_relay.php";
const String URL_RESET_RELAY   = BASE_URL + "/reset_relay.php";

// ---------------- RELAY (cabinet solenoid) ---------------------------------
// HL-52S 1-channel relay module — opto-isolated, ACTIVE LOW.
// GPIO 15 is a strapping pin that defaults HIGH at boot, so the relay
// stays OFF during reset (good). If your relay module is ACTIVE HIGH,
// flip RELAY_ACTIVE_LOW to false.
#define RELAY_PIN          15
#define RELAY_ACTIVE_LOW   true
#define RELAY_HOLD_MS      1500   // how long to hold the solenoid open

// ---------------- PN532 (SPI) -----------------------------------------------
#define PN532_SS   5
#define PN532_RST  4
#define PN532_SCK  18
#define PN532_MOSI 23
#define PN532_MISO 19
Adafruit_PN532 nfc(PN532_SCK, PN532_MISO, PN532_MOSI, PN532_SS);

// ---------------- Fingerprint (UART2) ---------------------------------------
// R307 / AS608: TX -> GPIO16 (RX2), RX -> GPIO17 (TX2)
HardwareSerial fpSerial(2);
Adafruit_Fingerprint finger(&fpSerial);

// ---------------- LCD -------------------------------------------------------
LiquidCrystal_I2C lcd(0x27, 16, 2);

// ---------------- KEYPAD ----------------------------------------------------
const byte ROWS = 4, COLS = 4;
char keys[ROWS][COLS] = {
  {'1','2','3','A'},
  {'4','5','6','B'},
  {'7','8','9','C'},
  {'*','0','#','D'}
};
byte rowPins[ROWS] = {26, 25, 33, 32};
byte colPins[COLS] = {13, 12, 14, 27};
Keypad keypad = Keypad(makeKeymap(keys), rowPins, colPins, ROWS, COLS);

// ---------------- STATE -----------------------------------------------------
bool isEnrolling = false;
volatile bool otaInProgress = false;
uint32_t lastRelayPoll = 0;

// ===========================================================================
// OTA SETUP
// ===========================================================================
void setupOTA() {
    ArduinoOTA.setHostname(OTA_HOSTNAME);
    ArduinoOTA.setPassword(OTA_PASSWORD);

    ArduinoOTA.onStart([]() {
        otaInProgress = true;
        String type = (ArduinoOTA.getCommand() == U_FLASH) ? "sketch" : "filesystem";
        Serial.println("OTA Start: " + type);
        lcd.clear(); lcd.print("OTA Updating...");
    });
    ArduinoOTA.onEnd([]() {
        Serial.println("\nOTA End");
        lcd.clear(); lcd.print("OTA Done");
    });
    ArduinoOTA.onProgress([](unsigned int progress, unsigned int total) {
        unsigned int pct = (progress / (total / 100));
        Serial.printf("OTA Progress: %u%%\r", pct);
        lcd.setCursor(0, 1);
        lcd.printf("%3u%%            ", pct);
    });
    ArduinoOTA.onError([](ota_error_t error) {
        otaInProgress = false;
        Serial.printf("OTA Error[%u]: ", error);
        lcd.clear();
        if      (error == OTA_AUTH_ERROR)    { lcd.print("OTA Auth Err");   }
        else if (error == OTA_BEGIN_ERROR)   { lcd.print("OTA Begin Err");  }
        else if (error == OTA_CONNECT_ERROR) { lcd.print("OTA Conn Err");   }
        else if (error == OTA_RECEIVE_ERROR) { lcd.print("OTA Recv Err");   }
        else if (error == OTA_END_ERROR)     { lcd.print("OTA End Err");    }
    });

    ArduinoOTA.begin();
    Serial.print("OTA ready at ");
    Serial.print(WiFi.localIP());
    Serial.print("  hostname=");
    Serial.println(OTA_HOSTNAME);
}

// ===========================================================================
// SETUP
// ===========================================================================
void setup() {
    Serial.begin(115200);

    // Relay first — make sure it boots OFF so the cabinet can't pop open
    // during a brown-out / reset.
    pinMode(RELAY_PIN, OUTPUT);
    digitalWrite(RELAY_PIN, RELAY_ACTIVE_LOW ? HIGH : LOW);

    lcd.init();
    lcd.backlight();
    lcd.setCursor(0, 0);
    lcd.print("Connecting WiFi");

    // Non-blocking-ish WiFi join. Some ESP32 + AP combinations get a DHCP
    // lease but never flip WL_CONNECTED, so a naive while-loop hangs forever
    // and starves OTA + LCD updates. We give it ~15 s; if WL_CONNECTED never
    // appears but the IP is non-zero, we proceed anyway. Worst case we boot
    // into "WiFi Fail" mode but OTA still runs so we can re-flash to recover.
    WiFi.persistent(false);
    WiFi.mode(WIFI_STA);
    WiFi.disconnect(true, true);
    delay(100);
    WiFi.begin(ssid, password);

    Serial.printf("[WiFi] connecting to '%s'\n", ssid);
    uint32_t t0 = millis();
    bool wifiOk = false;
    while (millis() - t0 < 15000) {
        wl_status_t st = WiFi.status();
        Serial.printf("[WiFi] status=%d ip=%s rssi=%d\n",
            (int)st, WiFi.localIP().toString().c_str(), WiFi.RSSI());
        if (st == WL_CONNECTED || (uint32_t)WiFi.localIP() != 0) {
            wifiOk = true;
            break;
        }
        // Show a progress dot on the LCD so the user knows we're alive.
        lcd.setCursor(15, 0);
        lcd.print((millis() / 500) % 2 ? "." : " ");
        delay(500);
    }

    lcd.clear();
    if (wifiOk) {
        lcd.print("WiFi OK");
        lcd.setCursor(0, 1);
        lcd.print(WiFi.localIP().toString());
        Serial.printf("[WiFi] OK ip=%s\n", WiFi.localIP().toString().c_str());
    } else {
        lcd.print("WiFi Fail");
        lcd.setCursor(0, 1);
        lcd.print("OTA only");
        Serial.println("[WiFi] timeout — proceeding anyway so OTA stays up");
    }
    delay(1200);

    // OTA — must be initialised after WiFi is up. We try even on failure
    // because sometimes the radio finishes associating during setupOTA().
    setupOTA();
    lcd.clear(); lcd.print("OTA: "); lcd.print(OTA_HOSTNAME); delay(800);

    // PN532
    nfc.begin();
    if (!nfc.getFirmwareVersion()) {
        lcd.clear(); lcd.print("PN532 Not Found");
        // Don't lock up the loop — OTA still needs to run so we can recover.
    } else {
        nfc.SAMConfig();
    }

    // Fingerprint sensor
    fpSerial.begin(57600, SERIAL_8N1, 16, 17);
    finger.begin(57600);
    if (!finger.verifyPassword()) {
        lcd.clear(); lcd.print("FP Not Found");
        // Same: stay in loop so OTA stays alive.
    }

    lcd.clear(); lcd.print("Cabinet Ready"); delay(800);
}

// ===========================================================================
// LOOP
// ===========================================================================
void loop() {
    // Service OTA on every iteration. If a flash is in progress, do nothing
    // else so we don't interfere with the upload.
    ArduinoOTA.handle();
    if (otaInProgress) { delay(10); return; }

    if (isEnrolling) { delay(200); return; }

    lcd.setCursor(0, 0);
    lcd.print("Tap card / Wait ");
    lcd.setCursor(0, 1);
    lcd.print("                ");

    // (1) Is admin asking us to enroll someone?
    int  pendingUser = 0;
    bool pending = checkEnrollmentRequest(&pendingUser);
    if (pending && pendingUser > 0) {
        isEnrolling = true;
        enrollmentMode(String(pendingUser));
        isEnrolling = false;
        return;
    }

    // (2) Did the admin click "Test Relay" in Settings? Poll once every
    // ~1.5 seconds so we don't flood the server.
    if (millis() - lastRelayPoll > 1500) {
        lastRelayPoll = millis();
        checkRemoteRelay();
    }

    // (3) Otherwise, see if a card was tapped → run 3-factor verification.
    String uid = tryReadRFID(1500);
    if (uid.length() > 0) {
        verificationMode(uid);
    }

    // Keep this short so OTA polling stays responsive.
    delay(200);
    ArduinoOTA.handle();
    delay(200);
}

// ===========================================================================
// VERIFICATION (Physical Cabinet branch)
// ===========================================================================
void verificationMode(const String& rfidUID) {
    lcd.clear(); lcd.print("Place finger");
    int fid = readFingerprintID();          // -1 on failure
    if (fid < 0) {
        denied("Fingerprint X");
        return;
    }

    String pass = readKeypadPassword();
    if (pass.length() == 0) { denied("No passcode");  return; }

    lcd.clear(); lcd.print("Verifying...");
    String body = "rfid=" + rfidUID +
                  "&fingerprint_id=" + String(fid) +
                  "&passcode=" + pass;
    String resp = httpPost(URL_VERIFY, body);

    if (resp.indexOf("\"granted\"") >= 0) {
        lcd.clear(); lcd.print("ACCESS GRANTED");
        fireRelay(RELAY_HOLD_MS);   // <-- physical click happens here
        delay(1500);
    } else {
        denied("ACCESS DENIED");
    }
}

// ===========================================================================
// RELAY DRIVER
// ===========================================================================
// Pulse the relay coil for `ms` milliseconds, then release. Active LOW or
// HIGH is controlled by RELAY_ACTIVE_LOW at the top of the file.
void fireRelay(uint16_t ms) {
    Serial.printf("[RELAY] firing %u ms\n", ms);
    digitalWrite(RELAY_PIN, RELAY_ACTIVE_LOW ? LOW  : HIGH);   // ON
    uint32_t start = millis();
    while (millis() - start < ms) {
        ArduinoOTA.handle();   // keep OTA responsive while the coil is on
        delay(10);
    }
    digitalWrite(RELAY_PIN, RELAY_ACTIVE_LOW ? HIGH : LOW);    // OFF
}

// Polled from the standby loop. Lets the admin "Test Relay" button in
// Settings (which writes relay.active=1 to the DB via activate_relay.php)
// trigger a real click. We immediately call reset_relay.php so the same
// row doesn't fire repeatedly on every poll.
void checkRemoteRelay() {
    String resp = httpGet(URL_CHECK_RELAY);
    resp.trim();
    if (resp.length() > 0 && resp != "0") {
        Serial.println("[RELAY] remote trigger received");
        lcd.clear();
        lcd.print("Remote unlock");
        fireRelay(RELAY_HOLD_MS);
        httpGet(URL_RESET_RELAY);
        lcd.clear();
    }
}

void denied(const char* msg) {
    lcd.clear(); lcd.print(msg);
    delay(2000);
}

// ===========================================================================
// ENROLLMENT (admin-armed from Settings)
// ===========================================================================
void enrollmentMode(const String& userID) {
    // Step 1 — RFID
    updateEnrollStep(userID, 1);
    lcd.clear(); lcd.print("Enroll: Tap RFID");
    String rfid = blockingReadRFID();

    // Step 2 — Fingerprint
    updateEnrollStep(userID, 2);
    lcd.clear(); lcd.print("Enroll Finger");
    int fid = enrollFingerprint(userID.toInt());
    if (fid < 0) {
        lcd.clear(); lcd.print("FP Enroll Fail");
        delay(2000);
        resetEnrollRequest(userID);
        return;
    }

    // Step 3 — Passcode
    updateEnrollStep(userID, 3);
    String pass = readKeypadPassword();

    // Submit
    updateEnrollStep(userID, 4);
    lcd.clear(); lcd.print("Saving...");
    String body = "user_id=" + userID +
                  "&rfid="   + rfid +
                  "&fingerprint_id=" + String(fid) +
                  "&keypad=" + pass;
    String resp = httpPost(URL_ENROLL_API, body);

    resetEnrollRequest(userID);

    lcd.clear();
    lcd.print(resp.indexOf("ENROLL_OK") >= 0 ? "Enroll Done" : "Enroll Err");
    delay(2500);
}

// ===========================================================================
// HARDWARE READERS — each long-blocking loop pumps OTA so flashes still work
// ===========================================================================
String tryReadRFID(uint16_t timeoutMs) {
    uint8_t uid[7];
    uint8_t uidLength;
    if (nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength, timeoutMs)) {
        String s = "";
        for (uint8_t i = 0; i < uidLength; i++) {
            if (uid[i] < 0x10) s += "0";
            s += String(uid[i], HEX);
        }
        s.toUpperCase();
        return s;
    }
    return "";
}

String blockingReadRFID() {
    String uid = "";
    while (uid.length() == 0) {
        ArduinoOTA.handle();
        uid = tryReadRFID(2000);
    }
    lcd.clear(); lcd.print("RFID OK"); delay(800);
    return uid;
}

int readFingerprintID() {
    uint32_t start = millis();
    while (millis() - start < 6000) {
        ArduinoOTA.handle();
        uint8_t p = finger.getImage();
        if (p == FINGERPRINT_OK) {
            if (finger.image2Tz() != FINGERPRINT_OK) return -1;
            if (finger.fingerSearch() != FINGERPRINT_OK) return -1;
            return finger.fingerID;
        }
        delay(120);
    }
    return -1;
}

int enrollFingerprint(int slotId) {
    if (slotId <= 0) slotId = 1;

    lcd.clear(); lcd.print("Place finger");
    while (finger.getImage() != FINGERPRINT_OK) { ArduinoOTA.handle(); delay(120); }
    if (finger.image2Tz(1) != FINGERPRINT_OK) return -1;

    lcd.clear(); lcd.print("Remove finger"); delay(1500);
    while (finger.getImage() != FINGERPRINT_NOFINGER) { ArduinoOTA.handle(); delay(120); }

    lcd.clear(); lcd.print("Same finger");
    while (finger.getImage() != FINGERPRINT_OK) { ArduinoOTA.handle(); delay(120); }
    if (finger.image2Tz(2) != FINGERPRINT_OK) return -1;

    if (finger.createModel() != FINGERPRINT_OK) return -1;
    if (finger.storeModel(slotId) != FINGERPRINT_OK) return -1;

    lcd.clear(); lcd.print("FP Enrolled"); delay(1000);
    return slotId;
}

String readKeypadPassword() {
    lcd.clear(); lcd.print("Enter Passcode");
    lcd.setCursor(0, 1);
    String input = "";
    while (true) {
        ArduinoOTA.handle();
        char k = keypad.getKey();
        if (!k) { delay(20); continue; }
        if (k == '#') return input;       // confirm
        if (k == '*') {                   // clear
            input = "";
            lcd.setCursor(0, 1); lcd.print("                ");
            lcd.setCursor(0, 1);
            continue;
        }
        if (input.length() < 8) {
            input += k;
            lcd.setCursor(0, 1);
            for (size_t i = 0; i < input.length(); i++) lcd.print('*');
        }
    }
}

// ===========================================================================
// HTTP HELPERS
// ===========================================================================
String httpPost(const String& url, const String& body) {
    if (WiFi.status() != WL_CONNECTED) return "";
    HTTPClient http;
    http.begin(url);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    int code = http.POST(body);
    String out = (code > 0) ? http.getString() : "";
    Serial.printf("[POST %d] %s -> %s\n", code, url.c_str(), out.c_str());
    http.end();
    return out;
}

String httpGet(const String& url) {
    if (WiFi.status() != WL_CONNECTED) return "";
    HTTPClient http;
    http.begin(url);
    int code = http.GET();
    String out = (code > 0) ? http.getString() : "";
    Serial.printf("[GET %d] %s -> %s\n", code, url.c_str(), out.c_str());
    http.end();
    return out;
}

bool checkEnrollmentRequest(int* outUserId) {
    String resp = httpGet(URL_CHECK_ENROLL);
    resp.trim();
    int comma = resp.indexOf(',');
    if (comma < 0) return false;
    int active = resp.substring(0, comma).toInt();
    int uid    = resp.substring(comma + 1).toInt();
    if (outUserId) *outUserId = uid;
    return active == 1 && uid > 0;
}

void resetEnrollRequest(const String& userID) {
    httpGet(URL_RESET_ENROLL + "?user_id=" + userID);
}

void updateEnrollStep(const String& userID, int step) {
    httpPost(URL_UPDATE_STEP, "user_id=" + userID + "&step=" + String(step));
}
