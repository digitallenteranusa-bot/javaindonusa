# Kekurangan Aplikasi ISP Billing

**Review:** 2026-03-17 (updated 2026-03-30)
**Total:** 42 item, kategori A-H

---

## Status Legend

- [ ] Belum dikerjakan
- [x] Sudah dikerjakan

---

## A. Keamanan (7 item)

1. [x] `APP_DEBUG=false` di production
2. [x] **Path Traversal di route receipt collector** — Fixed: `basename()` + `realpath()` validation memastikan file hanya dari folder receipts.
3. [x] **SQL Injection risk di DashboardService** — Fixed: `getYearMonthExpressions()` sekarang memvalidasi column name terhadap whitelist (`created_at`, `updated_at`, `deleted_at`, `paid_at`, `due_date`). Throw `InvalidArgumentException` jika column tidak diizinkan.
4. [x] **Backup download tanpa validasi ekstensi** — Fixed: whitelist ekstensi `.sql`, `.sql.gz`, `.sql.zip` + cek backslash.
5. [x] **Default password di Customer Import** — Tidak diubah: `client001` adalah password standar yang sudah dipakai semua pelanggan aktif. Bukan bug, ini by design.
6. [ ] **CSP terlalu permisif** — `SecurityHeaders.php` menggunakan `'unsafe-inline'` dan `'unsafe-eval'` untuk script-src. Fix: gunakan nonce-based CSP.
7. [x] **Callback signature validation** — Sudah ada: Tripay `verifySignature()` HMAC SHA256, Xendit `verifyWebhookToken()` via `X-Callback-Token`.

---

## B. Fitur Bisnis (8 item)

8. [ ] **Prorating** — Tidak ada perhitungan pro-rata untuk aktivasi/suspend di tengah bulan.
9. [ ] **Denda keterlambatan (surcharge/late fee)** — Infrastruktur ada (`DebtService::addLateFee()`, `DebtHistory::recordLateFee()`, `Setting::KEY_LATE_FEE`) tapi method tidak pernah dipanggil dari billing logic. `CheckOverdue` command hanya update status, tidak apply fee.
10. [x] **Refund/credit notes** — Implemented: CreditNote model + CreditNoteService (create/approve/reject) + admin CRUD pages. Tipe: refund, credit, adjustment. Approval workflow (pending → approved/rejected).
11. [ ] **Customer contract/SLA tracking** — Tidak ada tracking kontrak, tanggal mulai/berakhir, perpanjangan otomatis.
12. [x] **Payment plan/cicilan** — Implemented: PaymentPlan + PaymentPlanInstallment model, PaymentPlanService (create/cancel/record), admin CRUD pages. 2-24 bulan, jadwal otomatis, progress tracking.
13. [x] **Invoice line items** — Implemented: InvoiceItem model + migration. Invoice generation otomatis buat line items (paket, diskon, PPN). Show page menampilkan items jika ada, fallback ke legacy view.
14. [x] **Invoice amendment** — Implemented: `InvoiceService::amendInvoice()` + endpoint + UI modal. Bisa ubah jumlah invoice non-paid/cancelled, debt otomatis disesuaikan.
15. [x] **Idempotency payment callback** — Fixed: `lockForUpdate()` + `DB::transaction()` di TripayService dan XenditService mencegah double payment dari concurrent callback.

---

## C. Notifikasi (5 item)

16. [ ] **SMS notification** — Config driver ada (Zenziva, Twilio, Nexmo, RajaSMS, NusaSMS) tapi class `SmsChannel` tidak ada. Method `sendSms()` direferensi di `SendNotificationJob` dan `TestNotification` tapi belum diimplementasi di `NotificationService`.
17. [x] **Notification scheduling** — Implemented: Job-based scheduling via queue. `SendPaymentReminderJob` (configurable days before due), `SendOverdueNotices` (days after due: 1, 3, 7). Retry 3x dengan backoff 60s.
18. [x] **Do-not-disturb hours** — Implemented sebagai "Business Hours": config `notification.business_hours` (08:00-20:00 WIB). `SendNotificationJob::isWithinBusinessHours()` delay notif ke jam kerja berikutnya jika di luar jam. Optional skip weekends.
19. [ ] **Notification analytics** — Hanya logging success/fail ke `billing_logs` via `BillingLog::logSystem()`. Tidak ada metrics dashboard (delivery rate, failure rate, open rate).
20. [x] **WhatsApp rate limiting** — Sudah diimplementasi: `rate_limit.per_minute`, `delay_ms`, `bulk_delay_seconds` di config, staggered delay di SendBulkNotificationJob.

---

## D. Laporan (6 item)

21. [ ] **Revenue forecast/projection** — Tidak ada prediksi pendapatan. Yang ada: historical trend (`getMonthlyRevenueTrend`), YoY comparison, MoM growth rate — tapi tidak ada forecasting algorithm.
22. [ ] **Churn analysis** — Partial: `getCustomerGrowthTrend()` tracking new vs churn (soft delete) per bulan. Belum ada: retention cohort, churn rate %, lifecycle analysis, churn prediction.
23. [ ] **Scheduled report delivery** — Tidak bisa kirim laporan otomatis via email secara berkala.
24. [x] **Export CSV/Excel** — Implemented: 9 export class (Maatwebsite Excel) fully functional — Invoice, Payment, Expense, UnpaidCustomers, CollectorReport, CollectorDetail, CollectorPerformanceSummary, AuditLog, CustomerTemplate. Semua punya routes + UI buttons.
25. [ ] **Custom report builder** — Report pre-defined (Revenue Overview, Collector Performance, Area Performance). Admin tidak bisa pilih kolom/filter sendiri. Untuk skala ISP ini, prioritas rendah.
26. [ ] **Bad debt analysis** — Write-off function ada (`DebtService::writeOffDebt()`, `DebtHistory::TYPE_WRITEOFF`), debt aging 5 kategori ada (`ReportService::getDebtAging()`). Belum ada: bad debt analytics dashboard, write-off trend, write-off reason categorization.

---

## E. UI/UX (6 item)

27. [x] **Browser/E2E testing** — Implemented: Playwright installed + 6 smoke tests (login pages, auth redirect, health check). `npm run test:e2e`.
28. [x] **Error boundary Vue** — Implemented: `ErrorBoundary.vue` component + `app.config.errorHandler` global handler. Terintegrasi di semua 3 layout.
29. [x] **Skeleton loading tidak merata** — Improved: SkeletonLoader ditambahkan ke 11 halaman baru (Customer Invoices/Payments, Collector Customers/Expenses/Settlement, Admin Area/User/Expense/Settlement/Isolation/ODP). Total 16 halaman.
30. [x] **Accessibility (WCAG)** — Implemented: aria-labels di sidebar/nav/modal/button, role="navigation"/"main"/"dialog", keyboard Escape untuk close modal. Semua 3 layout + Modal + ConfirmDialog.
31. [x] **Loading overlay global** — Implemented: `useNavigationLoading` composable + `LoadingOverlay` terintegrasi di semua 3 layout. Otomatis muncul saat navigasi > 250ms.
32. [x] **Invoice PDF download di portal** — Implemented: Route `portal/invoices/{invoice}/pdf` + `downloadInvoicePdf()` di PortalController. Tombol "Download PDF" di Customer/Invoices.vue. Validasi kepemilikan invoice.

---

## F. Infrastruktur (5 item)

33. [x] **Backup ke Google Drive** — Sudah diimplementasi dengan spatie/laravel-backup + OAuth2.
34. [x] **Monitoring & alerting** — Partial: Sentry aktif (error tracking + performance tracing, `traces_sample_rate` 0.1). Breadcrumb tracking untuk logs, cache, SQL, queue, HTTP. Belum ada: Prometheus/Datadog untuk infrastructure metrics.
35. [x] **Sentry DSN kosong** — Fixed: Sentry DSN dikonfigurasi di production. Backend (PHP) + Frontend (Vue.js) error monitoring aktif. Performance tracing enabled. **TODO:** Hapus 2 test event di dashboard Sentry (resolve/delete issue "This is a test exception sent from the Sentry Laravel SDK"), dan set `APP_ENV=production` di `.env` server agar environment tidak tercatat sebagai `local`.
36. [ ] **Log aggregation** — Config Papertrail + Slack channel ada di `config/logging.php` tapi tidak dikonfigurasi di `.env`. Default masih `daily` ke `storage/logs/`. Tidak ada ELK/Datadog.
37. [x] **Cache invalidation** — Fixed: 19 `Cache::forget()` calls. `DashboardService::clearDashboardCache()` menghapus 10 cache key sekaligus. Dipanggil dari `InvoiceObserver`, `PaymentObserver`, `CustomerObserver` pada event created/updated/deleted. Tambahan: Setting, Permission, IspInfo, ExpenseController juga invalidate cache masing-masing.

---

## G. Testing (3 item)

38. [ ] **Integration test end-to-end** — 186 test methods total (unit + feature). 6 Playwright smoke tests. Tidak ada test flow Payment → Isolation → Notification secara end-to-end.
39. [ ] **API contract test** — 19 basic API tests (Auth 6, Customer 5, Invoice 4, Payment 4). Tidak ada OpenAPI spec validation atau contract testing framework.
40. [ ] **Performance/load test** — Tidak ada load test (Locust, k6, Artillery). Tidak bisa validasi dashboard load time atau queue under load.

---

## H. Integrasi (2 item)

41. [ ] **Circuit breaker pattern** — TripayService dan XenditService hanya basic try-catch. Tidak ada circuit breaker state (OPEN/CLOSED/HALF_OPEN), fallback, atau auto-recovery.
42. [ ] **Mikrotik retry logic** — `RouterOSClient::read()` punya fixed 10ms delay (`usleep(10000)`) dengan max 100 attempts, tapi hanya untuk empty word reads. Tidak ada exponential backoff untuk connection failure atau isolation command.

---

## Ringkasan per Kategori

| Kategori | Total | Selesai | Sisa |
|----------|-------|---------|------|
| A. Keamanan | 7 | 6 | 1 |
| B. Fitur Bisnis | 8 | 5 | 3 |
| C. Notifikasi | 5 | 3 | 2 |
| D. Laporan | 6 | 1 | 5 |
| E. UI/UX | 6 | 6 | 0 |
| F. Infrastruktur | 5 | 4 | 1 |
| G. Testing | 3 | 0 | 3 |
| H. Integrasi | 2 | 0 | 2 |
| **Total** | **42** | **25** | **17** |

---

## Prioritas Rekomendasi

### Harus Segera (Critical) — SEMUA DONE
- ~~#2 Path Traversal receipt~~ (fixed)
- ~~#7 Callback signature validation~~ (sudah ada)
- ~~#15 Idempotency payment callback~~ (fixed)

### Penting (High)
- ~~#3 SQL injection risk DashboardService~~ (fixed: whitelist validation)
- #41 Circuit breaker payment gateway
- #42 Mikrotik retry logic

### Sedang (Medium)
- #8 Prorating
- #9 Denda keterlambatan
- #6 CSP nonce-based
- #38 Integration test E2E

### Bisa Nanti (Low)
- #11 Customer contract/SLA
- #16 SMS notification
- #19 Notification analytics
- #21 Revenue forecast
- #22 Churn analysis
- #23 Scheduled report delivery
- #25 Custom report builder
- #26 Bad debt analysis dashboard
- #36 Log aggregation
- #39 API contract test
- #40 Performance/load test
