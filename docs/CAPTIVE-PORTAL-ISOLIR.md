# Captive Portal - Halaman Isolir Pelanggan

## Deskripsi

Ketika pelanggan diisolir karena tunggakan, traffic internet mereka di-redirect ke halaman notifikasi isolir. Halaman ini menampilkan informasi tunggakan dan instruksi pembayaran.

Sistem mendukung **auto-detect**: pelanggan isolir cukup buka browser, sistem otomatis mendeteksi siapa mereka dan menampilkan halaman isolir yang sesuai.

**URL:**
- `/portal/isolation/detect` — auto-detect customer dari IP (captive portal)
- `/portal/isolation/{customerId}` — akses langsung (public, tanpa login)

## Alur Kerja

### Metode: Profile + IP Pool Isolir (Recommended)

1. Sistem mengisolir pelanggan → PPPoE profile diubah ke `isolir`
2. Pelanggan reconnect → dapat IP dari pool isolir (`10.144.1.0/24`)
3. Mikrotik NAT rule redirect traffic subnet isolir ke server aplikasi
4. Request masuk ke `/portal/isolation/detect`
5. Aplikasi ambil IP pengirim → query Mikrotik API `/ppp/active` → dapat PPPoE username
6. Cari customer di DB berdasarkan `pppoe_username` → redirect ke `/portal/isolation/{customer_id}`
7. Pelanggan lihat info tunggakan & cara bayar
8. Setelah bayar, sistem buka isolasi → profile dikembalikan → akses internet normal

### Metode: Address List (Legacy)

1. Sistem mengisolir pelanggan → IP ditambahkan ke address list `ISOLIR`
2. Mikrotik NAT rule redirect traffic dari address list ke server
3. Pelanggan buka browser → redirect ke `/portal/isolation/{customerId}` (URL dari notifikasi WA)

## Konfigurasi Aplikasi

Tambahkan di `.env`:

```env
# Metode isolir: profile (recommended) atau address_list
MIKROTIK_ISOLATION_METHOD=profile

# Subnet IP pool isolir (untuk auto-detect)
MIKROTIK_ISOLATION_SUBNET=10.144.1.0/24
```

## Konfigurasi Mikrotik

> Ganti `<SERVER_IP>` dengan IP publik server aplikasi (VPS).

### Opsi A: Profile + IP Pool (Recommended)

#### 1. Buat IP Pool untuk Isolir

```mikrotik
/ip pool
add name=pool-isolir ranges=10.144.1.2-10.144.1.254
```

#### 2. Buat PPP Profile Isolir

```mikrotik
/ppp profile
add name=isolir local-address=10.144.1.1 remote-address=pool-isolir \
    dns-server=10.144.1.1 rate-limit=256k/256k
```

#### 3. Filter Rules (Izinkan DNS & Akses Server)

```mikrotik
/ip firewall filter
# 1. Izinkan DNS UDP
add chain=forward src-address=10.144.1.0/24 dst-port=53 protocol=udp action=accept \
    comment="Allow DNS untuk pelanggan isolir" place-before=0

# 2. Izinkan DNS TCP
add chain=forward src-address=10.144.1.0/24 dst-port=53 protocol=tcp action=accept \
    comment="Allow DNS TCP untuk pelanggan isolir" place-before=1

# 3. Izinkan akses ke server aplikasi (HTTP & HTTPS)
add chain=forward src-address=10.144.1.0/24 dst-address=<SERVER_IP> dst-port=80,443 protocol=tcp action=accept \
    comment="Allow akses ke portal isolir" place-before=2

# 4. Block semua traffic lain dari pelanggan isolir
add chain=forward src-address=10.144.1.0/24 action=drop \
    comment="Block semua traffic pelanggan isolir" place-before=3
```

#### 4. NAT Rules (Redirect ke Server)

```mikrotik
/ip firewall nat
add chain=dstnat src-address=10.144.1.0/24 dst-port=80 protocol=tcp \
    action=dst-nat to-addresses=<SERVER_IP> to-ports=80 \
    comment="Redirect isolir ke portal"
add chain=dstnat src-address=10.144.1.0/24 dst-port=443 protocol=tcp \
    action=dst-nat to-addresses=<SERVER_IP> to-ports=443 \
    comment="Redirect HTTPS isolir ke portal"
```

### Opsi B: Address List (Legacy)

#### Filter Rules

```mikrotik
/ip firewall filter
add chain=forward src-address-list=ISOLIR dst-port=53 protocol=udp action=accept \
    comment="Allow DNS untuk pelanggan isolir" place-before=0
add chain=forward src-address-list=ISOLIR dst-port=53 protocol=tcp action=accept \
    comment="Allow DNS TCP untuk pelanggan isolir" place-before=1
add chain=forward src-address-list=ISOLIR dst-address=<SERVER_IP> dst-port=80,443 protocol=tcp action=accept \
    comment="Allow akses ke portal isolir" place-before=2
add chain=forward src-address-list=ISOLIR action=drop \
    comment="Block semua traffic pelanggan isolir" place-before=3
```

#### NAT Rules

```mikrotik
/ip firewall nat
add chain=dstnat src-address-list=ISOLIR dst-port=80 protocol=tcp \
    action=dst-nat to-addresses=<SERVER_IP> to-ports=80 \
    comment="Redirect isolir ke portal"
add chain=dstnat src-address-list=ISOLIR dst-port=443 protocol=tcp \
    action=dst-nat to-addresses=<SERVER_IP> to-ports=443 \
    comment="Redirect HTTPS isolir ke portal"
```

### Urutan Rules (Penting!)

```
Filter Rules:
  1. Allow DNS UDP (isolir)
  2. Allow DNS TCP (isolir)
  3. Allow Server IP port 80,443 (isolir)
  4. Drop semua (isolir)
  ... rule lainnya ...

NAT Rules:
  1. Redirect port 80 ke server (isolir)
  2. Redirect port 443 ke server (isolir)
  ... masquerade dan rule lainnya ...
```

`place-before=0` memastikan rule ditaruh di atas rule lain supaya diproses duluan.

### Catatan Penting

- **Metode Profile**: IP pool isolir otomatis diberikan saat pelanggan reconnect. Tidak perlu manage address list manual.
- **Metode Address List**: Address list `ISOLIR` otomatis diisi oleh sistem saat proses isolasi pelanggan.
- Pastikan filter & NAT rule berada **di atas** rule lainnya agar diproses duluan.
- Setelah pelanggan dibuka isolasinya, profile dikembalikan / IP dihapus dari address list secara otomatis.

### Verifikasi

```mikrotik
# Cek PPP active connections (metode profile)
/ppp active print where caller-id~"isolir"

# Cek address list ISOLIR (metode address_list)
/ip firewall address-list print where list=ISOLIR

# Cek filter rules
/ip firewall filter print

# Cek NAT rules
/ip firewall nat print
```

## Halaman Isolir

Halaman menampilkan:
- Logo & nama ISP
- Peringatan isolir
- Nama & ID pelanggan (jika terdeteksi)
- Alasan isolir
- Total tunggakan (format Rupiah)
- Rekening bank & e-wallet untuk pembayaran
- Tombol WhatsApp untuk kirim bukti transfer
- Link ke portal pelanggan

Jika customer tidak terdeteksi (IP tidak cocok / router tidak bisa diquery), halaman menampilkan pesan generic dengan info kontak admin.

**File:** `resources/js/Pages/Customer/IsolationPage.vue`

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Halaman tidak muncul saat buka browser | Cek filter rule Allow sudah ada dan posisi di atas rule Drop/Block |
| Browser loading terus, tidak redirect | Cek NAT rule sudah aktif dan posisi di atas masquerade |
| Pelanggan tidak bisa resolve domain | Cek filter rule DNS (port 53) sudah Allow |
| Auto-detect tidak bekerja | Cek `MIKROTIK_ISOLATION_SUBNET` di `.env` sesuai pool isolir |
| Auto-detect salah customer | Pastikan `pppoe_username` di DB cocok dengan username PPPoE di Mikrotik |
| Halaman 404 | Pastikan `customerId` valid dan pelanggan berstatus `isolated` |
| Redirect ke login | Pelanggan sudah tidak isolir (status bukan `isolated`) |
| HTTPS tidak redirect | Pastikan NAT rule port 443 sudah ditambahkan |
