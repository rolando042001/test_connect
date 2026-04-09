@echo off
REM ============================================================
REM  CFMS_V2 — first-time USB flash via arduino-cli
REM  Usage:  flash_serial.bat            (auto-detect COM port)
REM          flash_serial.bat COM5       (specify COM port)
REM ============================================================
setlocal
set FQBN=esp32:esp32:esp32
set SKETCH=%~dp0

if "%~1"=="" (
    for /f "tokens=1" %%P in ('arduino-cli board list ^| findstr /R "COM[0-9]"') do (
        set PORT=%%P
        goto :found
    )
    echo No ESP32 detected. Plug it in or pass a COM port: flash_serial.bat COM5
    exit /b 1
) else (
    set PORT=%~1
)
:found
echo Compiling and flashing %SKETCH% to %PORT% ...
arduino-cli compile --fqbn %FQBN% "%SKETCH%" || exit /b 1
arduino-cli upload   --fqbn %FQBN% --port %PORT% "%SKETCH%" || exit /b 1
echo.
echo  Done. The device will reboot and connect to WiFi.
echo  After it shows its IP, you can switch to OTA: flash_ota.bat
endlocal
