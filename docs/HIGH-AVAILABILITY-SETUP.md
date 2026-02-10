# High Availability Setup (VPS + Proxmox)

## Tujuan
Menjalankan aplikasi di 2 server (VPS + Proxmox lokal) dengan sinkronisasi agar jika salah satu mati, yang lain tetap berjalan.

## Arsitektur

```
┌─────────────────┐         ┌─────────────────┐
│      VPS        │◄───────►│    Proxmox      │
│    (Master)     │  Sync   │    (Slave)      │
├─────────────────┤         ├─────────────────┤
│ - Laravel App   │         │ - Laravel App   │
│ - MySQL Master  │         │ - MySQL Slave   │
│ - Redis         │         │ - Redis         │
│ - Storage       │         │ - Storage       │
└─────────────────┘         └─────────────────┘
        │                           │
        └───────────┬───────────────┘
                    │
            ┌───────▼───────┐
            │ Load Balancer │ (Optional)
            │ atau DNS      │
            └───────────────┘
                    │
            ┌───────▼───────┐
            │    Users      │
            └───────────────┘
```

---

## Opsi 1: MySQL Master-Slave Replication (Rekomendasi)

### Kelebihan
- Mudah setup
- Slave bisa digunakan untuk read query (load balancing)
- Jika master mati, slave bisa di-promote jadi master

### Kekurangan
- Hanya master yang bisa write
- Perlu manual promote jika master mati

### Konfigurasi Master (VPS)

**1. Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:**
```ini
[mysqld]
server-id = 1
log_bin = /var/log/mysql/mysql-bin.log
binlog_do_db = javaindonusa
bind-address = 0.0.0.0
```

**2. Restart MySQL:**
```bash
sudo systemctl restart mysql
```

**3. Buat user replication:**
```sql
CREATE USER 'replicator'@'%' IDENTIFIED BY 'password_kuat';
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'%';
FLUSH PRIVILEGES;
SHOW MASTER STATUS;
```
Catat `File` dan `Position` dari output.

### Konfigurasi Slave (Proxmox)

**1. Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:**
```ini
[mysqld]
server-id = 2
relay-log = /var/log/mysql/mysql-relay-bin.log
log_bin = /var/log/mysql/mysql-bin.log
binlog_do_db = javaindonusa
read_only = 1
```

**2. Restart MySQL:**
```bash
sudo systemctl restart mysql
```

**3. Konfigurasi slave:**
```sql
CHANGE MASTER TO
    MASTER_HOST='ip_vps',
    MASTER_USER='replicator',
    MASTER_PASSWORD='password_kuat',
    MASTER_LOG_FILE='mysql-bin.000001',  -- dari SHOW MASTER STATUS
    MASTER_LOG_POS=123;                   -- dari SHOW MASTER STATUS

START SLAVE;
SHOW SLAVE STATUS\G
```

### Cek Status Replication
```sql
SHOW SLAVE STATUS\G
```
Pastikan:
- `Slave_IO_Running: Yes`
- `Slave_SQL_Running: Yes`

---

## Opsi 2: MySQL Galera Cluster (Multi-Master)

### Kelebihan
- Semua node bisa read/write
- Sync real-time
- Auto failover

### Kekurangan
- Lebih kompleks
- Butuh minimal 3 node untuk quorum
- Latency lebih tinggi untuk write

### Instalasi (Ubuntu/Debian)
```bash
sudo apt install mariadb-server galera-4
```

### Konfigurasi setiap node `/etc/mysql/mariadb.conf.d/60-galera.cnf`:
```ini
[galera]
wsrep_on = ON
wsrep_provider = /usr/lib/galera/libgalera_smm.so
wsrep_cluster_address = "gcomm://ip_vps,ip_proxmox"
wsrep_cluster_name = "javaindonusa_cluster"
wsrep_node_address = "ip_node_ini"
wsrep_node_name = "node1"
wsrep_sst_method = rsync
binlog_format = ROW
```

---

## Sync File Storage dengan Rsync

### Setup Rsync + SSH Key

**1. Di Master (VPS), generate SSH key:**
```bash
ssh-keygen -t rsa -b 4096
ssh-copy-id user@ip_proxmox
```

**2. Buat script sync `/opt/scripts/sync-storage.sh`:**
```bash
#!/bin/bash
rsync -avz --delete \
    /var/www/javaindonusa/storage/app/public/ \
    user@ip_proxmox:/var/www/javaindonusa/storage/app/public/
```

**3. Tambah ke crontab:**
```bash
crontab -e
```
```cron
*/5 * * * * /opt/scripts/sync-storage.sh >> /var/log/rsync-storage.log 2>&1
```

### Sync 2 Arah (Bidirectional)
Gunakan `lsyncd` untuk real-time sync:
```bash
sudo apt install lsyncd
```

Konfigurasi `/etc/lsyncd/lsyncd.conf.lua`:
```lua
sync {
    default.rsyncssh,
    source = "/var/www/javaindonusa/storage/app/public",
    host = "user@ip_proxmox",
    targetdir = "/var/www/javaindonusa/storage/app/public",
}
```

---

## Load Balancer dengan Nginx

### Konfigurasi `/etc/nginx/sites-available/javaindonusa-lb`:
```nginx
upstream javaindonusa_backend {
    server ip_vps:80 weight=5;
    server ip_proxmox:80 weight=3 backup;

    # Health check
    keepalive 32;
}

server {
    listen 80;
    server_name javaindonusa.my.id;

    location / {
        proxy_pass http://javaindonusa_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Failover timeout
        proxy_connect_timeout 5s;
        proxy_read_timeout 60s;
    }
}
```

---

## DNS Failover (Alternatif Load Balancer)

Gunakan layanan DNS dengan health check:
- **Cloudflare Load Balancing**
- **AWS Route 53 Health Checks**
- **DigitalOcean Load Balancer**

Konfigurasi:
1. Tambahkan kedua IP (VPS & Proxmox) sebagai origin
2. Set health check endpoint: `/health` atau `/api/ping`
3. DNS akan otomatis redirect ke server yang hidup

### Buat Health Check Endpoint

**routes/web.php:**
```php
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'ok'], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error'], 500);
    }
});
```

---

## Failover Manual (Jika Master Mati)

### 1. Promote Slave jadi Master

**Di Slave (Proxmox):**
```sql
STOP SLAVE;
RESET SLAVE ALL;
SET GLOBAL read_only = OFF;
```

### 2. Update .env Aplikasi
Ubah `DB_HOST` ke IP Proxmox

### 3. Update DNS
Arahkan domain ke IP Proxmox

### 4. Setelah Master Kembali Online
- Setup ulang sebagai Slave
- Atau sync data dari Proxmox ke VPS

---

## Checklist Implementasi

- [ ] Setup MySQL Replication Master-Slave
- [ ] Test replication dengan insert data
- [ ] Setup Rsync untuk storage
- [ ] Buat script failover
- [ ] Setup health check endpoint
- [ ] Test failover scenario
- [ ] Dokumentasi IP dan credential
- [ ] Setup monitoring (optional)

---

## Catatan Penting

1. **Backup rutin** - Meskipun ada replication, tetap backup berkala
2. **Test failover** - Uji coba matikan server secara berkala
3. **Monitor lag** - Cek `Seconds_Behind_Master` di slave
4. **Firewall** - Buka port MySQL (3306) hanya untuk IP trusted
5. **SSL/TLS** - Gunakan enkripsi untuk replication jika lewat internet
