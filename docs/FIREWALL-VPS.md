# Firewall VPS

Panduan keamanan server untuk melindungi dari serangan brute force SSH, web scanning, dan intrusi lainnya.

## fail2ban

### Instalasi

```bash
apt install fail2ban -y
```

### Konfigurasi

Buat file `/etc/fail2ban/jail.local`:

```ini
[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
findtime = 300
bantime = 86400
ignoreip = 127.0.0.1/8 ::1 IP_ADMIN_1 IP_ADMIN_2
```

- `maxretry = 3` : Ban setelah 3x gagal login
- `findtime = 300` : Dalam rentang 5 menit
- `bantime = 86400` : Ban selama 24 jam
- `ignoreip` : IP yang tidak akan pernah di-ban (isi dengan IP admin)

### Aktivasi

```bash
systemctl enable fail2ban
systemctl restart fail2ban
```

### Perintah Umum

```bash
# Cek status jail sshd
fail2ban-client status sshd

# Unban IP (jika IP admin terkunci)
fail2ban-client set sshd unbanip IP_ADDRESS

# Test konfigurasi
fail2ban-client -t

# Restart setelah ubah config
systemctl restart fail2ban
```

> **Catatan**: Jika IP admin terkunci dan tidak bisa SSH, gunakan **Console/VNC** dari panel hosting (Contabo/DigitalOcean/dll) untuk unban.

---

## Monitoring Keamanan

### 1. Cek Serangan SSH Brute Force

```bash
# Login gagal terakhir
grep "Failed password" /var/log/auth.log | tail -20

# Top 10 IP penyerang
grep "Failed password" /var/log/auth.log | awk '{print $(NF-3)}' | sort | uniq -c | sort -rn | head -10
```

### 2. Cek Login Berhasil

```bash
# Login berhasil terakhir
grep "Accepted" /var/log/auth.log | tail -20

# Siapa yang sedang login sekarang
who

# Riwayat login
last -20
```

### 3. Cek Koneksi & Proses

```bash
# Koneksi aktif (waspadai IP asing)
ss -tunap | grep ESTAB

# Port yang listening
ss -tlnp

# Proses yang berjalan
ps auxf

# Top CPU/RAM usage
top -bn1 | head -20
```

### 4. Cek Serangan Web

```bash
# Request mencurigakan (SQL injection, path traversal, dll)
grep -iE "(union|select|eval|base64|/etc/passwd|\.\./)" /var/log/nginx/access.log | tail -20

# Top 10 IP paling banyak request
awk '{print $1}' /var/log/nginx/access.log | sort | uniq -c | sort -rn | head -10

# Request yang error (4xx/5xx)
awk '$9 ~ /^(4|5)/ {print $1, $7, $9}' /var/log/nginx/access.log | sort | uniq -c | sort -rn | head -20
```

### 5. Cek File Mencurigakan

```bash
# File berubah dalam 24 jam di /etc
find /etc -mtime -1 -type f

# File PHP baru di web root
find /var/www -mtime -1 -type f -name "*.php"

# File dengan permission 777 (bahaya)
find /var/www -perm 777 -type f
```

### 6. Scan Rootkit

```bash
# rkhunter
apt install rkhunter -y
rkhunter --check --skip-keypress

# chkrootkit
apt install chkrootkit -y
chkrootkit
```

---

## Rekomendasi Keamanan Lanjutan

### 1. Disable Root Password Login (Gunakan SSH Key)

```bash
# Di PC lokal: generate SSH key
ssh-keygen -t ed25519

# Copy key ke server
ssh-copy-id root@IP_SERVER

# Di server: disable password login
sed -i 's/#PermitRootLogin yes/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config
systemctl restart sshd
```

### 2. Ganti Port SSH

```bash
# Edit config
nano /etc/ssh/sshd_config
# Ubah: Port 22 → Port 2222 (atau port lain)

systemctl restart sshd

# Jangan lupa update fail2ban jail.local
# port = 2222
```

### 3. UFW Firewall (Opsional)

```bash
ufw allow 22/tcp    # SSH (atau port custom)
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw allow 1194/tcp  # OpenVPN
ufw allow 51820/udp # WireGuard
ufw enable
```
