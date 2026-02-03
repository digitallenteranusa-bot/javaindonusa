# Panduan Instalasi VPN Server

Panduan lengkap untuk mengaktifkan fitur VPN Server Management pada sistem billing ISP.

## Arsitektur

```
VPS (IP Public)                          Mikrotik (Tanpa IP Public)
┌─────────────────────┐                  ┌─────────────────────┐
│  Billing System     │                  │  Router Cabang      │
│  + VPN Server       │◄────VPN Tunnel───│  (VPN Client)       │
│  10.200.1.1         │                  │  10.200.1.x         │
└─────────────────────┘                  └─────────────────────┘
```

**Keuntungan:**
- Mikrotik tidak perlu IP Public
- Billing bisa akses API Mikrotik via VPN
- Koneksi aman dan terenkripsi

## Prasyarat

- Ubuntu 20.04/22.04 LTS (di VPS)
- PHP 8.2+, Laravel 11
- Akses root/sudo
- Port terbuka di firewall:
  - OpenVPN: 1194/UDP (atau custom)
  - WireGuard: 51820/UDP (atau custom)

---

## Langkah 1: Update Project dari GitHub

```bash
cd /path/to/project
git pull origin main
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Langkah 2: Install OpenVPN & Easy-RSA

```bash
# Update package list
sudo apt update

# Install OpenVPN dan Easy-RSA
sudo apt install -y openvpn easy-rsa

# Verifikasi instalasi
openvpn --version
ls /usr/share/easy-rsa/
```

---

## Langkah 3: Install WireGuard (Opsional, untuk Mikrotik v7+)

```bash
# Install WireGuard
sudo apt install -y wireguard

# Verifikasi instalasi
wg --version

# Enable IP forwarding
echo "net.ipv4.ip_forward=1" | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

---

## Langkah 4: Konfigurasi Sudoers

Web server (www-data) perlu akses sudo untuk menjalankan perintah VPN.

```bash
# Buat file sudoers
sudo visudo -f /etc/sudoers.d/vpn-billing
```

Isi dengan:

```
# OpenVPN
www-data ALL=(ALL) NOPASSWD: /usr/sbin/openvpn
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start openvpn*
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop openvpn*
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart openvpn*
www-data ALL=(ALL) NOPASSWD: /bin/systemctl status openvpn*
www-data ALL=(ALL) NOPASSWD: /bin/systemctl enable openvpn*
www-data ALL=(ALL) NOPASSWD: /bin/systemctl is-active openvpn*

# Easy-RSA
www-data ALL=(ALL) NOPASSWD: /etc/openvpn/easy-rsa/easyrsa
www-data ALL=(ALL) NOPASSWD: /usr/share/easy-rsa/easyrsa

# WireGuard
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg-quick

# File operations
www-data ALL=(ALL) NOPASSWD: /usr/bin/tee /etc/openvpn/*
www-data ALL=(ALL) NOPASSWD: /usr/bin/tee /etc/wireguard/*
www-data ALL=(ALL) NOPASSWD: /bin/mkdir -p /etc/openvpn/*
www-data ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/log/openvpn
www-data ALL=(ALL) NOPASSWD: /bin/cp *
www-data ALL=(ALL) NOPASSWD: /bin/chmod *
www-data ALL=(ALL) NOPASSWD: /bin/cat /etc/openvpn/*
www-data ALL=(ALL) NOPASSWD: /bin/cat /var/log/openvpn/*
```

```bash
# Set permission
sudo chmod 440 /etc/sudoers.d/vpn-billing

# Validasi
sudo visudo -c
```

---

## Langkah 5: Buka Firewall

### UFW (Ubuntu Firewall)

```bash
# OpenVPN port
sudo ufw allow 1194/udp comment "OpenVPN"

# WireGuard port
sudo ufw allow 51820/udp comment "WireGuard"

# PENTING: Izinkan traffic dari VPN subnet
sudo ufw allow from 10.200.1.0/24 comment "VPN Subnet"

# Izinkan traffic pada interface VPN
sudo ufw allow in on wg0
sudo ufw allow in on tun0

# Reload
sudo ufw reload
sudo ufw status
```

### iptables (Tambahan)

```bash
# Accept traffic dari interface VPN
sudo iptables -A INPUT -i wg0 -j ACCEPT
sudo iptables -A INPUT -i tun0 -j ACCEPT

# Forward traffic VPN
sudo iptables -A FORWARD -i wg0 -j ACCEPT
sudo iptables -A FORWARD -o wg0 -j ACCEPT
sudo iptables -A FORWARD -i tun0 -j ACCEPT
sudo iptables -A FORWARD -o tun0 -j ACCEPT

# NAT untuk VPN clients (ganti eth0 dengan interface publik Anda)
sudo iptables -t nat -A POSTROUTING -s 10.200.1.0/24 -o eth0 -j MASQUERADE

# Simpan rules
sudo apt install iptables-persistent
sudo netfilter-persistent save
```

### Sysctl (Reverse Path Filter)

Jika ping tidak bekerja meskipun handshake berhasil, disable rp_filter:

```bash
# Disable reverse path filtering untuk VPN
sudo sysctl -w net.ipv4.conf.all.rp_filter=0
sudo sysctl -w net.ipv4.conf.wg0.rp_filter=0
sudo sysctl -w net.ipv4.conf.tun0.rp_filter=0

# Buat permanen
cat << EOF | sudo tee -a /etc/sysctl.conf
# VPN - disable reverse path filter
net.ipv4.conf.all.rp_filter=0
net.ipv4.conf.default.rp_filter=0
net.ipv4.conf.wg0.rp_filter=0
net.ipv4.conf.tun0.rp_filter=0
EOF

sudo sysctl -p
```

---

## Langkah 6: Setup via Web UI

1. Login ke Admin Panel
2. Buka menu **VPN Server** di sidebar
3. Pergi ke **Settings**

### 6.1 Konfigurasi Umum

| Setting | Contoh | Keterangan |
|---------|--------|------------|
| Public Endpoint | `vpn.example.com` atau `123.45.67.89` | IP/Domain publik VPS |
| VPN Network | `10.200.1.0/24` | Subnet untuk VPN tunnel |
| OpenVPN Port | `1194` | Port OpenVPN |
| Protocol | `UDP` | UDP lebih cepat |
| WireGuard Port | `51820` | Port WireGuard |

Klik **Simpan Pengaturan**.

### 6.2 Setup OpenVPN (Untuk Mikrotik v6/v7)

Jalankan langkah-langkah berikut secara berurutan:

1. **Initialize PKI** - Setup struktur direktori PKI
2. **Generate CA Certificate** - Buat Certificate Authority
3. **Generate Server Certificate** - Buat sertifikat server
4. **Generate DH Parameters** - Diffie-Hellman (butuh waktu ~1-5 menit)
5. **Generate TLS Auth Key** - Extra security layer

Setelah semua selesai (hijau), OpenVPN siap digunakan.

### 6.3 Setup WireGuard (Untuk Mikrotik v7+)

1. **Generate Server Keys** - Buat keypair untuk server

Public key akan ditampilkan setelah generate.

---

## Langkah 7: Start VPN Service

### Via Web UI

Di halaman VPN Server, klik tombol **Start** pada service yang diinginkan.

### Via Terminal (Manual)

```bash
# OpenVPN
sudo systemctl start openvpn-server@server
sudo systemctl enable openvpn-server@server
sudo systemctl status openvpn-server@server

# WireGuard
sudo wg-quick up wg0
sudo systemctl enable wg-quick@wg0
```

---

## Langkah 8: Tambah VPN Client

1. Di halaman **VPN Server**, klik **Tambah Client**
2. Isi form:
   - **Nama**: Identifikasi unik (contoh: `Mikrotik-Cabang-A`)
   - **Protocol**: OpenVPN atau WireGuard
   - **Router**: Link ke router di sistem (opsional)
   - **LAN Subnet**: Subnet di belakang Mikrotik (opsional, contoh: `192.168.1.0/24`)
3. Klik **Simpan**

Sistem akan otomatis:
- Generate certificate/keys
- Assign IP VPN (10.200.1.x)
- Buat Mikrotik script

---

## Langkah 9: Setup di Mikrotik

### OpenVPN (Mikrotik v6/v7)

1. Download file dari halaman detail client:
   - **ca.crt** - CA Certificate
   - **client-xxx.crt** - Client Certificate
   - **client-xxx.key** - Client Private Key
   - Atau download **.ovpn** (all-in-one)

2. Upload ke Mikrotik via WinBox/FTP

3. Jalankan script atau manual:

```rsc
# Import certificates
/certificate import file-name=ca.crt passphrase=""
/certificate import file-name=client-xxx.crt passphrase=""
/certificate import file-name=client-xxx.key passphrase=""

# Create OVPN client
/interface ovpn-client add name=ovpn-billing \
    connect-to=YOUR_VPS_IP port=1194 \
    mode=ip protocol=udp \
    user=client-xxx \
    certificate=client-xxx.crt_0 \
    cipher=aes256-cbc auth=sha256 \
    add-default-route=no

# Firewall
/ip firewall filter add chain=input src-address=10.200.1.0/24 action=accept \
    comment="Allow VPN Billing" place-before=0

# Enable
/interface ovpn-client enable ovpn-billing
```

### WireGuard (Mikrotik v7+ Only)

Download Mikrotik script (.rsc) dan jalankan di terminal, atau manual:

```rsc
# Create WireGuard interface
/interface wireguard add name=wg-billing \
    listen-port=13231 mtu=1420 \
    private-key="CLIENT_PRIVATE_KEY_HERE"

# Assign IP
/ip address add address=10.200.1.x/24 interface=wg-billing

# Add server as peer
/interface wireguard peers add interface=wg-billing \
    public-key="SERVER_PUBLIC_KEY_HERE" \
    endpoint-address=YOUR_VPS_IP \
    endpoint-port=51820 \
    allowed-address=10.200.1.0/24 \
    persistent-keepalive=25

# Firewall
/ip firewall filter add chain=input src-address=10.200.1.0/24 action=accept \
    comment="Allow WireGuard VPN" place-before=0

# Enable
/interface wireguard enable wg-billing
```

---

## Langkah 10: Verifikasi Koneksi

### Di Mikrotik

```rsc
# Cek interface
/interface print

# Cek status (OpenVPN)
/interface ovpn-client monitor ovpn-billing

# Cek status (WireGuard)
/interface wireguard peers print stats

# Ping server
/ping 10.200.1.1
```

### Di VPS

```bash
# Cek OpenVPN status
sudo systemctl status openvpn-server@server
cat /var/log/openvpn/openvpn-status.log

# Cek WireGuard status
sudo wg show

# Ping client
ping 10.200.1.x
```

### Di Web UI

Klik **Refresh Status** di halaman VPN Server untuk update status koneksi.

---

## Troubleshooting

### OpenVPN Tidak Start

```bash
# Cek log
sudo journalctl -u openvpn-server@server -n 50

# Cek config
sudo openvpn --config /etc/openvpn/server/server.conf --verb 4
```

### WireGuard Tidak Connect

```bash
# Cek interface
sudo wg show

# Cek log
sudo dmesg | grep wireguard

# Restart interface
sudo wg-quick down wg0
sudo wg-quick up wg0
```

### Handshake OK Tapi Tidak Bisa Ping

Jika `sudo wg show` menunjukkan handshake berhasil tapi ping timeout:

```bash
# 1. Cek apakah paket ICMP sampai
sudo tcpdump -i wg0 -n icmp

# 2. Jika paket sampai tapi tidak ada reply, cek routing
ip route get 10.200.1.2

# 3. Jika route salah (ke eth0 bukan wg0), cek konflik route
ip route show | grep 10.200.1

# 4. Jika ada konflik OpenVPN & WireGuard (keduanya pakai 10.200.1.0/24):
#    - Stop salah satu service
#    - Atau gunakan subnet berbeda

# 5. Disable rp_filter
sudo sysctl -w net.ipv4.conf.all.rp_filter=0
sudo sysctl -w net.ipv4.conf.wg0.rp_filter=0

# 6. Pastikan firewall mengizinkan VPN traffic
sudo ufw allow from 10.200.1.0/24
sudo iptables -A INPUT -i wg0 -j ACCEPT
```

### Mikrotik Tidak Bisa Connect

1. Pastikan port firewall terbuka di VPS
2. Cek certificate sudah di-import dengan benar
3. Pastikan waktu di Mikrotik sinkron (NTP)
4. Cek routing table di Mikrotik
5. Cek peer configuration di Mikrotik:
   ```
   /interface wireguard peers print detail
   ```

### Permission Denied

```bash
# Cek sudoers
sudo -l -U www-data

# Test manual
sudo -u www-data sudo wg show
```

### Interface wg0 Tidak Ada

```bash
# Start WireGuard
sudo wg-quick up wg0

# Enable auto-start
sudo systemctl enable wg-quick@wg0
```

---

## Perbandingan OpenVPN vs WireGuard

| Fitur | OpenVPN | WireGuard |
|-------|---------|-----------|
| Mikrotik Support | v6 & v7 | v7+ only |
| Speed | Moderate | Very Fast |
| Setup Complexity | Complex (PKI) | Simple (Keys) |
| Reconnect | Slower | Instant |
| CPU Usage | Higher | Lower |
| Firewall Bypass | TCP mode OK | UDP only |

**Rekomendasi:**
- Mikrotik v6: Gunakan **OpenVPN**
- Mikrotik v7+: Gunakan **WireGuard** (lebih cepat)

**PENTING:** Jika menggunakan OpenVPN dan WireGuard bersamaan, gunakan subnet yang berbeda untuk menghindari konflik routing:
- OpenVPN: `10.200.1.0/24`
- WireGuard: `10.200.2.0/24`

---

## Maintenance

### Backup PKI (OpenVPN)

```bash
sudo tar -czvf openvpn-pki-backup.tar.gz /etc/openvpn/easy-rsa/pki
```

### Rotate Keys (WireGuard)

1. Di Web UI, buka detail client
2. Klik **Regenerate Config**
3. Download script baru
4. Update di Mikrotik

### Monitor Traffic

Di halaman VPN Server, klik **Refresh Status** untuk update statistik traffic (RX/TX bytes).

---

## Support

Jika ada masalah, cek:
1. Log Laravel: `storage/logs/laravel.log`
2. Log OpenVPN: `/var/log/openvpn/openvpn.log`
3. Systemd journal: `journalctl -u openvpn-server@server`
