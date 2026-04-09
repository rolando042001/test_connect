# CFMS_V2 — ESP32 Cabinet Firmware

3-factor (RFID + Fingerprint + Passcode) controller for the CFM physical cabinet.
Compiles with **arduino-cli** and supports **ArduinoOTA** so subsequent updates
no longer need a USB cable.

## One-time setup

```bash
# 1. ESP32 board manager URL (already configured on this machine)
arduino-cli config add board_manager.additional_urls \
  https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json

# 2. ESP32 core (pinned to LTS 2.0.17 — newer cores hit a Windows MAX_PATH bug)
arduino-cli core update-index
arduino-cli core install esp32:esp32@2.0.17

# 3. Libraries
arduino-cli lib install \
  "Adafruit PN532" \
  "Adafruit Fingerprint Sensor Library" \
  "Keypad" \
  "LiquidCrystal I2C"
```

## Compile only (no upload)

```bash
arduino-cli compile --fqbn esp32:esp32:esp32 ./CFMS_V2
```

## First flash (USB cable required)

```cmd
flash_serial.bat            REM auto-detects COM port
flash_serial.bat COM5       REM or specify
```

After first boot the LCD displays the device IP and confirms `OTA: cfms-cabinet`.

## Subsequent flashes (over WiFi, no cable)

```cmd
flash_ota.bat                          REM uses mDNS cfms-cabinet.local
flash_ota.bat 192.168.18.42            REM or use the IP directly
```

The OTA password is read from `secrets.h` (`CFM_OTA_PASSWORD`). Copy
`secrets.example.h` to `secrets.h` and set real values before flashing —
`secrets.h` is gitignored so credentials never reach the repo.

## Pin map

| Component   | ESP32 pins                        |
|-------------|-----------------------------------|
| PN532 (SPI) | SCK 18, MOSI 23, MISO 19, SS 5    |
| Fingerprint | RX2 16, TX2 17, 3.3V, GND         |
| LCD I²C     | SDA 21, SCL 22 (default Wire)     |
| Keypad rows | 26, 25, 33, 32                    |
| Keypad cols | 13, 12, 14, 27                    |

## Server URL

The sketch points at `http://192.168.18.9/CFM/Backend`. Change the
`BASE_URL` constant in `CFMS_V2.ino` if your XAMPP host moves, then re-flash
(serial or OTA).
