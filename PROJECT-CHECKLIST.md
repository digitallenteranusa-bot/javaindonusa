# Project Checklist - ISP Billing System Java Indonusa

> Terakhir diperbarui: 2026-02-28

---

## FITUR UTAMA (Core Features)

### Admin Panel
- [x] Dashboard Admin (`Pages/Admin/Dashboard/Dashboard.vue`)
- [x] Manajemen Customer — CRUD + Show (`Pages/Admin/Customer/`)
- [x] Manajemen Paket/Package — CRUD (`Pages/Admin/Package/`)
- [x] Manajemen Area — CRUD (`Pages/Admin/Area/`)
- [x] Manajemen Router — CRUD + VPN Config (`Pages/Admin/Router/`)
- [x] Manajemen Router Brand (`Pages/Admin/RouterBrand/`)
- [x] Manajemen User/Staff — CRUD (`Pages/Admin/User/`)
- [x] Manajemen Role & Permission (`Pages/Admin/Role/`)
- [x] Manajemen ODP — CRUD (`Pages/Admin/Odp/`)
- [x] Manajemen OLT — CRUD + Show (`Pages/Admin/Olt/`)
- [x] Manajemen RADIUS Server — CRUD (`Pages/Admin/RadiusServer/`)
- [x] Invoice — List + Detail (`Pages/Admin/Invoice/`)
- [x] Payment — CRUD + Detail (`Pages/Admin/Payment/`)
- [x] Isolasi Pelanggan — List (`Pages/Admin/Isolation/`)
- [x] Monitoring Belum Bayar / Unpaid (`Pages/Admin/Billing/UnpaidMonitoring.vue`)
- [x] Expense Collector — List + Detail (`Pages/Admin/Expense/`)
- [x] Settlement Collector — List + Detail (`Pages/Admin/Settlement/`)
- [x] Finance Dashboard + Operational Expenses (`Pages/Admin/Finance/`)
- [x] Collector Performance (`Pages/Admin/Finance/CollectorPerformance.vue`)
- [x] Report — Area & Collector Performance (`Pages/Admin/Report/`)
- [x] Revenue Analytics (`Pages/Admin/Analytics/RevenueAnalytics.vue`)
- [x] Network Monitoring (`Pages/Admin/Analytics/NetworkMonitoring.vue`)
- [x] VPS Monitoring (`Pages/Admin/Analytics/VpsMonitoring.vue`)
- [x] Device Management / GenieACS (`Pages/Admin/Device/`)
- [x] Mapping / Peta (`Pages/Admin/Mapping/`)
- [x] Broadcast Notifikasi (`Pages/Admin/Broadcast/`)
- [x] Audit Log (`Pages/Admin/AuditLog/`)
- [x] System Settings (`Pages/Admin/Settings/`)
- [x] System Backup & Restore (`Pages/Admin/System/`)
- [x] VPN Server Management (`Pages/Admin/VpnServer/`)

### Collector Portal (Mobile-First)
- [x] Dashboard Collector (`Pages/Collector/Dashboard.vue`)
- [x] List Pelanggan (`Pages/Collector/Customers.vue`)
- [x] Detail Pelanggan (`Pages/Collector/CustomerDetail.vue`)
- [x] Form Pelanggan Baru (`Pages/Collector/CustomerForm.vue`)
- [x] Pencatatan Pengeluaran (`Pages/Collector/Expenses.vue`)
- [x] Settlement / Setor Uang (`Pages/Collector/Settlement.vue`)
- [x] Peta Pelanggan (`Pages/Collector/Mapping.vue`)

### Customer Portal
- [x] Login OTP via Telepon (`Pages/Customer/Login.vue`)
- [x] Verifikasi OTP (`Pages/Customer/VerifyOTP.vue`)
- [x] Dashboard Pelanggan (`Pages/Customer/Dashboard.vue`)
- [x] Riwayat Invoice (`Pages/Customer/Invoices.vue`)
- [x] Riwayat Pembayaran (`Pages/Customer/Payments.vue`)
- [x] Halaman Pembayaran Online (`Pages/Customer/Pay.vue`)
- [x] Halaman Info Isolasi (`Pages/Customer/IsolationPage.vue`)

---

## SERVICE LAYER (Business Logic)

### Billing Services
- [x] InvoiceService — generate, cancel, statistics
- [x] PaymentService — FIFO allocation, cancel, reopen trigger
- [x] DebtService — tracking, history, kalkulasi hutang
- [x] DebtIsolationService — cek overdue, isolasi otomatis, rapel exception

### Mikrotik Integration
- [x] RouterOSClient — socket-based RouterOS API client
- [x] MikrotikService — connect, isolate, reopen, sync profiles

### Notification
- [x] NotificationService — WhatsApp + Email
- [x] WhatsAppChannel — multi-driver (Fonnte, Meta, Wablas, Dripsender, Mekari)
- [ ] SMS Channel — belum ada class terpisah (low priority, WhatsApp sudah cukup)

### Payment Gateway
- [x] TripayService — payment gateway integration + callback
- [x] XenditService — payment gateway integration + callback

### GenieACS (TR-069)
- [x] GenieAcsService — sync devices, remote management

### Collector
- [x] CollectorService — data penagih, assignment
- [x] ExpenseService — pencatatan pengeluaran lapangan

### Customer Portal
- [x] CustomerPortalService — self-service, OTP auth

### Admin/Dashboard
- [x] DashboardService — statistik overview + caching (P3)
- [x] AdminAuditService — logging aksi admin
- [x] CollectorPerformanceService — report kinerja penagih + caching (P3)
- [x] FinanceService — laporan keuangan
- [x] NetworkMonitoringService — monitoring jaringan
- [x] ReportService — laporan area & collector
- [x] UpdateService — system update
- [x] VpsMonitoringService — monitoring VPS

### VPN
- [x] OpenVpnService — OpenVPN server management
- [x] WireGuardService — WireGuard server management

### Other
- [x] PdfService — generate PDF invoice, receipt, report

---

## JOBS (Async/Queue)
- [x] IsolateCustomerJob — isolasi via Mikrotik
- [x] ReopenCustomerJob — buka akses via Mikrotik
- [x] ProcessDailyIsolationJob — cek isolasi harian
- [x] SendNotificationJob — kirim notifikasi satuan
- [x] SendBulkNotificationJob — kirim notifikasi massal
- [x] SendPaymentReminderJob — reminder pembayaran

---

## ARTISAN COMMANDS
- [x] `billing:generate-invoices` — Generate invoice bulanan
- [x] `billing:check-overdue` — Cek keterlambatan
- [x] `billing:process-isolation` — Proses isolasi
- [x] `billing:send-reminders` — Kirim reminder
- [x] `billing:send-overdue` — Kirim notifikasi overdue
- [x] `genieacs:sync-devices` — Sync perangkat TR-069
- [x] `genieacs:status` — Status GenieACS
- [x] `mikrotik:status` — Status koneksi Mikrotik
- [x] `mikrotik:isolate` — Manual isolasi via CLI
- [x] `notification:test` — Test kirim notifikasi
- [x] `data:reset` — Reset semua data
- [x] `data:reset-customers` — Reset data pelanggan
- [x] `odp:recalculate-ports` — Recalculate port ODP

---

## SCHEDULED TASKS (Cron)
- [x] Generate invoices — tanggal 1, 00:01 WIB
- [x] Check overdue — daily 06:00 WIB
- [x] Process isolation — daily 06:30 WIB
- [x] Send reminders — daily 09:00 WIB
- [x] Send overdue notices — daily 10:00 WIB
- [x] Mikrotik status — setiap 5 menit
- [x] GenieACS sync — setiap 15 menit (conditional)
- [x] Cleanup old backups — weekly Minggu 02:00
- [x] `log:clear` — clear old log files

---

## DATABASE & MIGRATIONS
- [x] 46 migration files
- [x] Foreign keys pada tabel inti (customers, invoices, payments, expenses, settlements)
- [x] Indexes pada tabel inti (customers, invoices, payments)
- [x] Unique constraints (invoice_number, payment_number, pppoe_username, dll)
- [x] Soft deletes pada model sensitif (Customer, Invoice, Payment)
- [x] Index pada `admin_audit_logs` (admin_id, created_at, module, action)
- [x] Index pada `billing_logs` (user_id, action, loggable_type+id, created_at)
- [x] Index pada `debt_histories` (invoice_id, payment_id) — migration `2026_02_26_000001`
- [x] Index pada `collection_logs` (payment_id) — migration `2026_02_26_000001`
- [x] Fix duplikat timestamp migration `000012` — known issue, no FK dependency

---

## EXPORT & IMPORT
- [x] AdminAuditLogExport
- [x] CollectorDetailExport
- [x] CollectorPerformanceSummaryExport
- [x] CollectorReportExport
- [x] CustomerTemplateExport
- [x] ExpenseExport
- [x] InvoiceExport
- [x] PaymentExport
- [x] UnpaidCustomersExport
- [x] CustomerImport (import pelanggan dari Excel)

---

## SECURITY
- [x] SecurityHeaders middleware (X-Frame, X-Content-Type, CSP, HSTS)
- [x] CSRF protection + handler 419
- [x] Rate limiting pada OTP (5/min request, 10/min verify)
- [x] Rate limiting pada login admin (5/min per IP) — P3
- [x] Rate limiting pada webhook callback (30/min per IP) — P3
- [x] Rate limiting pada pembayaran online (5/min per IP) — P3
- [x] Rate limiting pada WhatsApp reminder collector (10/min) — P3
- [x] Role-based access (CheckRole middleware — 5 roles)
- [x] Permission-based access (CheckPermission middleware)
- [x] Password hashing (Hash::make)
- [x] PPPoE password encryption (Crypt::encrypt/decrypt)
- [x] Force HTTPS di production
- [x] Customer auth via OTP (CustomerAuth middleware)

---

## DOKUMENTASI
- [x] CLAUDE.md — panduan development
- [x] 16 file dokumentasi di `docs/`
- [x] Instalasi Linux + Windows
- [x] Database schema SQL
- [x] Alur integrasi
- [x] Logika tagihan
- [x] Struktur folder
- [x] Database penagih
- [x] Fitur penagih & pelanggan
- [x] VPN setup
- [x] Captive portal isolir
- [x] Database backup & restore
- [x] Firewall VPS
- [x] High availability setup
- [x] Panduan Meta WhatsApp
- [x] Reset data
- [x] VPN Server Installation

---

## FRONTEND
- [x] 3 Layout (Admin, Collector, Customer)
- [x] 74 halaman Vue
- [x] Chart.js untuk grafik/analytics
- [x] Leaflet untuk peta/mapping
- [x] Offline detection (OfflineNotice component — Capacitor)
- [x] Inertia progress bar
- [x] Composable: useNative.js, usePermission.js
- [x] Loading state konsisten — SkeletonLoader, LoadingSpinner, LoadingOverlay components
- [x] Skeleton loader (diterapkan di 5 halaman berat: Dashboard, Customer, Invoice, Payment, Collector)
- [x] Global loading overlay (LoadingOverlay component)
- [ ] Pinia state management — tidak terinstall (disebut di CLAUDE.md tapi tidak dipakai)

---

## BACKUP & DEPLOYMENT
- [x] Backup via UI Admin (System page)
- [x] Backup saat deploy (mysqldump di deploy.sh)
- [x] Deploy script (`scripts/deploy.sh`)
- [x] Install script (`scripts/install.sh`)
- [x] Status script (`scripts/status.sh`)
- [x] Supervisor config (`scripts/supervisor.conf`)
- [ ] Cloud backup (S3, Backblaze)

---

## ENGINEERING QUALITY (P0–P3)

### P0 — Testing + CI/CD (commit `96dea0d`) — SELESAI
- [x] 165 tests (unit + feature + jobs), 370 assertions
- [x] Feature tests untuk controller/route (7 file)
- [x] Unit tests untuk service (13 file)
- [x] Test untuk Jobs (6 file)
- [x] Test untuk webhook callback (Tripay, Xendit)
- [x] 6 factory baru (Area, Router, Expense, Settlement, DebtHistory, Odp)
- [x] GitHub Actions CI/CD pipeline

### P1 — Form Requests, Events/Listeners, DB Indexes (commit `01f8ed8`) — SELESAI
- [x] 28 Form Request classes
- [x] 4 Events + 4 Listeners
- [x] DB indexes pada debt_histories + collection_logs
- [x] `log:clear` artisan command

### P2 — Exceptions, Observers, Health Check, Mailable, Docker (commit `e7dea7f`) — SELESAI
- [x] 12 Custom Exception classes
- [x] 3 Observers (Customer, Invoice, Payment)
- [x] Health Check endpoint (`GET /api/health`)
- [x] 9 Mailable classes + 10 Blade email templates
- [x] Docker + docker-compose.yml (PHP 8.2-FPM + Nginx + Node 20)

### P3 — Rate Limiting + Dashboard Caching (commit `ed5cbaf`) — SELESAI
- [x] Named rate limiters (admin-login 5/min, webhook 30/min)
- [x] Throttle pada login, webhook, WhatsApp, payment
- [x] Dashboard caching (Cache::remember, TTL 1–15 min)
- [x] N+1 query optimization (getCollectorStats, getRevenueTrend, getCustomerTrend)
- [x] Cache invalidation via Observers

### P4 — Sentry, Skeleton/Loading, REST API, Swagger — SELESAI
- [x] Sentry error monitoring (sentry/sentry-laravel + @sentry/vue)
- [x] Frontend Skeleton/Loading (3 komponen: SkeletonLoader, LoadingSpinner, LoadingOverlay)
- [x] Skeleton diterapkan di 5 halaman berat
- [x] REST API v1 (/api/v1/) — Sanctum token auth, 11 endpoints
- [x] 7 API Controllers, 5 API Resources, 2 Form Requests
- [x] Role-based scoping (penagih hanya lihat pelanggan sendiri)
- [x] API rate limiting (60 req/min per user)
- [x] 19 API tests (auth, customer, invoice, payment)
- [x] User model: HasApiTokens trait
- [x] Swagger/OpenAPI via Scramble (/docs/api)
- [x] Gate viewApiDocs: local → all, production → admin only

---

## YANG BELUM DIKERJAKAN (Remaining)

### Nice-to-Have (tidak blocking production)
- [ ] SMS Channel — class terpisah (WhatsApp sudah mencukupi)
- [ ] Laravel Policies — object-level auth (sudah pakai Role+Permission middleware)
- [x] API Resources — REST API v1 (Sanctum auth, 7 controller, 5 resource, 19 tests)
- [x] Swagger/OpenAPI — Scramble auto-docs di `/docs/api`
- [x] Error Monitoring — Sentry (Laravel + Vue)
- [x] Frontend loading states + skeleton loaders (3 komponen + 5 halaman)
- [ ] Pinia state management — install atau hapus referensi di CLAUDE.md
- [ ] Cloud Backup — S3/Backblaze integration

---

## RINGKASAN

| Kategori | Sudah | Belum | Persentase |
|----------|-------|-------|------------|
| Fitur Admin | 30 | 0 | 100% |
| Fitur Collector | 7 | 0 | 100% |
| Fitur Customer Portal | 7 | 0 | 100% |
| Services | 24 | 1 (SMS) | 96% |
| Jobs | 6 | 0 | 100% |
| Artisan Commands | 14 | 0 | 100% |
| Database/Migration | 49 | 0 | 100% |
| Export/Import | 10 | 0 | 100% |
| Security | 13 | 0 | 100% |
| Dokumentasi | 17 | 0 | 100% |
| Testing | 184 tests | 0 | 100% |
| CI/CD | 1 workflow | 0 | 100% |
| Engineering Quality | P0-P4 | 0 | 100% |
| Frontend Polish | 10 | 1 | 91% |
| DevOps | 6 | 1 (cloud backup) | 86% |

**Kesimpulan:** Semua fitur bisnis, engineering quality (P0–P3), testing, CI/CD, security, dan database sudah 100% selesai. Yang tersisa hanya nice-to-have: frontend polish (skeleton/loading) dan cloud backup.
