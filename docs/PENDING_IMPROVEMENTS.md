# Pending Improvements - ISP Billing System

> Terakhir diupdate: 17 Maret 2026
> Total: 40 item (2 sudah selesai sebelumnya: APP_DEBUG=false, Backup Google Drive)

---

## Status Legend

- ⬜ Belum dikerjakan
- ✅ Selesai

---

## A. Keamanan (8 item)

| # | Item | Prioritas | Status |
|---|------|-----------|--------|
| 1 | 2FA/MFA untuk admin & collector (TOTP/authenticator) | **P0** | ⬜ |
| 2 | IP Whitelist untuk akses admin panel | P1 | ⬜ |
| 3 | Lockout setelah N kali gagal login | P1 | ⬜ |
| 4 | PII masking di log (no HP, email, NIK) | P2 | ⬜ |
| 5 | API key rotation & scope-based permissions | P2 | ⬜ |
| 6 | WAF (Web Application Firewall) | P2 | ✅ |
| 7 | Certificate pinning untuk API payment gateway | P2 | ⬜ |
| 8 | HSM/Key management | P2 | ⬜ |

> Session timeout sudah dikerjakan (17 Maret 2026) — `SESSION_EXPIRE_ON_CLOSE=true`, `SESSION_LIFETIME=30`

## B. Fitur Bisnis (7 item)

| # | Item | Prioritas | Status |
|---|------|-----------|--------|
| 9 | Proration (tagihan pro-rata mid-month) | P1 | ⬜ |
| 10 | Package upgrade/downgrade flow | P1 | ⬜ |
| 11 | Kontrak/perjanjian pelanggan (durasi, auto-renewal) | P1 | ⬜ |
| 12 | Refund mechanism | P2 | ⬜ |
| 13 | Promo/kupon/campaign-based discount | P2 | ⬜ |
| 14 | Usage-based billing (overage charges) | P2 | ⬜ |
| 15 | Denda keterlambatan otomatis | P1 | ⬜ |

## C. Notifikasi (5 item)

| # | Item | Prioritas | Status |
|---|------|-----------|--------|
| 16 | SMS channel (fallback jika WA gagal) | P1 | ⬜ |
| 17 | Push notification (mobile) | P2 | ⬜ |
| 18 | Per-customer notification preferences (opt-out) | P2 | ⬜ |
| 19 | Delivery confirmation / read receipt tracking | P2 | ⬜ |
| 20 | Rich media & interactive buttons di WA | P2 | ⬜ |

## D. Laporan (7 item)

| # | Item | Prioritas | Status |
|---|------|-----------|--------|
| 21 | Accounts Receivable aging report (per bracket umur) | **P0** | ⬜ |
| 22 | Laporan keuangan harian/bulanan (P&L summary) | P1 | ⬜ |
| 23 | Cash flow report | P1 | ⬜ |
| 24 | Churn/attrition report (tingkat berhenti langganan) | P2 | ⬜ |
| 25 | Laporan PPN/pajak untuk compliance | P1 | ⬜ |
| 26 | Report scheduling (email otomatis) | P2 | ⬜ |
| 27 | Revenue forecasting | P2 | ⬜ |

## E. UI/UX (5 item)

| # | Item | Prioritas | Status |
|---|------|-----------|--------|
| 28 | Dark mode (lengkap semua halaman) | P2 | ⬜ |
| 29 | Bulk actions (bulk payment, bulk suspend) | P1 | ⬜ |
| 30 | Advanced filtering + saved filters | P2 | ⬜ |
| 31 | Multi-language / i18n | P2 | ⬜ |
| 32 | Smart search / fuzzy search + autocomplete | P2 | ⬜ |

## F. Infrastruktur (4 item)

| # | Item | Prioritas | Status |
|---|------|-----------|--------|
| 33 | Monitoring & alerting (uptime, error rate) | **P0** | ⬜ |
| 34 | CDN untuk static assets | P2 | ⬜ |
| 35 | Log aggregation (ELK/CloudWatch) | P2 | ⬜ |
| 36 | Backup restore procedure (documented + tested) | P1 | ⬜ |

## G. Testing (3 item)

| # | Item | Prioritas | Status |
|---|------|-----------|--------|
| 37 | Browser/E2E tests (Dusk/Playwright) | **P0** | ⬜ |
| 38 | Vue component unit tests (Vitest) | P1 | ⬜ |
| 39 | Performance/load testing (k6/JMeter) | P2 | ⬜ |

## H. Integrasi (3 item)

| # | Item | Prioritas | Status |
|---|------|-----------|--------|
| 40 | Integrasi software akuntansi (Accurate/Jurnal) | P2 | ⬜ |
| 41 | Advanced Mikrotik (QoS queue, hotspot portal) | P2 | ⬜ |
| 42 | Map/GIS visualization (peta pelanggan di admin) | P1 | ⬜ |

---

## Ringkasan Prioritas

| Level | Jumlah | Item Utama |
|-------|--------|------------|
| **P0** | 4 | 2FA, A/R Aging Report, Monitoring & Alerting, E2E Tests |
| **P1** | 14 | IP whitelist, lockout, proration, upgrade/downgrade, kontrak, denda, SMS, P&L, cash flow, PPN, bulk actions, backup restore, Vue tests, GIS map |
| **P2** | 22 | PII masking, API rotation, WAF, cert pinning, HSM, refund, promo, usage billing, push notif, preferences, delivery confirm, rich media, churn, report scheduling, forecasting, dark mode, advanced filter, i18n, smart search, CDN, log aggregation, akuntansi, advanced Mikrotik |

---

## Yang Sudah Selesai (Sebelumnya)

- ✅ APP_DEBUG=false di production
- ✅ Backup ke Google Drive (OAuth2, 15GB gratis)
- ✅ Session timeout (expire_on_close + 30 menit lifetime)
- ✅ Double-submit prevention (payment collector)
- ✅ Waktu Server di admin dashboard
- ✅ Security Headers middleware
- ✅ Rate Limiting (login, API, webhook)
- ✅ Sentry error tracking
- ✅ REST API + Sanctum + Swagger docs
- ✅ RADIUS integration
- ✅ Xendit & Tripay payment gateway
- ✅ WatZap.id WhatsApp integration (menunggu API key)
- ✅ Sidebar restructure
