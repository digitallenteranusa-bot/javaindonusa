# Panduan Instalasi ISP Billing System - Windows

Panduan instalasi sistem billing ISP untuk pengembangan di Windows menggunakan Laragon.

---

## Daftar Isi

1. [Persyaratan Sistem](#1-persyaratan-sistem)
2. [Instalasi Laragon](#2-instalasi-laragon)
3. [Setup Project](#3-setup-project)
4. [Konfigurasi Environment](#4-konfigurasi-environment)
5. [Setup Database](#5-setup-database)
6. [Build Frontend](#6-build-frontend)
7. [Menjalankan Aplikasi](#7-menjalankan-aplikasi)
8. [Setup Queue Worker](#8-setup-queue-worker)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Persyaratan Sistem

### Minimum Hardware
- RAM: 8 GB
- Storage: 10 GB free space
- Windows 10/11 64-bit

### Software yang Dibutuhkan
| Software | Versi | Download |
|----------|-------|----------|
| Laragon | 6.0+ | https://laragon.org/download/ |
| Git | Latest | https://git-scm.com/download/win |
| Node.js | 18 LTS+ | https://nodejs.org/ |

---

## 2. Instalasi Laragon

### 2.1 Download dan Install Laragon

1. Download **Laragon Full** dari: https://laragon.org/download/
2. Jalankan installer, pilih lokasi instalasi (default: `C:\laragon`)
3. Selesaikan instalasi

### 2.2 Verifikasi Komponen Laragon

Buka Laragon, klik kanan pada window Laragon > **Tools** > **Quick settings**, pastikan:
- **PHP**: 8.2.x atau lebih tinggi
- **MySQL**: 8.0.x

### 2.3 Install Redis untuk Windows (Opsional)

Laragon versi standar tidak menyertakan Redis. Anda bisa:

**Opsi A: Tidak menggunakan Redis (Lebih Mudah)**

Ubah file `.env` nanti untuk menggunakan file-based cache (lihat bagian Konfigurasi Environment).

**Opsi B: Install Redis Manual**
1. Download Redis dari: https://github.com/tporadowski/redis/releases
2. Download file `.msi` atau `.zip`
3. Install atau extract ke `C:\laragon\bin\redis`
4. Restart Laragon

### 2.4 Pastikan PHP Extensions Aktif

1. Buka Laragon
2. Klik kanan pada window Laragon > **PHP** > **Extensions**
3. Pastikan extensions berikut aktif (tercentang):
   - curl
   - fileinfo
   - gd
   - mbstring
   - mysqli
   - openssl
   - pdo_mysql
   - zip
   - intl

> **Catatan:** Extension `redis` dan `bcmath` mungkin tidak tersedia di daftar.
> - Jika **redis tidak ada**: Ubah `.env` untuk tidak menggunakan Redis (lihat bagian Troubleshooting)
> - Jika **bcmath tidak ada**: Biasanya sudah termasuk dalam PHP default Laragon, tidak perlu diaktifkan manual

---

## 3. Setup Project

### 3.1 Clone/Copy Project

**Opsi A: Clone dari Git (Direkomendasikan)**

Buka **Terminal** (Klik kanan Laragon > **Terminal**) atau Command Prompt:

```cmd
cd C:\laragon\www
git clone https://github.com/digitallenteranusa-bot/javaindonusa.git billing
cd billing
```

**Opsi B: Copy Manual**

1. Copy folder project ke `C:\laragon\www\billing`
2. Buka Terminal di Laragon
3. Navigasi ke folder:
```cmd
cd C:\laragon\www\billing
```

### 3.2 Install Dependencies PHP

```cmd
cd C:\laragon\www\billing
composer install
```

Jika error memory limit:
```cmd
set COMPOSER_MEMORY_LIMIT=-1
composer install
```

### 3.3 Install Dependencies JavaScript

```cmd
cd C:\laragon\www\billing
npm install
```

---

## 4. Konfigurasi Environment

### 4.1 Buat File .env

```cmd
cd C:\laragon\www\billing
copy .env.example .env
php artisan key:generate
```

### 4.2 Edit File .env

Buka file `C:\laragon\www\billing\.env` dengan Notepad atau VS Code:

```env
#-------------------------------------------------
# APLIKASI
#-------------------------------------------------
APP_NAME="Java Indonusa Billing"
APP_ENV=local
APP_KEY=base64:xxxxx
APP_DEBUG=true
APP_TIMEZONE=Asia/Jakarta
APP_URL=http://billing.test

#-------------------------------------------------
# DATABASE
#-------------------------------------------------
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=billing_javaindonusa
DB_USERNAME=root
DB_PASSWORD=

#-------------------------------------------------
# CACHE & SESSION
# Pilih salah satu opsi di bawah:
#-------------------------------------------------

# OPSI 1: Jika TIDAK menggunakan Redis (Direkomendasikan untuk pemula)
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# OPSI 2: Jika menggunakan Redis (perlu install Redis terlebih dahulu)
# CACHE_STORE=redis
# SESSION_DRIVER=redis
# QUEUE_CONNECTION=redis
# REDIS_HOST=127.0.0.1
# REDIS_PASSWORD=null
# REDIS_PORT=6379
# REDIS_CLIENT=phpredis

#-------------------------------------------------
# BILLING (Opsional - gunakan default)
#-------------------------------------------------
BILLING_DUE_DAYS=20
BILLING_GRACE_DAYS=7

#-------------------------------------------------
# MIKROTIK (Opsional - isi jika ada)
#-------------------------------------------------
MIKROTIK_HOST=192.168.88.1
MIKROTIK_USER=admin
MIKROTIK_PASS=
MIKROTIK_PORT=8728

#-------------------------------------------------
# LOG
#-------------------------------------------------
LOG_CHANNEL=daily
LOG_LEVEL=debug
```

**Catatan Penting:**
- `DB_PASSWORD=` kosong (default Laragon MySQL)
- `APP_DEBUG=true` untuk development
- `APP_URL=http://billing.test` (otomatis dibuat oleh Laragon)
- Gunakan **OPSI 1** (tanpa Redis) jika Anda pemula atau tidak ingin install Redis

---

## 5. Setup Database

### 5.1 Buat Database

**Opsi A: Via HeidiSQL (GUI - Direkomendasikan)**

1. Buka Laragon
2. Klik kanan pada window Laragon > **MySQL** > **HeidiSQL**
3. Klik **Open** untuk connect (user: `root`, password: kosong)
4. Klik kanan pada panel kiri (nama server) > **Create new** > **Database**
5. Nama database: `billing_javaindonusa`
6. Klik **OK**

> **Catatan:** Collation akan otomatis menggunakan default MySQL (utf8mb4). Tidak perlu diubah.

**Opsi B: Via Terminal Laragon**

1. Klik kanan pada window Laragon > **MySQL** > **Console**
2. Jalankan perintah berikut:

```sql
CREATE DATABASE billing_javaindonusa;
EXIT;
```

**Opsi C: Via Command Prompt**

```cmd
cd C:\laragon\www\billing
mysql -u root -e "CREATE DATABASE billing_javaindonusa"
```

### 5.2 Jalankan Migrasi

```cmd
cd C:\laragon\www\billing
php artisan migrate
```

**Output yang diharapkan:**
```
Migration table created successfully.
Migrating: 2024_01_01_000001_create_users_table
Migrated:  2024_01_01_000001_create_users_table
...
```

### 5.3 Jalankan Seeder

```cmd
cd C:\laragon\www\billing
php artisan db:seed
```

**Atau fresh install (hapus data lama & isi ulang):**
```cmd
php artisan migrate:fresh --seed
```

---

## 6. Build Frontend

### 6.1 Build Assets

```cmd
cd C:\laragon\www\billing
npm run build
```

### 6.2 Buat Storage Link

```cmd
cd C:\laragon\www\billing
php artisan storage:link
```

---

## 7. Menjalankan Aplikasi

### 7.1 Start Laragon

1. Buka Laragon
2. Klik **Start All** (atau pastikan Apache/Nginx, MySQL, Redis sudah hijau)

### 7.2 Akses Aplikasi

Laragon otomatis membuat virtual host. Akses di browser:

| Portal | URL |
|--------|-----|
| Login | http://billing.test/login |
| Admin | http://billing.test/admin |
| Collector | http://billing.test/collector |
| Customer Portal | http://billing.test/portal |

**Jika `billing.test` tidak bisa diakses:**

1. Klik kanan Laragon > **Apache** > **sites-enabled** > cek ada file `billing.test.conf`
2. Jika tidak ada: Klik kanan Laragon > **Apache** > **Reload**
3. Restart Laragon

### 7.3 Login dengan Akun Default

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@javaindonusa.net` | `password` |
| Finance | `finance@javaindonusa.net` | `password` |
| Collector | `budi@javaindonusa.net` | `password` |

---

## 8. Setup Queue Worker

Queue worker diperlukan untuk memproses job async (notifikasi, isolasi, dll).

> **Catatan:** Jika Anda menggunakan `QUEUE_CONNECTION=sync` di file `.env`, langkah ini **tidak diperlukan**. Job akan diproses langsung tanpa queue.

### 8.1 Menjalankan Queue Worker (Jika menggunakan Redis)

Buka Terminal baru di Laragon (Klik kanan > **Terminal**) dan jalankan:

```cmd
cd C:\laragon\www\billing
php artisan queue:work
```

**Biarkan terminal ini tetap terbuka selama development.**

### 8.2 Mode Development dengan Hot Reload

Untuk frontend development dengan hot reload, buka Terminal lain:

```cmd
cd C:\laragon\www\billing
npm run dev
```

Akses aplikasi di `http://localhost:5173` (Vite dev server)

---

## 9. Troubleshooting

### Error: "Class not found" atau Autoload Error

```cmd
cd C:\laragon\www\billing
composer dump-autoload
php artisan optimize:clear
```

### Error: Redis Connection Refused

1. Pastikan Redis berjalan di Laragon (ikon Redis hijau)
2. Jika tidak ada Redis, ubah `.env`:
```env
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

### Error: 500 Internal Server Error

```cmd
cd C:\laragon\www\billing

# Cek log error
type storage\logs\laravel.log

# Clear semua cache
php artisan optimize:clear

# Generate ulang key
php artisan key:generate
```

### Error: SQLSTATE Connection Refused

1. Pastikan MySQL berjalan di Laragon
2. Cek kredensial di `.env` (DB_USERNAME, DB_PASSWORD)
3. Default Laragon: `root` tanpa password

### Error: billing.test Tidak Bisa Diakses

1. Klik kanan Laragon > **Nginx** atau **Apache** > **Reload**
2. Jika masih error, restart Laragon sepenuhnya
3. Cek file hosts: `C:\Windows\System32\drivers\etc\hosts`
   Harus ada: `127.0.0.1 billing.test`

### Error: npm run build Gagal

```cmd
# Hapus node_modules dan install ulang
cd C:\laragon\www\billing
rmdir /s /q node_modules
del package-lock.json
npm install
npm run build
```

### Error: PHP Extension Tidak Ditemukan

1. Buka Laragon > **Menu** > **PHP** > **Extensions**
2. Aktifkan extension yang diperlukan
3. Restart Laragon

---

## Perintah Development Harian

```cmd
# Masuk ke direktori project
cd C:\laragon\www\billing

# Clear cache
php artisan optimize:clear

# Jalankan migration baru (setelah pull)
php artisan migrate

# Jalankan seeder (reset data)
php artisan migrate:fresh --seed

# Generate IDE helper (opsional, untuk VS Code)
php artisan ide-helper:generate

# Jalankan tests
php artisan test

# Lihat log error
type storage\logs\laravel.log
```

---

## Struktur Folder di Windows

```
C:\laragon\www\billing\
├── app\                    # Kode aplikasi PHP
├── bootstrap\              # File bootstrap Laravel
├── config\                 # File konfigurasi
├── database\               # Migration dan seeder
├── public\                 # Document root web
│   └── build\             # Assets hasil build
├── resources\              # Views, JS, CSS source
├── routes\                 # Definisi routes
├── storage\                # File storage
│   └── logs\              # Log aplikasi
├── vendor\                 # Dependencies PHP
├── node_modules\           # Dependencies JS
├── .env                    # Konfigurasi environment
└── artisan                 # CLI Laravel
```

---

## Tips Development

### 1. Gunakan VS Code Extensions
- Laravel Blade Snippets
- PHP Intelephense
- Vue - Official
- Tailwind CSS IntelliSense
- GitLens

### 2. Buka Multiple Terminal
- Terminal 1: `php artisan serve` atau gunakan Laragon
- Terminal 2: `npm run dev` (untuk hot reload)
- Terminal 3: `php artisan queue:work` (untuk job processing)

### 3. Database GUI
- Gunakan HeidiSQL (bawaan Laragon)
- Atau TablePlus, DBeaver

### 4. Debugging
- Gunakan Laravel Telescope (sudah terinstall)
- Akses: http://billing.test/telescope
- Atau cek `storage/logs/laravel.log`

---

*Dokumentasi ini untuk development di Windows*
*Untuk production, gunakan panduan 01_INSTALASI.md (Linux)*
