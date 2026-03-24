# Kekurangan Aplikasi ISP Billing

**Review:** 2026-03-17 (updated 2026-03-24)
**Total:** 42 item, kategori A-H

---

## Status Legend

- [ ] Belum dikerjakan
- [x] Sudah dikerjakan

---

## A. Keamanan (7 item)

1. [x] `APP_DEBUG=false` di production
2. [x] **Path Traversal di route receipt collector** — Fixed: `basename()` + `realpath()` validation memastikan file hanya dari folder receipts.
3. [ ] **SQL Injection risk di DashboardService** — `getYearMonthExpressions()` interpolasi column name langsung ke raw SQL. Saat ini hardcoded `created_at`/`deleted_at`, tapi arsitektur memungkinkan injection. Fix: whitelist column.
4. [x] **Backup download tanpa validasi ekstensi** — Fixed: whitelist ekstensi `.sql`, `.sql.gz`, `.sql.zip` + cek backslash.
5. [x] **Default password di Customer Import** — Tidak diubah: `client001` adalah password standar yang sudah dipakai semua pelanggan aktif. Bukan bug, ini by design.
6. [ ] **CSP terlalu permisif** — `SecurityHeaders.php` menggunakan `'unsafe-inline'` dan `'unsafe-eval'` untuk script-src. Fix: gunakan nonce-based CSP.
7. [x] **Callback signature validation** — Sudah ada: Tripay `verifySignature()` HMAC SHA256, Xendit `verifyWebhookToken()` via `X-Callback-Token`.

---

## B. Fitur Bisnis (8 item)

8. [ ] **Prorating** — Tidak ada perhitungan pro-rata untuk aktivasi/suspend di tengah bulan.
9. [ ] **Denda keterlambatan (surcharge/late fee)** — Infrastruktur ada di DebtHistory tapi belum diimplementasikan di billing logic.
10. [x] **Refund/credit notes** — Implemented: CreditNote model + CreditNoteService (create/approve/reject) + admin CRUD pages. Tipe: refund, credit, adjustment. Approval workflow (pending → approved/rejected).
11. [ ] **Customer contract/SLA tracking** — Tidak ada tracking kontrak, tanggal mulai/berakhir, perpanjangan otomatis.
12. [x] **Payment plan/cicilan** — Implemented: PaymentPlan + PaymentPlanInstallment model, PaymentPlanService (create/cancel/record), admin CRUD pages. 2-24 bulan, jadwal otomatis, progress tracking.
13. [x] **Invoice line items** — Implemented: InvoiceItem model + migration. Invoice generation otomatis buat line items (paket, diskon, PPN). Show page menampilkan items jika ada, fallback ke legacy view.
14. [x] **Invoice amendment** — Implemented: `InvoiceService::amendInvoice()` + endpoint + UI modal. Bisa ubah jumlah invoice non-paid/cancelled, debt otomatis disesuaikan.
15. [x] **Idempotency payment callback** — Fixed: `lockForUpdate()` + `DB::transaction()` di TripayService dan XenditService mencegah double payment dari concurrent callback.

---

## C. Notifikasi (5 item)

16. [ ] **SMS notification** — Infrastruktur channel ada tapi tidak dikonfigurasi/aktif.
17. [ ] **Notification scheduling** — Tidak bisa schedule notifikasi untuk dikirim di waktu tertentu.
18. [ ] **Do-not-disturb hours** — Tidak ada pengaturan jam jangan ganggu pelanggan.
19. [ ] **Notification analytics** — Tidak ada tracking open rate, delivery rate, failure rate.
20. [x] **WhatsApp rate limiting** — Sudah diimplementasi: `rate_limit.per_minute`, `delay_ms`, `bulk_delay_seconds` di config, staggered delay di SendBulkNotificationJob.

---

## D. Laporan (6 item)

21. [ ] **Revenue forecast/projection** — Tidak ada prediksi pendapatan berdasarkan trend.
22. [ ] **Churn analysis** — Tidak ada analisa customer lifecycle, churn rate, retention cohort.
23. [ ] **Scheduled report delivery** — Tidak bisa kirim laporan otomatis via email secara berkala.
24. [ ] **Export CSV/Excel** — 9 halaman punya referensi export tapi tidak ada implementasi (tombol export tidak functional).
25. [ ] **Custom report builder** — Admin tidak bisa buat laporan custom dengan filter/kolom pilihan sendiri.
26. [ ] **Bad debt analysis** — Tidak ada analisa piutang tak tertagih dan write-off.

---

## E. UI/UX (6 item)

27. [ ] **Browser/E2E testing** — Zero automated UI test (tidak ada Dusk, Playwright, atau Cypress).
28. [ ] **Error boundary Vue** — Tidak ada global error boundary component, JS error tidak ter-handle gracefully.
29. [ ] **Skeleton loading tidak merata** — Hanya 12 halaman pakai SkeletonLoader, 40+ halaman lainnya belum.
30. [ ] **Accessibility (WCAG)** — Tidak ada aria-labels, role attributes, keyboard navigation di komponen kompleks.
31. [ ] **Loading overlay global** — `LoadingOverlay.vue` ada tapi tidak digunakan secara global untuk operasi lambat.
32. [ ] **Invoice PDF download di portal** — Customer portal hanya bisa lihat invoice, tidak bisa download PDF.

---

## F. Infrastruktur (5 item)

33. [x] **Backup ke Google Drive** — Sudah diimplementasi dengan spatie/laravel-backup + OAuth2.
34. [ ] **Monitoring & alerting** — Tidak ada Prometheus, New Relic, atau Datadog. Tidak bisa detect performance degradation.
35. [x] **Sentry DSN kosong** — Fixed: Sentry DSN dikonfigurasi di production. Backend (PHP) + Frontend (Vue.js) error monitoring aktif. Performance tracing enabled. **TODO:** Hapus 2 test event di dashboard Sentry (resolve/delete issue "This is a test exception sent from the Sentry Laravel SDK"), dan set `APP_ENV=production` di `.env` server agar environment tidak tercatat sebagai `local`.
36. [ ] **Log aggregation** — Log hanya di `storage/logs/`, tidak ada ELK/Papertrail/Datadog integration.
37. [ ] **Cache invalidation** — Hanya 2 `Cache::forget()` ditemukan. Dashboard cache tidak di-invalidate saat invoice/payment berubah.

---

## G. Testing (3 item)

38. [ ] **Integration test end-to-end** — Mayoritas test unit dengan mock. Tidak ada test flow Payment -> Isolation -> Notification secara end-to-end.
39. [ ] **API contract test** — Tidak ada validasi response API terhadap OpenAPI spec. Hanya 5 basic API test.
40. [ ] **Performance/load test** — Tidak ada load test (Locust, k6). Tidak bisa validasi dashboard load time atau queue under load.

---

## H. Integrasi (2 item)

41. [ ] **Circuit breaker pattern** — Jika payment gateway down, semua request gagal tanpa fallback. Tidak ada circuit breaker.
42. [ ] **Mikrotik retry logic** — 26 exception block ditemukan tapi tidak ada retry dengan exponential backoff. Isolation command bisa gagal tanpa retry.

---

## Ringkasan per Kategori

| Kategori | Total | Selesai | Sisa |
|----------|-------|---------|------|
| A. Keamanan | 7 | 5 | 2 |
| B. Fitur Bisnis | 8 | 5 | 3 |
| C. Notifikasi | 5 | 1 | 4 |
| D. Laporan | 6 | 0 | 6 |
| E. UI/UX | 6 | 0 | 6 |
| F. Infrastruktur | 5 | 2 | 3 |
| G. Testing | 3 | 0 | 3 |
| H. Integrasi | 2 | 0 | 2 |
| **Total** | **42** | **13** | **29** |

---

## Prioritas Rekomendasi

### Harus Segera (Critical) — SEMUA DONE
- ~~#2 Path Traversal receipt~~ (fixed)
- ~~#7 Callback signature validation~~ (sudah ada)
- ~~#15 Idempotency payment callback~~ (fixed)

### Penting (High)
- #3 SQL injection risk DashboardService
- #5 Default password import
- #34 Monitoring & alerting
- ~~#35 Sentry DSN~~ (configured)
- #41 Circuit breaker payment gateway
- #42 Mikrotik retry logic

### Sedang (Medium)
- #8 Prorating
- #9 Denda keterlambatan
- ~~#20 WhatsApp rate limiting~~ (sudah done)
- #24 Export CSV/Excel
- #37 Cache invalidation
- #38 Integration test E2E

### Bisa Nanti (Low)
- #16 SMS notification
- #17 Notification scheduling
- #21 Revenue forecast
- #22 Churn analysis
- #25 Custom report builder
- #30 Accessibility WCAG
