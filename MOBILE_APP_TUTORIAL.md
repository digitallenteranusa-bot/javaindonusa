# Tutorial Aplikasi Mobile Java Indonusa

Panduan lengkap untuk build dan install aplikasi Android Java Indonusa Billing System.

## Daftar Isi

1. [Prasyarat](#prasyarat)
2. [Build APK Pertama Kali](#build-apk-pertama-kali)
3. [Install di HP Android](#install-di-hp-android)
4. [Update Aplikasi](#update-aplikasi)
5. [Troubleshooting](#troubleshooting)

---

## Prasyarat

### Software yang Dibutuhkan (Windows)

1. **Node.js 18+**
   - Download: https://nodejs.org/
   - Atau via winget: `winget install OpenJS.NodeJS`

2. **Android Studio**
   - Download: https://developer.android.com/studio
   - Saat install, centang:
     - Android SDK
     - Android SDK Platform
     - Android SDK Build-Tools

3. **Git** (opsional, untuk update)
   - Download: https://git-scm.com/

### Verifikasi Instalasi

Buka Command Prompt dan jalankan:
```bash
node --version    # Harus v18+
npm --version     # Harus v9+
```

---

## Build APK Pertama Kali

### Langkah 1: Clone/Download Project

```bash
git clone https://github.com/digitallenteranusa-bot/javaindonusa.git
cd javaindonusa
```

### Langkah 2: Install Dependencies

```bash
npm install
```

### Langkah 3: Konfigurasi Server URL

Edit file `capacitor.config.ts`:

```typescript
server: {
  url: 'https://javaindonusa.my.id',  // Ganti dengan URL server Anda
  cleartext: true,
},
```

### Langkah 4: Build Web Assets

```bash
npm run build
```

### Langkah 5: Setup Android Platform

```bash
npx cap add android
npx cap sync android
```

### Langkah 6: Build APK

**Opsi A - Menggunakan Script (Recommended):**
```bash
# Windows
build-android.bat

# Linux/Mac
chmod +x build-android.sh
./build-android.sh
```

**Opsi B - Manual:**
```bash
# Set environment variables
set JAVA_HOME=C:\Program Files\Android\Android Studio\jbr
set ANDROID_HOME=%LOCALAPPDATA%\Android\Sdk

# Build APK
cd android
gradlew.bat assembleDebug
cd ..
```

### Langkah 7: Ambil File APK

Lokasi APK:
```
android\app\build\outputs\apk\debug\app-debug.apk
```

Ukuran: ~5 MB

---

## Install di HP Android

### Metode 1: Via USB

1. Hubungkan HP ke komputer via kabel USB
2. Copy file `app-debug.apk` ke HP
3. Di HP, buka file manager
4. Cari dan tap file APK
5. Jika muncul peringatan keamanan:
   - Tap **Settings/Pengaturan**
   - Aktifkan **Allow from this source/Izinkan dari sumber ini**
   - Kembali dan tap **Install**
6. Tunggu instalasi selesai
7. Tap **Open/Buka**

### Metode 2: Via WhatsApp/Telegram

1. Kirim file APK ke diri sendiri via WhatsApp/Telegram
2. Download file di HP
3. Buka file APK dan install

### Metode 3: Via Google Drive

1. Upload APK ke Google Drive
2. Di HP, buka Google Drive
3. Download dan install APK

### Metode 4: Via ADB (Developer)

```bash
adb install android\app\build\outputs\apk\debug\app-debug.apk
```

---

## Update Aplikasi

### Jika Ada Perubahan di Server (Kode Laravel/Vue)

**Tidak perlu update APK!** Aplikasi otomatis menampilkan versi terbaru dari server.

Cukup refresh/restart aplikasi di HP.

### Jika Ada Perubahan di Konfigurasi Mobile

Jalankan perintah berikut di komputer:

```bash
# 1. Pull perubahan terbaru dari GitHub
git pull origin main

# 2. Install dependencies baru (jika ada)
npm install

# 3. Build web assets
npm run build

# 4. Sync ke Android
npx cap sync android

# 5. Build APK baru
cd android
gradlew.bat assembleDebug
cd ..
```

APK baru tersedia di:
```
android\app\build\outputs\apk\debug\app-debug.apk
```

Install ulang APK di HP (uninstall dulu yang lama atau langsung install untuk update).

### Script Update Cepat

Buat file `update-apk.bat`:
```batch
@echo off
echo Updating APK...
git pull origin main
npm install
npm run build
npx cap sync android
cd android
gradlew.bat assembleDebug
cd ..
echo.
echo APK updated! Location:
echo android\app\build\outputs\apk\debug\app-debug.apk
pause
```

---

## Troubleshooting

### Error: JAVA_HOME is not set

**Solusi:**
```bash
set JAVA_HOME=C:\Program Files\Android\Android Studio\jbr
```

Atau tambahkan ke System Environment Variables secara permanen.

### Error: SDK location not found

**Solusi:**
```bash
set ANDROID_HOME=%LOCALAPPDATA%\Android\Sdk
```

Atau buat file `android/local.properties`:
```
sdk.dir=C:\\Users\\NAMAUSER\\AppData\\Local\\Android\\Sdk
```

### Error: Connection Timeout di Aplikasi

**Penyebab:** Server tidak bisa diakses

**Solusi:**
1. Pastikan server Laravel berjalan
2. Cek URL di `capacitor.config.ts` sudah benar
3. Pastikan HP terhubung ke internet
4. Jika development lokal, pastikan HP dan komputer di jaringan yang sama

### Aplikasi Blank/Putih

**Penyebab:** Server error atau URL salah

**Solusi:**
1. Buka URL server di browser HP untuk test
2. Cek log error di server Laravel
3. Pastikan HTTPS certificate valid (jika pakai https)

### Tidak Bisa Install APK

**Penyebab:** Sumber tidak dikenal diblokir

**Solusi:**
1. Buka **Settings > Security**
2. Aktifkan **Unknown Sources** atau **Install unknown apps**
3. Pilih file manager/browser yang digunakan
4. Izinkan install dari sumber tersebut

### Build Gagal: Out of Memory

**Solusi:**
Tambahkan di `android/gradle.properties`:
```
org.gradle.jvmargs=-Xmx4096m
```

---

## Struktur File Penting

```
├── capacitor.config.ts      # Konfigurasi app (URL server, nama app)
├── package.json             # Dependencies
├── build-android.bat        # Script build Windows
├── build-android.sh         # Script build Linux/Mac
├── android/                 # Project Android (auto-generated)
│   └── app/build/outputs/apk/debug/
│       └── app-debug.apk    # File APK hasil build
├── resources/
│   └── mobile/
│       ├── icon.svg         # Icon aplikasi
│       └── splash.svg       # Splash screen
└── MOBILE_APP_TUTORIAL.md   # File ini
```

---

## Customisasi

### Mengubah Nama Aplikasi

Edit `capacitor.config.ts`:
```typescript
appName: 'Nama Aplikasi Baru',
```

Lalu rebuild:
```bash
npx cap sync android
cd android && gradlew.bat assembleDebug
```

### Mengubah Icon Aplikasi

1. Ganti file `resources/mobile/icon.svg` dengan icon baru (512x512 px)
2. Install @capacitor/assets:
   ```bash
   npm install -D @capacitor/assets
   npx capacitor-assets generate
   ```
3. Rebuild APK

### Mengubah Warna Splash Screen

Edit `capacitor.config.ts`:
```typescript
plugins: {
  SplashScreen: {
    backgroundColor: '#1e40af',  // Ganti warna
  },
}
```

---

## FAQ

**Q: Apakah perlu rebuild APK setiap ada update?**
A: Tidak, selama perubahan hanya di server (Laravel/Vue). APK hanya perlu rebuild jika mengubah konfigurasi mobile (nama app, icon, URL server).

**Q: Berapa ukuran APK?**
A: Sekitar 5 MB.

**Q: Apakah bisa untuk iOS?**
A: Ya, tapi butuh Mac dengan Xcode. Lihat `BUILD_MOBILE.md` untuk panduan iOS.

**Q: Apakah bisa offline?**
A: Tidak, aplikasi membutuhkan koneksi ke server untuk berfungsi.

**Q: Bagaimana cara debug?**
A: Gunakan Chrome DevTools:
1. Hubungkan HP via USB dengan USB Debugging aktif
2. Buka `chrome://inspect` di Chrome komputer
3. Pilih device dan inspect

---

## Support

Jika ada masalah, buat issue di:
https://github.com/digitallenteranusa-bot/javaindonusa/issues
