@echo off
echo ========================================
echo   Java Indonusa - Build Android APK
echo ========================================
echo.

REM Check Node.js
where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Node.js tidak ditemukan. Install dari https://nodejs.org
    pause
    exit /b 1
)

REM Install dependencies jika belum
if not exist "node_modules" (
    echo [1/5] Installing dependencies...
    call npm install
) else (
    echo [1/5] Dependencies sudah terinstall
)

REM Build web assets
echo [2/5] Building web assets...
call npm run build

REM Add Android platform jika belum ada
if not exist "android" (
    echo [3/6] Adding Android platform...
    call npx cap add android
) else (
    echo [3/6] Android platform sudah ada
)

REM Copy custom MainActivity.java untuk fix cookie
echo [4/6] Applying cookie fix...
copy /Y "resources\android-overrides\MainActivity.java" "android\app\src\main\java\com\javaindonusa\billing\MainActivity.java"

REM Sync
echo [5/6] Syncing Capacitor...
call npx cap sync android

REM Build APK
echo [6/6] Building APK...
cd android
call gradlew.bat assembleDebug
cd ..

echo.
echo ========================================
if exist "android\app\build\outputs\apk\debug\app-debug.apk" (
    echo [SUCCESS] APK berhasil dibuat!
    echo.
    echo Lokasi APK:
    echo android\app\build\outputs\apk\debug\app-debug.apk
    echo.
    echo Copy APK ini ke HP Android dan install.
) else (
    echo [ERROR] Build gagal. Cek error di atas.
)
echo ========================================
pause
