# Sprint 1: Backup Otomatis & DB Transaction Wrapping

## 1. Backup Otomatis (spatie/laravel-backup v8.8)

### Lokasi File Backup di Server

```
/var/www/billing/storage/app/private/ISP Billing - Java Indonusa/
```

File berformat `.zip`, contoh: `2026-03-16-22-06-50.zip`

### Jadwal Backup (Otomatis via Scheduler)

| Jadwal | Command | Isi Backup |
|--------|---------|------------|
| **Setiap hari 02:00 WIB** | `backup:run --only-db` | Database saja (~185 KB compressed) |
| **Minggu 03:00 WIB** | `backup:run` | Database + .env + config + migrations + views |
| **Minggu 04:00 WIB** | `backup:clean` | Hapus backup lama sesuai retention policy |
| **Setiap hari 08:00 WIB** | `backup:monitor` | Cek kesehatan backup, email jika gagal |

> Scheduler harus aktif di crontab server:
> ```
> * * * * * cd /var/www/billing && php artisan schedule:run >> /dev/null 2>&1
> ```

### Retention Policy (Berapa Lama Backup Disimpan)

- 7 hari pertama: simpan semua backup
- Setelahnya: 1 backup per hari selama 30 hari
- Setelahnya: 1 backup per minggu selama 8 minggu
- Setelahnya: 1 backup per bulan selama 6 bulan
- Setelahnya: 1 backup per tahun selama 2 tahun
- Max total storage: 5 GB

### Command Manual

```bash
# Backup database saja
php artisan backup:run --only-db

# Full backup (database + config files)
php artisan backup:run

# Lihat daftar backup
php artisan backup:list

# Hapus backup lama
php artisan backup:clean

# Cek kesehatan backup
php artisan backup:monitor
```

### Download Backup dari Server ke PC

**Cara 1 — SCP (dari terminal PC lokal, bukan server):**
```bash
scp root@IP_SERVER:"/var/www/billing/storage/app/private/ISP Billing - Java Indonusa/*.zip" ~/Downloads/
```

**Cara 2 — Download file tertentu:**
```bash
# Cek daftar file backup di server dulu
find storage/app -name "*.zip" -type f -exec ls -lh {} \;

# Dari PC lokal, download file spesifik
scp root@IP_SERVER:"/var/www/billing/storage/app/private/ISP Billing - Java Indonusa/2026-03-16-22-06-50.zip" ~/Downloads/
```

**Cara 3 — Copy ke folder public sementara lalu download via browser:**
```bash
# Di server
cp "/var/www/billing/storage/app/private/ISP Billing - Java Indonusa/2026-03-16-22-06-50.zip" /var/www/billing/public/backup-temp.zip

# Download via browser: https://domain-kamu.com/backup-temp.zip
# PENTING: Hapus setelah download!
rm /var/www/billing/public/backup-temp.zip
```

### Notifikasi Email

Backup akan mengirim email notifikasi ke alamat di `.env`:
- **Backup gagal** → email dikirim
- **Backup berhasil** → email dikirim
- **Backup unhealthy** (terlalu lama tidak backup) → email dikirim

Jika tidak ingin notifikasi email, kosongkan `BACKUP_NOTIFICATION_EMAIL` di `.env`.

### Konfigurasi .env (di Server)

```env
# --- SMTP EMAIL (wajib untuk notifikasi) ---
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=emailkamu@gmail.com
MAIL_PASSWORD="xxxx xxxx xxxx xxxx"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="emailkamu@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"

# --- BACKUP ---
# Email penerima notifikasi backup (kosongkan untuk disable)
BACKUP_NOTIFICATION_EMAIL=emailkamu@gmail.com

# Password enkripsi arsip backup (opsional, kosongkan jika tidak perlu)
BACKUP_ARCHIVE_PASSWORD=

# Slack webhook untuk notifikasi backup (opsional)
BACKUP_SLACK_WEBHOOK=
```

> **Gmail App Password:** Buat di https://myaccount.google.com/apppasswords
> (2-Step Verification harus aktif). Password yang ada spasi **harus dibungkus tanda kutip**.

### Catatan Penting

- Extension `ext-zip` harus aktif di PHP server (`php -m | grep zip`)
- `mysqldump` harus tersedia di server (biasanya sudah ada)
- Log backup di `storage/logs/backup.log`
- `MAIL_FROM_ADDRESS` harus sama dengan `MAIL_USERNAME` untuk Gmail SMTP

---

## 2. Perintah Update Server

Setiap kali ada perubahan kode yang di-push ke GitHub, jalankan ini di server:

```bash
cd /var/www/billing && \
git stash && \
git pull origin main && \
composer update --no-dev --optimize-autoloader --ignore-platform-reqs && \
php artisan migrate --force && \
mkdir -p storage/framework/{views,cache,sessions,testing} && \
mkdir -p storage/app/backups && \
php artisan optimize:clear && \
php artisan optimize && \
php artisan queue:restart
```

> **PENTING:** Copy-paste dalam satu kali, jangan enter di tengah command.
> Jika ada prompt `Continue as root/super user [yes]?`, ketik `yes` lalu Enter.

### Troubleshooting Deploy

| Masalah | Solusi |
|---------|--------|
| `git pull` gagal karena file conflict | `git stash` sudah di-handle di perintah update |
| `untracked working tree files would be overwritten` | `rm file-yang-konflik` lalu `git pull` ulang |
| `artisan: command not found` | Jangan enter di tengah command, paste satu baris |
| `The environment file is invalid` | Cek `.env`, value yang ada spasi harus dibungkus `"..."` |
| `Disk [xxx] does not have a configured driver` | `php artisan optimize:clear && php artisan optimize` |
| Backup gagal `Sending notification failed` | Cek SMTP config di `.env`, atau kosongkan `BACKUP_NOTIFICATION_EMAIL` |

---

## 3. DB Transaction Wrapping

### Masalah yang Ditemukan

Beberapa operasi kritis melakukan **multiple writes tanpa transaction**, berisiko data inkonsisten jika terjadi error di tengah proses:

#### A. DebtService — 3 method tanpa transaction

| Method | Masalah | Risiko |
|--------|---------|--------|
| `addCredit()` | `increment()` + `create()` terpisah | Credit balance naik tapi DebtHistory tidak tercatat |
| `useCredit()` | `decrement()` + `update()` + `decrement()` + `create()` terpisah | Invoice ter-update tapi hutang tidak berkurang, atau sebaliknya |
| `recalculateDebt()` | `update()` + `create()` terpisah | Total debt berubah tanpa audit trail |

#### B. InvoiceService — 2 masalah

| Method | Masalah | Risiko |
|--------|---------|--------|
| `updateOverdueStatus()` | Loop update tanpa transaction | Sebagian invoice ter-update, sebagian tidak |
| `generateInvoiceNumber()` | Tidak pakai `lockForUpdate()` | Race condition: 2 invoice dapat nomor yang sama |

#### C. DebtIsolationService — 2 masalah

| Method | Masalah | Risiko |
|--------|---------|--------|
| `generateInvoiceNumber()` | Tidak pakai `lockForUpdate()` | Race condition pada concurrent invoice generation |
| `generatePaymentNumber()` | Tidak pakai `lockForUpdate()` | Race condition pada concurrent payment creation |

### Perbaikan yang Dilakukan

#### DebtService (`app/Services/Billing/DebtService.php`)

1. **`addCredit()`** — Dibungkus `DB::transaction()`
2. **`useCredit()`** — Dibungkus `DB::transaction()`
3. **`recalculateDebt()`** — Dibungkus `DB::transaction()` + `lockForUpdate()`

#### InvoiceService (`app/Services/Billing/InvoiceService.php`)

4. **`updateOverdueStatus()`** — Diganti dari loop ke single bulk update dalam `DB::transaction()`
5. **`generateInvoiceNumber()`** — Pakai `lockForUpdate()` (prevent race condition)

#### DebtIsolationService (`app/Services/Billing/DebtIsolationService.php`)

6. **`generateInvoiceNumber()`** — Pakai `lockForUpdate()`
7. **`generatePaymentNumber()`** — Pakai `lockForUpdate()`

---

## 4. File yang Diubah

| File | Perubahan |
|------|-----------|
| `composer.json` | Tambah `spatie/laravel-backup:^8.0` |
| `config/backup.php` | Baru — konfigurasi backup (Gzip, retention, notifikasi) |
| `config/database.php` | Tambah `dump` config MySQL (useSingleTransaction) |
| `routes/console.php` | Jadwal backup otomatis (harian + mingguan) |
| `.env.example` | Tambah section BACKUP |
| `app/Services/Billing/DebtService.php` | Transaction wrap: `addCredit()`, `useCredit()`, `recalculateDebt()` |
| `app/Services/Billing/InvoiceService.php` | Transaction wrap + lock: `updateOverdueStatus()`, `generateInvoiceNumber()` |
| `app/Services/Billing/DebtIsolationService.php` | Lock fix: `generateInvoiceNumber()`, `generatePaymentNumber()` |
