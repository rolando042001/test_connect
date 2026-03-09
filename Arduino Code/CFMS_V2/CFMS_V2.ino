#include <WiFi.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <Wire.h>
#include <Adafruit_PN532.h>
#include <Keypad.h>
#include <LiquidCrystal_I2C.h>

//////////////// WIFI //////////////////
const char* ssid = "HUAWEI-T4uu_2.4G";
const char* password = "6sPwR2Tk";

// XAMPP server files
String serverEnrollURL = "http://192.168.18.18/file_system/esp_enroll_api.php";
String serverCheckEnrollURL = "http://192.168.18.18/file_saystem/check_enroll.php";
String serverResetEnrollURL = "http://192.168.18.18/file_system/reset_enroll_request.php";

// Logged-in user ID (replace dynamically if needed)
String userID = "1";

//////////////// PN532 SPI //////////////////
#define PN532_SS   5
#define PN532_RST  4
#define PN532_SCK  18
#define PN532_MOSI 23
#define PN532_MISO 19

Adafruit_PN532 nfc(PN532_SCK, PN532_MISO, PN532_MOSI, PN532_SS);

//////////////// LCD //////////////////
LiquidCrystal_I2C lcd(0x27, 16, 2);

bool isEnrolling = false;

//////////////// KEYPAD //////////////////
const byte ROWS = 4;
const byte COLS = 4;
char keys[ROWS][COLS] = {
  {'1','2','3','A'},
  {'4','5','6','B'},
  {'7','8','9','C'},
  {'*','0','#','D'}
};
byte rowPins[ROWS] = {26, 25, 33, 32};
byte colPins[COLS] = {13, 12, 14, 27};
Keypad keypad = Keypad(makeKeymap(keys), rowPins, colPins, ROWS, COLS);

//////////////// GLOBAL VARIABLES //////////////////
String rfidUID = "";
String keypadInput = "";

//////////////// SETUP //////////////////
void setup() {
  Serial.begin(115200);

  // LCD Init
  lcd.init();
  lcd.backlight();

  // Connect WiFi
  lcd.setCursor(0,0);
  lcd.print("Connecting WiFi");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  lcd.clear();
  lcd.print("WiFi Connected");
  delay(1000);

  // PN532 Init
  nfc.begin();
  uint32_t versiondata = nfc.getFirmwareVersion();
  if (!versiondata) {
    lcd.clear();
    lcd.print("PN532 Not Found");
    while(1);
  }
  nfc.SAMConfig();
  lcd.clear();
  lcd.print("PN532 Ready");
  delay(1000);
}

//////////////// LOOP //////////////////
void loop() {

    if (!isEnrolling) {
        lcd.setCursor(0,0);
        lcd.print("Waiting...     ");

        int enrollRequest = checkEnrollmentRequest();

        if (enrollRequest == 1) {
            isEnrolling = true;
            enrollmentMode();
        }
    }

    delay(2000);
}

//////////////// FUNCTIONS //////////////////

String readRFID() {

  
  uint8_t success;
  uint8_t uid[7];
  uint8_t uidLength;

  lcd.clear();
  lcd.setCursor(0,0);
  lcd.print("Tap NFC Card");

  success = nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength);
  while (!success) {
    delay(100);
    success = nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength);
  }

  String uidStr = "";
  for (uint8_t i=0; i<uidLength; i++){
    if (uid[i] < 0x10) uidStr += "0";
    uidStr += String(uid[i], HEX);
  }
  uidStr.toUpperCase();

  lcd.clear();
  lcd.print("RFID:");
  lcd.setCursor(0,1);
  lcd.print(uidStr);
  delay(2000);
  return uidStr;


}

String readKeypadPassword() {
  lcd.clear();
  lcd.print("Enter Password");
  lcd.setCursor(0,1);
  String input = "";

  while (true) {
    char key = keypad.getKey();
    if (key) {
      if (key == '#') {  // Confirm
        break;
      } else if (key == '*') {  // Reset input
        input = "";
        lcd.setCursor(0,1);
        lcd.print("                ");
        lcd.setCursor(0,1);
      } else {
        input += key;
        lcd.setCursor(0,1);
        lcd.print(input);
      }
    }
  }
  return input;


}

void enrollmentMode() {

  updateEnrollStep(1);

    rfidUID = readRFID();

  updateEnrollStep(3);  // since skipping fingerprint
    keypadInput = readKeypadPassword();

    updateEnrollStep(4);

    lcd.clear();
    lcd.print("Sending Data...");
    sendToServer(rfidUID, keypadInput);

    resetEnrollRequest();

    lcd.clear();
    lcd.print("Enrollment Done");
    delay(3000);

    // Clear screen and return to standby
    lcd.clear();
    lcd.print("Standby Mode");
    delay(2000);

    isEnrolling = false;
}

void sendToServer(String rfid, String keypadPass) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverEnrollURL);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");

    String httpRequestData = "rfid=" + rfid + "&keypad=" + keypadPass + "&user_id=" + userID;

    int httpResponseCode = http.POST(httpRequestData);

    if (httpResponseCode > 0) {
      String response = http.getString();
      lcd.clear();
      lcd.print(response);
    } else {
      lcd.clear();
      lcd.print("Error Sending");
    }
    http.end();
  } else {
    lcd.clear();
    lcd.print("WiFi Disconnected");
  }
}

void setEnrollRequest() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    String url = serverEnrollURL + "?user_id=" + userID + "&set_flag=1";
    http.begin(url);
    http.GET();
    http.end();
  }
}

int checkEnrollmentRequest() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    String url = serverCheckEnrollURL + "?user_id=" + userID;
    http.begin(url);
    int httpCode = http.GET();
    int flag = 0;

    if (httpCode > 0) {
      String payload = http.getString();

      Serial.println("Server Response:");
      Serial.println(payload);

      flag = payload.toInt();
      Serial.print("Converted Flag: ");
      Serial.println(flag);
    } else {
      Serial.print("HTTP Error: ");
      Serial.println(httpCode);
    }

    http.end();
    return flag;
  }
  return 0;
}
void resetEnrollRequest() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    String url = serverResetEnrollURL + "?user_id=" + userID;
    http.begin(url);
    http.GET();
    http.end();
  }
}

void updateEnrollStep(int step) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    String url = "http://192.168.18.18/file_system/update_step.php";
    http.begin(url);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");

    String data = "user_id=" + userID + "&step=" + String(step);
    http.POST(data);

    http.end();
  }
}