#!/bin/bash

echo "========================================"
echo "  Java Indonusa - Build iOS IPA"
echo "========================================"
echo

# Check if running on macOS
if [[ "$OSTYPE" != "darwin"* ]]; then
    echo "[ERROR] iOS build hanya bisa dilakukan di macOS"
    exit 1
fi

# Check Xcode
if ! command -v xcodebuild &> /dev/null; then
    echo "[ERROR] Xcode tidak ditemukan. Install dari App Store"
    exit 1
fi

# Check CocoaPods
if ! command -v pod &> /dev/null; then
    echo "[WARNING] CocoaPods tidak ditemukan. Installing..."
    sudo gem install cocoapods
fi

# Check Node.js
if ! command -v node &> /dev/null; then
    echo "[ERROR] Node.js tidak ditemukan. Install dari https://nodejs.org"
    exit 1
fi

# Install dependencies jika belum
if [ ! -d "node_modules" ]; then
    echo "[1/6] Installing dependencies..."
    npm install
else
    echo "[1/6] Dependencies sudah terinstall"
fi

# Build web assets
echo "[2/6] Building web assets..."
npm run build

# Add iOS platform jika belum ada
if [ ! -d "ios" ]; then
    echo "[3/6] Adding iOS platform..."
    npx cap add ios
else
    echo "[3/6] iOS platform sudah ada"
fi

# Sync
echo "[4/6] Syncing Capacitor..."
npx cap sync ios

# Install CocoaPods
echo "[5/6] Installing CocoaPods dependencies..."
cd ios/App
pod install
cd ../..

# Open Xcode
echo "[6/6] Opening Xcode..."
npx cap open ios

echo
echo "========================================"
echo "Xcode sudah terbuka. Untuk build IPA:"
echo "1. Pilih target device 'Any iOS Device'"
echo "2. Menu Product > Archive"
echo "3. Distribute App > Development/Ad Hoc"
echo "========================================"
