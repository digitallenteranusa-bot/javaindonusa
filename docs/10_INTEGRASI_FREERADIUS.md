# Panduan Integrasi FreeRADIUS - ISP Billing System
## Java Indonusa

Dokumen ini menjelaskan cara menginstall, mengkonfigurasi, dan mengoperasikan integrasi FreeRADIUS dengan sistem billing. Integrasi ini menambahkan **dual sync** — kredensial pelanggan disinkronkan ke **Mikrotik** (seperti sebelumnya) **DAN** ke **FreeRADIUS database** secara bersamaan.

**Status:** Production-ready, terverifikasi di VPS billing dengan FreeRADIUS 3.0.26 + Mikrotik.

---

## Daftar Isi

1. [Arsitektur](#1-arsitektur)
2. [Persyaratan Sistem](#2-persyaratan-sistem)
3. [Instalasi FreeRADIUS](#3-instalasi-freeradius)
4. [Konfigurasi Database RADIUS](#4-konfigurasi-database-radius)
5. [Konfigurasi Aplikasi Billing](#5-konfigurasi-aplikasi-billing)
6. [Menjalankan Migration](#6-menjalankan-migration)
7. [Konfigurasi FreeRADIUS](#7-konfigurasi-freeradius)
8. [Integrasi dengan Mikrotik](#8-integrasi-dengan-mikrotik)
9. [Operasional & Command](#9-operasional--command)
10. [Alur Kerja Sistem](#10-alur-kerja-sistem)
11. [Admin Panel](#11-admin-panel)
12. [Metode Isolasi RADIUS](#12-metode-isolasi-radius)
13. [Struktur File](#13-struktur-file)
14. [Testing & Verifikasi](#14-testing--verifikasi)
15. [Troubleshooting](#15-troubleshooting)
16. [FAQ](#16-faq)
17. [Catatan Penting dari Production](#17-catatan-penting-dari-production)

---

## 1. Arsitektur

### 1.1 Arsitektur Dual Sync

```
┌─────────────────────────────────────────────────────────────────────┐
│                     BILLING SYSTEM (Laravel)                        │
│                                                                     │
│    Customer CRUD / Isolasi / Reopen / Observer / Jobs               │
│                          │                                          │
│              ┌───────────┴───────────┐                              │
│              │                       │                              │
│              ▼                       ▼                              │
│    ┌─────────────────┐    ┌─────────────────┐                      │
│    │ MikrotikService │    │  RadiusService  │   ← dual sync        │
│    │  (existing)     │    │  (baru)         │                      │
│    │  PPPoE secrets  │    │  radcheck       │                      │
│    │  address list   │    │  radreply       │                      │
│    └────────┬────────┘    └────────┬────────┘                      │
└─────────────┼──────────────────────┼───────────────────────────────┘
              │                      │
              ▼                      ▼
    ┌─────────────────┐    ┌─────────────────┐
    │  Mikrotik       │    │  FreeRADIUS     │
    │  Router API     │    │  MySQL DB       │
    │  Port 8728      │    │  Port 1812/1813 │
    └────────┬────────┘    └────────┬────────┘
             │                      │
             └──────────┬───────────┘
                        │
                        ▼
              ┌─────────────────┐
              │   Pelanggan     │
              │   PPPoE Client  │
              └─────────────────┘
```

### 1.2 Alur Autentikasi dengan RADIUS

```
┌───────────┐     ┌───────────┐     ┌───────────┐     ┌───────────┐
│ Pelanggan │────▶│ Mikrotik  │────▶│ FreeRADIUS│────▶│ MySQL DB  │
│ PPPoE     │     │ NAS       │     │ Server    │     │ (radius)  │
└───────────┘     └─────┬─────┘     └─────┬─────┘     └───────────┘
                        │                 │
                        │  Access-Request │
                        │  (username +    │
                        │   password)     │
                        │                 │
                        │  Access-Accept  │
                        │◀────────────────│
                        │  + Rate-Limit   │
                        │  + Address-List │
                        │                 │
                        │  Acct-Start     │
                        │────────────────▶│  → radacct
                        │                 │
                        │  Acct-Stop      │
                        │────────────────▶│  → radacct
```

### 1.3 Feature Flag

Integrasi RADIUS dikendalikan oleh environment variable `RADIUS_ENABLED`:

| `RADIUS_ENABLED` | Perilaku |
|-------------------|----------|
| `false` (default) | Semua operasi RADIUS di-skip. Sistem berjalan persis seperti sebelumnya. |
| `true` | Dual sync aktif. Customer CRUD, isolasi, dan reopen otomatis sync ke RADIUS DB. |

**Penting:** Deployment existing **tidak terganggu** karena default-nya `false`.

---

## 2. Persyaratan Sistem

### 2.1 Software Tambahan

| Software | Versi Minimum | Keterangan |
|----------|---------------|------------|
| FreeRADIUS | 3.0 | Server RADIUS |
| MySQL | 8.0 | Database untuk FreeRADIUS (bisa shared atau terpisah) |

### 2.2 Port yang Digunakan

| Port | Protokol | Fungsi |
|------|----------|--------|
| 1812 | UDP | RADIUS Authentication |
| 1813 | UDP | RADIUS Accounting |
| 3306 | TCP | MySQL (RADIUS database) |

### 2.3 Arsitektur Database

FreeRADIUS menggunakan database MySQL **terpisah** dari database billing utama:

```
┌───────────────────────────────┐     ┌───────────────────────────────┐
│  MySQL: billing_javaindonusa  │     │  MySQL: radius                │
│  (Database Utama)             │     │  (Database RADIUS)            │
├───────────────────────────────┤     ├───────────────────────────────┤
│  customers                    │     │  radcheck                     │
│  invoices                     │     │  radreply                     │
│  payments                     │     │  radusergroup                 │
│  packages                     │     │  radgroupcheck                │
│  routers                      │     │  radgroupreply                │
│  radius_servers               │     │  radacct                      │
│  ...                          │     │  radpostauth                  │
│                               │     │  nas                          │
└───────────────────────────────┘     └───────────────────────────────┘
```

Kedua database bisa berada di server MySQL yang sama atau berbeda.

---

## 3. Instalasi FreeRADIUS

### 3.1 Install di Ubuntu 22.04/24.04

```bash
# Install FreeRADIUS dan modul MySQL
sudo apt update
sudo apt install -y freeradius freeradius-mysql freeradius-utils

# Verifikasi instalasi
freeradius -v
```

### 3.2 Verifikasi Service

```bash
# Cek status
sudo systemctl status freeradius

# Start jika belum aktif
sudo systemctl start freeradius
sudo systemctl enable freeradius
```

### 3.3 Test FreeRADIUS Berjalan

```bash
# Test dengan radtest (ganti 'testing123' dengan secret Anda)
radtest testuser testpassword localhost 0 testing123
```

Jika mendapat `Access-Reject`, berarti FreeRADIUS sudah berjalan (reject karena user belum ada).

---

## 4. Konfigurasi Database RADIUS

### 4.1 Buat Database dan User

```sql
-- Login ke MySQL
mysql -u root -p

-- Buat database
CREATE DATABASE radius CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Buat user khusus RADIUS
CREATE USER 'radius'@'localhost' IDENTIFIED BY 'password_radius_yang_kuat';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'localhost';
FLUSH PRIVILEGES;

-- Jika FreeRADIUS dan billing di server berbeda:
CREATE USER 'radius'@'%' IDENTIFIED BY 'password_radius_yang_kuat';
GRANT ALL PRIVILEGES ON radius.* TO 'radius'@'%';
FLUSH PRIVILEGES;
```

### 4.2 Import Schema FreeRADIUS (Opsional)

Schema standar FreeRADIUS bisa di-import dari file bawaan atau menggunakan migration Laravel (lihat [Step 6](#6-menjalankan-migration)).

```bash
# Cara manual (opsional — migration Laravel sudah meng-handle ini)
sudo mysql -u root -p radius < /etc/freeradius/3.0/mods-config/sql/main/mysql/schema.sql
```

> **Catatan:** Migration Laravel akan otomatis membuat tabel yang belum ada (dengan `hasTable()` check), jadi aman dijalankan meskipun tabel sudah di-import manual.

---

## 5. Konfigurasi Aplikasi Billing

### 5.1 Edit File `.env`

Tambahkan konfigurasi berikut di file `.env`:

```env
# ==============================================================================
# FREERADIUS DATABASE
# ==============================================================================

# Aktifkan integrasi RADIUS
RADIUS_ENABLED=true

# Koneksi database RADIUS
RADIUS_DB_HOST=127.0.0.1
RADIUS_DB_PORT=3306
RADIUS_DB_DATABASE=radius
RADIUS_DB_USERNAME=radius
RADIUS_DB_PASSWORD=password_radius_yang_kuat

# Metode isolasi: pool (recommended) | rate_limit | group | delete
RADIUS_ISOLATION_METHOD=pool

# Pool isolasi — customer dapat IP dari pool ini saat diisolir
RADIUS_ISOLATION_POOL=pool-isolir

# Address list untuk NAT redirect ke halaman isolir
RADIUS_ISOLATION_ADDRESS_LIST=ISOLIR

# Rate limit saat isolasi (hanya jika RADIUS_ISOLATION_METHOD=rate_limit)
# RADIUS_ISOLATION_RATE_LIMIT=1k/1k
```

### 5.2 Penjelasan Konfigurasi

| Variable | Default | Keterangan |
|----------|---------|------------|
| `RADIUS_ENABLED` | `false` | Master switch integrasi RADIUS |
| `RADIUS_DB_HOST` | `127.0.0.1` | Host MySQL untuk database RADIUS |
| `RADIUS_DB_PORT` | `3306` | Port MySQL |
| `RADIUS_DB_DATABASE` | `radius` | Nama database RADIUS |
| `RADIUS_DB_USERNAME` | (dari `DB_USERNAME`) | Username MySQL RADIUS |
| `RADIUS_DB_PASSWORD` | (dari `DB_PASSWORD`) | Password MySQL RADIUS |
| `RADIUS_ISOLATION_METHOD` | `pool` | Metode isolasi (lihat [Section 12](#12-metode-isolasi-radius)) |
| `RADIUS_ISOLATION_POOL` | `pool-isolir` | Nama pool Mikrotik untuk IP isolasi |
| `RADIUS_ISOLATION_ADDRESS_LIST` | `ISOLIR` | Address list untuk NAT redirect |
| `RADIUS_ISOLATION_RATE_LIMIT` | `1k/1k` | Rate limit (hanya untuk method `rate_limit`) |

### 5.3 File Konfigurasi `config/radius.php`

File ini sudah otomatis tersedia dan membaca dari `.env`:

```php
return [
    'enabled' => env('RADIUS_ENABLED', false),
    'connection' => 'radius',
    'default_group' => 'default',
    'isolation_method' => env('RADIUS_ISOLATION_METHOD', 'pool'),
    'isolation_pool' => env('RADIUS_ISOLATION_POOL', 'pool-isolir'),
    'isolation_address_list' => env('RADIUS_ISOLATION_ADDRESS_LIST', 'ISOLIR'),
    'isolation_rate_limit' => env('RADIUS_ISOLATION_RATE_LIMIT', '1k/1k'),
    'isolation_group' => 'isolated',
    'auto_sync_nas' => true,
    'attributes' => [
        'rate_limit' => 'Mikrotik-Rate-Limit',
        'address_list' => 'Mikrotik-Address-List',
    ],
];
```

### 5.4 Koneksi Database

Koneksi `radius` sudah ditambahkan di `config/database.php`. Jika database RADIUS berada di server yang sama dengan billing, cukup set `RADIUS_DB_DATABASE`, `RADIUS_DB_USERNAME`, dan `RADIUS_DB_PASSWORD`.

---

## 6. Menjalankan Migration

### 6.1 Jalankan Migration

```bash
# Migration akan membuat tabel FreeRADIUS di database radius
php artisan migrate
```

Migration ini membuat 8 tabel standar FreeRADIUS:

| Tabel | Fungsi |
|-------|--------|
| `radcheck` | Credential pelanggan (username + password) |
| `radreply` | Reply attributes (Mikrotik-Rate-Limit, dll) |
| `radusergroup` | Mapping user ke group |
| `radgroupcheck` | Check attributes per group |
| `radgroupreply` | Reply attributes per group |
| `radacct` | Data accounting (session, bandwidth, IP) |
| `radpostauth` | Log autentikasi (success/reject) |
| `nas` | Daftar NAS (router/access point) |

> **Aman:** Migration menggunakan `hasTable()` check — jika tabel sudah ada (misalnya dari import manual), tabel tersebut **tidak akan ditimpa**.

### 6.2 Verifikasi Tabel

```bash
# Login ke MySQL dan cek tabel
mysql -u radius -p radius -e "SHOW TABLES;"
```

Output yang diharapkan:

```
+------------------+
| Tables_in_radius |
+------------------+
| nas              |
| radacct          |
| radcheck         |
| radgroupcheck    |
| radgroupreply    |
| radpostauth      |
| radreply         |
| radusergroup     |
+------------------+
```

---

## 7. Konfigurasi FreeRADIUS

### 7.1 Aktifkan Modul SQL

```bash
# Enable modul sql
sudo ln -sf /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql
```

### 7.2 Konfigurasi Koneksi SQL

Edit file `/etc/freeradius/3.0/mods-available/sql`:

```
sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"

    server = "localhost"
    port = 3306
    login = "radius"
    password = "password_radius_yang_kuat"

    radius_db = "radius"

    # Tabel standar
    acct_table1 = "radacct"
    acct_table2 = "radacct"
    postauth_table = "radpostauth"
    authcheck_table = "radcheck"
    groupcheck_table = "radgroupcheck"
    authreply_table = "radreply"
    groupreply_table = "radgroupreply"
    usergroup_table = "radusergroup"

    # Hapus stale sessions saat startup
    delete_stale_sessions = yes

    # Connection pooling
    pool {
        start = ${thread[pool].start_servers}
        min = ${thread[pool].min_spare_servers}
        max = ${thread[pool].max_servers}
        spare = ${thread[pool].max_spare_servers}
        uses = 0
        retry_delay = 30
        lifetime = 0
        idle_timeout = 60
    }

    read_clients = yes
    client_table = "nas"

    # Group membership query
    group_membership_query = "\
        SELECT groupname \
        FROM ${usergroup_table} \
        WHERE username = '%{SQL-User-Name}' \
        ORDER BY priority"
}
```

### 7.3 Aktifkan SQL di Site Default

Edit `/etc/freeradius/3.0/sites-available/default`:

```
authorize {
    # ... bagian lain tetap ...

    # Uncomment atau tambahkan:
    sql
}

accounting {
    # ... bagian lain tetap ...

    sql
}

session {
    sql
}

post-auth {
    sql

    Post-Auth-Type REJECT {
        sql
    }
}
```

### 7.4 Aktifkan SQL di Inner-Tunnel

Edit `/etc/freeradius/3.0/sites-available/inner-tunnel`:

```
authorize {
    sql
}

post-auth {
    sql
}
```

### 7.5 Include Dictionary Mikrotik

FreeRADIUS harus mengenali vendor-specific attributes Mikrotik (`Mikrotik-Rate-Limit`, `Mikrotik-Address-List`, dll).

Edit `/etc/freeradius/3.0/dictionary` dan tambahkan di baris terakhir:

```
$INCLUDE /usr/share/freeradius/dictionary.mikrotik
```

> **PENTING:** Attribute `Mikrotik-Local-Address` **TIDAK ADA** di dictionary FreeRADIUS. Jangan pernah insert attribute ini ke radreply — akan menyebabkan `Access-Reject`. Gunakan `local-address` di default PPP profile Mikrotik.

### 7.6 Set Permission dan Restart

```bash
# Set ownership
sudo chown -R freerad:freerad /etc/freeradius/3.0/mods-enabled/sql

# Test konfigurasi (jalankan dalam mode debug)
sudo freeradius -X

# Jika tidak ada error, restart service
sudo systemctl restart freeradius
```

### 7.7 Verifikasi FreeRADIUS Membaca dari Database

```bash
# Insert test user langsung ke database
mysql -u radius -p radius -e "
INSERT INTO radcheck (username, attribute, op, value)
VALUES ('testuser', 'Cleartext-Password', ':=', 'testpass');
"

# Test autentikasi
radtest testuser testpass localhost 0 testing123

# Seharusnya mendapat Access-Accept
# Hapus test user setelah selesai
mysql -u radius -p radius -e "DELETE FROM radcheck WHERE username='testuser';"
```

---

## 8. Integrasi dengan Mikrotik

### 8.1 Konfigurasi RADIUS Client di Mikrotik

Login ke Mikrotik via Winbox atau terminal:

```
# Tambah RADIUS server (gunakan IP PUBLIK VPS, bukan 127.0.0.1)
/radius add service=ppp address=<IP_PUBLIK_VPS> secret=SECRET_RADIUS \
    authentication-port=1812 accounting-port=1813 timeout=3000

# Aktifkan RADIUS untuk PPP
/ppp aaa set use-radius=yes accounting=yes interim-update=5m

# PENTING: Set default PPP profile dengan local-address
# Tanpa ini, RADIUS user tidak akan mendapat IP
/ppp profile set default local-address=10.170.1.1 remote-address=broadband
```

| Parameter | Nilai | Keterangan |
|-----------|-------|------------|
| `address` | IP **publik** server VPS | **BUKAN** 127.0.0.1 — harus reachable dari Mikrotik |
| `secret` | Secret RADIUS | Harus sama dengan di tabel `nas` dan di RADIUS Server config billing |
| `timeout` | `3000` (ms) | Timeout koneksi |
| `interim-update` | `5m` | Interval update accounting |
| `local-address` | IP gateway (misal `10.170.1.1`) | **WAJIB** di default PPP profile |
| `remote-address` | Pool normal (misal `broadband`) | Pool IP untuk pelanggan aktif |

> **PENTING:** `local-address` di default PPP profile **WAJIB** ada. Jika tidak diset, RADIUS user akan gagal mendapat IP dan muncul error *"could not determine remote IP address"*.

### 8.2 Konfigurasi Pool Isolasi di Mikrotik

Pastikan pool isolasi dan NAT redirect sudah dikonfigurasi:

```
# Pool untuk customer yang diisolir (jika belum ada)
/ip pool add name=pool-isolir ranges=10.170.100.1-10.170.100.254

# NAT redirect — arahkan traffic HTTP customer isolir ke halaman notifikasi
/ip firewall nat add chain=dstnat src-address-list=ISOLIR dst-port=80 \
    protocol=tcp action=dst-nat to-addresses=<IP_WEB_ISOLIR> to-ports=80 \
    comment="Redirect isolir ke halaman notifikasi"
```

### 8.3 Daftarkan Mikrotik sebagai NAS

Di aplikasi billing:

1. Buka **Admin Panel** → **Radius Server**
2. Pastikan Radius Server sudah dibuat dan statusnya **Aktif**
3. Pastikan **Router** sudah di-assign ke Radius Server yang sesuai (edit Router → pilih Radius Server)
4. Klik tombol **"Sync NAS"** di halaman Radius Server

Atau via artisan command:

```bash
php artisan radius:sync --nas
```

### 8.4 Verifikasi NAS di Database

```bash
mysql -u radius -p radius -e "SELECT * FROM nas;"
```

Output:

```
+----+----------------+-----------+-------+-------+--------+--------+-----------+--------------------+
| id | nasname        | shortname | type  | ports | secret | server | community | description        |
+----+----------------+-----------+-------+-------+--------+--------+-----------+--------------------+
|  1 | 192.168.88.1   | MK-UTAMA  | other |  NULL | xxxxx  | NULL   | NULL      | Router: MK-UTAMA   |
+----+----------------+-----------+-------+-------+--------+--------+-----------+--------------------+
```

### 8.5 Konfigurasi FreeRADIUS untuk Membaca NAS dari Database

Pastikan `read_clients = yes` dan `client_table = "nas"` sudah diset di `/etc/freeradius/3.0/mods-available/sql` (sudah dibahas di [Section 7.2](#72-konfigurasi-koneksi-sql)).

Restart FreeRADIUS setelah menambah NAS baru:

```bash
sudo systemctl restart freeradius
```

---

## 9. Operasional & Command

### 9.1 Artisan Commands

#### `radius:sync` — Sinkronisasi Data

```bash
# Sync semua customer + NAS
php artisan radius:sync --all

# Sync customer saja
php artisan radius:sync --customers

# Sync NAS saja
php artisan radius:sync --nas

# Preview tanpa eksekusi (dry run)
php artisan radius:sync --all --dry-run
```

Output contoh:

```
Syncing customers to RADIUS DB...
  Synced: 150
  Failed: 2
  Skipped: 0
Syncing routers to NAS table...
  Synced: 3
  Failed: 0

Done.
```

#### `radius:status` — Status & Statistik

```bash
php artisan radius:status
```

Output contoh:

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

### 9.2 Sync Awal (First-Time Setup)

Setelah instalasi pertama kali, jalankan sync untuk mengisi database RADIUS dengan data existing:

```bash
# 1. Cek status koneksi
php artisan radius:status

# 2. Preview data yang akan disync
php artisan radius:sync --all --dry-run

# 3. Jalankan sync
php artisan radius:sync --all

# 4. Verifikasi
php artisan radius:status
```

#### `radius:cleanup` — Bersihkan Data Lama

```bash
# Preview data yang akan dihapus (dry run)
php artisan radius:cleanup --dry-run

# Hapus data lebih lama dari 3 bulan (default)
php artisan radius:cleanup

# Hapus data lebih lama dari 6 bulan
php artisan radius:cleanup --months=6
```

Data yang dihapus:
- `radacct`: Hanya session yang sudah **selesai** (`acctstoptime IS NOT NULL`)
- `radpostauth`: Log autentikasi lama

> **Catatan:** Session aktif (`acctstoptime IS NULL`) **tidak akan dihapus**.

Cleanup otomatis dijadwalkan setiap **Minggu 04:30** di `routes/console.php`.

### 9.3 Kapan Harus Menjalankan `radius:sync`

| Situasi | Command |
|---------|---------|
| Instalasi pertama kali | `radius:sync --all` |
| Database RADIUS di-reset / di-restore | `radius:sync --all` |
| Menambah router baru ke RADIUS | `radius:sync --nas` |
| Data tidak sinkron (troubleshooting) | `radius:sync --customers` |
| Routine maintenance | Tidak perlu — sync otomatis via Observer |

---

## 10. Alur Kerja Sistem

### 10.1 Customer Dibuat (Create)

```
Admin membuat customer baru (dengan PPPoE username/password)
    │
    ├──▶ CustomerObserver::created()
    │       │
    │       ├──▶ MikrotikService → PPPoE secret di Mikrotik
    │       │
    │       └──▶ RadiusService::syncCustomer()
    │               │
    │               ├──▶ radcheck  : INSERT Cleartext-Password
    │               ├──▶ radreply  : INSERT Mikrotik-Rate-Limit
    │               ├──▶ radreply  : INSERT Framed-Pool (dari paket)
    │               └──▶ radusergroup : INSERT group 'default'
    │
    └──▶ Pelanggan bisa login PPPoE via RADIUS
```

### 10.2 Customer Diupdate (Update)

```
Admin mengubah password/paket/username pelanggan
    │
    ├──▶ CustomerObserver::updated()
    │       │
    │       ├──▶ Jika username berubah:
    │       │       └──▶ Hapus entry username LAMA dari radcheck/radreply/radusergroup
    │       │
    │       └──▶ RadiusService::syncCustomer()
    │               │
    │               ├──▶ DELETE semua entry username dari radcheck/radreply/radusergroup
    │               └──▶ INSERT ulang dengan data terbaru
    │
    └──▶ Pelanggan login dengan credential baru
```

### 10.3 Customer Diisolir (Isolate) — Pool Method

```
Scheduler / Admin mengisolir pelanggan
    │
    ├──▶ IsolateCustomerJob::handle()
    │       │
    │       ├──▶ MikrotikService::isolateCustomer()
    │       │       └──▶ Address list / profile change di Mikrotik
    │       │
    │       └──▶ RadiusService::isolateCustomer()
    │               │
    │               ├──▶ radreply: Framed-Pool = 'pool-isolir'
    │               ├──▶ radreply: Mikrotik-Address-List = 'ISOLIR'
    │               └──▶ radreply: DELETE Mikrotik-Rate-Limit
    │
    └──▶ Pelanggan reconnect PPPoE → dapat IP dari pool-isolir
         → masuk address list ISOLIR → NAT redirect ke halaman isolir
         → bandwidth TIDAK dibatasi (agar halaman isolir bisa dimuat)
```

### 10.4 Customer Direopen (Reopen)

```
Payment diterima → Customer direopen
    │
    ├──▶ ReopenCustomerJob::handle()
    │       │
    │       ├──▶ MikrotikService::reopenCustomer()
    │       │       └──▶ Remove dari address list / restore profile
    │       │
    │       └──▶ RadiusService::reopenCustomer()
    │               │
    │               ├──▶ radreply: Framed-Pool = pool dari paket (broadband)
    │               ├──▶ radreply: Mikrotik-Rate-Limit = rate dari paket
    │               ├──▶ radreply: DELETE Mikrotik-Address-List
    │               └──▶ radusergroup: Restore group ke 'default'
    │
    └──▶ Pelanggan reconnect → dapat IP normal + bandwidth normal
```

### 10.5 Customer Dihapus (Delete)

```
Admin menghapus customer
    │
    ├──▶ CustomerObserver::deleted()
    │       │
    │       └──▶ RadiusService::removeCustomer()
    │               │
    │               ├──▶ DELETE dari radcheck
    │               ├──▶ DELETE dari radreply
    │               └──▶ DELETE dari radusergroup
    │
    └──▶ Pelanggan tidak bisa login PPPoE lagi
```

---

## 11. Admin Panel

### 11.1 Halaman Radius Server

Buka **Admin Panel** → menu **Radius Server** (`/admin/radius-servers`).

Fitur yang tersedia:

| Fitur | Keterangan |
|-------|------------|
| **Daftar Server** | Lihat semua RADIUS server yang terdaftar |
| **Tambah Server** | Buat konfigurasi RADIUS server baru (nama, IP, port, secret) |
| **Edit Server** | Ubah konfigurasi server |
| **Test Koneksi** | Test apakah port RADIUS bisa dijangkau |
| **Sync NAS** | Sync semua router ke tabel `nas` di database RADIUS |
| **Hapus Server** | Hapus server (hanya jika tidak ada router yang terhubung) |

### 11.2 Menghubungkan Router ke RADIUS Server

1. Buka **Admin Panel** → **Router** → Edit router
2. Pada field **Radius Server**, pilih server yang sesuai
3. Simpan
4. Kembali ke halaman **Radius Server** → klik **Sync NAS**
5. Router akan muncul di tabel `nas` di database RADIUS

---

## 12. Metode Isolasi RADIUS

Empat metode isolasi tersedia, dikonfigurasi via `RADIUS_ISOLATION_METHOD`:

### 12.1 `pool` (Default & Rekomendasi)

```env
RADIUS_ISOLATION_METHOD=pool
RADIUS_ISOLATION_POOL=pool-isolir
RADIUS_ISOLATION_ADDRESS_LIST=ISOLIR
```

**Cara kerja:**
- Saat isolasi:
  - `Framed-Pool` diubah ke `pool-isolir` (customer dapat IP dari pool isolasi)
  - `Mikrotik-Address-List` diset ke `ISOLIR` (untuk NAT redirect)
  - `Mikrotik-Rate-Limit` **dihapus** (bandwidth tidak dibatasi)
- Saat reopen:
  - `Framed-Pool` dikembalikan ke pool paket (misal `broadband`)
  - `Mikrotik-Rate-Limit` dikembalikan ke rate dari paket
  - `Mikrotik-Address-List` dihapus

**Kelebihan:**
- Pelanggan tetap terhubung dan **bisa membuka halaman isolir** (bandwidth tidak dibatasi)
- NAT redirect otomatis ke halaman pemberitahuan pembayaran
- Pemisahan IP yang jelas antara pelanggan aktif dan isolir

**Prasyarat Mikrotik:**
- Pool `pool-isolir` harus sudah dibuat
- NAT rule dst-nat untuk `src-address-list=ISOLIR` ke web server halaman isolir

### 12.2 `rate_limit`

```env
RADIUS_ISOLATION_METHOD=rate_limit
RADIUS_ISOLATION_RATE_LIMIT=1k/1k
```

**Cara kerja:**
- Saat isolasi: Rate limit di `radreply` diubah menjadi `1k/1k` (1 Kbps upload/download)
- Saat reopen: Rate limit dikembalikan ke nilai dari paket pelanggan

> **Perhatian:** Dengan bandwidth 1k/1k, pelanggan **tidak akan bisa memuat halaman apapun** termasuk halaman isolir. Gunakan method `pool` jika ingin menampilkan halaman pemberitahuan.

### 12.3 `group`

```env
RADIUS_ISOLATION_METHOD=group
```

**Cara kerja:**
- Saat isolasi: User dipindah ke group `isolated` + rate limit diubah
- Saat reopen: User dikembalikan ke group `default` + rate limit normal
- Bisa dikombinasikan dengan group-based policy di FreeRADIUS

### 12.4 `delete`

```env
RADIUS_ISOLATION_METHOD=delete
```

**Cara kerja:**
- Saat isolasi: Semua entry dihapus dari `radcheck`, `radreply`, `radusergroup`
- Saat reopen: Entry dibuat ulang via `syncCustomer()`
- Pelanggan **tidak bisa login PPPoE** saat diisolir

---

## 13. Struktur File

### 13.1 File Baru

```
app/
├── Console/Commands/
│   ├── RadiusSync.php              # Artisan: radius:sync
│   ├── RadiusStatus.php            # Artisan: radius:status
│   └── RadiusCleanup.php           # Artisan: radius:cleanup (bersihkan data lama)
├── Models/
│   ├── Radius/
│   │   ├── RadCheck.php            # Model tabel radcheck
│   │   ├── RadReply.php            # Model tabel radreply
│   │   ├── RadUserGroup.php        # Model tabel radusergroup
│   │   ├── RadAcct.php             # Model tabel radacct (read-only)
│   │   └── Nas.php                 # Model tabel nas
│   └── RadiusServer.php            # Model RADIUS server (billing DB)
├── Observers/
│   └── RouterObserver.php          # Auto sync NAS saat router berubah
└── Services/Radius/
    └── RadiusService.php           # Service utama RADIUS

config/
└── radius.php                      # Konfigurasi RADIUS

database/migrations/
├── 2026_03_01_000001_create_freeradius_tables.php
└── 2026_03_19_053846_add_pppoe_pool_to_packages_table.php
```

### 13.2 File yang Dimodifikasi

```
config/database.php                 # + connection 'radius' (support SQLite untuk testing)
.env.example                        # + RADIUS_* variables
app/Models/Package.php              # + pppoe_pool field (pool PPPoE per paket)
app/Observers/CustomerObserver.php  # + RADIUS sync on CRUD
app/Jobs/IsolateCustomerJob.php     # + RadiusService::isolateCustomer()
app/Jobs/ReopenCustomerJob.php      # + RadiusService::reopenCustomer()
app/Http/Controllers/Admin/
    RadiusServerController.php      # + testConnection via DB, syncNas()
    CustomerController.php          # + radiusData di show()
resources/js/Pages/Admin/
    RadiusServer/Index.vue          # + Sync NAS button
    RadiusServer/Show.vue           # Detail server + panduan Mikrotik
    Customer/Show.vue               # + RADIUS session card
    Package/Form.vue                # + PPPoE Pool field
routes/admin.php                    # + POST radius-servers/sync-nas
routes/console.php                  # + radius:cleanup schedule
```

### 13.3 RadiusService Methods

| Method | Input | Fungsi |
|--------|-------|--------|
| `isEnabled()` | — | Cek apakah RADIUS aktif |
| `syncCustomer($customer)` | Customer | Sync credentials ke radcheck + radreply + radusergroup |
| `removeCustomer($customer)` | Customer | Hapus semua entry customer |
| `isolateCustomer($customer)` | Customer | Ubah rate-limit / group / hapus entry |
| `reopenCustomer($customer)` | Customer | Kembalikan rate-limit dan group normal |
| `syncNas($router)` | Router | Upsert router ke tabel `nas` |
| `removeNas($router)` | Router | Hapus router dari tabel `nas` |
| `isOnline($customer)` | Customer | Cek session aktif di radacct |
| `getAccountingData($customer)` | Customer | Ambil data session dan bandwidth |
| `syncAllCustomers()` | — | Bulk sync semua customer aktif |
| `syncAllNas()` | — | Bulk sync semua router dengan RADIUS |
| `removeByUsername($username)` | string | Hapus entry berdasarkan username |

---

## 14. Testing & Verifikasi

### 14.1 Checklist Verifikasi

```bash
# 1. Pastikan RADIUS disabled tidak merusak apapun
# Set RADIUS_ENABLED=false di .env, lalu:
php artisan radius:status
# Expected: "RADIUS integration is disabled"

# 2. Aktifkan RADIUS
# Set RADIUS_ENABLED=true di .env, lalu:
php artisan radius:status
# Expected: Connection OK, tabel stats

# 3. Sync semua data
php artisan radius:sync --all

# 4. Cek data di database
mysql -u radius -p radius -e "SELECT username, attribute, value FROM radcheck LIMIT 5;"
mysql -u radius -p radius -e "SELECT username, attribute, value FROM radreply LIMIT 5;"
mysql -u radius -p radius -e "SELECT * FROM nas;"

# 5. Test autentikasi pelanggan
radtest USERNAME_PELANGGAN PASSWORD_PELANGGAN localhost 0 SECRET_RADIUS

# 6. Test dari Mikrotik
# Login ke Mikrotik, cek log PPPoE:
/log print where topics~"radius"
```

### 14.2 Test Isolasi (Pool Method)

```bash
# 1. Isolir pelanggan via billing (admin panel atau command)
# 2. Cek radreply berubah:
mysql -u root -p radius -e "
SELECT username, attribute, value FROM radreply
WHERE username='USERNAME_PELANGGAN';
"
# Expected:
#   Framed-Pool = pool-isolir
#   Mikrotik-Address-List = ISOLIR
#   (TIDAK ada Mikrotik-Rate-Limit)

# 3. Reopen pelanggan
# 4. Cek radreply kembali normal:
mysql -u root -p radius -e "
SELECT username, attribute, value FROM radreply
WHERE username='USERNAME_PELANGGAN';
"
# Expected:
#   Framed-Pool = broadband
#   Mikrotik-Rate-Limit = rate dari paket (misal: 10240k/10240k)
#   (TIDAK ada Mikrotik-Address-List)
```

### 14.3 Test Graceful Degradation

```bash
# 1. Matikan akses ke database RADIUS (misalnya stop MySQL atau ubah password)
# 2. Buat/update/hapus customer di billing
# 3. Pastikan operasi billing tetap sukses
# 4. Cek log Laravel:
tail -f storage/logs/laravel.log | grep RADIUS
# Expected: Warning log, bukan error fatal
```

---

## 15. Troubleshooting

### 15.1 `radius:status` — Connection FAILED

**Gejala:**
```
Connection: FAILED - SQLSTATE[HY000] [2002] Connection refused
```

**Solusi:**
1. Pastikan MySQL berjalan: `sudo systemctl status mysql`
2. Pastikan database `radius` ada: `mysql -e "SHOW DATABASES;" | grep radius`
3. Pastikan credentials benar di `.env`
4. Jika database di server lain, pastikan firewall port 3306 terbuka

### 15.2 `radtest` — Access-Reject

**Gejala:**
```
Received Access-Reject
```

**Solusi:**
1. Cek apakah user ada di `radcheck`:
   ```bash
   mysql -u radius -p radius -e "SELECT * FROM radcheck WHERE username='USERNAME';"
   ```
2. Pastikan attribute `Cleartext-Password` dengan operator `:=`
3. Pastikan modul `sql` aktif di FreeRADIUS:
   ```bash
   ls -la /etc/freeradius/3.0/mods-enabled/sql
   ```
4. Cek log FreeRADIUS:
   ```bash
   sudo tail -f /var/log/freeradius/radius.log
   ```

### 15.3 Mikrotik Tidak Menggunakan RADIUS

**Gejala:** Pelanggan tetap login via PPPoE secret lokal, bukan RADIUS.

**Solusi:**
1. Cek konfigurasi RADIUS di Mikrotik:
   ```
   /radius print
   /ppp aaa print
   ```
2. Pastikan `use-radius=yes` dan `accounting=yes`
3. Pastikan IP FreeRADIUS server dan secret benar
4. Cek firewall Mikrotik tidak memblokir port 1812/1813

### 15.4 Data Tidak Sinkron

**Gejala:** Data di billing dan RADIUS DB berbeda.

**Solusi:**
```bash
# Force sync ulang semua data
php artisan radius:sync --all
```

### 15.5 RADIUS Error Tapi Billing Tetap Jalan

**Ini adalah perilaku yang diharapkan.** Integrasi RADIUS menggunakan **graceful degradation** — semua operasi RADIUS dibungkus try-catch. Jika RADIUS gagal:

- Operasi billing (CRUD customer, isolasi, reopen) tetap berjalan
- Error dicatat di log Laravel
- Tidak ada exception yang muncul ke user

Cek log untuk detail:
```bash
grep "RADIUS" storage/logs/laravel.log | tail -20
```

### 15.6 Session Aktif Tidak Tercatat di radacct

**Solusi:**
1. Pastikan accounting aktif di Mikrotik: `/ppp aaa print` → `accounting=yes`
2. Pastikan `sql` ada di section `accounting` di FreeRADIUS site config
3. Cek interim-update interval: `/ppp aaa print` → `interim-update=5m`

---

## 16. FAQ

### Q: Apakah harus install FreeRADIUS untuk menggunakan billing ini?

**Tidak.** FreeRADIUS adalah fitur opsional. Sistem billing berjalan normal tanpa RADIUS. Set `RADIUS_ENABLED=false` (default) dan abaikan semua konfigurasi RADIUS.

### Q: Apakah data pelanggan existing akan hilang?

**Tidak.** Integrasi RADIUS hanya **menambahkan** sync ke database RADIUS. Data di database billing utama tidak terpengaruh sama sekali.

### Q: Apa bedanya PPPoE via Mikrotik vs via RADIUS?

| Aspek | Mikrotik (PPPoE Secret) | RADIUS |
|-------|------------------------|--------|
| Lokasi credential | Di router Mikrotik | Di database MySQL |
| Skalabilitas | Per-router | Centralized |
| Accounting | Terbatas | Lengkap (session, bandwidth, IP) |
| Multi-router | Harus sync per router | 1 database untuk semua router |
| Failover | Jika router mati, data hilang | Database bisa di-backup/replicate |

### Q: Apakah Mikrotik masih bisa login PPPoE kalau RADIUS down?

**Ya**, jika di Mikrotik PPPoE secret masih ada (dual mode). Mikrotik akan fallback ke local authentication jika RADIUS tidak merespons (tergantung konfigurasi `use-radius`).

### Q: Bagaimana kalau ingin migrasi penuh ke RADIUS (tanpa Mikrotik PPPoE secret)?

Untuk saat ini, sistem **selalu** dual sync. Jika ingin migrasi penuh ke RADIUS-only:
1. Pastikan semua router sudah dikonfigurasi sebagai RADIUS client
2. Hapus PPPoE secret di Mikrotik secara manual
3. Set Mikrotik `/ppp aaa set use-radius=yes` tanpa local fallback

### Q: Database RADIUS harus di server yang sama?

**Tidak harus.** Bisa di server yang sama (recommended untuk simplicity) atau di server terpisah. Cukup set `RADIUS_DB_HOST` ke IP server yang sesuai.

### Q: Berapa sering data di-sync?

Data disinkronkan **secara real-time** melalui Observer dan Jobs:
- Customer dibuat/diubah/dihapus → langsung sync
- Customer diisolir/direopen → langsung sync

Command `radius:sync` hanya perlu dijalankan untuk:
- Instalasi pertama kali
- Recovery setelah database RADIUS di-reset
- Troubleshooting data tidak sinkron

---

---

## 17. Catatan Penting dari Production

Catatan dari pengalaman setup FreeRADIUS 3.0.26 di VPS billing (Maret 2026):

### 17.1 RADIUS Menggunakan UDP, Bukan TCP

- Port 1812/1813 menggunakan **UDP**
- `fsockopen()` atau `telnet` tidak bisa digunakan untuk test koneksi RADIUS
- Gunakan `radtest` atau cek koneksi database RADIUS sebagai pengganti

### 17.2 Dictionary Mikrotik

- **WAJIB** include di `/etc/freeradius/3.0/dictionary`:
  ```
  $INCLUDE /usr/share/freeradius/dictionary.mikrotik
  ```
- Tanpa ini, attribute `Mikrotik-Rate-Limit` dan `Mikrotik-Address-List` akan menyebabkan `Access-Reject`
- Attribute `Mikrotik-Local-Address` **TIDAK ADA** di dictionary — jangan pernah insert ke radreply

### 17.3 Default PPP Profile WAJIB Punya local-address

```
/ppp profile set default local-address=10.170.1.1 remote-address=broadband
```

Tanpa `local-address`, RADIUS user akan gagal mendapat IP dan muncul error:
```
could not determine remote IP address
```

### 17.4 Framed-Pool per Paket

- Setiap paket (`packages` table) punya field `pppoe_pool` (default: `broadband`)
- RADIUS mengirim `Framed-Pool` sesuai paket pelanggan
- Saat isolasi, `Framed-Pool` diubah ke `pool-isolir`
- Pool harus sudah ada di Mikrotik (`/ip pool`)

### 17.5 Isolasi: Jangan Pakai Rate Limit 1k/1k

- Bandwidth 1k/1k **terlalu lambat** untuk memuat halaman apapun
- Pelanggan tidak akan bisa melihat halaman pemberitahuan isolir
- Gunakan method `pool` — pelanggan dapat IP dari `pool-isolir` + NAT redirect ke halaman isolir tanpa batas bandwidth

### 17.6 RADIUS Secret Terenkripsi

- Secret RADIUS disimpan **terenkripsi** di tabel `radius_servers` (menggunakan Laravel `Crypt` facade)
- Akses plaintext via accessor `$radiusServer->decrypted_secret`
- Secret di tabel `nas` (RADIUS DB) disimpan plaintext (standar FreeRADIUS)

### 17.7 Fallback saat VPN Putus

- Mikrotik terhubung ke VPS billing via **VPN** (untuk API Mikrotik, bukan RADIUS)
- Jika VPN putus: **RADIUS tetap jalan** (selama ada routing ke VPS via IP publik)
- PPPoE secret lokal di Mikrotik berfungsi sebagai fallback jika RADIUS unreachable
- Billing API (port 8728) yang tidak bisa diakses saat VPN putus

### 17.8 Menambah Router Baru

Checklist saat menambah router baru ke RADIUS:

1. **Billing:** Buat router + assign ke Radius Server → NAS otomatis tersync (RouterObserver)
2. **Mikrotik:**
   ```
   /radius add service=ppp address=<IP_PUBLIK_VPS> secret=<SECRET>
   /ppp aaa set use-radius=yes accounting=yes interim-update=5m
   /ppp profile set default local-address=<GATEWAY_IP> remote-address=<POOL_NORMAL>
   ```
3. **FreeRADIUS:** Restart service (`sudo systemctl restart freeradius`) agar NAS baru terbaca
4. **Firewall VPS:** Pastikan port 1812-1813/UDP terbuka untuk IP/subnet router baru

### 17.9 Verifikasi RADIUS Aktif

PPPoE session yang menggunakan RADIUS ditandai dengan flag **`R`** di Mikrotik:

```
/ppp active print
# Flags: R - RADIUS
#   0 R <...> username@ind.net  pppoe  ...
```

Jika tidak ada flag `R`, berarti masih menggunakan PPPoE secret lokal.

### 17.10 Scheduled Tasks RADIUS

| Jadwal | Command | Fungsi |
|--------|---------|--------|
| Minggu 04:30 | `radius:cleanup` | Hapus radacct & radpostauth > 3 bulan |

---

> **Dokumen ini dibuat untuk ISP Billing System Java Indonusa v1.0**
> Terakhir diupdate: 19 Maret 2026
