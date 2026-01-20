# Panduan Setup VPN untuk Cloud Deployment

Dokumen ini menjelaskan cara mengkonfigurasi VPN agar aplikasi billing yang di-deploy di cloud dapat terhubung dengan perangkat di jaringan lokal (Mikrotik Router dan GenieACS).

## Daftar Isi

1. [Arsitektur Jaringan](#arsitektur-jaringan)
2. [Opsi VPN](#opsi-vpn)
3. [Setup VPN Server di Mikrotik](#setup-vpn-server-di-mikrotik)
4. [Setup VPN Client di Cloud Server](#setup-vpn-client-di-cloud-server)
5. [Konfigurasi Aplikasi](#konfigurasi-aplikasi)
6. [Testing Konektivitas](#testing-konektivitas)
7. [Troubleshooting](#troubleshooting)

---

## Arsitektur Jaringan

### Skenario Deployment

```
┌─────────────────────────────────────────────────────────────────────┐
│                           CLOUD (VPS)                                │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │           ISP Billing System (Laravel)                       │    │
│  │           - Web Server (Nginx/Apache)                        │    │
│  │           - MySQL Database                                   │    │
│  │           - Redis Queue                                      │    │
│  │           - VPN Client                                       │    │
│  └─────────────────────────────────────────────────────────────┘    │
│                              │                                       │
│                         VPN Tunnel                                   │
│                              │                                       │
└──────────────────────────────┼───────────────────────────────────────┘
                               │
                          [Internet]
                               │
┌──────────────────────────────┼───────────────────────────────────────┐
│                         JARINGAN LOKAL ISP                           │
│                              │                                       │
│                    ┌─────────┴─────────┐                            │
│                    │   Mikrotik Router  │                            │
│                    │   (VPN Server)     │                            │
│                    │   192.168.88.1     │                            │
│                    └─────────┬─────────┘                            │
│                              │                                       │
│           ┌──────────────────┼──────────────────┐                   │
│           │                  │                  │                   │
│    ┌──────┴──────┐    ┌──────┴──────┐    ┌──────┴──────┐          │
│    │  GenieACS    │    │  PPPoE      │    │  Pelanggan  │          │
│    │  Server      │    │  Server     │    │  ONU/ONT    │          │
│    │  192.168.88.10│   │             │    │             │          │
│    └─────────────┘    └─────────────┘    └─────────────┘          │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

### Kebutuhan Konektivitas

| Komponen | IP Lokal | Port | Keterangan |
|----------|----------|------|------------|
| Mikrotik Router | 192.168.88.1 | 8728 (API) | RouterOS API untuk isolir/reopen |
| GenieACS NBI | 192.168.88.10 | 7557 | REST API untuk device management |
| GenieACS FS | 192.168.88.10 | 7567 | File Server untuk firmware |

---

## Opsi VPN

### Perbandingan Protokol VPN

| Protokol | Kelebihan | Kekurangan | Rekomendasi |
|----------|-----------|------------|-------------|
| **L2TP/IPSec** | Mudah setup di Mikrotik, stabil | Lebih lambat | Default choice |
| **PPTP** | Sangat mudah, cepat | Kurang aman | Tidak disarankan |
| **OpenVPN** | Sangat aman, fleksibel | Setup lebih kompleks | Untuk keamanan tinggi |
| **WireGuard** | Cepat, modern, aman | Perlu RouterOS v7+ | Untuk performa terbaik |
| **SSTP** | Menembus firewall | Setup kompleks | Jika L2TP diblokir |

**Rekomendasi:** Gunakan **L2TP/IPSec** untuk kemudahan atau **WireGuard** untuk performa terbaik.

---

## Setup VPN Server di Mikrotik

### Opsi A: L2TP/IPSec VPN Server

#### 1. Buat IP Pool untuk VPN Client

```routeros
/ip pool
add name=vpn-pool ranges=10.10.10.2-10.10.10.254
```

#### 2. Buat PPP Profile

```routeros
/ppp profile
add name=vpn-profile local-address=10.10.10.1 remote-address=vpn-pool \
    dns-server=8.8.8.8,8.8.4.4 use-encryption=yes
```

#### 3. Aktifkan L2TP Server

```routeros
/interface l2tp-server server
set enabled=yes default-profile=vpn-profile authentication=mschap2 \
    use-ipsec=required ipsec-secret=YourIPSecSecret123
```

#### 4. Buat User VPN

```routeros
/ppp secret
add name=billing-cloud password=SecurePassword123 profile=vpn-profile \
    service=l2tp
```

#### 5. Konfigurasi Firewall

```routeros
# Izinkan koneksi L2TP & IPSec
/ip firewall filter
add chain=input protocol=udp dst-port=500,1701,4500 action=accept \
    comment="Allow L2TP/IPSec VPN"
add chain=input protocol=ipsec-esp action=accept comment="Allow IPSec ESP"

# Izinkan traffic dari VPN ke jaringan lokal
add chain=forward src-address=10.10.10.0/24 dst-address=192.168.88.0/24 \
    action=accept comment="VPN to LAN"
add chain=forward src-address=192.168.88.0/24 dst-address=10.10.10.0/24 \
    action=accept comment="LAN to VPN"
```

#### 6. NAT Masquerade (opsional)

```routeros
/ip firewall nat
add chain=srcnat src-address=10.10.10.0/24 out-interface=!<vpn-interface> \
    action=masquerade comment="NAT VPN clients"
```

---

### Opsi B: WireGuard VPN Server (RouterOS v7+)

#### 1. Buat Interface WireGuard

```routeros
/interface wireguard
add name=wg-vpn listen-port=51820 private-key=auto
```

#### 2. Lihat Public Key Server

```routeros
/interface wireguard print
# Catat public-key untuk konfigurasi client
```

#### 3. Tambah IP Address

```routeros
/ip address
add address=10.10.10.1/24 interface=wg-vpn
```

#### 4. Tambah Peer (Cloud Server)

```routeros
/interface wireguard peers
add interface=wg-vpn public-key="CLIENT_PUBLIC_KEY_HERE" \
    allowed-address=10.10.10.2/32 comment="Billing Cloud Server"
```

#### 5. Firewall Rules

```routeros
/ip firewall filter
add chain=input protocol=udp dst-port=51820 action=accept \
    comment="Allow WireGuard"
add chain=forward src-address=10.10.10.0/24 dst-address=192.168.88.0/24 \
    action=accept comment="WireGuard to LAN"
add chain=forward src-address=192.168.88.0/24 dst-address=10.10.10.0/24 \
    action=accept comment="LAN to WireGuard"
```

---

## Setup VPN Client di Cloud Server

### Opsi A: L2TP/IPSec Client (Ubuntu/Debian)

#### 1. Install Package

```bash
sudo apt update
sudo apt install -y strongswan xl2tpd
```

#### 2. Konfigurasi IPSec

```bash
sudo nano /etc/ipsec.conf
```

```ini
config setup
    charondebug="ike 2, knl 2, cfg 2, net 2, esp 2, dmn 2, mgr 2"

conn billing-vpn
    authby=secret
    auto=start
    keyexchange=ikev1
    type=transport
    left=%defaultroute
    leftprotoport=17/1701
    right=YOUR_MIKROTIK_PUBLIC_IP
    rightprotoport=17/1701
    ike=aes256-sha1-modp1024!
    esp=aes256-sha1!
```

#### 3. Konfigurasi IPSec Secret

```bash
sudo nano /etc/ipsec.secrets
```

```
: PSK "YourIPSecSecret123"
```

#### 4. Konfigurasi xl2tpd

```bash
sudo nano /etc/xl2tpd/xl2tpd.conf
```

```ini
[lac billing-vpn]
lns = YOUR_MIKROTIK_PUBLIC_IP
ppp debug = yes
pppoptfile = /etc/ppp/options.l2tpd.client
length bit = yes
```

#### 5. Konfigurasi PPP Options

```bash
sudo nano /etc/ppp/options.l2tpd.client
```

```
ipcp-accept-local
ipcp-accept-remote
refuse-eap
require-mschap-v2
noccp
noauth
mtu 1280
mru 1280
noipdefault
defaultroute
usepeerdns
connect-delay 5000
name billing-cloud
password SecurePassword123
```

#### 6. Buat Script Koneksi

```bash
sudo nano /usr/local/bin/vpn-connect.sh
```

```bash
#!/bin/bash

# Start IPSec
sudo ipsec restart
sleep 2

# Start xl2tpd
sudo systemctl restart xl2tpd
sleep 2

# Establish L2TP connection
sudo bash -c 'echo "c billing-vpn" > /var/run/xl2tpd/l2tp-control'
sleep 5

# Add route to local network via VPN
sudo ip route add 192.168.88.0/24 dev ppp0
```

```bash
sudo chmod +x /usr/local/bin/vpn-connect.sh
```

#### 7. Buat Systemd Service

```bash
sudo nano /etc/systemd/system/billing-vpn.service
```

```ini
[Unit]
Description=Billing VPN Connection
After=network.target

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/usr/local/bin/vpn-connect.sh
ExecStop=/usr/bin/ipsec stop

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable billing-vpn
sudo systemctl start billing-vpn
```

---

### Opsi B: WireGuard Client (Ubuntu/Debian)

#### 1. Install WireGuard

```bash
sudo apt update
sudo apt install -y wireguard
```

#### 2. Generate Keys

```bash
wg genkey | sudo tee /etc/wireguard/private.key
sudo chmod 600 /etc/wireguard/private.key
sudo cat /etc/wireguard/private.key | wg pubkey | sudo tee /etc/wireguard/public.key
```

#### 3. Konfigurasi WireGuard

```bash
sudo nano /etc/wireguard/wg0.conf
```

```ini
[Interface]
PrivateKey = YOUR_PRIVATE_KEY_HERE
Address = 10.10.10.2/24

[Peer]
PublicKey = MIKROTIK_PUBLIC_KEY_HERE
AllowedIPs = 10.10.10.0/24, 192.168.88.0/24
Endpoint = YOUR_MIKROTIK_PUBLIC_IP:51820
PersistentKeepalive = 25
```

#### 4. Start WireGuard

```bash
# Start manual
sudo wg-quick up wg0

# Enable auto-start
sudo systemctl enable wg-quick@wg0
sudo systemctl start wg-quick@wg0
```

#### 5. Verifikasi

```bash
sudo wg show
ip route
```

---

## Konfigurasi Aplikasi

Setelah VPN terhubung, update file `.env` di aplikasi billing:

### Konfigurasi Mikrotik

```env
# Gunakan IP lokal Mikrotik (dapat diakses via VPN)
MIKROTIK_HOST=192.168.88.1
MIKROTIK_PORT=8728
MIKROTIK_USER=admin
MIKROTIK_PASS=yourpassword
```

### Konfigurasi GenieACS

```env
# Gunakan IP lokal GenieACS Server
GENIEACS_NBI_URL=http://192.168.88.10:7557
GENIEACS_FS_URL=http://192.168.88.10:7567
GENIEACS_TIMEOUT=30
```

---

## Testing Konektivitas

### 1. Test VPN Connection

```bash
# Cek interface VPN aktif
ip addr show | grep -E "(ppp0|wg0)"

# Cek routing
ip route | grep 192.168.88
```

### 2. Test Ping ke Jaringan Lokal

```bash
# Ping Mikrotik
ping -c 4 192.168.88.1

# Ping GenieACS
ping -c 4 192.168.88.10
```

### 3. Test Port Connectivity

```bash
# Test Mikrotik API port
nc -zv 192.168.88.1 8728

# Test GenieACS NBI port
nc -zv 192.168.88.10 7557

# Test GenieACS FS port
nc -zv 192.168.88.10 7567
```

### 4. Test via Aplikasi

```bash
# Masuk ke folder aplikasi
cd /var/www/billing

# Test koneksi Mikrotik
php artisan mikrotik:status

# Test koneksi GenieACS (via tinker)
php artisan tinker
>>> app(\App\Services\GenieAcs\GenieAcsService::class)->checkConnection()
```

---

## Troubleshooting

### VPN Tidak Terhubung

#### L2TP/IPSec

```bash
# Cek status IPSec
sudo ipsec statusall

# Cek log
sudo journalctl -u strongswan -f
sudo tail -f /var/log/syslog | grep -E "(xl2tpd|pppd)"

# Restart service
sudo systemctl restart strongswan xl2tpd
```

#### WireGuard

```bash
# Cek status
sudo wg show

# Cek log
sudo dmesg | grep wireguard
sudo journalctl -u wg-quick@wg0

# Restart
sudo wg-quick down wg0 && sudo wg-quick up wg0
```

### Tidak Bisa Akses Jaringan Lokal

1. **Cek Routing**
   ```bash
   ip route
   # Pastikan ada route ke 192.168.88.0/24 via interface VPN
   ```

2. **Cek Firewall Mikrotik**
   ```routeros
   /ip firewall filter print where chain=forward
   # Pastikan rule forward VPN to LAN ada dan enabled
   ```

3. **Cek NAT**
   ```routeros
   /ip firewall nat print
   ```

### Connection Timeout

1. **Cek MTU**
   ```bash
   ping -M do -s 1400 192.168.88.1
   # Kurangi ukuran jika gagal, tambahkan MTU setting di config
   ```

2. **Cek Firewall Cloud Server**
   ```bash
   sudo ufw status
   # Pastikan tidak memblokir traffic VPN
   ```

### VPN Disconnect Otomatis

1. **Tambah Keepalive untuk L2TP**
   ```bash
   # Di /etc/ppp/options.l2tpd.client tambahkan:
   lcp-echo-interval 30
   lcp-echo-failure 4
   ```

2. **Buat Cron Job Monitoring**
   ```bash
   sudo crontab -e
   ```
   ```
   */5 * * * * ping -c 1 192.168.88.1 > /dev/null || systemctl restart billing-vpn
   ```

---

## Script Monitoring VPN

Buat script untuk monitoring dan auto-reconnect:

```bash
sudo nano /usr/local/bin/vpn-monitor.sh
```

```bash
#!/bin/bash

LOG_FILE="/var/log/vpn-monitor.log"
MIKROTIK_IP="192.168.88.1"
MAX_RETRIES=3

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> $LOG_FILE
}

check_vpn() {
    ping -c 2 -W 5 $MIKROTIK_IP > /dev/null 2>&1
    return $?
}

reconnect_vpn() {
    log "VPN disconnected, attempting reconnect..."

    # For L2TP
    # systemctl restart billing-vpn

    # For WireGuard
    wg-quick down wg0 2>/dev/null
    sleep 2
    wg-quick up wg0

    sleep 10
}

# Main loop
retry=0
while [ $retry -lt $MAX_RETRIES ]; do
    if check_vpn; then
        log "VPN OK - Connected to $MIKROTIK_IP"
        exit 0
    else
        log "VPN check failed (attempt $((retry+1))/$MAX_RETRIES)"
        reconnect_vpn
        retry=$((retry+1))
    fi
done

log "ERROR: VPN reconnection failed after $MAX_RETRIES attempts"
# Optional: Send notification
# curl -X POST "https://api.telegram.org/bot<TOKEN>/sendMessage" \
#     -d "chat_id=<CHAT_ID>&text=VPN Billing disconnected!"

exit 1
```

```bash
sudo chmod +x /usr/local/bin/vpn-monitor.sh

# Tambah ke crontab
sudo crontab -e
```

```
*/5 * * * * /usr/local/bin/vpn-monitor.sh
```

---

## Keamanan

### Best Practices

1. **Gunakan password yang kuat** untuk VPN credentials
2. **Batasi IP** yang boleh connect ke VPN server di Mikrotik
3. **Gunakan IPSec** untuk enkripsi tambahan
4. **Monitor log** secara berkala
5. **Update firmware** Mikrotik dan package di server

### Firewall Mikrotik - Batasi VPN Access

```routeros
# Hanya izinkan IP tertentu connect ke VPN
/ip firewall filter
add chain=input src-address=YOUR_CLOUD_SERVER_IP protocol=udp \
    dst-port=500,1701,4500 action=accept comment="Allow VPN from Cloud"
add chain=input protocol=udp dst-port=500,1701,4500 action=drop \
    comment="Drop other VPN attempts"
```

---

## Referensi

- [Mikrotik L2TP Server](https://wiki.mikrotik.com/wiki/Manual:Interface/L2TP)
- [Mikrotik WireGuard](https://help.mikrotik.com/docs/display/ROS/WireGuard)
- [StrongSwan Documentation](https://docs.strongswan.org/)
- [WireGuard Quick Start](https://www.wireguard.com/quickstart/)
