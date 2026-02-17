# Captive Portal - Halaman Isolir Pelanggan

## Deskripsi

Ketika pelanggan diisolir karena tunggakan, traffic internet mereka di-redirect ke halaman notifikasi isolir. Halaman ini menampilkan informasi tunggakan dan instruksi pembayaran.

**URL:** `/portal/isolation/{customerId}` (public, tanpa login)

## Alur Kerja

1. Sistem mengisolir pelanggan (otomatis/manual)
2. IP pelanggan masuk ke address list `ISOLIR` di Mikrotik
3. Mikrotik NAT rule redirect traffic ke server aplikasi
4. Pelanggan buka browser → muncul halaman isolir
5. Pelanggan lihat info tunggakan & cara bayar
6. Setelah bayar, sistem buka isolasi → akses internet kembali normal

## Konfigurasi Mikrotik

> Ganti `<SERVER_IP>` dengan IP publik server aplikasi (VPS).

### Langkah 1: Filter Rules (Izinkan Traffic)

Pelanggan isolir secara default di-block semua aksesnya. Tambahkan filter rule untuk mengizinkan DNS dan akses ke server portal:

```mikrotik
/ip firewall filter
# 1. Izinkan DNS UDP (supaya browser bisa resolve domain)
add chain=forward src-address-list=ISOLIR dst-port=53 protocol=udp action=accept \
    comment="Allow DNS untuk pelanggan isolir" place-before=0

# 2. Izinkan DNS TCP
add chain=forward src-address-list=ISOLIR dst-port=53 protocol=tcp action=accept \
    comment="Allow DNS TCP untuk pelanggan isolir" place-before=1

# 3. Izinkan akses ke server aplikasi (HTTP & HTTPS)
add chain=forward src-address-list=ISOLIR dst-address=<SERVER_IP> dst-port=80,443 protocol=tcp action=accept \
    comment="Allow akses ke portal isolir" place-before=2

# 4. Block semua traffic lain dari pelanggan isolir
add chain=forward src-address-list=ISOLIR action=drop \
    comment="Block semua traffic pelanggan isolir" place-before=3
```

### Langkah 2: NAT Rules (Redirect Traffic)

Redirect traffic HTTP/HTTPS pelanggan isolir ke server aplikasi:

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

Urutan rule harus benar agar berfungsi:

```
Filter Rules:
  1. Allow DNS UDP (ISOLIR)
  2. Allow DNS TCP (ISOLIR)
  3. Allow Server IP port 80,443 (ISOLIR)
  4. Drop semua (ISOLIR)
  ... rule lainnya ...

NAT Rules:
  1. Redirect port 80 ke server (ISOLIR)
  2. Redirect port 443 ke server (ISOLIR)
  ... masquerade dan rule lainnya ...
```

`place-before=0` memastikan rule ditaruh di atas rule lain supaya diproses duluan.

### Catatan Penting

- Address list `ISOLIR` sudah **otomatis diisi** oleh sistem saat proses isolasi pelanggan
- Pastikan filter & NAT rule berada **di atas** rule lainnya agar diproses duluan
- Rule ini hanya berlaku untuk pelanggan yang IP-nya ada di address list `ISOLIR`
- Setelah pelanggan dibuka isolasinya, IP otomatis dihapus dari address list

### Verifikasi

```mikrotik
# Cek address list ISOLIR
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
- Nama & ID pelanggan
- Alasan isolir
- Total tunggakan (format Rupiah)
- Rekening bank & e-wallet untuk pembayaran
- Tombol WhatsApp untuk kirim bukti transfer
- Link ke portal pelanggan

**File:** `resources/js/Pages/Customer/IsolationPage.vue`

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Halaman tidak muncul saat buka browser | Cek filter rule Allow sudah ada dan posisi di atas rule Drop/Block |
| Browser loading terus, tidak redirect | Cek NAT rule sudah aktif dan posisi di atas masquerade |
| Pelanggan tidak bisa resolve domain | Cek filter rule DNS (port 53) sudah Allow |
| Halaman 404 | Pastikan `customerId` valid dan pelanggan berstatus `isolated` |
| Redirect ke login | Pelanggan sudah tidak isolir (status bukan `isolated`) |
| HTTPS tidak redirect | Pastikan NAT rule port 443 sudah ditambahkan |
