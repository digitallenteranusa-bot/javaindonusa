# Panduan Integrasi Meta WhatsApp Cloud API

Panduan lengkap untuk menghubungkan sistem billing ISP dengan WhatsApp Business API langsung dari Meta (official). Dengan menggunakan API resmi Meta, nomor WhatsApp tidak akan di-banned seperti gateway tidak resmi.

---

## Daftar Isi

1. [Persiapan Akun Meta Business](#1-persiapan-akun-meta-business)
2. [Buat Aplikasi di Meta for Developers](#2-buat-aplikasi-di-meta-for-developers)
3. [Setup WhatsApp Business API](#3-setup-whatsapp-business-api)
4. [Buat Permanent Access Token](#4-buat-permanent-access-token)
5. [Verifikasi Bisnis](#5-verifikasi-bisnis)
6. [Daftarkan Nomor WhatsApp Produksi](#6-daftarkan-nomor-whatsapp-produksi)
7. [Buat Message Template](#7-buat-message-template)
8. [Konfigurasi di Sistem Billing](#8-konfigurasi-di-sistem-billing)
9. [Setup Webhook (Opsional)](#9-setup-webhook-opsional)
10. [Testing](#10-testing)
11. [Troubleshooting](#11-troubleshooting)
12. [Contoh Template Message](#12-contoh-template-message)
13. [Biaya & Pricing](#13-biaya--pricing)

---

## 1. Persiapan Akun Meta Business

### 1.1 Buat Meta Business Account

1. Buka **[business.facebook.com](https://business.facebook.com)**
2. Klik **Buat Akun** (atau gunakan akun bisnis yang sudah ada)
3. Isi informasi bisnis:
   - Nama bisnis: `Java Indonusa` (atau nama ISP Anda)
   - Nama Anda
   - Email bisnis
4. Klik **Kirim**

### 1.2 Siapkan Dokumen Verifikasi

Untuk verifikasi bisnis, siapkan salah satu:
- SIUP / NIB (Nomor Induk Berusaha)
- Akta pendirian perusahaan
- Rekening koran perusahaan (menunjukkan nama & alamat)
- Tagihan utilitas atas nama perusahaan

---

## 2. Buat Aplikasi di Meta for Developers

### 2.1 Buka Meta for Developers

1. Buka **[developers.facebook.com](https://developers.facebook.com)**
2. Login dengan akun Facebook yang terhubung ke Meta Business Account
3. Klik **My Apps** di kanan atas

### 2.2 Buat App Baru

1. Klik **Create App**
2. Pilih use case: **Other**
3. Pilih tipe app: **Business**
4. Isi detail:
   - **App name**: `ISP Billing WhatsApp` (atau nama sesuai keinginan)
   - **App contact email**: email bisnis Anda
   - **Business Account**: pilih akun bisnis yang sudah dibuat
5. Klik **Create App**

### 2.3 Tambahkan Produk WhatsApp

1. Di dashboard app, scroll ke bagian **Add products to your app**
2. Cari **WhatsApp** dan klik **Set up**
3. Pilih Meta Business Account yang akan digunakan
4. Klik **Continue**

---

## 3. Setup WhatsApp Business API

### 3.1 Dapatkan Phone Number ID

1. Di sidebar kiri, klik **WhatsApp** > **API Setup**
2. Anda akan melihat:
   - **Temporary Access Token** (berlaku 24 jam, untuk testing saja)
   - **Phone Number ID** — **CATAT INI** (contoh: `123456789012345`)
   - **WhatsApp Business Account ID** — **CATAT INI** (contoh: `987654321098765`)

> **Catatan**: Meta memberikan nomor test gratis untuk development. Untuk produksi, Anda perlu mendaftarkan nomor sendiri (lihat langkah 6).

### 3.2 Test Kirim Pesan (Opsional)

Di halaman **API Setup**, Anda bisa langsung test:

1. Klik **Select a recipient phone number** atau tambahkan nomor test
2. Klik **Send Message**
3. Pesan "Hello World" template akan terkirim ke nomor tersebut

---

## 4. Buat Permanent Access Token

> **PENTING**: Token temporary dari API Setup hanya berlaku 24 jam. Untuk produksi, Anda WAJIB membuat **System User Token** yang permanent.

### 4.1 Buat System User

1. Buka **[business.facebook.com/settings](https://business.facebook.com/settings)**
2. Di sidebar kiri, klik **Users** > **System Users**
3. Klik **Add** untuk membuat System User baru
4. Isi:
   - **System User Name**: `billing-whatsapp-api`
   - **System User Role**: **Admin**
5. Klik **Create System User**

### 4.2 Assign Asset ke System User

1. Klik system user yang baru dibuat
2. Klik **Add Assets**
3. Pilih tab **Apps**
4. Pilih app `ISP Billing WhatsApp` yang sudah dibuat
5. Aktifkan toggle **Manage app** (Full Control)
6. Klik **Save Changes**

### 4.3 Generate Permanent Token

1. Klik **Generate New Token** pada system user tersebut
2. Pilih app: `ISP Billing WhatsApp`
3. Set **Token expiration**: **Never** (token permanent)
4. Centang permissions berikut:
   - `whatsapp_business_messaging` — untuk kirim & terima pesan
   - `whatsapp_business_management` — untuk kelola template & nomor
5. Klik **Generate Token**
6. **SALIN TOKEN INI SEKARANG** — token hanya ditampilkan sekali!

```
Contoh token (sangat panjang):
EAAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx...
```

> **SIMPAN TOKEN DENGAN AMAN!** Jangan share token ini. Jika bocor, segera revoke dan generate baru.

---

## 5. Verifikasi Bisnis

> Verifikasi bisnis **WAJIB** untuk mengirim pesan ke nomor yang bukan tester dan untuk mendapatkan limit pengiriman lebih tinggi.

### 5.1 Submit Verifikasi

1. Buka **[business.facebook.com/settings](https://business.facebook.com/settings)**
2. Klik **Security Center** di sidebar
3. Klik **Start Verification**
4. Isi informasi:
   - **Legal business name**: sesuai dokumen legal
   - **Alamat**: sesuai dokumen
   - **Nomor telepon bisnis**: nomor yang bisa dihubungi
   - **Website**: URL website ISP
5. Upload dokumen verifikasi (SIUP/NIB/Akta)
6. Pilih metode verifikasi (email atau telepon)
7. Submit dan tunggu review (biasanya 1-3 hari kerja)

### 5.2 Status Verifikasi

- **Not verified** → Hanya bisa kirim ke nomor tester (maks 5 nomor)
- **Verified** → Bisa kirim ke semua nomor, limit lebih tinggi

---

## 6. Daftarkan Nomor WhatsApp Produksi

### 6.1 Tambahkan Nomor Telepon

1. Buka **Meta for Developers** > app Anda > **WhatsApp** > **API Setup**
2. Klik **Add phone number**
3. Isi:
   - **WhatsApp Business Profile display name**: `Java Indonusa` (nama yang muncul di WA penerima)
   - **Category**: `Internet Service Provider` atau `Telecommunications`
4. Masukkan nomor telepon baru

> **PENTING**: Nomor ini TIDAK BOLEH sudah terdaftar di WhatsApp biasa atau WhatsApp Business App. Jika sudah, hapus dulu akun WA-nya.

### 6.2 Verifikasi Nomor

1. Pilih metode verifikasi: **SMS** atau **Telepon**
2. Masukkan kode OTP yang diterima
3. Nomor berhasil didaftarkan

### 6.3 Catat Phone Number ID Baru

Setelah nomor produksi ditambahkan, catat **Phone Number ID** yang baru (berbeda dari nomor test).

---

## 7. Buat Message Template

> Meta **MEWAJIBKAN** template message yang sudah di-approve untuk memulai percakapan dengan pelanggan. Anda tidak bisa kirim pesan bebas kecuali dalam 24-hour service window (setelah pelanggan membalas).

### 7.1 Buka WhatsApp Manager

1. Buka **[business.facebook.com/wa/manage/message-templates](https://business.facebook.com/wa/manage/message-templates)**
2. Atau dari Meta for Developers: **WhatsApp** > **Message Templates**

### 7.2 Buat Template Baru

Klik **Create Template** dan isi:

| Field | Contoh |
|-------|--------|
| **Category** | `UTILITY` (untuk notifikasi transaksional) |
| **Name** | `payment_confirmation` (huruf kecil, underscore) |
| **Language** | `Indonesian (id)` |

### 7.3 Template Body dengan Parameter

Gunakan `{{1}}`, `{{2}}`, dst. sebagai placeholder yang akan diisi dinamis.

**Contoh body template `payment_confirmation`:**

```
Halo {{1}}, pembayaran Anda sebesar Rp {{2}} telah kami terima.

No. Pembayaran: {{3}}
Tanggal: {{4}}
Status: {{5}}

Terima kasih atas pembayarannya. Jika ada pertanyaan, hubungi CS kami.
```

### 7.4 Submit & Tunggu Approval

1. Klik **Submit** untuk review
2. Meta akan review template Anda (biasanya 1-5 menit, kadang sampai 24 jam)
3. Status:
   - **Approved** ✅ — siap digunakan
   - **Rejected** ❌ — revisi sesuai alasan penolakan
   - **Pending** ⏳ — masih dalam review

### 7.5 Daftar Template yang Perlu Dibuat

Buat minimal template berikut (sesuaikan nama dan isi):

| Nama Template | Kegunaan | Contoh Parameter |
|---------------|----------|------------------|
| `payment_confirmation` | Konfirmasi bayar | nama, nominal, no_bayar, tanggal, status |
| `invoice_notification` | Tagihan baru | nama, periode, no_invoice, paket, nominal, jatuh_tempo, customer_id |
| `payment_reminder` | Pengingat bayar | nama, nominal, hari_sblm_jt, customer_id |
| `overdue_notice` | Pemberitahuan overdue | nama, nominal, customer_id |
| `isolation_notice` | Peringatan isolasi | nama, nominal, telepon_cs, customer_id |
| `access_opened` | Akses dibuka kembali | nama, telepon_cs |
| `otp_code` | OTP login portal | kode_otp |

---

## 8. Konfigurasi di Sistem Billing

### 8.1 Via Panel Admin (Rekomendasi)

1. Login ke panel admin
2. Buka **Settings** > tab **WhatsApp**
3. Pilih driver: **Meta Cloud API**
4. Isi:
   - **Permanent Access Token**: token dari langkah 4.3
   - **Phone Number ID**: dari langkah 3.1 atau 6.3
   - **Business Account ID**: dari langkah 3.1
5. Isi **Template Name** per jenis notifikasi sesuai template yang sudah di-approve
6. Klik **Simpan Konfigurasi**

### 8.2 Via Environment Variable (.env)

Alternatif, bisa juga set lewat file `.env`:

```env
WHATSAPP_ENABLED=true
WHATSAPP_DRIVER=meta
WHATSAPP_API_KEY=EAAxxxxxxxxxxxxxxxxxx    # Permanent Access Token

META_WA_PHONE_NUMBER_ID=123456789012345
META_WA_BUSINESS_ACCOUNT_ID=987654321098765
```

> **Catatan**: Setting di panel admin (database) akan override setting di `.env`.

---

## 9. Setup Webhook (Opsional)

Webhook berguna untuk menerima status pengiriman pesan (delivered, read, failed).

### 9.1 Konfigurasi Webhook di Meta

1. Di Meta for Developers > app Anda > **WhatsApp** > **Configuration**
2. Di bagian **Webhook**, klik **Edit**
3. Isi:
   - **Callback URL**: `https://yourdomain.com/webhook/whatsapp/meta`
   - **Verify Token**: buat string random yang sama di `.env`
4. Klik **Verify and Save**
5. Subscribe ke event:
   - `messages` — pesan masuk
   - `message_status` — status pengiriman (sent, delivered, read)

### 9.2 Webhook di Sistem Billing

Webhook endpoint belum tersedia secara default. Jika diperlukan untuk tracking delivery status, bisa ditambahkan kemudian.

---

## 10. Testing

### 10.1 Test dari Panel Admin

1. Buka **Settings** > tab **WhatsApp**
2. Di bagian **Test Kirim Pesan**, masukkan nomor tujuan
3. Klik **Kirim Test**

### 10.2 Test via Artisan Command

```bash
php artisan notification:test whatsapp 08123456789
```

### 10.3 Verifikasi di Log

Cek log pengiriman di:

```bash
# Log Laravel
tail -f storage/logs/laravel.log

# Atau cek di tabel billing_logs
```

### 10.4 Cek Status Koneksi

Test apakah token dan Phone Number ID valid:

1. Di panel admin, setelah simpan konfigurasi Meta
2. Klik **Kirim Test** — jika berhasil, konfigurasi sudah benar
3. Atau cek via API endpoint: `GET /admin/settings/whatsapp/status`

---

## 11. Troubleshooting

### Error: "Phone Number ID tidak dikonfigurasi"
- Pastikan field **Phone Number ID** sudah diisi di Settings > WhatsApp
- Cek apakah driver terpilih sudah `meta`

### Error: "(#100) Invalid parameter"
- Pastikan nomor tujuan dalam format internasional tanpa `+` (contoh: `6281234567890`)
- Pastikan template name yang diisi sudah benar (case-sensitive, huruf kecil)

### Error: "(#132015) Template not found"
- Template belum di-approve atau nama template salah
- Cek di WhatsApp Manager apakah template statusnya **Approved**
- Pastikan bahasa template sesuai (gunakan `id` untuk Indonesia)

### Error: "(#131047) Re-engagement message"
- Anda mencoba kirim pesan di luar 24-hour service window tanpa template
- Solusi: Pastikan selalu kirim pakai template message

### Error: "(#80007) Rate limit hit"
- Terlalu banyak pesan dalam waktu singkat
- Default limit: 250 pesan/24 jam untuk tier awal
- Solusi: Naikkan tier dengan verifikasi bisnis + riwayat pengiriman bagus

### Error: "Invalid OAuth access token"
- Token expired atau salah
- Pastikan menggunakan **Permanent System User Token**, bukan temporary token
- Generate ulang token jika perlu

### Template Rejected oleh Meta
Alasan umum penolakan:
- Mengandung konten promosi di kategori UTILITY
- Placeholder `{{1}}` di posisi yang tidak wajar
- Bahasa campur (harus konsisten 1 bahasa)
- Mengandung URL yang mencurigakan

**Tips agar template di-approve:**
- Gunakan kategori yang tepat (UTILITY untuk transaksional)
- Tulis dalam bahasa Indonesia yang baku
- Jangan ada link yang mencurigakan
- Isi contoh (sample) yang realistis saat submit

---

## 12. Contoh Template Message

Berikut contoh template yang bisa langsung digunakan. Salin dan sesuaikan saat membuat template di WhatsApp Manager.

### `payment_confirmation` (Konfirmasi Pembayaran)
**Category**: UTILITY

```
Halo {{1}}, pembayaran Anda sebesar Rp {{2}} telah kami terima.

No. Pembayaran: {{3}}
Tanggal: {{4}}
Status: {{5}}

Terima kasih atas pembayarannya.
```

Parameter: nama, nominal, no_bayar, tanggal, status_sisa

---

### `invoice_notification` (Tagihan Baru)
**Category**: UTILITY

```
Yth. {{1}}, tagihan internet periode {{2}} telah terbit.

No. Invoice: {{3}}
Paket: {{4}}
Total: Rp {{5}}
Jatuh Tempo: {{6}}
ID Pelanggan: {{7}}

Silakan lakukan pembayaran sebelum jatuh tempo. Terima kasih.
```

Parameter: nama, periode, no_invoice, paket, nominal, jatuh_tempo, customer_id

---

### `payment_reminder` (Pengingat Pembayaran)
**Category**: UTILITY

```
Halo {{1}}, ini adalah pengingat bahwa tagihan internet Anda sebesar Rp {{2}} akan jatuh tempo dalam {{3}} hari.

ID Pelanggan: {{4}}

Mohon segera lakukan pembayaran. Terima kasih.
```

Parameter: nama, nominal, hari_sblm_jatuh_tempo, customer_id

---

### `overdue_notice` (Pemberitahuan Overdue)
**Category**: UTILITY

```
Yth. {{1}}, tagihan internet Anda sebesar Rp {{2}} telah melewati jatuh tempo.

ID Pelanggan: {{3}}

Mohon segera lakukan pembayaran untuk menghindari pemutusan layanan. Terima kasih.
```

Parameter: nama, nominal, customer_id

---

### `isolation_notice` (Peringatan Isolasi)
**Category**: UTILITY

```
Yth. {{1}}, layanan internet Anda telah diisolasi karena tunggakan sebesar Rp {{2}}.

Silakan hubungi CS kami di {{3}} atau lakukan pembayaran untuk mengaktifkan kembali layanan.

ID Pelanggan: {{4}}
```

Parameter: nama, nominal, telepon_cs, customer_id

---

### `access_opened` (Akses Dibuka Kembali)
**Category**: UTILITY

```
Halo {{1}}, layanan internet Anda telah aktif kembali.

Terima kasih atas pembayarannya. Jika ada kendala, hubungi CS kami di {{2}}.
```

Parameter: nama, telepon_cs

---

### `otp_code` (Kode OTP)
**Category**: AUTHENTICATION

```
Kode OTP Anda: {{1}}

Jangan berikan kode ini kepada siapapun. Kode berlaku 5 menit.
```

Parameter: kode_otp

> **Catatan untuk template OTP**: Pilih kategori **AUTHENTICATION** agar lebih cepat di-approve. Meta memiliki template khusus untuk OTP yang lebih sederhana.

---

## 13. Biaya & Pricing

### Conversation-based Pricing

Meta mengenakan biaya per **conversation** (24 jam), bukan per pesan.

| Tipe Conversation | Biaya (Indonesia, per conversation) |
|-------------------|-------------------------------------|
| **Utility** (tagihan, update) | ~Rp 310 ($0.0200) |
| **Authentication** (OTP) | ~Rp 465 ($0.0300) |
| **Marketing** (promo) | ~Rp 775 ($0.0500) |
| **Service** (reply pelanggan) | ~Rp 230 ($0.0150) |

> Harga bisa berubah. Cek terbaru di: [developers.facebook.com/docs/whatsapp/pricing](https://developers.facebook.com/docs/whatsapp/pricing)

### Free Tier

- **1.000 conversation gratis per bulan** (kategori Service)
- Berlaku untuk setiap WhatsApp Business Account

### Messaging Limits (Tier)

| Tier | Limit per 24 jam | Syarat |
|------|-------------------|--------|
| **Unverified** | 250 pesan | - |
| **Tier 1** | 1.000 pesan | Bisnis terverifikasi |
| **Tier 2** | 10.000 pesan | Riwayat pengiriman baik |
| **Tier 3** | 100.000 pesan | Riwayat pengiriman baik |
| **Tier 4** | Unlimited | Riwayat pengiriman baik |

Tier naik otomatis jika quality rating bagus dan volume pengiriman konsisten.

### Cara Bayar

1. Buka **[business.facebook.com](https://business.facebook.com)** > **Billing**
2. Tambahkan metode pembayaran (kartu kredit/debit)
3. Biaya dihitung otomatis berdasarkan pemakaian

---

## Checklist Setup

Gunakan checklist ini untuk memastikan semua langkah sudah dilakukan:

- [ ] Meta Business Account sudah dibuat
- [ ] App di Meta for Developers sudah dibuat
- [ ] Produk WhatsApp sudah ditambahkan ke app
- [ ] System User sudah dibuat
- [ ] Permanent Access Token sudah di-generate dan disimpan
- [ ] Verifikasi bisnis sudah submit / approved
- [ ] Nomor telepon produksi sudah didaftarkan
- [ ] Template message sudah dibuat dan di-approve:
  - [ ] `payment_confirmation`
  - [ ] `invoice_notification`
  - [ ] `payment_reminder`
  - [ ] `overdue_notice`
  - [ ] `isolation_notice`
  - [ ] `access_opened`
  - [ ] `otp_code`
- [ ] Konfigurasi di panel admin sudah diisi:
  - [ ] Driver: Meta Cloud API
  - [ ] Access Token
  - [ ] Phone Number ID
  - [ ] Business Account ID
  - [ ] Template name per jenis notifikasi
- [ ] Test kirim pesan berhasil
- [ ] Metode pembayaran sudah ditambahkan di Meta Business Billing

---

## Bantuan & Referensi

- **Meta WhatsApp Cloud API Docs**: [developers.facebook.com/docs/whatsapp/cloud-api](https://developers.facebook.com/docs/whatsapp/cloud-api)
- **WhatsApp Business Platform**: [business.whatsapp.com](https://business.whatsapp.com)
- **Template Guidelines**: [developers.facebook.com/docs/whatsapp/message-templates](https://developers.facebook.com/docs/whatsapp/message-templates)
- **Pricing**: [developers.facebook.com/docs/whatsapp/pricing](https://developers.facebook.com/docs/whatsapp/pricing)
- **Meta Business Help**: [facebook.com/business/help](https://www.facebook.com/business/help)
