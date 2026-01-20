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
9. [Setup Cron Job](#9-setup-cron-job)
10. [Konfigurasi Firewall](#10-konfigurasi-firewall)
11. [SSL Certificate](#11-ssl-certificate)
12. [Verifikasi Instalasi](#12-verifikasi-instalasi)
13. [Troubleshooting](#13-troubleshooting)

---

## 1. Persyaratan Sistem

### Minimum Hardware
- CPU: 2 Core
- RAM: 4 GB
- Storage: 20 GB SSD
- Bandwidth: 100 Mbps

### Rekomendasi Hardware (Production)
- CPU: 4 Core
- RAM: 8 GB
- Storage: 50 GB SSD
- Bandwidth: 1 Gbps

### Software Requirements
- OS: Ubuntu 22.04 LTS atau 24.04 LTS
- PHP: 8.2 atau lebih tinggi
- MySQL: 8.0 atau lebih tinggi
- Redis: 6.0 atau lebih tinggi
- Node.js: 18 LTS atau lebih tinggi
- Nginx: 1.18 atau lebih tinggi
- Composer: 2.x
- Git: 2.x

---

## 2. Instalasi Dependencies

### 2.1 Update Sistem

```bash
# Login sebagai root atau gunakan sudo
sudo apt update && sudo apt upgrade -y
```

### 2.2 Instalasi Paket Dasar

```bash
sudo apt install -y curl wget gnupg2 ca-certificates lsb-release apt-transport-https software-properties-common unzip git
```

### 2.3 Instalasi PHP 8.2

```bash
# Tambahkan repository PHP
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.2 dan ekstensi yang diperlukan
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-common \
    php8.2-mysql php8.2-zip php8.2-gd php8.2-mbstring php8.2-curl \
    php8.2-xml php8.2-bcmath php8.2-intl php8.2-readline \
    php8.2-redis php8.2-soap php8.2-ldap php8.2-imagick

# Verifikasi instalasi PHP
php -v
```

### 2.4 Konfigurasi PHP

```bash
# Edit konfigurasi PHP-FPM
sudo nano /etc/php/8.2/fpm/php.ini
```

Ubah nilai berikut:

```ini
upload_max_filesize = 50M
post_max_size = 50M
memory_limit = 512M
max_execution_time = 300
date.timezone = Asia/Jakarta
```

```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
sudo systemctl enable php8.2-fpm
```

### 2.5 Instalasi MySQL 8.0

```bash
# Install MySQL Server
sudo apt install -y mysql-server

# Jalankan secure installation
sudo mysql_secure_installation
```

Ikuti wizard dan jawab pertanyaan berikut:
- VALIDATE PASSWORD COMPONENT: **Y** (Ya)
- Password Strength: **2** (STRONG)
- Masukkan password root MySQL yang kuat
- Remove anonymous users: **Y**
- Disallow root login remotely: **Y**
- Remove test database: **Y**
- Reload privilege tables: **Y**

```bash
# Verifikasi MySQL berjalan
sudo systemctl status mysql
sudo systemctl enable mysql
```

### 2.6 Instalasi Redis

```bash
# Install Redis Server
sudo apt install -y redis-server

# Konfigurasi Redis
sudo nano /etc/redis/redis.conf
```

Ubah baris berikut:

```conf
#supervised no = supervised systemd
#maxmemory <bytes> = maxmemory 256mb
#maxmemory-policy noeviction = maxmemory-policy allkeys-lru
```

```bash
# Restart dan enable Redis
sudo systemctl restart redis-server
sudo systemctl enable redis-server

# Verifikasi Redis
redis-cli ping
# Harus menampilkan: PONG
```

### 2.7 Instalasi Node.js 18 LTS

```bash
# Install Node.js menggunakan NodeSource
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Verifikasi instalasi
node -v
npm -v
```

### 2.8 Instalasi Composer

```bash
# Download dan install Composer
cd /tmp
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

#jika terjadi error
echo 'export PATH="$PATH:/usr/local/bin"' >> ~/.bashrc
source ~/.bashrc
composer --version

# Verifikasi instalasi
composer --version
```

### 2.9 Instalasi Nginx

```bash
cd
# Install Nginx
sudo apt install -y nginx

# Start dan enable Nginx
sudo systemctl start nginx
sudo systemctl enable nginx

# Verifikasi
sudo systemctl status nginx
```

---

## 3. Setup Project

### 3.1 Buat User untuk Aplikasi (Opsional tapi Direkomendasikan)

```bash
# Buat user baru untuk aplikasi
sudo adduser --disabled-password --gecos "" billing

# Tambahkan user ke grup www-data
sudo usermod -aG www-data billing
```

### 3.2 Buat Direktori Project

```bash
# Buat direktori untuk aplikasi
sudo mkdir -p /var/www/billing
sudo chown -R $USER:www-data /var/www/billing
cd /var/www/billing
```

### 3.3 Upload/Clone Project

**Opsi A: Upload dari lokal (menggunakan SCP/SFTP)**

```bash
# Dari komputer lokal, upload file project
# Ganti user@server_ip dengan kredensial server Anda
scp -r /path/to/java-indonusa/* user@server_ip:/var/www/billing/
```

**Opsi B: Clone dari Git Repository**

```bash
# Jika project ada di Git repository
cd /var/www/billing
git clone https://github.com/username/java-indonusa.git .
```

**Opsi C: Extract dari ZIP**

```bash
# Upload file zip ke server, kemudian extract
cd /var/www/billing
unzip billing-system.zip
```

### 3.4 Set Permission

```bash
# Set ownership
sudo chown -R $USER:www-data /var/www/billing

# Set permission direktori
sudo find /var/www/billing -type d -exec chmod 755 {} \;

# Set permission file
sudo find /var/www/billing -type f -exec chmod 644 {} \;

# Set permission khusus untuk storage dan cache
sudo chmod -R 775 /var/www/billing/storage
sudo chmod -R 775 /var/www/billing/bootstrap/cache

# Pastikan www-data bisa menulis ke storage
sudo chgrp -R www-data /var/www/billing/storage
sudo chgrp -R www-data /var/www/billing/bootstrap/cache
```

### 3.5 Install Dependencies PHP

```bash
cd /var/www/billing

# Install dependencies menggunakan Composer
composer install --optimize-autoloader --no-dev

# Jika ada error memory, gunakan:
COMPOSER_MEMORY_LIMIT=-1 composer install --optimize-autoloader --no-dev
```

### 3.6 Install Dependencies JavaScript

```bash
cd /var/www/billing

# Install dependencies Node.js
npm install
```

---

## 4. Konfigurasi Environment

### 4.1 Buat File Environment

```bash
cd /var/www/billing

# Copy file environment contoh
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4.2 Edit Konfigurasi Environment

```bash
nano /var/www/billing/.env
```

Ubah konfigurasi berikut sesuai kebutuhan:

```env
#-------------------------------------------------
# APLIKASI
#-------------------------------------------------
APP_NAME="Java Indonusa Billing"
APP_ENV=production
APP_KEY=base64:xxxxx  # Sudah di-generate
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_URL=https://billing.javaindonusa.com

#-------------------------------------------------
# DATABASE
#-------------------------------------------------
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=billing_javaindonusa
DB_USERNAME=billing_user
DB_PASSWORD=password_yang_kuat_dan_aman

#-------------------------------------------------
# CACHE & SESSION
#-------------------------------------------------
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

#-------------------------------------------------
# REDIS
#-------------------------------------------------
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=phpredis

#-------------------------------------------------
# MAIL (Opsional - untuk notifikasi email)
#-------------------------------------------------
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=email@javaindonusa.com
MAIL_PASSWORD=app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@javaindonusa.com
MAIL_FROM_NAME="${APP_NAME}"

#-------------------------------------------------
# BILLING
#-------------------------------------------------
BILLING_DUE_DAYS=20
BILLING_GRACE_DAYS=7
BILLING_INVOICE_DAY=1
BILLING_ISOLATION_MIN_MONTHS=2
BILLING_RECENT_PAYMENT_DAYS=30
BILLING_RAPEL_TOLERANCE_MONTHS=3

#-------------------------------------------------
# MIKROTIK
#-------------------------------------------------
MIKROTIK_HOST=192.168.88.1
MIKROTIK_USER=admin
MIKROTIK_PASS=password_mikrotik
MIKROTIK_PORT=8728
MIKROTIK_TIMEOUT=10
MIKROTIK_ISOLATED_PROFILE=ISOLIR
MIKROTIK_ISOLATED_ADDRESS_LIST=ISOLIR

#-------------------------------------------------
# GENIEACS (TR-069)
#-------------------------------------------------
GENIEACS_NBI_URL=http://localhost:7557
GENIEACS_UI_URL=http://localhost:3000
GENIEACS_FS_URL=http://localhost:7567
GENIEACS_USERNAME=admin
GENIEACS_PASSWORD=admin
GENIEACS_TIMEOUT=30

#-------------------------------------------------
# WHATSAPP GATEWAY
#-------------------------------------------------
WHATSAPP_GATEWAY_URL=https://api.whatsapp.gateway.com
WHATSAPP_API_KEY=your_api_key
WHATSAPP_SENDER=6281234567890

#-------------------------------------------------
# SMS GATEWAY (Opsional)
#-------------------------------------------------
SMS_GATEWAY_URL=https://api.sms.gateway.com
SMS_API_KEY=your_api_key
SMS_SENDER_ID=JAVAINDONUSA

#-------------------------------------------------
# LOG
#-------------------------------------------------
LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DAILY_DAYS=14
```

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

Jalankan query SQL berikut:

```sql
-- Buat database
CREATE DATABASE billing_javaindonusa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Buat user database
CREATE USER 'billing_user'@'localhost' IDENTIFIED BY 'password_yang_kuat_dan_aman';

-- Berikan privilege
GRANT ALL PRIVILEGES ON billing_javaindonusa.* TO 'billing_user'@'localhost';

-- Apply privilege
FLUSH PRIVILEGES;

-- Keluar dari MySQL
EXIT;
```

### 5.2 Jalankan Migrasi Database

```bash
cd /var/www/billing

# Jalankan migrasi
php artisan migrate --force

# Output yang diharapkan:
# Migration table created successfully.
# Migrating: 2024_01_01_000001_create_users_table
# Migrated:  2024_01_01_000001_create_users_table
# ... (semua migration)
```

### 5.3 Jalankan Seeder (Data Awal)

```bash
# Jalankan seeder untuk data default
php artisan db:seed --force

# Atau untuk production dengan data minimal:
php artisan db:seed --class=ProductionSeeder --force
```

### 5.4 Buat User Admin Pertama

```bash
# Buat user admin menggunakan tinker
php artisan tinker
```

```php
// Di dalam tinker, jalankan:
$user = new \App\Models\User();
$user->name = 'Administrator';
$user->email = 'admin@javaindonusa.com';
$user->password = bcrypt('password_admin_yang_kuat');
$user->role = 'admin';
$user->email_verified_at = now();
$user->save();

// Keluar dari tinker
exit
```

---

## 6. Build Frontend

### 6.1 Build Assets untuk Production

```bash
cd /var/www/billing

# Build assets production
npm run build
```

### 6.2 Verifikasi Build

```bash
# Pastikan folder public/build ada dan berisi file
ls -la /var/www/billing/public/build/
```

---

## 7. Konfigurasi Web Server

### 7.1 Buat Konfigurasi Nginx

```bash
sudo nano /etc/nginx/sites-available/billing
```

Isi dengan konfigurasi berikut:

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
    gzip_disable "MSIE [1-6]\.";

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

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

        # Timeout settings
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

    # Favicon & robots
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

### 7.2 Aktifkan Konfigurasi

```bash
# Buat symbolic link
sudo ln -s /etc/nginx/sites-available/billing /etc/nginx/sites-enabled/

# Hapus default site (opsional)
sudo rm /etc/nginx/sites-enabled/default

# Test konfigurasi Nginx
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

---

## 8. Setup Queue Worker

Queue worker diperlukan untuk memproses job seperti pengiriman notifikasi, isolasi, dll.

### 8.1 Buat Service Systemd untuk Queue Worker

```bash
sudo nano /etc/systemd/system/billing-worker.service
```

Isi dengan:

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

# Logging
StandardOutput=append:/var/log/billing-worker.log
StandardError=append:/var/log/billing-worker-error.log

[Install]
WantedBy=multi-user.target
```

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

### 8.3 Setup Multiple Queue Workers (Opsional untuk High Load)

```bash
# Buat service untuk worker kedua
sudo nano /etc/systemd/system/billing-worker-2.service
```

```ini
[Unit]
Description=Java Indonusa Billing Queue Worker 2
After=network.target mysql.service redis.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/billing
ExecStart=/usr/bin/php /var/www/billing/artisan queue:work redis --queue=notifications --sleep=3 --tries=3 --max-time=3600

StandardOutput=append:/var/log/billing-worker-2.log
StandardError=append:/var/log/billing-worker-2-error.log

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl start billing-worker-2
sudo systemctl enable billing-worker-2
```

---

## 9. Setup Cron Job

Cron job diperlukan untuk menjalankan tugas terjadwal seperti generate invoice, cek overdue, dll.

### 9.1 Tambahkan Cron Job Laravel

```bash
# Edit crontab untuk www-data
sudo crontab -u www-data -e
```

Tambahkan baris berikut:

```cron
# Laravel Scheduler - Jalankan setiap menit
* * * * * cd /var/www/billing && php artisan schedule:run >> /dev/null 2>&1
```

### 9.2 Verifikasi Cron Job

```bash
# Lihat cron yang aktif
sudo crontab -u www-data -l

# Test scheduler
cd /var/www/billing
php artisan schedule:list
```

Output yang diharapkan:

```
  0 1 1 * *  php artisan billing:generate-invoices ........ Next Due: 1 bulan lagi
  0 6 * * *  php artisan billing:check-overdue ............ Next Due: besok 06:00
  0 9 * * *  php artisan billing:send-reminders ........... Next Due: besok 09:00
  */15 * * * *  php artisan genieacs:sync ................. Next Due: 15 menit lagi
  0 2 * * *  php artisan mikrotik:sync-profiles ........... Next Due: besok 02:00
```

---

## 10. Konfigurasi Firewall

### 10.1 Setup UFW Firewall

```bash
# Install UFW jika belum ada
sudo apt install -y ufw

# Reset rules (hati-hati jika remote)
sudo ufw reset

# Allow SSH (PENTING: lakukan ini dulu agar tidak terkunci!)
sudo ufw allow ssh
# atau
sudo ufw allow 22/tcp

# Allow HTTP dan HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow MySQL hanya dari localhost (default, tidak perlu diubah)
# Jika perlu akses remote MySQL (tidak direkomendasikan):
# sudo ufw allow from IP_TERTENTU to any port 3306

# Enable firewall
sudo ufw enable

# Verifikasi
sudo ufw status verbose
```

---

## 11. SSL Certificate

### 11.1 Install Certbot

```bash
# Install Certbot untuk Nginx
sudo apt install -y certbot python3-certbot-nginx
```

### 11.2 Generate SSL Certificate

```bash
# Generate certificate (pastikan domain sudah pointing ke server)
sudo certbot --nginx -d billing.javaindonusa.com
```

Ikuti wizard:
1. Masukkan email untuk notifikasi
2. Setuju Terms of Service
3. Pilih redirect HTTP ke HTTPS (recommended)

### 11.3 Verifikasi Auto-Renewal

```bash
# Test renewal
sudo certbot renew --dry-run

# Cek timer certbot
sudo systemctl status certbot.timer
```

### 11.4 Konfigurasi Nginx dengan SSL (Otomatis diubah Certbot)

Konfigurasi nginx akan otomatis diubah menjadi:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name billing.javaindonusa.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;

    server_name billing.javaindonusa.com;
    root /var/www/billing/public;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/billing.javaindonusa.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/billing.javaindonusa.com/privkey.pem;
    ssl_trusted_certificate /etc/letsencrypt/live/billing.javaindonusa.com/chain.pem;

    # SSL Security
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_stapling on;
    ssl_stapling_verify on;

    # HSTS
    add_header Strict-Transport-Security "max-age=63072000" always;

    # ... (konfigurasi lainnya sama seperti sebelumnya)
}
```

---

## 12. Verifikasi Instalasi

### 12.1 Optimasi Laravel untuk Production

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

# Buat symbolic link storage
php artisan storage:link
```

### 12.2 Verifikasi Semua Service

```bash
# Cek status semua service
echo "=== PHP-FPM ===" && sudo systemctl status php8.2-fpm --no-pager
echo ""
echo "=== MySQL ===" && sudo systemctl status mysql --no-pager
echo ""
echo "=== Redis ===" && sudo systemctl status redis-server --no-pager
echo ""
echo "=== Nginx ===" && sudo systemctl status nginx --no-pager
echo ""
echo "=== Queue Worker ===" && sudo systemctl status billing-worker --no-pager
```

### 12.3 Test Koneksi Database

```bash
cd /var/www/billing
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connected!';"
```

### 12.4 Test Redis

```bash
cd /var/www/billing
php artisan tinker --execute="Redis::ping(); echo 'Redis connected!';"
```

### 12.5 Test Mikrotik Connection

```bash
cd /var/www/billing
php artisan mikrotik:status
```

### 12.6 Akses Website

Buka browser dan akses:
- **URL**: `https://billing.javaindonusa.com`
- **Login Admin**: `admin@javaindonusa.com` / `password_admin`
- **Portal Pelanggan**: `https://billing.javaindonusa.com/portal`
- **Portal Collector**: `https://billing.javaindonusa.com/collector`

---

## 13. Troubleshooting

### 13.1 Error Permission Denied

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

### 13.2 Error 500 Internal Server Error

```bash
# Cek error log Laravel
tail -f /var/www/billing/storage/logs/laravel.log

# Cek error log Nginx
tail -f /var/log/nginx/billing_error.log

# Cek error log PHP-FPM
tail -f /var/log/php8.2-fpm.log
```

### 13.3 Queue Worker Tidak Berjalan

```bash
# Restart queue worker
sudo systemctl restart billing-worker

# Cek log
tail -f /var/log/billing-worker.log

# Test manual
cd /var/www/billing
php artisan queue:work --once
```

### 13.4 Database Connection Error

```bash
# Test koneksi manual
mysql -u billing_user -p billing_javaindonusa

# Cek user dan privilege
mysql -u root -p -e "SELECT user, host FROM mysql.user WHERE user='billing_user';"
mysql -u root -p -e "SHOW GRANTS FOR 'billing_user'@'localhost';"
```

### 13.5 Redis Connection Error

```bash
# Test Redis
redis-cli ping

# Cek status Redis
sudo systemctl status redis-server

# Restart Redis
sudo systemctl restart redis-server
```

### 13.6 SSL Certificate Error

```bash
# Cek certificate
sudo certbot certificates

# Renew manual
sudo certbot renew

# Force renew
sudo certbot renew --force-renewal
```

### 13.7 Clear Semua Cache

```bash
cd /var/www/billing

# Clear semua cache Laravel
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

# Cek process
htop

# Backup database manual
mysqldump -u billing_user -p billing_javaindonusa > /backup/billing_$(date +%Y%m%d).sql

# Jalankan migration baru (setelah update)
cd /var/www/billing
php artisan migrate --force

# Clear dan rebuild cache (setelah update)
php artisan optimize:clear && php artisan optimize
```

---

## Struktur Direktori Final

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
├── tests/                  # Unit tests
├── vendor/                 # Dependencies PHP
├── .env                    # Konfigurasi environment
├── artisan                 # CLI Laravel
├── composer.json           # Dependencies PHP
└── package.json            # Dependencies JS
```

---

## Kontak Support

Jika mengalami kendala dalam instalasi, hubungi:

- **Email**: support@javaindonusa.com
- **WhatsApp**: +62 812-3456-7890
- **Dokumentasi**: https://docs.javaindonusa.com

---

*Dokumen ini dibuat untuk ISP Billing System Java Indonusa v1.0*
*Terakhir diperbarui: Januari 2026*
