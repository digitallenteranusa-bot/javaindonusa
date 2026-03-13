# Panduan Integrasi WatZap.id (WhatsApp Business API)

Panduan lengkap untuk menghubungkan sistem billing ISP dengan WhatsApp Business API melalui **WatZap.id** — Official Meta Tech Provider. WatZap menyediakan WhatsApp Business API (WABA) resmi yang aman dan anti-banned.

---

## Daftar Isi

1. [Persiapan Akun WatZap](#1-persiapan-akun-watzap)
2. [Hubungkan Nomor WhatsApp](#2-hubungkan-nomor-whatsapp)
3. [Dapatkan API Key & Number Key](#3-dapatkan-api-key--number-key)
4. [Konfigurasi di Sistem Billing](#4-konfigurasi-di-sistem-billing)
5. [Konfigurasi via Environment (.env)](#5-konfigurasi-via-environment-env)
6. [Testing Kirim Pesan](#6-testing-kirim-pesan)
7. [Fitur yang Didukung](#7-fitur-yang-didukung)
8. [API Endpoints](#8-api-endpoints)
9. [Response Codes](#9-response-codes)
10. [Troubleshooting](#10-troubleshooting)
11. [Harga & Paket](#11-harga--paket)

---

## 1. Persiapan Akun WatZap

### 1.1 Daftar Akun

1. Buka **[watzap.id](https://watzap.id)**
2. Klik **Daftar** atau **Mulai Sekarang**
3. Isi data pendaftaran dan verifikasi email
4. Pilih paket yang sesuai (lihat [Harga & Paket](#11-harga--paket))

### 1.2 Login ke Dashboard

1. Buka **[app.watzap.id](https://app.watzap.id)**
2. Login dengan akun yang sudah didaftarkan

---

## 2. Hubungkan Nomor WhatsApp

### 2.1 Tambah Nomor

1. Di dashboard WatZap, buka menu **WhatsApp**
2. Pindah ke **Tab API**
3. Klik **Add WhatsApp Number**
4. Akan muncul **QR Code**

### 2.2 Scan QR Code

1. Buka **WhatsApp Business** di HP Anda
2. Buka **Settings** → **Linked Devices** → **Link a Device**
3. Scan QR Code yang ditampilkan di dashboard WatZap
4. Tunggu sampai muncul notifikasi **"WhatsApp is Connected"**

> **Penting:** Gunakan **WhatsApp Business** (bukan WhatsApp biasa) untuk fitur bisnis yang lebih lengkap.

### 2.3 Verifikasi Koneksi

Setelah terhubung, status nomor akan berubah menjadi **Connected** di dashboard WatZap. Pastikan HP tetap terhubung ke internet agar koneksi tidak terputus.

---

## 3. Dapatkan API Key & Number Key

### 3.1 API Key

1. Di dashboard WatZap, buka menu **Integration**
2. Pilih **API Key & Apps**
3. Salin **API Key** yang ditampilkan

### 3.2 Number Key

1. Di dashboard WatZap, buka menu **WhatsApp**
2. Pindah ke **Tab API**
3. Salin **Number Key** yang ditampilkan

> **Tips:** Jika Anda memiliki beberapa nomor aktif, gunakan nilai `ALL` sebagai Number Key untuk menggunakan semua nomor secara bergantian (load balancing).

---

## 4. Konfigurasi di Sistem Billing

### 4.1 Via Admin Panel (Recommended)

1. Login ke **Admin Panel** sistem billing
2. Buka **Settings** → tab **WhatsApp**
3. Pilih provider/driver: **WatZap**
4. Isi field berikut:
   - **API Key** — API Key dari dashboard WatZap
   - **Number Key** — Number Key dari tab API (atau `ALL`)
5. Klik **Simpan Konfigurasi**

### 4.2 Test Kirim Pesan

Setelah konfigurasi disimpan:

1. Scroll ke bagian **Test Kirim Pesan**
2. Masukkan nomor tujuan (format: `08123456789` atau `628123456789`)
3. Klik **Kirim Test**
4. Cek apakah pesan diterima di WhatsApp tujuan

---

## 5. Konfigurasi via Environment (.env)

Jika Anda ingin konfigurasi via file `.env` (misalnya untuk deployment awal):

```env
# Pilih WatZap sebagai driver
WHATSAPP_DRIVER=watzap

# API Key dari WatZap (Integration → API Key & Apps)
WHATSAPP_API_KEY=your_api_key_here

# Number Key dari WatZap (WhatsApp → Tab API)
# Gunakan "ALL" untuk menggunakan semua nomor aktif
WATZAP_NUMBER_KEY=your_number_key_here

# Nomor pengirim (opsional, untuk display saja)
WHATSAPP_SENDER=628123456789
```

Setelah mengubah `.env`, jalankan:

```bash
php artisan optimize:clear
php artisan queue:restart
```

> **Catatan:** Konfigurasi via Admin Panel (Settings DB) akan **override** konfigurasi `.env`.

---

## 6. Testing Kirim Pesan

### 6.1 Via Admin Panel

Gunakan fitur **Test Kirim Pesan** di halaman Settings → WhatsApp.

### 6.2 Via Artisan Command

```bash
php artisan notification:test 08123456789
```

### 6.3 Via Tinker

```bash
php artisan tinker
```

```php
$service = app(\App\Services\Notification\NotificationService::class);
$result = $service->sendWhatsApp('08123456789', 'Test pesan dari billing system');
dd($result);
```

---

## 7. Fitur yang Didukung

Integrasi WatZap mendukung semua notifikasi billing:

| Notifikasi | Deskripsi | Otomatis |
|------------|-----------|----------|
| **Tagihan Invoice** | Dikirim saat invoice baru dibuat (1st of month) | Ya |
| **Pengingat Bayar** | Reminder H-7, H-3, H-1 sebelum jatuh tempo | Ya |
| **Overdue Notice** | Peringatan setelah lewat jatuh tempo | Ya |
| **Isolasi** | Notifikasi saat akses internet diisolasi | Ya |
| **Akses Dibuka** | Konfirmasi setelah pembayaran & akses dibuka | Ya |
| **Konfirmasi Bayar** | Bukti pembayaran berhasil | Ya |
| **OTP Login** | Kode OTP untuk login customer portal | Ya |
| **Broadcast** | Pesan massal (maintenance, promo, dll) | Manual |

### Media yang Didukung

| Tipe | Endpoint | Keterangan |
|------|----------|------------|
| Teks | `waba_send_message` | Pesan teks biasa |
| Template | `waba_send_message_template` | Template message (WABA) |
| Gambar | `waba_send_image_url` | Kirim gambar via URL |
| File/Dokumen | `waba_send_file_url` | Kirim PDF, dokumen, dll |
| Voice/Audio | `waba_send_voice_url` | Kirim pesan suara |

---

## 8. API Endpoints

Base URL: `https://api.watzap.id/v1/`

Semua request menggunakan **POST** dengan **Content-Type: application/json**.

### 8.1 Kirim Pesan Teks

```
POST /waba_send_message
```

```json
{
  "api_key": "YOUR_API_KEY",
  "number_key": "YOUR_NUMBER_KEY",
  "phone_no": "6281234567890",
  "message": "Isi pesan di sini"
}
```

### 8.2 Kirim Template Message

```
POST /waba_send_message_template
```

```json
{
  "api_key": "YOUR_API_KEY",
  "number_key": "YOUR_NUMBER_KEY",
  "phone_no": "6281234567890",
  "template_name": "nama_template",
  "parameters": ["param1", "param2"],
  "language": "id"
}
```

### 8.3 Kirim Gambar

```
POST /waba_send_image_url
```

```json
{
  "api_key": "YOUR_API_KEY",
  "number_key": "YOUR_NUMBER_KEY",
  "phone_no": "6281234567890",
  "image_url": "https://example.com/image.jpg",
  "caption": "Caption gambar"
}
```

### 8.4 Kirim File/Dokumen

```
POST /waba_send_file_url
```

```json
{
  "api_key": "YOUR_API_KEY",
  "number_key": "YOUR_NUMBER_KEY",
  "phone_no": "6281234567890",
  "file_url": "https://example.com/invoice.pdf",
  "caption": "Invoice bulan Maret 2026"
}
```

### 8.5 Kirim Voice/Audio

```
POST /waba_send_voice_url
```

```json
{
  "api_key": "YOUR_API_KEY",
  "number_key": "YOUR_NUMBER_KEY",
  "phone_no": "6281234567890",
  "voice_url": "https://example.com/audio.mp3"
}
```

### 8.6 Cek API Key

```
POST /checking_key
```

```json
{
  "api_key": "YOUR_API_KEY"
}
```

### 8.7 Webhook

```
POST /set_webhook    → Set webhook URL
POST /get_webhook    → Get webhook URL
POST /unset_webhook  → Hapus webhook
```

---

## 9. Response Codes

| Code | Arti | Solusi |
|------|------|--------|
| **200** | Sukses | Pesan berhasil dikirim |
| **1002** | API Key tidak valid | Cek API Key di dashboard WatZap |
| **1003** | Number Key tidak valid | Cek Number Key di WhatsApp → Tab API |
| **1004** | Pairing gagal | Scan ulang QR Code di dashboard |
| **1005** | Error dynamic message | Cek format pesan, hindari karakter khusus |
| **1006** | Error lainnya | Hubungi support WatZap |
| **3001** | Perlu upgrade paket | Upgrade paket di dashboard WatZap |

---

## 10. Troubleshooting

### Pesan Tidak Terkirim

1. **Cek koneksi nomor** — Pastikan status nomor **Connected** di dashboard WatZap
2. **Cek API Key** — Pastikan API Key benar dan aktif
3. **Cek Number Key** — Pastikan Number Key sesuai dengan nomor yang terhubung
4. **Cek format nomor** — Gunakan format internasional (628xxx), sistem akan otomatis konversi dari 08xxx
5. **Cek paket** — Pastikan kuota belum habis (error 3001)

### Nomor Terputus (Disconnected)

1. Pastikan HP dengan WhatsApp tetap **online** (terhubung ke internet)
2. Jangan logout dari **Linked Devices** di WhatsApp
3. Jika terputus, scan ulang QR Code di dashboard WatZap

### Error 1004 (Pairing Failed)

1. Hapus nomor dari dashboard WatZap
2. Tambahkan ulang nomor
3. Scan QR Code baru
4. Pastikan tidak ada session WatZap lain yang aktif

### Queue Tidak Berjalan

Pastikan queue worker aktif:

```bash
php artisan queue:work redis --queue=notifications
```

Untuk produksi, gunakan Supervisor:

```ini
[program:billing-notification]
command=php /var/www/billing/artisan queue:work redis --queue=notifications --tries=3
autostart=true
autorestart=true
```

---

## 11. Harga & Paket

| Paket | Harga/Tahun | Kontak | Nomor WA |
|-------|-------------|--------|----------|
| **Personal** | Rp 449.000 | 10.000 | 2-3 |
| **Plus** | Rp 549.000 | 25.000 | 4 |
| **Pro** | Rp 749.000 | 50.000 | 6 |
| **Business** | Rp 1.190.000 | 100.000+ | 8+ |

> **Rekomendasi:** Untuk ISP dengan < 500 pelanggan, paket **Personal** sudah cukup. Untuk ISP lebih besar, pilih **Plus** atau **Pro**.

Informasi terbaru: [watzap.id](https://watzap.id)

---

## Referensi

- Website: [watzap.id](https://watzap.id)
- Dashboard: [app.watzap.id](https://app.watzap.id)
- API Docs: [api-docs.watzap.id](https://api-docs.watzap.id)
- Docs/Help: [docs.watzap.id](https://docs.watzap.id)
