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

// Your XAMPP server IP
String serverName = "http://192.168.18.18/file_system/esp_enroll_api.php";

//////////////// PN532 SPI //////////////////
#define PN532_SS   5
#define PN532_RST  4
#define PN532_SCK  18
#define PN532_MOSI 23
#define PN532_MISO 19

Adafruit_PN532 nfc(PN532_SCK, PN532_MISO, PN532_MOSI, PN532_SS);

//////////////// LCD //////////////////
LiquidCrystal_I2C lcd(0x27, 16, 2);

//////////////// KEYPAD //////////////////
const byte ROWS = 4;
const byte COLS = 4;
char keys[ROWS][COLS] = {
  {'1','2','3','A'},
  {'4','5','6','B'},
  {'7','8','9','C'},
  {'*','0','#','D'}
};
byte rowPins[ROWS] = {13,12,14,27};
byte colPins[COLS] = {32,33,25,26};
Keypad keypad = Keypad(makeKeymap(keys), rowPins, colPins, ROWS, COLS);

//////////////// GLOBALS //////////////////
String rfidUID = "";
String keypadInput = "";

//////////////// SETUP //////////////////
void setup() {
  Serial.begin(115200);

  // LCD init
  lcd.init();
  lcd.backlight();

   // PN532 init
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

  // Connect WiFi
  WiFi.begin(ssid, password);
  lcd.setCursor(0,0);
  lcd.print("Connecting WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  lcd.clear();
  lcd.print("WiFi Connected");
  delay(1000);

 
}

//////////////// LOOP //////////////////
void loop() {

  // -------- STEP 1: Read RFID ----------
  rfidUID = readRFID();

  // -------- STEP 2: Keypad Password ----------
  keypadInput = readKeypadPassword();

  // -------- STEP 3: Send Data to Server ----------
  lcd.clear();
  lcd.print("Sending Data...");
  sendToServer(rfidUID, keypadInput);

  delay(3000);
  lcd.clear();
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

void sendToServer(String rfid, String keypadPass) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverName);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");

    String httpRequestData = "rfid=" + rfid + "&keypad=" + keypadPass;

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