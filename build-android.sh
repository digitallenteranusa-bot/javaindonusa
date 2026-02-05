#!/bin/bash

echo "========================================"
echo "  Java Indonusa - Build Android APK"
echo "========================================"
echo

# Check Node.js
if ! command -v node &> /dev/null; then
    echo "[ERROR] Node.js tidak ditemukan. Install dari https://nodejs.org"
    exit 1
fi

# Install dependencies jika belum
if [ ! -d "node_modules" ]; then
    echo "[1/5] Installing dependencies..."
    npm install
else
    echo "[1/5] Dependencies sudah terinstall"
fi

# Build web assets
echo "[2/5] Building web assets..."
npm run build

# Add Android platform jika belum ada
if [ ! -d "android" ]; then
    echo "[3/5] Adding Android platform..."
    npx cap add android
else
    echo "[3/5] Android platform sudah ada"
fi

# Sync
echo "[4/5] Syncing Capacitor..."
npx cap sync android

# Build APK
echo "[5/5] Building APK..."
cd android
./gradlew assembleDebug
cd ..

echo
echo "========================================"
if [ -f "android/app/build/outputs/apk/debug/app-debug.apk" ]; then
    echo "[SUCCESS] APK berhasil dibuat!"
    echo
    echo "Lokasi APK:"
    echo "android/app/build/outputs/apk/debug/app-debug.apk"
    echo
    echo "Copy APK ini ke HP Android dan install."
else
    echo "[ERROR] Build gagal. Cek error di atas."
fi
echo "========================================"
