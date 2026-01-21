# Panduan Instalasi ISP Billing System - Java Indonusa

Panduan lengkap instalasi sistem billing ISP untuk server Linux (Ubuntu 22.04/24.04 LTS).

---

## Daftar Isi

1. [Persyaratan Sistem](#1-persyaratan-sistem)
2. [Instalasi Dependencies](#2-instalasi-dependencies)
3. [Setup Project](#3-setup-project)
4. [Konfigurasi Environment](#4-konfigurasi-environment)
5. [Setup Database](#5-setup-database)
6. [Build Frontend](#6-build-frontend)
7. [Konfigurasi Web Server](#7-konfigurasi-web-server)
8. [Setup Queue Worker](#8-setup-queue-worker)
9. [Setup Scheduler (Cron)](#9-setup-scheduler-cron)
10. [Konfigurasi Firewall & SSL](#10-konfigurasi-firewall--ssl)
11. [Verifikasi & Optimasi](#11-verifikasi--optimasi)
12. [Login Pertama Kali](#12-login-pertama-kali)
13. [Troubleshooting](#13-troubleshooting)

---

## 1. Persyaratan Sistem

### Minimum Hardware

| Komponen | Minimum | Rekomendasi (Production) |
|----------|---------|--------------------------|
| CPU | 2 Core | 4 Core |
| RAM | 4 GB | 8 GB |
| Storage | 20 GB SSD | 50 GB SSD |
| Bandwidth | 100 Mbps | 1 Gbps |

### Software Requirements

| Software | Versi Minimum | Rekomendasi |
|----------|---------------|-------------|
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |
| PHP | 8.2 | 8.2+ |
| MySQL | 8.0 | 8.0+ |
| Redis | 6.0 | 7.0+ |
| Node.js | 18 LTS | 20 LTS |
| Nginx | 1.18 | Latest |
| Composer | 2.0 | 2.6+ |
| Git | 2.x | Latest |

### PHP Extensions Required

```
php-cli php-fpm php-mysql php-redis php-mbstring php-xml
php-curl php-zip php-gd php-bcmath php-intl php-soap php-imagick
```

---

## 2. Instalasi Dependencies

### 2.1 Update Sistem

```bash
# Masuk ke server via SSH
ssh user@server_ip

# Update sistem
sudo apt update && sudo apt upgrade -y
```

### 2.2 Install Paket Dasar

```bash
sudo apt install -y curl wget gnupg2 ca-certificates lsb-release \
    apt-transport-https software-properties-common unzip git
```

### 2.3 Install PHP 8.2

```bash
# Tambah repository PHP
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.2 dan extensions
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-common \
    php8.2-mysql php8.2-zip php8.2-gd php8.2-mbstring php8.2-curl \
    php8.2-xml php8.2-bcmath php8.2-intl php8.2-readline \
    php8.2-redis php8.2-soap php8.2-ldap php8.2-imagick

# Verifikasi
php -v
```

**Output yang diharapkan:**
```
PHP 8.2.x (cli) ...
```

### 2.4 Konfigurasi PHP

```bash
# Edit konfigurasi PHP-FPM
sudo nano /etc/php/8.2/fpm/php.ini
```

**Cari dan ubah nilai berikut:**

```ini
upload_max_filesize = 50M
post_max_size = 50M
memory_limit = 512M
max_execution_time = 300
date.timezone = Asia/Jakarta
```

**Simpan file:** `Ctrl+O`, `Enter`, `Ctrl+X`

```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
sudo systemctl enable php8.2-fpm
```

### 2.5 Install MySQL 8.0

```bash
# Install MySQL Server
sudo apt install -y mysql-server

# Jalankan secure installation
sudo mysql_secure_installation
```

**Jawab pertanyaan wizard:**
- VALIDATE PASSWORD COMPONENT: **Y**
- Password Strength: **2** (STRONG)
- Masukkan password root MySQL yang kuat
- Remove anonymous users: **Y**
- Disallow root login remotely: **Y**
- Remove test database: **Y**
- Reload privilege tables: **Y**

```bash
# Verifikasi MySQL
sudo systemctl status mysql
sudo systemctl enable mysql
```

### 2.6 Install Redis

```bash
# Install Redis
sudo apt install -y redis-server

# Edit konfigurasi
sudo nano /etc/redis/redis.conf
```

**Cari dan ubah:**

```conf
# Cari baris "supervised no" dan ubah menjadi:
supervised systemd

# Cari baris "# maxmemory" dan ubah menjadi:
maxmemory 256mb

# Cari baris "# maxmemory-policy" dan ubah menjadi:
maxmemory-policy allkeys-lru
```

**Simpan file:** `Ctrl+O`, `Enter`, `Ctrl+X`

```bash
# Restart Redis
sudo systemctl restart redis-server
sudo systemctl enable redis-server

# Verifikasi (harus menampilkan "PONG")
redis-cli ping
```

### 2.7 Install Node.js 18 LTS

```bash
# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Verifikasi
node -v
npm -v
```

### 2.8 Install Composer

```bash
# Download dan install Composer
cd /tmp
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Jika terjadi error "command not found"
echo 'export PATH="$PATH:/usr/local/bin"' >> ~/.bashrc
source ~/.bashrc

# Verifikasi
composer --version
```

### 2.9 Install Nginx

```bash
# Install Nginx
sudo apt install -y nginx

# Start dan enable
sudo systemctl start nginx
sudo systemctl enable nginx

# Verifikasi
sudo systemctl status nginx
```

---

## 3. Setup Project

### 3.1 Buat Direktori Project

```bash
# Buat direktori aplikasi
sudo mkdir -p /var/www/billing
cd /var/www/billing
```

### 3.2 Upload/Clone Project

**Opsi A: Clone dari Git Repository (Direkomendasikan)**

```bash
cd /var/www/billing
sudo git clone https://github.com/digitallenteranusa-bot/javaindonusa.git .
```

**Opsi B: Upload dari Lokal (via SCP)**

```bash
# Jalankan di komputer lokal:
scp -r /path/to/java-indonusa/* user@server_ip:/var/www/billing/
```

**Opsi C: Extract dari ZIP**

```bash
# Upload file zip ke server, kemudian:
cd /var/www/billing
sudo unzip billing-system.zip
```

### 3.3 Set Ownership dan Permission

```bash
# Set ownership
sudo chown -R $USER:www-data /var/www/billing

# Set permission direktori
sudo find /var/www/billing -type d -exec chmod 755 {} \;

# Set permission file
sudo find /var/www/billing -type f -exec chmod 644 {} \;

# Set permission khusus untuk storage dan cache (PENTING!)
sudo chmod -R 775 /var/www/billing/storage
sudo chmod -R 775 /var/www/billing/bootstrap/cache
sudo chgrp -R www-data /var/www/billing/storage
sudo chgrp -R www-data /var/www/billing/bootstrap/cache
```

### 3.4 Install Dependencies PHP

```bash
cd /var/www/billing

# Install dependencies
composer install --optimize-autoloader --no-dev

# Jika error memory limit:
COMPOSER_MEMORY_LIMIT=-1 composer install --optimize-autoloader --no-dev
```

### 3.5 Install Dependencies JavaScript

```bash
cd /var/www/billing
npm install
```

---

## 4. Konfigurasi Environment

### 4.1 Buat File Environment

```bash
cd /var/www/billing

# Copy file contoh
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4.2 Edit File .env

```bash
nano /var/www/billing/.env
```

**Edit konfigurasi berikut (sesuaikan dengan kebutuhan Anda):**

```env
#-------------------------------------------------
# APLIKASI (WAJIB)
#-------------------------------------------------
APP_NAME="Java Indonusa Billing"
APP_ENV=production
APP_KEY=base64:xxxxx  # Sudah di-generate otomatis
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_URL=https://billing.javaindonusa.com   # Ganti dengan domain Anda

#-------------------------------------------------
# DATABASE (WAJIB)
#-------------------------------------------------
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=billing_javaindonusa
DB_USERNAME=billing_user
DB_PASSWORD=password_yang_kuat_dan_aman     # Ganti dengan password Anda

#-------------------------------------------------
# CACHE & SESSION (WAJIB)
#-------------------------------------------------
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

#-------------------------------------------------
# REDIS (WAJIB)
#-------------------------------------------------
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=phpredis

#-------------------------------------------------
# BILLING (OPSIONAL - gunakan default jika tidak yakin)
#-------------------------------------------------
BILLING_DUE_DAYS=20
BILLING_GRACE_DAYS=7
BILLING_INVOICE_DAY=1
BILLING_ISOLATION_MIN_MONTHS=2
BILLING_RECENT_PAYMENT_DAYS=30
BILLING_RAPEL_TOLERANCE_MONTHS=3

#-------------------------------------------------
# MIKROTIK (OPSIONAL - isi jika menggunakan Mikrotik)
#-------------------------------------------------
MIKROTIK_HOST=192.168.88.1
MIKROTIK_USER=admin
MIKROTIK_PASS=password_mikrotik
MIKROTIK_PORT=8728
MIKROTIK_TIMEOUT=10
MIKROTIK_ISOLATED_PROFILE=ISOLIR
MIKROTIK_ISOLATED_ADDRESS_LIST=ISOLIR

#-------------------------------------------------
# GENIEACS (OPSIONAL - isi jika menggunakan TR-069)
#-------------------------------------------------
GENIEACS_NBI_URL=http://localhost:7557
GENIEACS_UI_URL=http://localhost:3000
GENIEACS_FS_URL=http://localhost:7567
GENIEACS_USERNAME=admin
GENIEACS_PASSWORD=admin
GENIEACS_TIMEOUT=30

#-------------------------------------------------
# WHATSAPP GATEWAY (OPSIONAL)
#-------------------------------------------------
WHATSAPP_GATEWAY_URL=https://api.whatsapp.gateway.com
WHATSAPP_API_KEY=your_api_key
WHATSAPP_SENDER=6281234567890

#-------------------------------------------------
# LOG
#-------------------------------------------------
LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DAILY_DAYS=14
```

**Simpan file:** `Ctrl+O`, `Enter`, `Ctrl+X`

### 4.3 Set Permission File .env

```bash
chmod 600 /var/www/billing/.env
```

---

## 5. Setup Database

### 5.1 Buat Database dan User

```bash
# Login ke MySQL
sudo mysql -u root -p
```

**Jalankan query SQL berikut (ganti password sesuai keinginan):**

```sql
-- Buat database
CREATE DATABASE billing_javaindonusa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Buat user database
CREATE USER 'billing_user'@'localhost' IDENTIFIED BY 'password_yang_kuat_dan_aman';

-- Berikan privilege
GRANT ALL PRIVILEGES ON billing_javaindonusa.* TO 'billing_user'@'localhost';

-- Apply privilege
FLUSH PRIVILEGES;

-- Keluar
EXIT;
```

### 5.2 Jalankan Migrasi Database

```bash
cd /var/www/billing

# Jalankan migrasi
php artisan migrate --force
```

**Output yang diharapkan:**
```
Migration table created successfully.
Migrating: 2024_01_01_000001_create_users_table
Migrated:  2024_01_01_000001_create_users_table (xxx ms)
... (semua migration berhasil)
```

### 5.3 Jalankan Seeder (Data Awal)

```bash
cd /var/www/billing

# Jalankan seeder untuk data default (termasuk user admin)
php artisan db:seed --force
```

**Atau untuk fresh install (HAPUS semua data lama):**

```bash
php artisan migrate:fresh --seed --force
```

---

## 6. Build Frontend

### 6.1 Build Assets untuk Production

```bash
cd /var/www/billing

# Build assets
npm run build
```

### 6.2 Verifikasi Build

```bash
# Pastikan folder public/build ada
ls -la /var/www/billing/public/build/
```

**Output yang diharapkan:** Folder berisi file `.js` dan `.css`

### 6.3 Buat Symbolic Link Storage

```bash
cd /var/www/billing
php artisan storage:link
```

---

## 7. Konfigurasi Web Server

### 7.1 Buat Konfigurasi Nginx

```bash
sudo nano /etc/nginx/sites-available/billing
```

**Isi dengan konfigurasi berikut (ganti `billing.javaindonusa.com` dengan domain Anda):**

```nginx
server {
    listen 80;
    listen [::]:80;

    server_name billing.javaindonusa.com;
    root /var/www/billing/public;

    index index.php index.html;

    charset utf-8;

    # Logging
    access_log /var/log/nginx/billing_access.log;
    error_log /var/log/nginx/billing_error.log;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml application/javascript application/json;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Upload size (untuk firmware files)
    client_max_body_size 100M;

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP Processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;

        fastcgi_connect_timeout 60;
        fastcgi_send_timeout 180;
        fastcgi_read_timeout 180;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    location = /favicon.ico {
        access_log off;
        log_not_found off;
    }

    location = /robots.txt {
        access_log off;
        log_not_found off;
    }
}
```

**Simpan file:** `Ctrl+O`, `Enter`, `Ctrl+X`

### 7.2 Aktifkan Konfigurasi

```bash
# Buat symbolic link
sudo ln -s /etc/nginx/sites-available/billing /etc/nginx/sites-enabled/

# Hapus default site (opsional)
sudo rm -f /etc/nginx/sites-enabled/default

# Test konfigurasi
sudo nginx -t
```

**Output yang diharapkan:**
```
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
```

```bash
# Reload Nginx
sudo systemctl reload nginx
```

---

## 8. Setup Queue Worker

Queue worker diperlukan untuk memproses job async (notifikasi, isolasi, dll).

### 8.1 Buat Service Systemd

```bash
sudo nano /etc/systemd/system/billing-worker.service
```

**Isi dengan:**

```ini
[Unit]
Description=Java Indonusa Billing Queue Worker
After=network.target mysql.service redis.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/billing
ExecStart=/usr/bin/php /var/www/billing/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600

StandardOutput=append:/var/log/billing-worker.log
StandardError=append:/var/log/billing-worker-error.log

[Install]
WantedBy=multi-user.target
```

**Simpan file:** `Ctrl+O`, `Enter`, `Ctrl+X`

### 8.2 Aktifkan Queue Worker

```bash
# Reload systemd
sudo systemctl daemon-reload

# Start queue worker
sudo systemctl start billing-worker

# Enable auto-start saat boot
sudo systemctl enable billing-worker

# Cek status
sudo systemctl status billing-worker
```

**Output yang diharapkan:** Status "active (running)"

---

## 9. Setup Scheduler (Cron)

Scheduler menjalankan task otomatis seperti generate invoice, cek overdue, kirim reminder.

### 9.1 Tambahkan Cron Job

```bash
# Edit crontab untuk www-data
sudo crontab -u www-data -e
```

**Pilih editor (pilih 1 untuk nano), lalu tambahkan baris:**

```cron
# Laravel Scheduler - Jalankan setiap menit
* * * * * cd /var/www/billing && php artisan schedule:run >> /dev/null 2>&1
```

**Simpan file:** `Ctrl+O`, `Enter`, `Ctrl+X`

### 9.2 Verifikasi Cron

```bash
# Lihat cron yang aktif
sudo crontab -u www-data -l

# Test scheduler
cd /var/www/billing
php artisan schedule:list
```

**Jadwal Task Otomatis:**

| Waktu | Task | Deskripsi |
|-------|------|-----------|
| Tgl 1, 00:01 | `billing:generate-invoices` | Generate invoice bulanan |
| Setiap hari 06:00 | `billing:check-overdue` | Cek & proses isolir |
| Setiap hari 09:00 | `billing:send-reminders` | Kirim reminder tagihan |
| Setiap 15 menit | `genieacs:sync` | Sync device GenieACS |
| Setiap hari 02:00 | `mikrotik:sync-profiles` | Sync profile Mikrotik |

---

## 10. Konfigurasi Firewall & SSL

### 10.1 Setup UFW Firewall

```bash
# Install UFW
sudo apt install -y ufw

# PENTING: Allow SSH dulu agar tidak terkunci!
sudo ufw allow ssh
sudo ufw allow 22/tcp

# Allow HTTP dan HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable

# Verifikasi
sudo ufw status verbose
```

### 10.2 Install SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Generate certificate (pastikan domain sudah pointing ke server)
sudo certbot --nginx -d billing.javaindonusa.com
```

**Ikuti wizard:**
1. Masukkan email untuk notifikasi
2. Setuju Terms of Service
3. Pilih redirect HTTP ke HTTPS (recommended)

```bash
# Verifikasi auto-renewal
sudo certbot renew --dry-run
```

---

## 11. Verifikasi & Optimasi

### 11.1 Optimasi Laravel untuk Production

```bash
cd /var/www/billing

# Cache konfigurasi
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

### 11.2 Verifikasi Semua Service

```bash
# Cek semua service
echo "=== PHP-FPM ===" && sudo systemctl status php8.2-fpm --no-pager | head -5
echo ""
echo "=== MySQL ===" && sudo systemctl status mysql --no-pager | head -5
echo ""
echo "=== Redis ===" && sudo systemctl status redis-server --no-pager | head -5
echo ""
echo "=== Nginx ===" && sudo systemctl status nginx --no-pager | head -5
echo ""
echo "=== Queue Worker ===" && sudo systemctl status billing-worker --no-pager | head -5
```

### 11.3 Test Koneksi

```bash
cd /var/www/billing

# Test Database
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database OK!';"

# Test Redis
php artisan tinker --execute="Illuminate\Support\Facades\Redis::ping(); echo 'Redis OK!';"

# Test Mikrotik (jika dikonfigurasi)
php artisan mikrotik:status
```

---

## 12. Login Pertama Kali

### 12.1 Akses Aplikasi

Buka browser dan akses:

| Portal | URL | Keterangan |
|--------|-----|------------|
| Admin Panel | `https://billing.domain.com/admin` | Dashboard admin |
| Customer Portal | `https://billing.domain.com/portal` | Portal pelanggan |
| Collector Portal | `https://billing.domain.com/collector` | Portal penagih |

### 12.2 Akun Default (dari Seeder)

| Role | Email | Password |
|------|-------|----------|
| Super Admin | `admin@javaindonusa.net` | `password` |
| Finance | `finance@javaindonusa.net` | `password` |
| Collector | `budi@javaindonusa.net` | `password` |

> **PENTING:** Segera ganti password setelah login pertama kali!

### 12.3 Konfigurasi Awal (Setelah Login)

1. **Settings > ISP Info** - Isi informasi ISP (nama, alamat, rekening bank)
2. **Master Data > Paket** - Tambah paket internet
3. **Master Data > Area** - Tambah area/wilayah
4. **Master Data > Router** - Tambah router Mikrotik (jika ada)
5. **Settings > Notifikasi** - Konfigurasi WhatsApp/SMS (jika ada)

---

## 13. Troubleshooting

### Error: Permission Denied

```bash
# Reset permission
sudo chown -R www-data:www-data /var/www/billing/storage
sudo chown -R www-data:www-data /var/www/billing/bootstrap/cache
sudo chmod -R 775 /var/www/billing/storage
sudo chmod -R 775 /var/www/billing/bootstrap/cache

# Clear cache
cd /var/www/billing
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Error: 500 Internal Server Error

```bash
# Cek error log Laravel
tail -50 /var/www/billing/storage/logs/laravel.log

# Cek error log Nginx
tail -50 /var/log/nginx/billing_error.log

# Cek error log PHP-FPM
tail -50 /var/log/php8.2-fpm.log
```

### Error: Database Connection Refused

```bash
# Cek status MySQL
sudo systemctl status mysql

# Test koneksi manual
mysql -u billing_user -p billing_javaindonusa

# Cek user dan privilege
sudo mysql -u root -p -e "SELECT user, host FROM mysql.user WHERE user='billing_user';"
```

### Error: Redis Connection Refused

```bash
# Cek status Redis
sudo systemctl status redis-server

# Test Redis
redis-cli ping

# Restart Redis
sudo systemctl restart redis-server
```

### Error: Queue Worker Tidak Berjalan

```bash
# Cek status
sudo systemctl status billing-worker

# Lihat log
tail -50 /var/log/billing-worker.log

# Restart worker
sudo systemctl restart billing-worker

# Test manual
cd /var/www/billing
php artisan queue:work --once
```

### Error: Scheduler Tidak Jalan

```bash
# Cek crontab
sudo crontab -u www-data -l

# Test manual
cd /var/www/billing && php artisan schedule:run

# Lihat jadwal
php artisan schedule:list
```

### Clear Semua Cache (Jika Ada Masalah)

```bash
cd /var/www/billing

# Clear semua cache
php artisan optimize:clear

# Rebuild cache
php artisan optimize
```

---

## Perintah Maintenance Harian

```bash
# Monitoring log real-time
tail -f /var/www/billing/storage/logs/laravel.log

# Restart semua service
sudo systemctl restart php8.2-fpm nginx billing-worker

# Cek disk usage
df -h

# Cek memory usage
free -m

# Backup database manual
mysqldump -u billing_user -p billing_javaindonusa > /backup/billing_$(date +%Y%m%d).sql

# Setelah update code dari git
cd /var/www/billing
git pull
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
sudo systemctl restart billing-worker
```

---

## Checklist Go-Live

- [ ] Server requirements terpenuhi
- [ ] Database sudah di-migrate dan di-seed
- [ ] File `.env` sudah dikonfigurasi dengan benar
- [ ] Permission folder sudah benar (storage & bootstrap/cache)
- [ ] Frontend sudah di-build (`npm run build`)
- [ ] Nginx sudah dikonfigurasi dan berjalan
- [ ] Queue worker berjalan (systemd)
- [ ] Scheduler (cron) sudah dikonfigurasi
- [ ] SSL certificate terpasang
- [ ] Firewall sudah dikonfigurasi
- [ ] Koneksi Mikrotik berhasil (jika digunakan)
- [ ] Koneksi GenieACS berhasil (jika digunakan)
- [ ] Notifikasi WhatsApp/SMS berhasil (jika digunakan)
- [ ] Password admin sudah diganti
- [ ] Backup database sudah dijadwalkan

---

## Struktur Direktori

```
/var/www/billing/
├── app/                    # Kode aplikasi PHP
├── bootstrap/              # File bootstrap Laravel
│   └── cache/             # Cache aplikasi (writable)
├── config/                 # File konfigurasi
├── database/               # Migration dan seeder
├── public/                 # Document root web
│   ├── build/             # Assets hasil build
│   └── index.php          # Entry point
├── resources/              # Views, JS, CSS source
├── routes/                 # Definisi routes
├── storage/                # File storage (writable)
│   ├── app/               # File aplikasi
│   ├── framework/         # Cache framework
│   └── logs/              # Log aplikasi
├── vendor/                 # Dependencies PHP
├── .env                    # Konfigurasi environment
├── artisan                 # CLI Laravel
├── composer.json           # Dependencies PHP
└── package.json            # Dependencies JS
```

---

## Support

Jika mengalami kendala dalam instalasi:

1. Cek dokumentasi di folder `docs/`
2. Cek file log di `storage/logs/`
3. Hubungi tim support

---

## Quick Reference Commands (Linux)

### Perintah Sehari-hari

```bash
# === NAVIGASI ===
cd /var/www/billing                    # Masuk ke folder project

# === UPDATE CODE ===
git pull origin main                   # Tarik update terbaru
composer install --no-dev              # Install dependencies PHP
npm install && npm run build           # Install & build frontend
php artisan migrate --force            # Jalankan migrasi database

# === CLEAR CACHE ===
php artisan cache:clear                # Hapus cache aplikasi
php artisan config:clear               # Hapus cache config
php artisan route:clear                # Hapus cache route
php artisan view:clear                 # Hapus cache view
php artisan optimize:clear             # Hapus SEMUA cache

# === REBUILD CACHE ===
php artisan config:cache               # Cache config
php artisan route:cache                # Cache route
php artisan view:cache                 # Cache view
php artisan optimize                   # Optimize semua

# === SERVICE MANAGEMENT ===
sudo systemctl restart php8.2-fpm      # Restart PHP
sudo systemctl restart nginx           # Restart Nginx
sudo systemctl restart billing-worker  # Restart Queue Worker
sudo systemctl restart mysql           # Restart MySQL
sudo systemctl restart redis-server    # Restart Redis

# Restart semua sekaligus
sudo systemctl restart php8.2-fpm nginx billing-worker

# === CEK STATUS SERVICE ===
sudo systemctl status php8.2-fpm
sudo systemctl status nginx
sudo systemctl status billing-worker
sudo systemctl status mysql
sudo systemctl status redis-server

# === LOG ===
tail -f /var/www/billing/storage/logs/laravel.log    # Log Laravel (realtime)
tail -100 /var/www/billing/storage/logs/laravel.log  # 100 baris terakhir
tail -f /var/log/nginx/billing_error.log             # Log Nginx error
tail -f /var/log/billing-worker.log                  # Log Queue Worker

# === DATABASE ===
php artisan migrate --force            # Jalankan migrasi
php artisan migrate:status             # Cek status migrasi
php artisan db:seed --force            # Jalankan seeder
php artisan migrate:fresh --seed --force  # Reset database (HAPUS SEMUA DATA!)

# Backup database
mysqldump -u billing_user -p billing_javaindonusa > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore database
mysql -u billing_user -p billing_javaindonusa < backup_file.sql

# === QUEUE ===
php artisan queue:work --once          # Proses 1 job (untuk testing)
php artisan queue:restart              # Restart queue worker
php artisan queue:failed               # Lihat job yang gagal
php artisan queue:retry all            # Retry semua job gagal

# === SCHEDULER ===
php artisan schedule:list              # Lihat jadwal task
php artisan schedule:run               # Jalankan scheduler manual

# === ARTISAN COMMANDS (BILLING) ===
php artisan billing:generate-invoices  # Generate invoice bulanan
php artisan billing:check-overdue      # Cek & proses isolir
php artisan billing:send-reminders     # Kirim reminder tagihan
php artisan mikrotik:status            # Cek koneksi Mikrotik

# === PERMISSION (jika ada error) ===
sudo chown -R www-data:www-data /var/www/billing/storage
sudo chown -R www-data:www-data /var/www/billing/bootstrap/cache
sudo chmod -R 775 /var/www/billing/storage
sudo chmod -R 775 /var/www/billing/bootstrap/cache

# === MONITORING ===
df -h                                  # Cek disk usage
free -m                                # Cek memory usage
top                                    # Cek CPU & memory (realtime)
htop                                   # Cek CPU & memory (lebih bagus)
```

### Script Update Lengkap (Copy-Paste)

```bash
# Update aplikasi dari Git (jalankan setelah ada update)
cd /var/www/billing && \
git pull origin main && \
composer install --no-dev --optimize-autoloader && \
npm install && npm run build && \
php artisan migrate --force && \
php artisan optimize:clear && \
php artisan optimize && \
sudo systemctl restart billing-worker && \
echo "Update selesai!"
```

### Script Troubleshooting (Jika Ada Error)

```bash
# Reset semua cache dan permission
cd /var/www/billing && \
php artisan optimize:clear && \
sudo chown -R www-data:www-data storage bootstrap/cache && \
sudo chmod -R 775 storage bootstrap/cache && \
php artisan optimize && \
sudo systemctl restart php8.2-fpm nginx billing-worker && \
echo "Reset selesai!"
```

---

*Dokumen ini dibuat untuk ISP Billing System Java Indonusa v1.0*
*Terakhir diperbarui: Januari 2026*
