# Panduan Backup & Restore Database

## Perintah Update VPS

```bash
# SSH ke VPS
ssh user@ip_vps

# Masuk ke direktori aplikasi
cd /var/www/javaindonusa

# Pull perubahan terbaru
git pull origin main

# Install dependencies jika ada perubahan
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Jalankan migrasi
php artisan migrate --force

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue worker
sudo supervisorctl restart javaindonusa-worker:*
```

---

## Fitur Backup Database via UI

### Lokasi
Menu **Admin > Sistem** (`/admin/system`) > Section **"Backup Database"**

### Fitur yang Tersedia

| Aksi | Deskripsi |
|------|-----------|
| **Backup Database Sekarang** | Export seluruh database MySQL ke file `.sql.gz` |
| **Upload Backup Database** | Upload file backup `.sql` atau `.sql.gz` dari komputer/server lain |
| **Download** | Download file backup ke komputer |
| **Restore** | Restore database dari file backup (dengan konfirmasi) |
| **Hapus** | Hapus file backup dari server |

### Cara Backup Database

1. Buka `/admin/system`
2. Scroll ke section **"Backup Database"**
3. Klik tombol **"Backup Database Sekarang"** (tombol orange)
4. Tunggu proses selesai
5. File backup akan muncul di daftar backup

### Cara Restore Database

1. Buka `/admin/system`
2. Jika file backup belum ada di server tujuan, **upload** dulu file `.sql.gz`
3. Di daftar backup, klik icon **restore** (icon panah putar, warna orange)
4. Baca peringatan di modal konfirmasi
5. Klik **"Ya, Restore Database"**
6. Tunggu proses selesai (aplikasi akan masuk maintenance mode sementara)

---

## Cara Migrasi Database Antar Server

### Skenario: VPS -> Server Baru (atau Proxmox)

**Di VPS (sumber):**
1. Buka `/admin/system`
2. Klik **"Backup Database Sekarang"**
3. **Download** file backup yang baru dibuat

**Di Server Tujuan:**
1. Pastikan aplikasi sudah terinstall dan bisa diakses
2. Buka `/admin/system`
3. Klik **"Upload Backup Database"** > pilih file `.sql.gz` yang sudah didownload
4. Klik **Upload**
5. Setelah upload selesai, klik tombol **Restore** pada backup tersebut
6. Konfirmasi restore

### Skenario: Backup via CLI (Alternatif)

**Export database:**
```bash
mysqldump --host=127.0.0.1 --port=3306 --user=root --password=PASSWORD \
    --single-transaction --routines --triggers --add-drop-table \
    javaindonusa > backup_db.sql

# Compress
gzip backup_db.sql
# Hasil: backup_db.sql.gz
```

**Import database:**
```bash
# Decompress
gunzip backup_db.sql.gz

# Import
mysql --host=127.0.0.1 --port=3306 --user=root --password=PASSWORD \
    javaindonusa < backup_db.sql

# Jalankan migrasi (jika versi app berbeda)
php artisan migrate --force
```

**Transfer file antar server:**
```bash
# Dari VPS ke Proxmox
scp backup_db.sql.gz user@ip_proxmox:/var/www/javaindonusa/storage/app/backups/database/

# Atau sebaliknya
scp user@ip_vps:/var/www/javaindonusa/storage/app/backups/database/backup_db.sql.gz ./
```

---

## Lokasi File Backup

| Tipe | Lokasi |
|------|--------|
| Backup Aplikasi (kode) | `storage/app/backups/*.zip` |
| Backup Database | `storage/app/backups/database/*.sql.gz` |

---

## Backup Otomatis via Cron (Opsional)

Untuk backup database otomatis setiap hari:

```bash
crontab -e
```

Tambahkan:
```cron
# Backup database setiap hari jam 02:00
0 2 * * * cd /var/www/javaindonusa && php artisan schedule:run >> /dev/null 2>&1
```

Atau langsung via mysqldump:
```cron
# Backup database harian, simpan 7 hari terakhir
0 2 * * * mysqldump --host=127.0.0.1 --user=root --password=PASSWORD --single-transaction javaindonusa | gzip > /var/www/javaindonusa/storage/app/backups/database/db_auto_$(date +\%Y-\%m-\%d).sql.gz && find /var/www/javaindonusa/storage/app/backups/database/ -name "db_auto_*.sql.gz" -mtime +7 -delete
```

---

## Catatan Penting

1. **Sebelum restore**, pastikan sudah backup database yang sedang berjalan
2. **Restore akan menimpa** seluruh data database yang ada
3. Selama proses restore, aplikasi masuk **maintenance mode** (tidak bisa diakses user)
4. Setelah restore, **migrasi otomatis dijalankan** untuk menyesuaikan struktur tabel
5. File backup berformat **`.sql.gz`** (terkompresi) untuk menghemat storage
6. Backup mencakup: **semua tabel, data, trigger, dan routine**
