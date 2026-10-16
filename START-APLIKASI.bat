@echo off
title Bintang Mobil - AI Auto Sales Launcher
color 0B

echo =================================================================
echo        BINTANG MOBIL WHATSAPP AI AUTO SALES - LAUNCHER
echo =================================================================
echo.
echo [1/3] Memeriksa dependensi sistem...

where php >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] PHP tidak ditemukan di PATH! Pastikan PHP sudah terinstall.
    pause
    exit /b
)

where node >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Node.js tidak ditemukan di PATH! Pastikan Node.js sudah terinstall.
    pause
    exit /b
)

echo [OK] PHP dan Node.js terdeteksi.
echo.

echo [2/3] Memeriksa & Menjalankan Server Laravel (Port 8000)...
netstat -ano | findstr :8000 | findstr LISTENING >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo [INFO] Laravel Server (port 8000) sudah berjalan.
) else (
    echo [INFO] Memulai php artisan serve di background...
    start "Bintang Mobil - Laravel Server" /min cmd /c "cd /d "%~dp0" && php artisan serve"
)

echo.
echo [3/3] Menjalankan WhatsApp Bridge (HP B)...
start "Bintang Mobil - WhatsApp QR Bridge" cmd /c "cd /d "%~dp0" && call start-bridge.bat"

echo.
echo Menunggu 3 detik agar semua servis siap...
timeout /t 3 /nobreak >nul

echo Membuka Web Dashboard di browser...
start http://127.0.0.1:8000/admin/dashboard

echo.
echo =================================================================
echo  SEMUA SISTEM TELAH AKTIF!
echo  - Web Dashboard : http://127.0.0.1:8000/admin/dashboard
echo  - WhatsApp HP B : Berjalan di jendela WhatsApp Bridge
echo  - Akun Login    : admin@bintangmobil.test / admin123456
echo =================================================================
echo.
echo Jendela ini dapat Anda tutup sekarang, atau biarkan terbuka.
timeout /t 5 >nul
