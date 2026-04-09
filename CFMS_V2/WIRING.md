# CFMS_V2 — Wiring Reference

Hardware: ESP32 DevKit V1 (38-pin) + PN532 RFID + R307/AS608 Fingerprint
+ 16×2 I²C LCD + 4×4 matrix keypad + HL-52S 1-channel relay + buck
converter (12 V → 5 V) + 12 V cabinet solenoid.

> **Single source of truth** for the pin assignments is [`CFMS_V2.ino`](CFMS_V2.ino) —
> the constants/`#define`s near the top of the file. If you ever change a pin,
> update this table at the same time.

---

## Pin assignments

### PN532 RFID — SPI
Set the on-module DIP switches to **SPI** (`SEL0=OFF`, `SEL1=ON`).

| PN532 pin | ESP32 GPIO | Wire colour (suggested) |
|---|---|---|
| SCK  | **GPIO 18** | yellow |
| MOSI | **GPIO 23** | blue   |
| MISO | **GPIO 19** | green  |
| SS   | **GPIO 5**  | white  |
| RST  | **GPIO 4**  | grey   |
| VCC  | **3.3 V**   | red    |
| GND  | **GND**     | black  |

### R307 / AS608 Fingerprint — UART2
The data lines are 3.3 V on both modules — wire them straight to the ESP32, no
level shifter needed. Body voltage differs:

| Sensor | VCC | Notes |
|---|---|---|
| **R307** (round, metal body) | 4.5–6.0 V — use **5 V** | data lines stay 3.3 V |
| **AS608** (small blue PCB)   | **3.3 V only** — do NOT give 5 V | |

| Sensor pin | ESP32 GPIO |
|---|---|
| TX (out of sensor) | **GPIO 16** (RX2) |
| RX (into sensor)   | **GPIO 17** (TX2) |
| VCC | see table above |
| GND | **GND** |

### LCD 16×2 (I²C, address 0x27)

| LCD pin | ESP32 GPIO | Notes |
|---|---|---|
| SDA | **GPIO 21** | default Wire SDA |
| SCL | **GPIO 22** | default Wire SCL |
| VCC | **5 V** | backlight needs 5 V |
| GND | **GND** | |

If your LCD address is 0x3F instead of 0x27 (varies by manufacturer), change
the `LiquidCrystal_I2C lcd(0x27, 16, 2);` line in [`CFMS_V2.ino`](CFMS_V2.ino).

### 4×4 matrix keypad

| Keypad pin | ESP32 GPIO |
|---|---|
| ROW 1 (top)    | **GPIO 26** |
| ROW 2          | **GPIO 25** |
| ROW 3          | **GPIO 33** |
| ROW 4 (bottom) | **GPIO 32** |
| COL 1 (left)   | **GPIO 13** |
| COL 2          | **GPIO 12** ⚠ |
| COL 3          | **GPIO 14** |
| COL 4 (right)  | **GPIO 27** |

⚠ **GPIO 12** is a strapping pin (must be LOW at boot). Keypads are
high-impedance when no key is pressed so it's normally fine, but if the
ESP32 fails to boot intermittently, swap COL2 to **GPIO 15** and free up
the relay pin to **GPIO 2**.

### HL-52S relay — 1 channel, opto-isolated, **active LOW**

| Relay pin | ESP32 GPIO / supply |
|---|---|
| IN  | **GPIO 15** |
| VCC | **5 V** (from buck converter) |
| GND | **GND** |
| COM | + side of solenoid |
| NO  | + 12 V from cabinet PSU |

GPIO 15 is a strapping pin that defaults **HIGH** at boot — perfect for an
active-LOW opto-isolated relay because HIGH = OFF, so the cabinet stays
locked through every reset / brown-out.

If your relay module is **active HIGH** instead, flip this in
[`CFMS_V2.ino`](CFMS_V2.ino):

```cpp
#define RELAY_ACTIVE_LOW   false
```

---

## Power layout

```
   220 V AC
       │
       ▼
  ┌─────────────┐
  │ 12 V / 2 A  │  barrel adapter
  │ wall PSU    │
  └─────────────┘
       │
       │ +12 V ───────────────────────────────► COM of relay  ──► +Solenoid
       │                                                          │
       │                                              ┌───────────┘
       │                                              │
       │ GND  ────────────┬─────────────┬─────────────┴── (common ground)
       │                  │             │             │
       │                  │             │             │
       ▼                  │             │             │
  ┌─────────────┐         │             │             │
  │ Buck conv.  │         │             │             │
  │ 12V → 5V    │         │             │             │
  └─────────────┘         │             │             │
       │ +5 V             │             │             │
       │                  │             │             │
       ├──► ESP32 VIN     │             │             │
       ├──► LCD VCC       │             │             │
       └──► Relay VCC     │             │             │
                          │             │             │
                          │             │             │
                  ESP32 onboard 3.3 V regulator
                          │
                          ├──► PN532 VCC (3.3 V)
                          └──► AS608/R307 logic side (3.3 V)
```

⚠ **Common ground is mandatory.** The cabinet 12 V supply ground, the
buck converter low side, the relay module ground, and every device ground
must all share **one rail**. If the solenoid 12 V ground isn't bridged to
the ESP32 GND, you'll get random "relay clicks but coil doesn't latch"
behaviour.

---

## ESP32 strapping pins — what to avoid

| Pin | Notes |
|---|---|
| **GPIO 0**  | bootloader strap. Don't drive externally. Not used. ✅ |
| **GPIO 2**  | strap (must be LOW or floating at boot). Free in this design. ✅ |
| **GPIO 12** | strap (must be LOW at boot) — used as Keypad COL2. OK because keypad is high-Z when idle, but if you see flash-voltage boot errors, move COL2 → GPIO 15 and relay → GPIO 2. |
| **GPIO 15** | strap (defaults HIGH at boot) — used for the relay. HIGH = relay OFF (good). |
| **GPIO 6–11** | reserved for SPI flash. **NEVER USE.** |
| **GPIO 34–39** | input only. Not used. |

---

## Bring-up checklist

Open the Arduino IDE serial monitor at **115200 baud** and watch the LCD:

1. Power on. LCD: `Connecting WiFi` → `WiFi OK / 192.168.x.x` → `OTA: cfms-cabinet` → `Cabinet Ready`. ✔ confirms WiFi + LCD + I²C.
2. Tap any RFID card on the PN532. LCD: `Place finger`. ✔ confirms PN532 SPI.
3. Place the enrolled finger. LCD: `Enter Passcode`. ✔ confirms fingerprint UART.
4. Type the 6-digit PIN, press `#`. LCD: `Verifying...` → `ACCESS GRANTED`. ✔ confirms keypad + WiFi HTTP.
5. The relay should click within ~100 ms of "GRANTED" and the solenoid should release for ~1.5 s. ✔ confirms relay GPIO + power.
6. From the admin Settings page, click **Test Relay 1**. Within ~1.5 s the LCD should show `Remote unlock` and the cabinet should fire again. ✔ confirms server-side admin trigger.

If any step hangs, the LCD message tells you which subsystem failed:
`PN532 Not Found`, `FP Not Found`, `WiFi Disconnected`, etc.

---

## Common issues

| Symptom | Likely cause |
|---|---|
| "PN532 Not Found" | DIP switches not in SPI mode, or VCC is 5 V on a 3.3-V-only clone |
| "FP Not Found" | TX/RX swapped — the sensor's TX goes to ESP32 GPIO 16, not 17 |
| LCD shows boxes / nothing | Wrong I²C address (try 0x3F), or contrast pot needs adjustment |
| Relay clicks but solenoid doesn't move | 12 V ground not bridged to ESP32 GND |
| Keypad missing rows/columns | One of the matrix wires off-by-one — try the on-board self-test code in the Adafruit Keypad library |
| ESP32 reboot loop on power-on | Strapping pin held HIGH at boot — see GPIO 12/15 notes above |
| OTA upload fails with "Auth Error" | `OTA_PASSWORD` mismatch between `secrets.h` and `flash_ota.bat` env var |

---

## Visual diagram

See [`wiring.svg`](wiring.svg) for the colour-coded schematic of the same
information.
