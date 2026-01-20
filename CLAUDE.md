# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ISP Billing System for Java Indonusa - a complete billing solution with Mikrotik API integration, GenieACS (TR-069), Collector (Penagih) system, and Customer Portal.

**Tech Stack:** Laravel 11 + Vue 3 + Inertia.js + Tailwind CSS + MySQL 8 + Redis

## Common Commands

```bash
# Install dependencies
composer install
npm install

# Development
php artisan serve          # Start Laravel server
npm run dev                # Vite dev server with HMR

# Build
npm run build              # Production build

# Database
php artisan migrate --seed # Run migrations with seeders
php artisan migrate:fresh --seed  # Reset and reseed

# Queue worker (required for jobs)
php artisan queue:work redis

# Scheduler (for cron jobs)
php artisan schedule:run

# Testing
php artisan test                    # Run all tests
php artisan test --filter=TestName  # Run specific test

# Artisan Commands (Billing)
php artisan billing:generate-invoices      # Generate monthly invoices
php artisan billing:check-overdue          # Check overdue & isolate
php artisan billing:send-reminders         # Send payment reminders
php artisan mikrotik:status                # Check Mikrotik connection
php artisan notification:test {phone}      # Test notification
```

## Architecture

### Service Layer Pattern
Business logic lives in `app/Services/`, organized by domain:
- `Billing/` - InvoiceService, PaymentService, DebtService, DebtIsolationService
- `Mikrotik/` - RouterOSClient, MikrotikService (router API communication)
- `Notification/` - NotificationService with WhatsApp/SMS channels
- `Collector/` - CollectorService, ExpenseService (field collector features)
- `Customer/` - CustomerPortalService (customer self-service)

### Three User Portals
1. **Admin Panel** (`/admin/*`) - Full system management, role:admin middleware
2. **Collector Portal** (`/collector/*`) - Mobile-first for field collectors, data isolation per collector
3. **Customer Portal** (`/portal/*`) - OTP login via phone, invoice history, isolation info

### Key Business Logic

**Debt & Isolation System** (`DebtIsolationService`):
- Invoices auto-generated on 1st of each month
- Isolation triggered after 2 consecutive overdue months + 7 days grace
- **Rapel Exception**: Customers with `payment_behavior='rapel'` get extended tolerance (default 3 months)
- **Recent Payment Exception**: No isolation if payment received within 30 days
- Payment allocation uses FIFO (oldest invoice first)

**Mikrotik Integration**:
- PPPoE users: Profile changed to 'isolated', added to ISOLIR address list
- Static IP users: ARP entry disabled
- Auto-reopen access after payment clears overdue status

### Queue Jobs
Jobs in `app/Jobs/` handle async operations:
- `IsolateCustomerJob` / `ReopenCustomerJob` - Mikrotik operations
- `SendNotificationJob` / `SendBulkNotificationJob` - WhatsApp/SMS
- `ProcessDailyIsolationJob` - Scheduled isolation check

### Routes Structure
- `routes/admin.php` - Admin panel routes (resource controllers)
- `routes/collector.php` - Collector dashboard, payments, expenses, settlements
- `routes/customer.php` - Customer portal with OTP auth

### Frontend (Vue 3 + Inertia)
- Pages: `resources/js/Pages/{Admin,Collector,Customer}/*.vue`
- Components: `resources/js/Components/`
- Uses Pinia for state management, composables in `Composables/`

## Database

Key models with relationships:
- `Customer` belongs to Package, Area, Router, Collector (User)
- `Invoice` belongs to Customer, has many Payments
- `Payment` belongs to Customer, Invoice, Collector
- `DebtHistory` tracks all debt changes with audit trail
- `Expense` / `Settlement` for collector petty cash management

Customer payment behavior types: `regular`, `rapel`, `problematic`

## Environment Variables

Copy from `.env.example`, `.env.mikrotik.example`, `.env.notification.example`

Key configs:
- `MIKROTIK_*` - Router API connection
- `GENIEACS_NBI_URL` - TR-069 ACS server
- `WHATSAPP_*` / `SMS_*` - Notification gateways
- `BILLING_DUE_DAYS=20`, `BILLING_GRACE_DAYS=7`

## Scheduled Tasks (Kernel)

- **1st of month 00:01** - Generate invoices, add to debt
- **Daily 06:00** - Check overdue, process isolation
- **Daily 09:00** - Send payment reminders
- **Every 15 min** - Sync GenieACS devices
- **Daily 02:00** - Sync Mikrotik profiles
