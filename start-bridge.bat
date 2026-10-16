@echo off
title Bintang Mobil - WhatsApp QR Bridge (HP B)
color 0A
echo ========================================================
echo  BINTANG MOBIL WHATSAPP LOCAL QR BRIDGE
echo  Menghubungkan HP B (WhatsApp Bisnis) ke Laravel Local
echo ========================================================
echo.

where node >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Node.js belum terinstall atau belum masuk ke PATH!
    echo Silakan install Node.js terlebih dahulu.
    pause
    exit /b
)

cd /d "%~dp0whatsapp-bridge"
echo [STATUS] Menjalankan Node.js server.js...
echo [TIPS] Jangan tutup jendela ini selama simulasi WhatsApp berlangsung.
echo.

:run_loop
node server.js
if %ERRORLEVEL% neq 0 (
    echo.
    echo [INFO] Server bridge terhenti. Menghubungkan ulang dalam 3 detik...
    timeout /t 3 /nobreak >nul
    goto run_loop
)

pause
