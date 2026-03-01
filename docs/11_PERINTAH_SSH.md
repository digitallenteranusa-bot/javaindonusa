# Panduan Perintah SSH - ISP Billing System
## Java Indonusa

Referensi lengkap semua perintah yang dapat dijalankan via SSH/terminal di server. Semua perintah dijalankan dari direktori root aplikasi.

---

## Daftar Isi

1. [Akses Server](#1-akses-server)
2. [Perintah Billing](#2-perintah-billing)
3. [Perintah Mikrotik](#3-perintah-mikrotik)
4. [Perintah GenieACS](#4-perintah-genieacs)
5. [Perintah RADIUS](#5-perintah-radius)
6. [Perintah Notifikasi](#6-perintah-notifikasi)
7. [Perintah Data & Reset](#7-perintah-data--reset)
8. [Perintah Maintenance](#8-perintah-maintenance)
9. [Service Management](#9-service-management)
10. [Jadwal Otomatis (Scheduler)](#10-jadwal-otomatis-scheduler)
11. [Deploy & Update Server](#11-deploy--update-server)
12. [Monitoring & Debugging](#12-monitoring--debugging)
13. [Backup & Restore](#13-backup--restore)
14. [Cheat Sheet](#14-cheat-sheet)

---

## 1. Akses Server

### 1.1 Login SSH

```bash
# Login ke server
ssh user@IP_SERVER

# Masuk ke direktori aplikasi
cd /var/www/billing
```

### 1.2 Cek Versi & Environment

```bash
# Cek versi PHP
php -v

# Cek versi Laravel
php artisan --version

# Cek environment aktif
php artisan env

# Lihat semua perintah yang tersedia
php artisan list

# Bantuan untuk perintah tertentu
php artisan help billing:generate-invoices
```

---

## 2. Perintah Billing

### 2.1 Generate Invoice Bulanan

Membuat invoice untuk semua pelanggan aktif. Otomatis berjalan setiap tanggal 1 pukul 00:01.

```bash
# Generate invoice bulan ini
php artisan billing:generate-invoices

# Generate invoice bulan tertentu
php artisan billing:generate-invoices --month=3 --year=2026

# Generate invoice bulan lalu
php artisan billing:generate-invoices --month=2 --year=2026
```

**Output:**

```
Generating invoices for March 2026...

+----------------+-------+
| Hasil          | Jumlah|
+----------------+-------+
| Generated      | 150   |
| Skipped        | 5     |
| Errors         | 0     |
+----------------+-------+
```

### 2.2 Cek Overdue

Mengecek dan update status overdue invoice. Otomatis berjalan setiap hari pukul 06:00.

```bash
# Cek overdue saja
php artisan billing:check-overdue

# Cek overdue + proses isolasi sekaligus
php artisan billing:check-overdue --isolate
```

### 2.3 Proses Isolasi

Proses isolasi otomatis untuk pelanggan yang tunggakan melebihi batas. Otomatis berjalan setiap hari pukul 06:30.

```bash
# Preview — lihat siapa yang akan diisolir tanpa eksekusi
php artisan billing:process-isolation --dry-run

# Proses isolasi (dispatch ke queue)
php artisan billing:process-isolation

# Proses isolasi langsung (tanpa queue)
php artisan billing:process-isolation --sync
```

**Output `--dry-run`:**

```
Customers eligible for isolation:

+----+----------+-------------------+----------+------------+
| ID | Customer | Name              | Overdue  | Total Debt |
+----+----------+-------------------+----------+------------+
| 12 | JI-00012 | Budi Santoso      | 3 bulan  | Rp 750.000 |
| 45 | JI-00045 | Siti Aminah       | 2 bulan  | Rp 500.000 |
+----+----------+-------------------+----------+------------+

Total: 2 customers would be isolated
```

### 2.4 Kirim Reminder Pembayaran

Mengirim pengingat pembayaran sebelum jatuh tempo. Otomatis berjalan setiap hari pukul 09:00.

```bash
# Kirim reminder default (3 hari dan 1 hari sebelum jatuh tempo)
php artisan billing:send-reminders

# Kirim reminder custom (5 hari sebelum)
php artisan billing:send-reminders --days=5

# Kirim langsung tanpa queue
php artisan billing:send-reminders --sync
```

### 2.5 Kirim Notifikasi Overdue

Mengirim notifikasi ke pelanggan yang sudah melewati jatuh tempo. Otomatis berjalan setiap hari pukul 10:00.

```bash
# Kirim notifikasi default (1, 3, 7 hari setelah jatuh tempo)
php artisan billing:send-overdue

# Kirim notifikasi custom
php artisan billing:send-overdue --days=1 --days=3 --days=7 --days=14

# Kirim langsung tanpa queue
php artisan billing:send-overdue --sync
```

---

## 3. Perintah Mikrotik

### 3.1 Cek Status Router

```bash
# Cek status semua router
php artisan mikrotik:status

# Cek status router tertentu (by ID)
php artisan mikrotik:status 1

# Cek status router tertentu (by name)
php artisan mikrotik:status MK-UTAMA
```

**Output (semua router):**

```
+----+----------+----------------+--------+------+--------+-----+
| ID | Identity | IP Address     | Status | CPU  | Memory | Up  |
+----+----------+----------------+--------+------+--------+-----+
|  1 | MK-UTAMA | 192.168.88.1   | Online | 12%  | 45%    | 30d |
|  2 | MK-CADANG| 192.168.88.2   | Online | 5%   | 30%    | 15d |
+----+----------+----------------+--------+------+--------+-----+
```

**Output (detail router):**

```
Router: MK-UTAMA
=================
Identity     : MK-UTAMA
IP Address   : 192.168.88.1
Version      : 7.12
Model        : RB4011iGS+
Serial       : XXXXXXXXXXXX
Uptime       : 30 days 12:34:56
CPU Load     : 12%
Memory Usage : 45%
Active PPPoE : 87
```

### 3.2 Isolasi / Reopen Manual

```bash
# Isolasi pelanggan by ID
php artisan mikrotik:isolate 12

# Isolasi pelanggan by customer_id
php artisan mikrotik:isolate JI-00012

# Isolasi tanpa kirim notifikasi
php artisan mikrotik:isolate 12 --no-notification

# Isolasi langsung (tanpa queue)
php artisan mikrotik:isolate 12 --sync

# Reopen / buka akses pelanggan
php artisan mikrotik:isolate 12 --reopen

# Reopen langsung tanpa queue dan tanpa notifikasi
php artisan mikrotik:isolate 12 --reopen --sync --no-notification
```

---

## 4. Perintah GenieACS

### 4.1 Status GenieACS

```bash
php artisan genieacs:status
```

**Output:**

```
GenieACS Connection Status
===========================
NBI URL   : http://192.168.88.10:7557
Status    : Connected

+----------------------+-------+
| Metric               | Count |
+----------------------+-------+
| Devices (GenieACS)   | 200   |
| Devices (Local DB)   | 195   |
| Online               | 180   |
| Offline              | 15    |
| Unmatched            | 5     |
+----------------------+-------+
```

### 4.2 Sync Devices

```bash
# Sync devices dari GenieACS ke database lokal
php artisan genieacs:sync-devices

# Force sync (abaikan cache)
php artisan genieacs:sync-devices --force
```

---

## 5. Perintah RADIUS

> **Catatan:** Perintah RADIUS hanya berfungsi jika `RADIUS_ENABLED=true` di `.env`.
> Lihat [Panduan Integrasi FreeRADIUS](10_INTEGRASI_FREERADIUS.md) untuk setup lengkap.

### 5.1 Status RADIUS

```bash
php artisan radius:status
```

**Output:**

```
FreeRADIUS Database Status
==========================

Connection: OK
Database: radius

+------------------------+-------+
| Metric                 | Count |
+------------------------+-------+
| Users (radcheck)       | 150   |
| NAS entries            | 3     |
| Active sessions        | 87    |
| Total sessions (radacct)| 15420 |
+------------------------+-------+

Config:
  Isolation method: rate_limit
  Isolation rate limit: 1k/1k
  Default group: default
```

### 5.2 Sync RADIUS

```bash
# Sync semua (customer + NAS)
php artisan radius:sync --all

# Sync customer saja
php artisan radius:sync --customers

# Sync NAS (router) saja
php artisan radius:sync --nas

# Preview tanpa eksekusi
php artisan radius:sync --all --dry-run
```

---

## 6. Perintah Notifikasi

### 6.1 Test Notifikasi

```bash
# Test WhatsApp
php artisan notification:test whatsapp 6281234567890

# Test SMS
php artisan notification:test sms 6281234567890

# Test Email
php artisan notification:test email admin@domain.com

# Test dengan pesan custom
php artisan notification:test whatsapp 6281234567890 --message="Pesan test dari billing"
```

---

## 7. Perintah Data & Reset

> **PERINGATAN:** Perintah di bawah ini akan **menghapus data secara permanen**. Pastikan sudah backup terlebih dahulu!

### 7.1 Reset Data Pelanggan

```bash
# Reset data pelanggan + transaksi (dengan konfirmasi)
php artisan data:reset-customers

# Force tanpa konfirmasi
php artisan data:reset-customers --force

# Reset pelanggan tapi pertahankan data master (paket, area, router)
php artisan data:reset-customers --keep-master
```

### 7.2 Reset Data Selektif

```bash
# Reset hanya data transaksi (invoice, payment)
php artisan data:reset --transactions

# Reset hanya data pelanggan
php artisan data:reset --customers

# Reset data master (area, paket, router)
php artisan data:reset --master

# Reset data GenieACS / CPE
php artisan data:reset --genieacs

# Reset SEMUA data (kecuali user admin)
php artisan data:reset --all

# Force tanpa konfirmasi
php artisan data:reset --all --force
```

### 7.3 Recalculate ODP Ports

```bash
# Hitung ulang used_ports semua ODP
php artisan odp:recalculate-ports
```

---

## 8. Perintah Maintenance

### 8.1 Bersihkan Log

```bash
# Hapus log lebih dari 30 hari
php artisan log:clear

# Hapus log lebih dari 7 hari
php artisan log:clear --days=7

# Truncate semua file log (kosongkan tanpa hapus)
php artisan log:clear --all
```

### 8.2 Cache & Optimasi

```bash
# Bersihkan semua cache
php artisan optimize:clear

# Optimasi untuk production
php artisan optimize

# Bersihkan cache per komponen
php artisan cache:clear       # Application cache
php artisan config:clear      # Config cache
php artisan route:clear       # Route cache
php artisan view:clear        # View cache

# Cache untuk production (lebih cepat)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 8.3 Database

```bash
# Jalankan migration
php artisan migrate

# Jalankan migration + seeder
php artisan migrate --seed

# Reset database (HATI-HATI!)
php artisan migrate:fresh --seed

# Cek status migration
php artisan migrate:status

# Rollback migration terakhir
php artisan migrate:rollback
```

---

## 9. Service Management

### 9.1 Queue Worker

Queue worker memproses job async (isolasi, notifikasi, dll). **Wajib berjalan di production.**

```bash
# Start queue worker
php artisan queue:work redis

# Start dengan opsi lengkap
php artisan queue:work redis --queue=default,isolation,notifications --sleep=3 --tries=3

# Restart queue setelah deploy
php artisan queue:restart

# Cek job yang gagal
php artisan queue:failed

# Retry job yang gagal
php artisan queue:retry all

# Hapus semua job yang gagal
php artisan queue:flush
```

**Menggunakan Supervisor (Production):**

File `/etc/supervisor/conf.d/billing-worker.conf`:

```ini
[program:billing-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/billing/artisan queue:work redis --queue=default,isolation,notifications --sleep=3 --tries=3 --max-time=3600
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
# Reload supervisor config
sudo supervisorctl reread
sudo supervisorctl update

# Cek status worker
sudo supervisorctl status billing-worker:*

# Restart worker
sudo supervisorctl restart billing-worker:*
```

### 9.2 Scheduler (Cron)

Scheduler menjalankan perintah terjadwal. **Wajib ada di crontab.**

```bash
# Edit crontab
crontab -e

# Tambahkan baris ini:
* * * * * cd /var/www/billing && php artisan schedule:run >> /dev/null 2>&1
```

```bash
# Cek jadwal yang terdaftar
php artisan schedule:list

# Jalankan scheduler sekali (untuk testing)
php artisan schedule:run

# Test perintah terjadwal tertentu
php artisan schedule:test
```

### 9.3 Web Server

```bash
# Nginx
sudo systemctl status nginx
sudo systemctl restart nginx
sudo nginx -t                    # Test konfigurasi

# PHP-FPM
sudo systemctl status php8.2-fpm
sudo systemctl restart php8.2-fpm

# MySQL
sudo systemctl status mysql
sudo systemctl restart mysql

# Redis
sudo systemctl status redis
sudo systemctl restart redis

# FreeRADIUS (jika diinstall)
sudo systemctl status freeradius
sudo systemctl restart freeradius
```

---

## 10. Jadwal Otomatis (Scheduler)

Semua jadwal berjalan otomatis via cron. Timezone: **Asia/Jakarta (WIB)**.

### 10.1 Jadwal Harian

| Jam WIB | Perintah | Fungsi |
|---------|----------|--------|
| 00:01 (tgl 1) | `billing:generate-invoices` | Generate invoice bulanan |
| 06:00 | `billing:check-overdue` | Update status overdue |
| 06:30 | `billing:process-isolation` | Proses isolasi otomatis |
| 09:00 | `billing:send-reminders` | Kirim reminder pembayaran |
| 10:00 | `billing:send-overdue` | Kirim notifikasi overdue |

### 10.2 Jadwal Periodik

| Interval | Perintah | Fungsi |
|----------|----------|--------|
| Tiap 5 menit | `mikrotik:status` | Sync status router |
| Tiap 15 menit | `genieacs:sync-devices` | Sync device (jika aktif) |

### 10.3 Jadwal Mingguan

| Hari & Jam | Perintah | Fungsi |
|------------|----------|--------|
| Minggu 01:00 | `log:clear` | Bersihkan log > 30 hari |
| Minggu 02:00 | Backup cleanup | Hapus backup > 30 hari |

### 10.4 Cek Log Scheduler

```bash
# Lihat log scheduler
tail -f storage/logs/scheduler.log

# Lihat 50 baris terakhir
tail -50 storage/logs/scheduler.log

# Filter log hari ini
grep "$(date +%Y-%m-%d)" storage/logs/scheduler.log
```

---

## 11. Deploy & Update Server

### 11.1 Update Standar

```bash
cd /var/www/billing && \
git stash && \
git pull origin main && \
composer update --no-dev --optimize-autoloader --ignore-platform-reqs && \
php artisan migrate --force && \
mkdir -p storage/framework/{views,cache,sessions,testing} && \
php artisan optimize:clear && \
php artisan optimize && \
php artisan queue:restart
```

### 11.2 Update dengan Build Frontend

```bash
cd /var/www/billing && \
git stash && \
git pull origin main && \
composer update --no-dev --optimize-autoloader --ignore-platform-reqs && \
npm install && \
npm run build && \
php artisan migrate --force && \
mkdir -p storage/framework/{views,cache,sessions,testing} && \
php artisan optimize:clear && \
php artisan optimize && \
php artisan queue:restart
```

### 11.3 Fix Permission Setelah Deploy

```bash
sudo chown -R www-data:www-data /var/www/billing
sudo chmod -R 755 /var/www/billing
sudo chmod -R 775 /var/www/billing/storage
sudo chmod -R 775 /var/www/billing/bootstrap/cache
```

---

## 12. Monitoring & Debugging

### 12.1 Lihat Log Aplikasi

```bash
# Log Laravel (real-time)
tail -f storage/logs/laravel.log

# Log hari ini
cat storage/logs/laravel-$(date +%Y-%m-%d).log

# Filter error saja
grep "ERROR" storage/logs/laravel.log | tail -20

# Filter RADIUS log
grep "RADIUS" storage/logs/laravel.log | tail -20

# Filter Mikrotik log
grep -i "mikrotik\|isolat\|reopen" storage/logs/laravel.log | tail -20
```

### 12.2 Cek Queue & Job

```bash
# Cek job yang gagal
php artisan queue:failed

# Retry semua job gagal
php artisan queue:retry all

# Retry job tertentu (by ID)
php artisan queue:retry 5

# Hapus semua job gagal
php artisan queue:flush

# Monitor queue real-time (Redis)
redis-cli monitor | grep queue
```

### 12.3 Cek Kesehatan Sistem

```bash
# Cek semua service
sudo systemctl status nginx php8.2-fpm mysql redis

# Cek disk space
df -h

# Cek memory
free -m

# Cek CPU load
top -bn1 | head -5

# Cek koneksi database
php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';"

# Cek koneksi Redis
redis-cli ping
```

### 12.4 Laravel Tinker (Interactive Shell)

```bash
# Masuk ke tinker
php artisan tinker

# Contoh perintah di dalam tinker:
>>> App\Models\Customer::count()
>>> App\Models\Invoice::where('status', 'overdue')->count()
>>> App\Models\Customer::find(1)->total_debt
>>> App\Models\Customer::where('status', 'isolated')->pluck('name')
```

---

## 13. Backup & Restore

### 13.1 Backup Database

```bash
# Backup database utama
mysqldump -u root -p billing_javaindonusa > ~/backup_billing_$(date +%Y%m%d).sql

# Backup database RADIUS
mysqldump -u root -p radius > ~/backup_radius_$(date +%Y%m%d).sql

# Backup dengan gzip (compressed)
mysqldump -u root -p billing_javaindonusa | gzip > ~/backup_billing_$(date +%Y%m%d).sql.gz

# Backup hanya struktur (tanpa data)
mysqldump -u root -p --no-data billing_javaindonusa > ~/backup_structure.sql
```

### 13.2 Restore Database

```bash
# Restore dari file SQL
mysql -u root -p billing_javaindonusa < ~/backup_billing_20260301.sql

# Restore dari gzip
gunzip < ~/backup_billing_20260301.sql.gz | mysql -u root -p billing_javaindonusa
```

### 13.3 Backup File Aplikasi

```bash
# Backup seluruh aplikasi
tar -czf ~/backup_app_$(date +%Y%m%d).tar.gz \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='storage/logs/*' \
  /var/www/billing

# Backup file .env saja
cp /var/www/billing/.env ~/backup_env_$(date +%Y%m%d)
```

---

## 14. Cheat Sheet

### Perintah yang Paling Sering Dipakai

```bash
# ============ SEHARI-HARI ============

# Cek status router
php artisan mikrotik:status

# Isolir pelanggan
php artisan mikrotik:isolate JI-00012

# Buka isolir
php artisan mikrotik:isolate JI-00012 --reopen

# Cek job queue gagal
php artisan queue:failed

# Lihat log terbaru
tail -f storage/logs/laravel.log


# ============ BULANAN ============

# Generate invoice manual (jika scheduler gagal)
php artisan billing:generate-invoices

# Cek siapa yang akan diisolir
php artisan billing:process-isolation --dry-run


# ============ DEPLOY ============

# Update kode dari GitHub
cd /var/www/billing && git stash && git pull origin main

# Update dependencies + migrate
composer update --no-dev --optimize-autoloader --ignore-platform-reqs
php artisan migrate --force

# Clear & optimize cache
php artisan optimize:clear && php artisan optimize

# Restart worker
php artisan queue:restart


# ============ TROUBLESHOOTING ============

# Cek semua service
sudo systemctl status nginx php8.2-fpm mysql redis

# Cek log error
grep "ERROR" storage/logs/laravel.log | tail -20

# Test notifikasi
php artisan notification:test whatsapp 6281234567890

# Cek status RADIUS
php artisan radius:status

# Cek status GenieACS
php artisan genieacs:status
```

### Daftar Semua Perintah Custom

| Perintah | Fungsi |
|----------|--------|
| `billing:generate-invoices` | Generate invoice bulanan |
| `billing:check-overdue` | Cek & update status overdue |
| `billing:process-isolation` | Proses isolasi otomatis |
| `billing:send-reminders` | Kirim reminder pembayaran |
| `billing:send-overdue` | Kirim notifikasi overdue |
| `mikrotik:status` | Cek status router Mikrotik |
| `mikrotik:isolate` | Isolir / reopen pelanggan |
| `genieacs:status` | Cek status GenieACS |
| `genieacs:sync-devices` | Sync devices dari GenieACS |
| `radius:status` | Cek status RADIUS DB |
| `radius:sync` | Sync data ke RADIUS DB |
| `notification:test` | Test kirim notifikasi |
| `data:reset` | Reset data selektif |
| `data:reset-customers` | Reset data pelanggan |
| `odp:recalculate-ports` | Hitung ulang port ODP |
| `log:clear` | Bersihkan file log lama |

---

> **Dokumen ini dibuat untuk ISP Billing System Java Indonusa v1.0**
> Terakhir diupdate: Maret 2026
