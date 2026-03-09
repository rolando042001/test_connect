#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <Keypad.h>
#include <SPI.h>
#include <Adafruit_PN532.h>
#include <Adafruit_Fingerprint.h>

// WIFI
const char* ssid = "HUAWEI-T4uu_2.4G";
const char* password = "6sPwR2Tk";

bool RFID_ENABLED = true;
bool FINGER_ENABLED = true;
bool PASSCODE_ENABLED = true;

String server = "http://192.168.18.18/test_connect/";

// RELAY
#define RELAY1 4
#define RELAY2 2

// LCD
LiquidCrystal_I2C lcd(0x27,16,2);

// FINGERPRINT
HardwareSerial mySerial(2);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);

// PN532 SPI
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

WiFi.begin(ssid,password);

lcd.setCursor(0,1);
lcd.print("Connecting WiFi");

while(WiFi.status()!=WL_CONNECTED){
delay(500);
}

lcd.clear();
lcd.print("WiFi Connected");
delay(2000);

// FINGERPRINT
mySerial.begin(57600, SERIAL_8N1, 16, 17);
finger.begin(57600);

// NFC
SPI.begin();
nfc.begin();
nfc.SAMConfig();

lcd.clear();
lcd.print("System Ready");

}

void loop(){

checkRegisterState();

if(active==1){

  if(step==1 && RFID_ENABLED){
      scanRFID();
  }

  if(step==2 && FINGER_ENABLED){
      enrollFingerprint();
  }

  if(step==3 && PASSCODE_ENABLED){
      enterPasscode();
  }

}

delay(1000);
checkRelay();

}

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

HTTPClient http;

String url = server + "get_register_state.php";

http.begin(url);
int code = http.GET();

if(code>0){

String payload = http.getString();

int comma = payload.indexOf(',');

active = payload.substring(0,comma).toInt();
step = payload.substring(comma+1).toInt();

}

http.end();

}

// STEP 1 RFID
void scanRFID(){

lcd.clear();
lcd.print("Scan RFID");

uint8_t success;
uint8_t uid[7];
uint8_t uidLength;

success = nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength);

if(success){

rfidUID = formatUID(uid,uidLength);


lcd.clear();
lcd.print("RFID OK");
delay(2000);

updateStep(2);


}


}

// STEP 2 FINGERPRINT
void enrollFingerprint(){

lcd.clear();
lcd.print("Place Finger");

while(finger.getImage()!=FINGERPRINT_OK);

if(finger.image2Tz(1)!=FINGERPRINT_OK){
//lcd.print("Error");
return;
}

lcd.clear();
lcd.print("Remove Finger");
delay(2000);

while(finger.getImage()!=FINGERPRINT_NOFINGER);

lcd.clear();
lcd.print("Place Again");

while(finger.getImage()!=FINGERPRINT_OK);

if(finger.image2Tz(2)!=FINGERPRINT_OK){
//lcd.print("Error");
return;
}

if(finger.createModel()!=FINGERPRINT_OK){
lcd.print("Mismatch");
return;
}

fingerprintID = getNextFingerID();

if(finger.storeModel(fingerprintID)==FINGERPRINT_OK){

lcd.clear();
lcd.print("Finger Saved");

updateStep(3);

}else{

lcd.print("Save Failed");

}

delay(2000);

}
// STEP 3 PASSCODE
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

HTTPClient http;

String url = server + "update_step.php?step="+String(s);

http.begin(url);
http.GET();
http.end();

}

// STORE USER
void storeUser(){

HTTPClient http;

String url = server + "store_user.php";

http.begin(url);
http.addHeader("Content-Type","application/x-www-form-urlencoded");

String data =
"id="+String(fingerprintID)+
"&rfid_uid="+rfidUID+
"&passcode="+passcode;

int code = http.POST(data);

String response = http.getString();

Serial.println(response);

http.end();

finishRegister();

}

int getNextFingerID(){

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

HTTPClient http;

String url = server + "finish_register.php";

http.begin(url);
http.GET();
http.end();

lcd.clear();
lcd.print("User Saved");

delay(3000);

}

void checkRelay(){

HTTPClient http;

String url = server + "check_relay.php";

http.begin(url);

int code = http.GET();

if(code>0){

int relay = http.getString().toInt();

if(relay==1){
activateRelay1();
}

if(relay==2){
activateRelay2();
}

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

HTTPClient http;

String url = server + "reset_relay.php";

http.begin(url);
http.GET();
http.end();

}

// // SIMPLE ID GENERATOR
// int getNextFingerID(){

// static int id=1;
// return id++;

