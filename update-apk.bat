@echo off
echo ========================================
echo   Java Indonusa - Update APK
echo ========================================
echo.

REM Set environment variables
set JAVA_HOME=C:\Program Files\Android\Android Studio\jbr
set ANDROID_HOME=%LOCALAPPDATA%\Android\Sdk

echo [1/5] Pulling latest changes from GitHub...
git pull origin main
if %errorlevel% neq 0 (
    echo [WARNING] Git pull failed. Continuing with local files...
)

echo [2/5] Installing dependencies...
call npm install

echo [3/5] Building web assets...
call npm run build

echo [4/5] Syncing to Android...
call npx cap sync android

echo [5/5] Building APK...
cd android
call gradlew.bat assembleDebug
cd ..

echo.
echo ========================================
if exist "android\app\build\outputs\apk\debug\app-debug.apk" (
    echo [SUCCESS] APK berhasil di-update!
    echo.
    echo Lokasi APK:
    echo android\app\build\outputs\apk\debug\app-debug.apk
    echo.
    echo Install APK baru ini ke HP Android.
) else (
    echo [ERROR] Build gagal. Cek error di atas.
)
echo ========================================
pause
