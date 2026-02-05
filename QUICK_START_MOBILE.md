# Quick Start - Build APK Android

## Langkah Cepat (5 menit)

### 1. Install Android Studio
Download dari: https://developer.android.com/studio

### 2. Install Dependencies & Build
```bash
npm install
```

### 3. Konfigurasi Server URL
Edit `capacitor.config.ts`, ganti IP server Laravel:
```typescript
server: {
  url: 'http://IP_SERVER_ANDA:8000',
}
```

### 4. Build APK
**Windows:**
```bash
build-android.bat
```

**Linux/Mac:**
```bash
chmod +x build-android.sh
./build-android.sh
```

### 5. Ambil APK
Lokasi: `android/app/build/outputs/apk/debug/app-debug.apk`

Copy ke HP Android, install.

---

## Tips

**Jalankan Laravel dengan IP:**
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

**Cari IP komputer:**
- Windows: `ipconfig`
- Linux/Mac: `ifconfig` atau `ip addr`

Gunakan IP lokal (192.168.x.x) di `capacitor.config.ts`
