# Panduan Build Aplikasi Mobile Java Indonusa

Aplikasi mobile ini menggunakan **Capacitor** untuk wrap web app Laravel + Vue.js menjadi native Android (APK) dan iOS (IPA).

## Prasyarat

### Untuk Android:
- Node.js 18+
- Android Studio (download: https://developer.android.com/studio)
- Java JDK 17+
- Android SDK (API Level 33+)

### Untuk iOS (hanya di macOS):
- Node.js 18+
- Xcode 15+ (dari App Store)
- CocoaPods (`sudo gem install cocoapods`)

---

## Langkah 1: Install Dependencies

```bash
npm install
```

## Langkah 2: Konfigurasi Server URL

Edit file `capacitor.config.ts`, ganti URL server Laravel:

```typescript
server: {
  url: 'http://IP_SERVER_LARAVEL:PORT',
  cleartext: true,
}
```

Contoh:
- Development lokal: `http://192.168.1.100:8000`
- Server production: `http://billing.javaindonusa.com`

## Langkah 3: Build Web Assets

```bash
npm run build
```

---

## Build Android (APK)

### 3a. Tambahkan Platform Android

```bash
npx cap add android
```

### 3b. Sync Project

```bash
npx cap sync android
```

### 3c. Buka di Android Studio

```bash
npx cap open android
```

### 3d. Generate APK

Di Android Studio:
1. Menu **Build** > **Build Bundle(s) / APK(s)** > **Build APK(s)**
2. Tunggu proses build selesai
3. APK tersedia di: `android/app/build/outputs/apk/debug/app-debug.apk`

### Build APK via Command Line (tanpa Android Studio GUI):

```bash
cd android
./gradlew assembleDebug
```

APK output: `android/app/build/outputs/apk/debug/app-debug.apk`

### Build Release APK (untuk distribusi):

```bash
cd android
./gradlew assembleRelease
```

> Note: Release APK perlu signing. Lihat bagian "Signing APK" di bawah.

---

## Build iOS (IPA) - Khusus macOS

### 4a. Tambahkan Platform iOS

```bash
npx cap add ios
```

### 4b. Sync Project

```bash
npx cap sync ios
```

### 4c. Install CocoaPods Dependencies

```bash
cd ios/App
pod install
cd ../..
```

### 4d. Buka di Xcode

```bash
npx cap open ios
```

### 4e. Build IPA

Di Xcode:
1. Pilih target device atau "Any iOS Device"
2. Menu **Product** > **Archive**
3. Setelah archive selesai, klik **Distribute App**
4. Pilih **Development** atau **Ad Hoc** untuk distribusi tanpa App Store
5. Export IPA

---

## Signing APK untuk Release

### Buat Keystore (sekali saja):

```bash
keytool -genkey -v -keystore java-indonusa.keystore -alias javaindonusa -keyalg RSA -keysize 2048 -validity 10000
```

### Konfigurasi Signing di `android/app/build.gradle`:

```gradle
android {
    signingConfigs {
        release {
            storeFile file('../../java-indonusa.keystore')
            storePassword 'PASSWORD_ANDA'
            keyAlias 'javaindonusa'
            keyPassword 'PASSWORD_ANDA'
        }
    }
    buildTypes {
        release {
            signingConfig signingConfigs.release
            minifyEnabled true
            proguardFiles getDefaultProguardFile('proguard-android-optimize.txt'), 'proguard-rules.pro'
        }
    }
}
```

---

## Install APK di Android

### Via USB:
```bash
adb install android/app/build/outputs/apk/debug/app-debug.apk
```

### Via File Manager:
1. Copy file APK ke HP Android
2. Buka file APK
3. Izinkan "Install from Unknown Sources"
4. Install

---

## Install IPA di iOS

### Via Xcode (dengan kabel):
1. Connect iPhone ke Mac
2. Di Xcode, pilih device Anda
3. Build & Run

### Via Apple Configurator 2:
1. Download Apple Configurator 2 dari App Store (Mac)
2. Connect iPhone
3. Drag & drop IPA ke device

### Via AltStore (tanpa Mac):
1. Install AltStore di iPhone (https://altstore.io)
2. Sideload IPA via AltStore

---

## Troubleshooting

### Android: "cleartext HTTP traffic not permitted"
Sudah dikonfigurasi di `capacitor.config.ts`. Jika masih error, tambahkan di `android/app/src/main/AndroidManifest.xml`:
```xml
<application android:usesCleartextTraffic="true" ...>
```

### iOS: "App Transport Security"
Untuk HTTP (non-HTTPS), tambahkan di `ios/App/App/Info.plist`:
```xml
<key>NSAppTransportSecurity</key>
<dict>
    <key>NSAllowsArbitraryLoads</key>
    <true/>
</dict>
```

### Tidak bisa connect ke server
1. Pastikan HP dan server dalam jaringan yang sama
2. Pastikan Laravel server berjalan di `0.0.0.0` bukan `127.0.0.1`:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
3. Cek firewall server

---

## Update Aplikasi

Setelah ada perubahan kode:

```bash
npm run build
npx cap sync
```

Lalu rebuild APK/IPA seperti langkah di atas.

---

## Struktur Folder Mobile

```
├── android/                 # Project Android Studio
│   ├── app/
│   │   ├── build/outputs/  # Output APK
│   │   └── src/main/
│   │       ├── res/        # Icons & splash
│   │       └── AndroidManifest.xml
│   └── build.gradle
├── ios/                     # Project Xcode (macOS only)
│   └── App/
├── capacitor.config.ts      # Konfigurasi Capacitor
├── resources/mobile/        # Source icons & splash
└── BUILD_MOBILE.md          # File ini
```
