# test_connect — local setup

This folder is the **canonical source** for the test_connect cabinet
backend. Every fix in this folder has been verified by `health.php`
(30/30 checks passing as of last run).

## What you need

- XAMPP (Apache + MariaDB on port 3306, root user, no password — the
  XAMPP defaults)
- The `test_connect` database (one-time import from the parent
  folder's `test_connect.sql`)

## One-time setup on a fresh machine

```cmd
:: 1. Create the database and import the schema
c:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS test_connect"
c:\xampp\mysql\bin\mysql.exe -u root test_connect < test_connect.sql

:: 2. Apply the relay seed fix (the SQL dump's seed has relay_in=0
::    which doesn't match the V3 sketch's relay=1/2 channels)
c:\xampp\mysql\bin\mysql.exe -u root test_connect -e "DELETE FROM relay; INSERT INTO relay (id, relay_in, active) VALUES (1, 1, 0), (2, 2, 0);"

:: 3. Widen the passcode column so bcrypt hashes fit
c:\xampp\mysql\bin\mysql.exe -u root test_connect -e "ALTER TABLE users MODIFY passcode VARCHAR(255) DEFAULT NULL"

:: 4. Make the URL http://<lan-ip>/test_connect/ resolve to this folder
::    by copying it to xampp\htdocs:
xcopy /E /I /Y . c:\xampp\htdocs\test_connect
```

## Daily use

After setup, open these in your browser:

| URL | What it does |
|---|---|
| `http://<lan-ip>/test_connect/register.html` | Admin panel — Start Registration + Unlock Cabinet |
| `http://<lan-ip>/test_connect/health.php` | Live health dashboard — 30 checks, refresh to re-run |

To find your LAN IP: `ipconfig` from cmd, look for `IPv4 Address` under
the active Wi-Fi adapter.

## Endpoints (for the ESP32 firmware)

| Endpoint | Method | Returns |
|---|---|---|
| `test_connection.php` | GET | `Database Connected Successfully` |
| `get_register_state.php` | GET | `active,step` (e.g. `0,0` or `1,2`) |
| `register_start.php` | GET | `REGISTER_STARTED` |
| `update_step.php?step=N` | GET | `STEP_UPDATED` (N must be 0-4) |
| `get_next_fingerid.php` | GET | next slot id (1-127) |
| `get_rfids.php` | GET | JSON array of RFIDs |
| `get_users.php` | GET | JSON array of users (no passcode hash) |
| `store_user.php` | POST | `USER_SAVED` (id, rfid_uid, passcode) |
| `finish_register.php` | GET | `REGISTER_FINISHED` |
| `activate_relay.php` | POST | `RELAY_ENABLED` (relay=1\|2) |
| `check_relay.php` | GET | `0`, `1`, or `2` |
| `reset_relay.php` | GET | `RESET` |
| **`verify_user.php`** | POST | `GRANTED` or `DENIED_*` |

The `verify_user.php` endpoint is new — it does walk-up 3-factor
verification and arms the relay on grant. See `VERIFY_FIRMWARE_SNIPPET.md`
for the Arduino code that calls it.

## Security notes

- Passcodes are stored as bcrypt hashes (`$2y$10$...`), not plaintext
- All endpoints use prepared statements (no SQL injection)
- `db.php` returns ASCII `ERROR` on connection failure so the firmware
  can parse it without choking on an HTML error page
- Validation: `rfid_uid` must be uppercase hex 4-20 chars; passcode
  must be 6-8 digits; relay channel must be 1 or 2; step must be 0-4

## If something breaks

Open `http://<lan-ip>/test_connect/health.php` and look for the red
rows. The dashboard runs all 30 checks against the live endpoints and
shows the actual response body for any failure.
