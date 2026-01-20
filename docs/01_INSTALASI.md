# Panduan Instalasi ISP Billing System

Dokumen ini menjelaskan langkah-langkah instalasi sistem billing ISP dari awal hingga siap digunakan.

## Daftar Isi

1. [Kebutuhan Sistem](#kebutuhan-sistem)
2. [Instalasi di Server Lokal](#instalasi-di-server-lokal)
3. [Instalasi di VPS/Cloud](#instalasi-di-vpscloud)
4. [Konfigurasi Environment](#konfigurasi-environment)
5. [Setup Database](#setup-database)
6. [Konfigurasi Queue Worker](#konfigurasi-queue-worker)
7. [Konfigurasi Scheduler (Cron)](#konfigurasi-scheduler-cron)
8. [Konfigurasi Web Server](#konfigurasi-web-server)
9. [Post-Installation](#post-installation)
10. [Troubleshooting](#troubleshooting)

---

## Kebutuhan Sistem

### Minimum Requirements

| Komponen | Versi Minimum | Rekomendasi |
|----------|---------------|-------------|
| PHP | 8.2 | 8.3 |
| MySQL | 8.0 | 8.0+ |
| Redis | 6.0 | 7.0+ |
| Node.js | 18 | 20 LTS |
| Composer | 2.0 | 2.6+ |
| RAM | 2 GB | 4 GB+ |
| Storage | 20 GB | 50 GB+ |

### PHP Extensions Required

```
php-cli php-fpm php-mysql php-redis php-mbstring php-xml
php-curl php-zip php-gd php-bcmath php-intl
```

### Software Tambahan

- **Nginx** atau **Apache** (web server)
- **Supervisor** (untuk queue worker)
- **Git** (untuk deployment)

---

## Instalasi di Server Lokal

### 1. Install Dependencies (Ubuntu/Debian)

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.3 dan extensions
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-mysql \
    php8.3-redis php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl

# Install MySQL
sudo apt install -y mysql-server

# Install Redis
sudo apt install -y redis-server

# Install Nginx
sudo apt install -y nginx

# Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Supervisor
sudo apt install -y supervisor
```

### 2. Clone & Setup Project

```bash
# Clone repository
cd /var/www
sudo git clone https://github.com/your-repo/billing-isp.git billing
sudo chown -R www-data:www-data billing
cd billing

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies & build
npm ci
npm run build

# Setup environment
cp .env.example .env
php artisan key:generate
```

### 3. Konfigurasi File .env

Edit file `.env` dan sesuaikan:

```bash
nano .env
```

**Konfigurasi WAJIB:**

```env
# Aplikasi
APP_NAME="Nama ISP Anda"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://billing.domain.com

# Database
DB_HOST=127.0.0.1
DB_DATABASE=billing_isp
DB_USERNAME=billing_user
DB_PASSWORD=password_yang_kuat

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Konfigurasi OPSIONAL (sesuai kebutuhan):**

```env
# Mikrotik (jika digunakan)
MIKROTIK_HOST=192.168.88.1
MIKROTIK_USER=admin
MIKROTIK_PASS=password

# GenieACS (jika digunakan)
GENIEACS_ENABLED=true
GENIEACS_NBI_URL=http://192.168.88.10:7557

# WhatsApp (jika digunakan)
WHATSAPP_ENABLED=true
WHATSAPP_DRIVER=fonnte
WHATSAPP_API_KEY=your_api_key
```

### 4. Setup Database

```bash
# Buat database dan user
sudo mysql -u root -p
```

```sql
CREATE DATABASE billing_isp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'root'@'localhost' IDENTIFIED BY 'password_yang_kuat';
GRANT ALL PRIVILEGES ON billing_isp.* TO 'billing_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# Jalankan migrasi dan seeder
php artisan migrate --seed

# Atau fresh install (hapus data lama)
php artisan migrate:fresh --seed
```

### 5. Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/billing
sudo chmod -R 755 /var/www/billing
sudo chmod -R 775 /var/www/billing/storage
sudo chmod -R 775 /var/www/billing/bootstrap/cache
```

---

## Instalasi di VPS/Cloud

Untuk instalasi di VPS/Cloud, ikuti langkah yang sama dengan instalasi lokal, dengan tambahan:

### Setup VPN (Jika Mikrotik/GenieACS di jaringan lokal)

Jika Mikrotik dan GenieACS berada di jaringan lokal ISP, Anda perlu setup VPN agar server cloud dapat terhubung.

Lihat dokumentasi lengkap: **[09_VPN_SETUP.md](09_VPN_SETUP.md)**

### Firewall

```bash
# Buka port yang diperlukan
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

### SSL Certificate (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d billing.domain.com
```

---

## Konfigurasi Queue Worker

Queue worker diperlukan untuk menjalankan job async (notifikasi, isolir, dll).

### Menggunakan Supervisor

```bash
# Buat konfigurasi supervisor
sudo nano /etc/supervisor/conf.d/billing-worker.conf
```

```ini
[program:billing-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/billing/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/billing/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Reload dan start supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start billing-worker:*

# Cek status
sudo supervisorctl status
```

### Perintah Supervisor Berguna

```bash
# Restart worker
sudo supervisorctl restart billing-worker:*

# Stop worker
sudo supervisorctl stop billing-worker:*

# Lihat log
tail -f /var/www/billing/storage/logs/worker.log
```

---

## Konfigurasi Scheduler (Cron)

Scheduler menjalankan task otomatis seperti generate invoice, cek overdue, kirim reminder.

### Setup Crontab

```bash
sudo crontab -e
```

Tambahkan baris:

```cron
* * * * * cd /var/www/billing && php artisan schedule:run >> /dev/null 2>&1
```

### Jadwal Task Otomatis

| Waktu | Task | Deskripsi |
|-------|------|-----------|
| Tgl 1, 00:01 | `billing:generate-invoices` | Generate invoice bulanan |
| Setiap hari 06:00 | `billing:check-overdue` | Update status overdue |
| Setiap hari 06:30 | `billing:process-isolation` | Proses isolir |
| Setiap hari 09:00 | `billing:send-reminders` | Kirim reminder tagihan |
| Setiap hari 10:00 | `billing:send-overdue` | Kirim notice overdue |
| Setiap 15 menit | `genieacs:sync-devices` | Sync device dari GenieACS |
| Minggu 01:00 | Maintenance | Bersihkan log lama |

### Test Scheduler

```bash
# Jalankan scheduler manual
php artisan schedule:run

# Lihat jadwal yang terdaftar
php artisan schedule:list
```

---

## Konfigurasi Web Server

### Nginx Configuration

```bash
sudo nano /etc/nginx/sites-available/billing
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name billing.domain.com;
    root /var/www/billing/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Increase upload size for firmware files
    client_max_body_size 100M;
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/billing /etc/nginx/sites-enabled/

# Test dan restart
sudo nginx -t
sudo systemctl restart nginx
```

### Apache Configuration (Alternatif)

```bash
sudo nano /etc/apache2/sites-available/billing.conf
```

```apache
<VirtualHost *:80>
    ServerName billing.domain.com
    DocumentRoot /var/www/billing/public

    <Directory /var/www/billing/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/billing-error.log
    CustomLog ${APACHE_LOG_DIR}/billing-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite billing.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

## Post-Installation

### 1. Login Pertama Kali

Setelah instalasi, akses aplikasi dan login dengan:

- **URL**: `https://billing.domain.com/login`
- **Email**: `admin@javaindonusa.net`
- **Password**: `password`

**Akun Lainnya (seeder default):**

| Role | Email | Password |
|------|-------|----------|
| Super Admin | `admin@javaindonusa.net` | `password` |
| Finance | `finance@javaindonusa.net` | `password` |
| Collector | `budi@javaindonusa.net` | `password` |

> **PENTING**: Segera ganti password setelah login!

### 2. Konfigurasi Awal

1. **Settings > ISP Info** - Isi informasi ISP (nama, alamat, rekening bank)
2. **Master Data > Paket** - Tambah paket internet
3. **Master Data > Area** - Tambah area/wilayah
4. **Master Data > Router** - Tambah router Mikrotik
5. **Settings > Notifikasi** - Konfigurasi WhatsApp/SMS

### 3. Test Koneksi

```bash
# Test koneksi Mikrotik
php artisan mikrotik:status

# Test koneksi GenieACS
php artisan genieacs:status

# Test kirim notifikasi
php artisan notification:test 081234567890
```

### 4. Optimasi Production

```bash
# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

---

## Troubleshooting

### Error: Permission Denied

```bash
sudo chown -R www-data:www-data /var/www/billing
sudo chmod -R 775 /var/www/billing/storage
sudo chmod -R 775 /var/www/billing/bootstrap/cache
```

### Error: Redis Connection Refused

```bash
# Cek status Redis
sudo systemctl status redis

# Start Redis jika mati
sudo systemctl start redis
sudo systemctl enable redis
```

### Error: Queue Worker Tidak Jalan

```bash
# Cek status supervisor
sudo supervisorctl status

# Restart worker
sudo supervisorctl restart billing-worker:*

# Lihat log
tail -f /var/www/billing/storage/logs/worker.log
```

### Error: Scheduler Tidak Jalan

```bash
# Cek crontab
crontab -l

# Test manual
cd /var/www/billing && php artisan schedule:run

# Lihat log scheduler
tail -f /var/www/billing/storage/logs/scheduler.log
```

### Error: 500 Internal Server Error

```bash
# Cek log Laravel
tail -f /var/www/billing/storage/logs/laravel.log

# Cek log Nginx
tail -f /var/log/nginx/error.log

# Clear cache
php artisan cache:clear
php artisan config:clear
```

### Error: Mikrotik Connection Timeout

1. Pastikan IP Mikrotik benar
2. Pastikan API port (8728) terbuka
3. Jika via VPN, pastikan VPN aktif
4. Test manual: `telnet 192.168.88.1 8728`

### Error: GenieACS Connection Failed

1. Pastikan URL GenieACS benar
2. Pastikan port 7557 terbuka
3. Jika via VPN, pastikan VPN aktif
4. Test manual: `curl http://192.168.88.10:7557/devices`

---

## Checklist Go-Live

- [ ] Server requirements terpenuhi
- [ ] Database sudah di-migrate dan di-seed
- [ ] File `.env` sudah dikonfigurasi dengan benar
- [ ] Permission folder sudah benar
- [ ] Queue worker berjalan (supervisor)
- [ ] Scheduler (cron) sudah dikonfigurasi
- [ ] SSL certificate terpasang
- [ ] Koneksi Mikrotik berhasil (jika digunakan)
- [ ] Koneksi GenieACS berhasil (jika digunakan)
- [ ] Notifikasi WhatsApp/SMS berhasil (jika digunakan)
- [ ] Password admin sudah diganti
- [ ] Backup database sudah dijadwalkan
- [ ] Monitoring server sudah aktif

---

## Support

Jika mengalami masalah, silakan:

1. Cek dokumentasi di folder `docs/`
2. Cek file log di `storage/logs/`
3. Hubungi tim support

---

*Dokumentasi ini terakhir diperbarui: Januari 2025*
