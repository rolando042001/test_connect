@echo off
REM ============================================================
REM  CFMS_V2 — Over-The-Air flash via arduino-cli
REM  Usage:  flash_ota.bat                       (uses cfms-cabinet.local)
REM          flash_ota.bat 192.168.18.42         (use a static IP)
REM
REM  Requires: device already booted with OTA-enabled sketch on
REM  the same LAN as this PC. mDNS hostname is set in secrets.h.
REM  Override OTA password by:    set OTA_PASS=mypassword & flash_ota.bat
REM ============================================================
setlocal
set FQBN=esp32:esp32:esp32
set SKETCH=%~dp0
if "%OTA_PASS%"=="" set OTA_PASS=change-me

if "%~1"=="" (
    set TARGET=cfms-cabinet.local
) else (
    set TARGET=%~1
)

echo Compiling %SKETCH% ...
arduino-cli compile --fqbn %FQBN% "%SKETCH%" || exit /b 1

echo Uploading OTA to %TARGET% ...
arduino-cli upload --fqbn %FQBN% --protocol network --port %TARGET% --upload-field password=%OTA_PASS% "%SKETCH%"
if errorlevel 1 (
    echo.
    echo  OTA failed. Common causes:
    echo    - Device not on same LAN
    echo    - mDNS not resolving "cfms-cabinet.local" - try the IP directly
    echo    - Wrong OTA password ^(check OTA_PASSWORD in CFMS_V2.ino^)
    exit /b 1
)
echo.
echo  OTA upload complete.
endlocal
