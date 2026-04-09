#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <Keypad.h>
#include <SPI.h>
#include <Adafruit_PN532.h>
#include <Adafruit_Fingerprint.h>
#include <ArduinoJson.h>

// WIFI
const char* ssid = "HUAWEI-T4uu_2.4G";
const char* password = "6sPwR2Tk";

String server = "http://192.168.18.18/test_connect/";

// RELAYS
#define RELAY1 15
#define RELAY2 2

// LCD
LiquidCrystal_I2C lcd(0x27,16,2);

// FINGERPRINT
HardwareSerial mySerial(2);
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&mySerial);

// NFC
#define PN532_SS 5
Adafruit_PN532 nfc(PN532_SS);

// KEYPAD
const byte ROWS=4, COLS=4;
char keys[ROWS][COLS]={{'1','2','3','A'},{'4','5','6','B'},{'7','8','9','C'},{'*','0','#','D'}};
byte rowPins[ROWS]={26,25,33,32};
byte colPins[COLS]={13,12,14,27};
Keypad keypad = Keypad(makeKeymap(keys), rowPins, colPins, ROWS, COLS);

// AUTH VARIABLES
String expectedUID="";
String userPass="";
int userFinger=0;
int targetRelay=0;
bool authActive=false;

unsigned long authStartTime=0;
const int AUTH_TIMEOUT=20000; // 20 sec

// ================= SETUP =================
void setup(){

Serial.begin(115200);

pinMode(RELAY1,OUTPUT);
pinMode(RELAY2,OUTPUT);
digitalWrite(RELAY1,LOW);
digitalWrite(RELAY2,LOW);

lcd.init();
lcd.backlight();
lcd.print("Connecting WiFi");

WiFi.begin(ssid,password);
while(WiFi.status()!=WL_CONNECTED) delay(500);

lcd.clear();
lcd.print("WiFi OK");
delay(1000);

// Fingerprint
mySerial.begin(57600, SERIAL_8N1, 16, 17);
finger.begin(57600);

// NFC
SPI.begin();
nfc.begin();
nfc.SAMConfig();

lcd.clear();
lcd.print("System Ready");
}

// ================= LOOP =================
void loop(){

checkAuthRequest();

if(authActive){

if(millis()-authStartTime > AUTH_TIMEOUT){
fail("Timeout");
resetAuth();
return;
}

if(!checkRFID()) return;
if(!checkFingerprint()) return;
if(!checkPasscode()) return;

success();
unlockRelay(targetRelay);
resetAuth();
}

delay(500);
}

// ================= AUTH REQUEST =================
void checkAuthRequest(){

HTTPClient http;
http.begin(server+"get_auth_request.php");

if(http.GET()==200){

DynamicJsonDocument doc(256);
deserializeJson(doc,http.getString());

if(doc["auth_request"]==1){

int userID = doc["auth_user_id"];
targetRelay = doc["relay"];

getUserData(userID);

authActive=true;
authStartTime=millis();

lcd.clear();
lcd.print("Start Auth");

}
}
http.end();
}

// ================= GET USER =================
void getUserData(int id){

HTTPClient http;
http.begin(server+"get_user.php?id="+String(id));

if(http.GET()==200){

DynamicJsonDocument doc(256);
deserializeJson(doc,http.getString());

expectedUID = doc["rfid_uid"].as<String>();
expectedUID.toUpperCase();

userFinger = doc["fingerprint_id"];
userPass = doc["passcode"].as<String>();

}

http.end();
}

// ================= RFID =================
bool checkRFID(){

lcd.clear();
lcd.print("Scan RFID");

uint8_t uid[7], len;

if(nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &len)){

String scanned = formatUID(uid,len);

Serial.println("Expected: "+expectedUID);
Serial.println("Scanned: "+scanned);

if(scanned != expectedUID){
fail("Wrong Card");
return false;
}

lcd.print("RFID OK");
delay(1000);
return true;
}
return false;
}

// ================= FINGER =================
bool checkFingerprint(){

lcd.clear();
lcd.print("Scan Finger");

while(finger.getImage()!=FINGERPRINT_OK){
if(timeout()) return false;
}

finger.image2Tz();

if(finger.fingerFastSearch()!=FINGERPRINT_OK){
fail("No Match");
return false;
}

if(finger.fingerID != userFinger){
fail("Wrong Finger");
return false;
}

lcd.print("Finger OK");
delay(1000);
return true;
}

// ================= PASSCODE =================
bool checkPasscode(){

lcd.clear();
lcd.print("Enter Pass");

String input="";

while(true){

if(timeout()) return false;

char key = keypad.getKey();

if(key){

if(isDigit(key)){
input+=key;
lcd.print("*");
}

if(key=='*' && input.length()>0){
input.remove(input.length()-1);
lcd.setCursor(input.length(),1);
lcd.print(" ");
lcd.setCursor(input.length(),1);
}

if(key=='#'){
break;
}
}
}

if(input != userPass){
fail("Wrong Pass");
return false;
}

return true;
}

// ================= RELAY =================
void unlockRelay(int r){

lcd.clear();
lcd.print("Access OK");

if(r==1){
digitalWrite(RELAY1,HIGH);
delay(5000);
digitalWrite(RELAY1,LOW);
}

if(r==2){
digitalWrite(RELAY2,HIGH);
delay(5000);
digitalWrite(RELAY2,LOW);
}

}

// ================= HELPERS =================
void resetAuth(){

HTTPClient http;
http.begin(server+"clear_auth.php");
http.GET();
http.end();

authActive=false;
}

void fail(String msg){

lcd.clear();
lcd.print(msg);
delay(2000);
}

void success(){
lcd.clear();
lcd.print("SUCCESS");
delay(1000);
}

bool timeout(){
return millis()-authStartTime > AUTH_TIMEOUT;
}

String formatUID(uint8_t *uid, uint8_t length){

String s="";
for(int i=0;i<length;i++){
if(uid[i]<0x10) s+="0";
s+=String(uid[i],HEX);
}
s.toUpperCase();
return s;
}