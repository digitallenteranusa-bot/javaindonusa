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

### NAT Rules (Wajib)

Tambahkan NAT rule di Mikrotik agar traffic pelanggan isolir di-redirect ke server:

```mikrotik
/ip firewall nat
add chain=dstnat src-address-list=ISOLIR dst-port=80 protocol=tcp \
    action=dst-nat to-addresses=<SERVER_IP> to-ports=80 \
    comment="Redirect isolir ke portal"
add chain=dstnat src-address-list=ISOLIR dst-port=443 protocol=tcp \
    action=dst-nat to-addresses=<SERVER_IP> to-ports=443 \
    comment="Redirect HTTPS isolir ke portal"
```

> Ganti `<SERVER_IP>` dengan IP server aplikasi (VPS).

### Catatan Penting

- Address list `ISOLIR` sudah **otomatis diisi** oleh sistem saat proses isolasi pelanggan
- Pastikan NAT rule ini berada **di atas** rule NAT lainnya (masquerade) agar diproses duluan
- Rule ini hanya berlaku untuk pelanggan yang IP-nya ada di address list `ISOLIR`
- Setelah pelanggan dibuka isolasinya, IP otomatis dihapus dari address list

### Verifikasi

```mikrotik
# Cek address list ISOLIR
/ip firewall address-list print where list=ISOLIR

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
| Halaman tidak muncul saat buka browser | Cek NAT rule sudah aktif dan posisi di atas masquerade |
| Halaman 404 | Pastikan `customerId` valid dan pelanggan berstatus `isolated` |
| Redirect ke login | Pelanggan sudah tidak isolir (status bukan `isolated`) |
| HTTPS tidak redirect | Pastikan NAT rule port 443 sudah ditambahkan |
