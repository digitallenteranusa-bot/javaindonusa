# Struktur Folder Project
## Billing ISP Java Indonusa - Laravel 11

---

## Struktur Lengkap

```
billing-isp/
├── app/
│   ├── Actions/                    # Action Classes (single responsibility)
│   │   ├── Customer/
│   │   │   ├── CreateCustomerAction.php
│   │   │   ├── UpdateCustomerAction.php
│   │   │   ├── IsolateCustomerAction.php
│   │   │   └── OpenAccessAction.php
│   │   ├── Invoice/
│   │   │   ├── GenerateInvoiceAction.php
│   │   │   └── CancelInvoiceAction.php
│   │   └── Payment/
│   │       └── ProcessPaymentAction.php
│   │
│   ├── Console/
│   │   └── Commands/
│   │       ├── Billing/
│   │       │   ├── GenerateMonthlyInvoices.php
│   │       │   ├── CheckOverdueInvoices.php
│   │       │   └── SendBillingReminders.php
│   │       ├── Mikrotik/
│   │       │   ├── SyncProfiles.php
│   │       │   └── TestConnection.php
│   │       └── GenieAcs/
│   │           └── SyncDevices.php
│   │
│   ├── Contracts/                  # Interface Definitions
│   │   ├── RouterServiceInterface.php
│   │   ├── NotificationServiceInterface.php
│   │   └── PaymentGatewayInterface.php
│   │
│   ├── DataTransferObjects/        # DTO untuk type safety
│   │   ├── CustomerData.php
│   │   ├── InvoiceData.php
│   │   ├── PaymentData.php
│   │   └── RouterCommandData.php
│   │
│   ├── Enums/                      # PHP 8.1 Enums
│   │   ├── CustomerStatus.php
│   │   ├── InvoiceStatus.php
│   │   ├── PaymentMethod.php
│   │   ├── ConnectionType.php
│   │   ├── RouterBrand.php
│   │   └── LogType.php
│   │
│   ├── Events/
│   │   ├── Customer/
│   │   │   ├── CustomerCreated.php
│   │   │   ├── CustomerIsolated.php
│   │   │   └── CustomerAccessOpened.php
│   │   ├── Invoice/
│   │   │   ├── InvoiceGenerated.php
│   │   │   └── InvoiceOverdue.php
│   │   └── Payment/
│   │       └── PaymentReceived.php
│   │
│   ├── Exceptions/                 # Custom Exceptions
│   │   ├── Billing/
│   │   │   ├── InvoiceAlreadyExistsException.php
│   │   │   └── InsufficientPaymentException.php
│   │   ├── Router/
│   │   │   ├── RouterConnectionException.php
│   │   │   └── RouterCommandException.php
│   │   └── GenieAcs/
│   │       └── DeviceNotFoundException.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/               # API Controllers
│   │   │   │   ├── V1/
│   │   │   │   │   ├── CustomerController.php
│   │   │   │   │   ├── InvoiceController.php
│   │   │   │   │   ├── PaymentController.php
│   │   │   │   │   └── WebhookController.php
│   │   │   │   └── AuthController.php
│   │   │   │
│   │   │   └── Web/               # Web Controllers
│   │   │       ├── DashboardController.php
│   │   │       ├── CustomerController.php
│   │   │       ├── InvoiceController.php
│   │   │       ├── PaymentController.php
│   │   │       ├── RouterController.php
│   │   │       ├── PackageController.php
│   │   │       ├── AreaController.php
│   │   │       ├── ReportController.php
│   │   │       └── SettingController.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── CheckRole.php
│   │   │   ├── ApiRateLimiter.php
│   │   │   └── LogActivity.php
│   │   │
│   │   ├── Requests/              # Form Requests (Validation)
│   │   │   ├── Customer/
│   │   │   │   ├── StoreCustomerRequest.php
│   │   │   │   └── UpdateCustomerRequest.php
│   │   │   ├── Invoice/
│   │   │   │   └── StoreInvoiceRequest.php
│   │   │   ├── Payment/
│   │   │   │   └── StorePaymentRequest.php
│   │   │   └── Router/
│   │   │       └── StoreRouterRequest.php
│   │   │
│   │   └── Resources/             # API Resources (JSON transformation)
│   │       ├── CustomerResource.php
│   │       ├── CustomerCollection.php
│   │       ├── InvoiceResource.php
│   │       ├── PaymentResource.php
│   │       └── DebtHistoryResource.php
│   │
│   ├── Jobs/                      # Queue Jobs
│   │   ├── Customer/
│   │   │   ├── IsolateCustomerJob.php
│   │   │   └── OpenAccessJob.php
│   │   ├── Invoice/
│   │   │   └── GenerateInvoiceJob.php
│   │   ├── Notification/
│   │   │   ├── SendWhatsAppJob.php
│   │   │   ├── SendSmsJob.php
│   │   │   └── SendEmailJob.php
│   │   └── Router/
│   │       ├── ExecuteRouterCommandJob.php
│   │       └── SyncRouterProfilesJob.php
│   │
│   ├── Listeners/                 # Event Listeners
│   │   ├── Invoice/
│   │   │   ├── SendInvoiceNotification.php
│   │   │   └── AddInvoiceToDebtHistory.php
│   │   └── Payment/
│   │       ├── UpdateInvoiceStatus.php
│   │       ├── UpdateCustomerDebt.php
│   │       └── CheckAutoOpenAccess.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Customer.php
│   │   ├── Package.php
│   │   ├── Router.php
│   │   ├── Area.php
│   │   ├── Invoice.php
│   │   ├── Payment.php
│   │   ├── DebtHistory.php
│   │   ├── BillingLog.php
│   │   ├── CustomerDevice.php
│   │   ├── RouterCommand.php
│   │   ├── Notification.php
│   │   ├── Setting.php
│   │   └── Traits/               # Model Traits
│   │       ├── HasCustomerId.php
│   │       ├── Loggable.php
│   │       └── HasDebt.php
│   │
│   ├── Notifications/             # Laravel Notifications
│   │   ├── InvoiceGeneratedNotification.php
│   │   ├── PaymentReceivedNotification.php
│   │   ├── IsolationWarningNotification.php
│   │   └── AccessOpenedNotification.php
│   │
│   ├── Observers/                 # Model Observers
│   │   ├── CustomerObserver.php
│   │   ├── InvoiceObserver.php
│   │   └── PaymentObserver.php
│   │
│   ├── Policies/                  # Authorization Policies
│   │   ├── CustomerPolicy.php
│   │   ├── InvoicePolicy.php
│   │   ├── PaymentPolicy.php
│   │   └── RouterPolicy.php
│   │
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php
│   │   ├── EventServiceProvider.php
│   │   ├── RouteServiceProvider.php
│   │   └── RepositoryServiceProvider.php
│   │
│   ├── Repositories/              # Repository Pattern
│   │   ├── Contracts/
│   │   │   ├── CustomerRepositoryInterface.php
│   │   │   ├── InvoiceRepositoryInterface.php
│   │   │   └── PaymentRepositoryInterface.php
│   │   └── Eloquent/
│   │       ├── CustomerRepository.php
│   │       ├── InvoiceRepository.php
│   │       └── PaymentRepository.php
│   │
│   ├── Services/                  # Business Logic Services
│   │   ├── Billing/
│   │   │   ├── InvoiceService.php
│   │   │   ├── PaymentService.php
│   │   │   └── DebtService.php
│   │   ├── Router/
│   │   │   ├── MikrotikService.php
│   │   │   ├── RouterCommandService.php
│   │   │   └── ProfileSyncService.php
│   │   ├── Device/
│   │   │   └── GenieAcsService.php
│   │   ├── Notification/
│   │   │   ├── NotificationService.php
│   │   │   ├── WhatsAppService.php
│   │   │   └── SmsService.php
│   │   ├── Report/
│   │   │   ├── RevenueReportService.php
│   │   │   ├── CustomerReportService.php
│   │   │   └── DebtReportService.php
│   │   └── BillingLogService.php
│   │
│   └── View/
│       └── Components/            # Blade Components
│           ├── Layout/
│           │   ├── App.php
│           │   ├── Sidebar.php
│           │   └── Header.php
│           ├── Forms/
│           │   ├── Input.php
│           │   ├── Select.php
│           │   └── DatePicker.php
│           └── Tables/
│               ├── DataTable.php
│               └── Pagination.php
│
├── bootstrap/
│   ├── app.php
│   ├── cache/
│   └── providers.php
│
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── billing.php               # Konfigurasi Billing
│   ├── database.php
│   ├── mikrotik.php              # Konfigurasi Mikrotik API
│   ├── genieacs.php              # Konfigurasi GenieACS
│   ├── notification.php          # Konfigurasi Notifikasi
│   ├── queue.php
│   └── sanctum.php
│
├── database/
│   ├── factories/
│   │   ├── CustomerFactory.php
│   │   ├── InvoiceFactory.php
│   │   └── PaymentFactory.php
│   ├── migrations/
│   │   ├── 0001_create_users_table.php
│   │   ├── 0002_create_areas_table.php
│   │   ├── 0003_create_packages_table.php
│   │   ├── 0004_create_routers_table.php
│   │   ├── 0005_create_customers_table.php
│   │   ├── 0006_create_invoices_table.php
│   │   ├── 0007_create_payments_table.php
│   │   ├── 0008_create_debt_history_table.php
│   │   ├── 0009_create_billing_logs_table.php
│   │   ├── 0010_create_customer_devices_table.php
│   │   ├── 0011_create_router_commands_table.php
│   │   ├── 0012_create_settings_table.php
│   │   └── 0013_create_notifications_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── UserSeeder.php
│       ├── PackageSeeder.php
│       ├── AreaSeeder.php
│       ├── SettingSeeder.php
│       └── DemoDataSeeder.php
│
├── public/
│   ├── index.php
│   ├── favicon.ico
│   ├── robots.txt
│   └── build/                    # Vite compiled assets
│
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.js
│   │   ├── Pages/               # Inertia Pages (Vue/React)
│   │   │   ├── Dashboard.vue
│   │   │   ├── Auth/
│   │   │   │   ├── Login.vue
│   │   │   │   └── Profile.vue
│   │   │   ├── Customers/
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Create.vue
│   │   │   │   ├── Edit.vue
│   │   │   │   └── Show.vue
│   │   │   ├── Invoices/
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Create.vue
│   │   │   │   └── Show.vue
│   │   │   ├── Payments/
│   │   │   │   ├── Index.vue
│   │   │   │   └── Create.vue
│   │   │   ├── Routers/
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Create.vue
│   │   │   │   └── Show.vue
│   │   │   ├── Packages/
│   │   │   │   └── Index.vue
│   │   │   ├── Reports/
│   │   │   │   ├── Revenue.vue
│   │   │   │   ├── Customers.vue
│   │   │   │   └── Debts.vue
│   │   │   └── Settings/
│   │   │       ├── General.vue
│   │   │       ├── Billing.vue
│   │   │       └── Notifications.vue
│   │   ├── Components/          # Reusable Vue Components
│   │   │   ├── Layout/
│   │   │   │   ├── AppLayout.vue
│   │   │   │   ├── Sidebar.vue
│   │   │   │   ├── Header.vue
│   │   │   │   └── Footer.vue
│   │   │   ├── Forms/
│   │   │   │   ├── TextInput.vue
│   │   │   │   ├── SelectInput.vue
│   │   │   │   ├── DatePicker.vue
│   │   │   │   └── CurrencyInput.vue
│   │   │   ├── Tables/
│   │   │   │   ├── DataTable.vue
│   │   │   │   ├── Pagination.vue
│   │   │   │   └── TableActions.vue
│   │   │   ├── Cards/
│   │   │   │   ├── StatCard.vue
│   │   │   │   ├── CustomerCard.vue
│   │   │   │   └── InvoiceCard.vue
│   │   │   ├── Modals/
│   │   │   │   ├── ConfirmModal.vue
│   │   │   │   └── PaymentModal.vue
│   │   │   └── Common/
│   │   │       ├── Badge.vue
│   │   │       ├── Button.vue
│   │   │       ├── Alert.vue
│   │   │       └── Spinner.vue
│   │   ├── Composables/         # Vue Composables
│   │   │   ├── useCustomer.js
│   │   │   ├── useInvoice.js
│   │   │   ├── usePayment.js
│   │   │   └── useNotification.js
│   │   └── Stores/              # Pinia Stores (State Management)
│   │       ├── auth.js
│   │       ├── customer.js
│   │       └── notification.js
│   ├── views/
│   │   ├── app.blade.php        # Main Inertia template
│   │   ├── emails/              # Email templates
│   │   │   ├── invoice.blade.php
│   │   │   └── payment.blade.php
│   │   └── pdf/                 # PDF templates
│   │       ├── invoice.blade.php
│   │       └── receipt.blade.php
│   └── lang/
│       └── id/                  # Bahasa Indonesia
│           ├── validation.php
│           ├── auth.php
│           ├── billing.php
│           └── customer.php
│
├── routes/
│   ├── api.php                  # API Routes
│   ├── web.php                  # Web Routes
│   ├── console.php              # Console Routes
│   └── channels.php             # Broadcast Channels
│
├── storage/
│   ├── app/
│   │   ├── public/
│   │   └── exports/             # Export files (Excel, PDF)
│   ├── framework/
│   └── logs/
│
├── tests/
│   ├── Feature/
│   │   ├── Customer/
│   │   │   ├── CustomerCreationTest.php
│   │   │   └── CustomerIsolationTest.php
│   │   ├── Invoice/
│   │   │   ├── InvoiceGenerationTest.php
│   │   │   └── InvoicePaymentTest.php
│   │   └── Api/
│   │       └── CustomerApiTest.php
│   ├── Unit/
│   │   ├── Services/
│   │   │   ├── InvoiceServiceTest.php
│   │   │   ├── PaymentServiceTest.php
│   │   │   └── DebtServiceTest.php
│   │   └── Models/
│   │       ├── CustomerTest.php
│   │       └── InvoiceTest.php
│   └── TestCase.php
│
├── .env.example
├── .gitignore
├── artisan
├── composer.json
├── package.json
├── phpunit.xml
├── tailwind.config.js
├── vite.config.js
└── README.md
```

---

## Penjelasan Struktur

### 1. Actions (`app/Actions/`)
Action classes untuk operasi spesifik dengan single responsibility:
- Mudah di-test
- Dapat di-reuse di controller, command, atau job
- Memisahkan logika bisnis dari controller

### 2. Services (`app/Services/`)
Business logic layer yang complex:
- **Billing/** - Logika invoice, payment, debt
- **Router/** - Komunikasi dengan Mikrotik
- **Device/** - Integrasi GenieACS
- **Notification/** - Kirim notifikasi multi-channel

### 3. Repositories (`app/Repositories/`)
Data access layer (opsional, untuk project besar):
- Abstraksi query database
- Mudah di-mock untuk testing
- Centralized data access

### 4. DTOs (`app/DataTransferObjects/`)
Type-safe data containers:
```php
// app/DataTransferObjects/CustomerData.php
readonly class CustomerData
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $address,
        public string $connectionType,
        public int $packageId,
        public int $routerId,
    ) {}
}
```

### 5. Enums (`app/Enums/`)
PHP 8.1 Enums untuk konstanta:
```php
// app/Enums/CustomerStatus.php
enum CustomerStatus: string
{
    case ACTIVE = 'active';
    case ISOLATED = 'isolated';
    case SUSPENDED = 'suspended';
    case TERMINATED = 'terminated';
}
```

### 6. Frontend (`resources/js/`)
Menggunakan Inertia.js + Vue 3:
- **Pages/** - Halaman utama
- **Components/** - Komponen reusable
- **Composables/** - Vue composition functions
- **Stores/** - Pinia state management

---

## Konfigurasi Files

### config/billing.php
```php
return [
    'invoice_prefix' => env('INVOICE_PREFIX', 'INV'),
    'payment_prefix' => env('PAYMENT_PREFIX', 'PAY'),
    'due_days' => env('BILLING_DUE_DAYS', 20),
    'grace_days' => env('BILLING_GRACE_DAYS', 7),
    'auto_isolate' => env('BILLING_AUTO_ISOLATE', true),
    'ppn' => [
        'enabled' => env('PPN_ENABLED', false),
        'percentage' => env('PPN_PERCENTAGE', 11),
    ],
];
```

### config/mikrotik.php
```php
return [
    'default_port' => env('MIKROTIK_PORT', 8728),
    'ssl_port' => env('MIKROTIK_SSL_PORT', 8729),
    'timeout' => env('MIKROTIK_TIMEOUT', 10),
    'attempts' => env('MIKROTIK_ATTEMPTS', 3),
    'profiles' => [
        'default' => env('MIKROTIK_DEFAULT_PROFILE', 'default'),
        'isolated' => env('MIKROTIK_ISOLATED_PROFILE', 'isolated'),
    ],
];
```

### config/genieacs.php
```php
return [
    'nbi_url' => env('GENIEACS_NBI_URL', 'http://localhost:7557'),
    'timeout' => env('GENIEACS_TIMEOUT', 30),
    'sync_interval' => env('GENIEACS_SYNC_INTERVAL', 15), // menit
];
```

---

## Routes Structure

### routes/web.php
```php
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Customers
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/isolate', [CustomerController::class, 'isolate']);
    Route::post('customers/{customer}/open-access', [CustomerController::class, 'openAccess']);

    // Invoices
    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/generate', [InvoiceController::class, 'generateMonthly']);

    // Payments
    Route::resource('payments', PaymentController::class);

    // Routers
    Route::resource('routers', RouterController::class);
    Route::post('routers/{router}/sync', [RouterController::class, 'sync']);

    // Packages & Areas
    Route::resource('packages', PackageController::class);
    Route::resource('areas', AreaController::class);

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('revenue', [ReportController::class, 'revenue']);
        Route::get('customers', [ReportController::class, 'customers']);
        Route::get('debts', [ReportController::class, 'debts']);
    });

    // Settings
    Route::get('settings', [SettingController::class, 'index']);
    Route::post('settings', [SettingController::class, 'update']);
});
```

### routes/api.php
```php
Route::prefix('v1')->group(function () {
    // Public
    Route::post('auth/login', [AuthController::class, 'login']);

    // Protected
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('invoices', InvoiceController::class);
        Route::apiResource('payments', PaymentController::class);

        // Customer actions
        Route::post('customers/{customer}/isolate', [CustomerController::class, 'isolate']);
        Route::post('customers/{customer}/open-access', [CustomerController::class, 'openAccess']);
    });

    // Webhooks (dengan signature verification)
    Route::post('webhook/payment/{provider}', [WebhookController::class, 'handlePayment']);
});
```

---

## Keuntungan Struktur Ini

1. **Modular** - Setiap fitur terpisah dengan jelas
2. **Scalable** - Mudah menambah fitur baru
3. **Testable** - Service/Action mudah di-unit test
4. **Maintainable** - Developer baru mudah memahami
5. **Responsive** - Frontend dengan Vue 3 + Inertia
6. **API Ready** - REST API tersedia untuk mobile app
