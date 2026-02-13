<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\RouterController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\SettlementController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\OdpController;
use App\Http\Controllers\Admin\OltController;
use App\Http\Controllers\Admin\RadiusServerController;
use App\Http\Controllers\Admin\VpnController;
use App\Http\Controllers\Admin\VpnServerController;
use App\Http\Controllers\Admin\MappingController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RouterBrandController;
use App\Http\Controllers\Admin\BroadcastController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
| Routes for admin panel. Protected by auth and admin role middleware.
|
*/

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    // ================================================================
    // DASHBOARD
    // ================================================================
    Route::middleware(['permission:dashboard.view'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/stats', [DashboardController::class, 'stats'])->name('stats');
        Route::get('/top-paying', [DashboardController::class, 'topPayingCustomers'])->name('top-paying');
        Route::get('/top-debtors', [DashboardController::class, 'topDebtors'])->name('top-debtors');
    });

    // ================================================================
    // CUSTOMERS
    // ================================================================
    // Static routes first (before parameterized routes)
    Route::middleware(['permission:customers.create'])->group(function () {
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers-import-template', [CustomerController::class, 'downloadTemplate'])
            ->name('customers.import-template');
        Route::post('/customers-import', [CustomerController::class, 'import'])
            ->name('customers.import');
    });
    // Parameterized routes after static routes
    Route::middleware(['permission:customers.view'])->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    });
    Route::middleware(['permission:customers.edit'])->group(function () {
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{customer}/recalculate-debt', [CustomerController::class, 'recalculateDebt'])
            ->name('customers.recalculate-debt');
    });
    Route::middleware(['permission:customers.delete'])->group(function () {
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });
    Route::middleware(['permission:customers.adjust-debt'])->group(function () {
        Route::post('/customers/{customer}/adjust-debt', [CustomerController::class, 'adjustDebt'])
            ->name('customers.adjust-debt');
        Route::post('/customers/{customer}/add-historical-invoice', [CustomerController::class, 'addHistoricalInvoice'])
            ->name('customers.add-historical-invoice');
    });
    Route::middleware(['permission:customers.write-off'])->group(function () {
        Route::post('/customers/{customer}/write-off-debt', [CustomerController::class, 'writeOffDebt'])
            ->name('customers.write-off-debt');
    });

    // ================================================================
    // PACKAGES (Master Data)
    // ================================================================
    Route::middleware(['permission:packages.create'])->group(function () {
        Route::get('/packages/create', [PackageController::class, 'create'])->name('packages.create');
        Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
    });
    Route::middleware(['permission:packages.view'])->group(function () {
        Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
        Route::get('/packages/{package}', [PackageController::class, 'show'])->name('packages.show');
    });
    Route::middleware(['permission:packages.edit'])->group(function () {
        Route::get('/packages/{package}/edit', [PackageController::class, 'edit'])->name('packages.edit');
        Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::post('/packages/{package}/toggle-active', [PackageController::class, 'toggleActive'])
            ->name('packages.toggle-active');
    });
    Route::middleware(['permission:packages.delete'])->group(function () {
        Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');
    });

    // ================================================================
    // AREAS (Master Data)
    // ================================================================
    Route::middleware(['permission:areas.create'])->group(function () {
        Route::get('/areas/create', [AreaController::class, 'create'])->name('areas.create');
        Route::post('/areas', [AreaController::class, 'store'])->name('areas.store');
    });
    Route::middleware(['permission:areas.view'])->group(function () {
        Route::get('/areas', [AreaController::class, 'index'])->name('areas.index');
        Route::get('/areas/{area}', [AreaController::class, 'show'])->name('areas.show');
    });
    Route::middleware(['permission:areas.edit'])->group(function () {
        Route::get('/areas/{area}/edit', [AreaController::class, 'edit'])->name('areas.edit');
        Route::put('/areas/{area}', [AreaController::class, 'update'])->name('areas.update');
        Route::post('/areas/{area}/toggle-active', [AreaController::class, 'toggleActive'])
            ->name('areas.toggle-active');
    });
    Route::middleware(['permission:areas.delete'])->group(function () {
        Route::delete('/areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');
    });

    // ================================================================
    // ROUTERS (Master Data)
    // ================================================================
    Route::middleware(['permission:routers.create'])->group(function () {
        Route::get('/routers/create', [RouterController::class, 'create'])->name('routers.create');
        Route::post('/routers', [RouterController::class, 'store'])->name('routers.store');
    });
    Route::middleware(['permission:routers.view'])->group(function () {
        Route::get('/routers', [RouterController::class, 'index'])->name('routers.index');
        Route::get('/routers/{router}', [RouterController::class, 'show'])->name('routers.show');
        Route::get('/routers/{router}/api/resources', [RouterController::class, 'apiResources'])
            ->name('routers.api.resources');
        Route::get('/routers/{router}/api/interfaces', [RouterController::class, 'apiInterfaces'])
            ->name('routers.api.interfaces');
        Route::get('/routers/{router}/api/queues', [RouterController::class, 'apiQueues'])
            ->name('routers.api.queues');
        Route::get('/routers/{router}/api/active-connections', [RouterController::class, 'apiActiveConnections'])
            ->name('routers.api.active-connections');
        Route::post('/routers/{router}/test-connection', [RouterController::class, 'testConnection'])
            ->name('routers.test-connection');
        Route::post('/routers/{router}/sync-info', [RouterController::class, 'syncInfo'])
            ->name('routers.sync-info');
    });
    Route::middleware(['permission:routers.edit'])->group(function () {
        Route::get('/routers/{router}/edit', [RouterController::class, 'edit'])->name('routers.edit');
        Route::put('/routers/{router}', [RouterController::class, 'update'])->name('routers.update');
        Route::post('/routers/{router}/toggle-active', [RouterController::class, 'toggleActive'])
            ->name('routers.toggle-active');
    });
    Route::middleware(['permission:routers.delete'])->group(function () {
        Route::delete('/routers/{router}', [RouterController::class, 'destroy'])->name('routers.destroy');
    });

    // ================================================================
    // DEVICES (GenieACS / TR-069)
    // ================================================================
    Route::middleware(['permission:devices.view'])->group(function () {
        Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::get('/devices/status', [DeviceController::class, 'status'])->name('devices.status');
        Route::get('/devices/firmware-files', [DeviceController::class, 'getFirmwareFiles'])
            ->name('devices.firmware-files');
        Route::get('/devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
        Route::get('/devices/{device}/tasks', [DeviceController::class, 'getTasks'])
            ->name('devices.tasks');
    });
    Route::middleware(['permission:devices.manage'])->group(function () {
        Route::post('/devices/sync', [DeviceController::class, 'sync'])->name('devices.sync');
        Route::post('/devices/upload-firmware', [DeviceController::class, 'uploadFirmware'])
            ->name('devices.upload-firmware');
        Route::delete('/devices/delete-firmware', [DeviceController::class, 'deleteFirmware'])
            ->name('devices.delete-firmware');
        Route::post('/devices/{device}/reboot', [DeviceController::class, 'reboot'])
            ->name('devices.reboot');
        Route::post('/devices/{device}/refresh', [DeviceController::class, 'refresh'])
            ->name('devices.refresh');
        Route::post('/devices/{device}/factory-reset', [DeviceController::class, 'factoryReset'])
            ->name('devices.factory-reset');
        Route::post('/devices/{device}/wifi', [DeviceController::class, 'updateWifi'])
            ->name('devices.update-wifi');
        Route::post('/devices/{device}/install-update', [DeviceController::class, 'installUpdate'])
            ->name('devices.install-update');
    });
    Route::middleware(['permission:devices.link'])->group(function () {
        Route::post('/devices/{device}/link', [DeviceController::class, 'linkCustomer'])
            ->name('devices.link');
        Route::post('/devices/{device}/unlink', [DeviceController::class, 'unlinkCustomer'])
            ->name('devices.unlink');
    });
    Route::middleware(['permission:devices.delete'])->group(function () {
        Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    });

    // ================================================================
    // INVOICES
    // ================================================================
    // Static routes MUST come before parameterized routes
    Route::middleware(['permission:invoices.generate'])->group(function () {
        Route::get('/invoices/customers-without-invoice', [InvoiceController::class, 'getCustomersWithoutInvoice'])
            ->name('invoices.customers-without-invoice');
        Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
        Route::post('/invoices/generate-selected', [InvoiceController::class, 'generateForSelected'])
            ->name('invoices.generate-selected');
        Route::post('/invoices/update-overdue', [InvoiceController::class, 'updateOverdueStatus'])
            ->name('invoices.update-overdue');
    });
    Route::middleware(['permission:invoices.view'])->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])
            ->name('invoices.pdf');
        Route::get('/invoices/{invoice}/pdf/preview', [InvoiceController::class, 'streamPdf'])
            ->name('invoices.pdf.preview');
    });
    Route::middleware(['permission:invoices.mark-paid'])->group(function () {
        Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])
            ->name('invoices.mark-paid');
    });
    Route::middleware(['permission:invoices.cancel'])->group(function () {
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
            ->name('invoices.cancel');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])
            ->name('invoices.destroy');
    });
    Route::middleware(['permission:invoices.export'])->group(function () {
        Route::get('/invoices-export', [InvoiceController::class, 'export'])->name('invoices.export');
        Route::post('/invoices/bulk-pdf', [InvoiceController::class, 'bulkExportPdf'])
            ->name('invoices.bulk-pdf');
    });

    // ================================================================
    // PAYMENTS
    // ================================================================
    Route::middleware(['permission:payments.create'])->group(function () {
        Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    });
    Route::middleware(['permission:payments.view'])->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/daily-summary', [PaymentController::class, 'dailySummary'])
            ->name('payments.daily-summary');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('/payments/{payment}/pdf', [PaymentController::class, 'downloadPdf'])
            ->name('payments.pdf');
        Route::get('/payments/{payment}/pdf/preview', [PaymentController::class, 'streamPdf'])
            ->name('payments.pdf.preview');
    });
    Route::middleware(['permission:payments.cancel'])->group(function () {
        Route::post('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])
            ->name('payments.cancel');
    });
    Route::middleware(['permission:payments.export'])->group(function () {
        Route::get('/payments-export', [PaymentController::class, 'export'])->name('payments.export');
    });

    // ================================================================
    // EXPENSES (Collector Expenses)
    // ================================================================
    Route::middleware(['permission:expenses.view'])->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/expenses/pending', [ExpenseController::class, 'pending'])->name('expenses.pending');
        Route::get('/expenses/export', [ExpenseController::class, 'export'])->name('expenses.export');
        Route::get('/expenses/collector-summary', [ExpenseController::class, 'collectorSummary'])
            ->name('expenses.collector-summary');
        Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    });
    Route::middleware(['permission:expenses.approve'])->group(function () {
        Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])
            ->name('expenses.approve');
        Route::post('/expenses/bulk-approve', [ExpenseController::class, 'bulkApprove'])
            ->name('expenses.bulk-approve');
    });
    Route::middleware(['permission:expenses.reject'])->group(function () {
        Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])
            ->name('expenses.reject');
    });

    // ================================================================
    // SETTLEMENTS (Collector Settlements)
    // ================================================================
    Route::middleware(['permission:settlements.view'])->group(function () {
        Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements.index');
        Route::get('/settlements/pending', [SettlementController::class, 'pending'])->name('settlements.pending');
        Route::get('/settlements/collector-balance', [SettlementController::class, 'collectorBalance'])
            ->name('settlements.collector-balance');
        Route::get('/settlements/daily-summary', [SettlementController::class, 'dailySummary'])
            ->name('settlements.daily-summary');
        Route::get('/settlements/{settlement}', [SettlementController::class, 'show'])->name('settlements.show');
    });
    Route::middleware(['permission:settlements.verify'])->group(function () {
        Route::post('/settlements/{settlement}/verify', [SettlementController::class, 'verify'])
            ->name('settlements.verify');
    });
    Route::middleware(['permission:settlements.reject'])->group(function () {
        Route::post('/settlements/{settlement}/reject', [SettlementController::class, 'reject'])
            ->name('settlements.reject');
    });

    // ================================================================
    // USERS
    // ================================================================
    Route::middleware(['permission:users.create'])->group(function () {
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
    });
    Route::middleware(['permission:users.view'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/collectors', [UserController::class, 'collectors'])->name('collectors');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    });
    Route::middleware(['permission:users.edit'])->group(function () {
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])
            ->name('users.toggle-active');
    });
    Route::middleware(['permission:users.reset-password'])->group(function () {
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->name('users.reset-password');
    });
    Route::middleware(['permission:users.delete'])->group(function () {
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // ================================================================
    // SETTINGS
    // ================================================================
    Route::middleware(['permission:settings.view'])->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    });
    Route::middleware(['permission:settings.edit'])->group(function () {
        Route::post('/settings/general', [SettingsController::class, 'updateGeneral'])
            ->name('settings.general');
        Route::post('/settings/isp-info', [SettingsController::class, 'updateIspInfo'])
            ->name('settings.isp-info');
        Route::post('/settings/notification', [SettingsController::class, 'updateNotification'])
            ->name('settings.notification');
        Route::post('/settings/mikrotik', [SettingsController::class, 'updateMikrotik'])
            ->name('settings.mikrotik');
        Route::post('/settings/genieacs', [SettingsController::class, 'updateGenieacs'])
            ->name('settings.genieacs');
    });

    // ================================================================
    // SYSTEM INFO
    // ================================================================
    Route::middleware(['permission:system.view'])->group(function () {
        Route::get('/system', [SettingsController::class, 'system'])->name('system.index');
        Route::post('/system/check-update', [SettingsController::class, 'checkUpdate'])
            ->name('system.check-update');
        Route::get('/system/backups', [SettingsController::class, 'getBackups'])
            ->name('system.backups');
        Route::get('/system/backups/download/{filename}', [SettingsController::class, 'downloadBackup'])
            ->name('system.download-backup');
        // Database backups (view)
        Route::get('/system/db-backups', [SettingsController::class, 'getDatabaseBackups'])
            ->name('system.db-backups');
        Route::get('/system/db-backups/download/{filename}', [SettingsController::class, 'downloadDatabaseBackup'])
            ->name('system.download-db-backup');
    });
    Route::middleware(['permission:system.manage'])->group(function () {
        Route::post('/system/clear-cache', [SettingsController::class, 'clearCache'])
            ->name('system.clear-cache');
        Route::post('/system/backup', [SettingsController::class, 'createBackup'])
            ->name('system.backup');
        Route::post('/system/install-update', [SettingsController::class, 'installUpdate'])
            ->name('system.install-update');
        Route::post('/system/git-pull-update', [SettingsController::class, 'gitPullUpdate'])
            ->name('system.git-pull-update');
        Route::post('/system/upload-backup', [SettingsController::class, 'uploadBackup'])
            ->name('system.upload-backup');
        Route::post('/system/restore-backup', [SettingsController::class, 'restoreBackup'])
            ->name('system.restore-backup');
        Route::delete('/system/delete-backup', [SettingsController::class, 'deleteBackup'])
            ->name('system.delete-backup');
        // Database backups (manage)
        Route::post('/system/db-backup', [SettingsController::class, 'createDatabaseBackup'])
            ->name('system.db-backup');
        Route::post('/system/upload-db-backup', [SettingsController::class, 'uploadDatabaseBackup'])
            ->name('system.upload-db-backup');
        Route::post('/system/restore-db-backup', [SettingsController::class, 'restoreDatabaseBackup'])
            ->name('system.restore-db-backup');
        Route::delete('/system/delete-db-backup', [SettingsController::class, 'deleteDatabaseBackup'])
            ->name('system.delete-db-backup');
    });

    // ================================================================
    // REPORTS
    // ================================================================
    Route::middleware(['permission:reports.view'])->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/collectors', [ReportController::class, 'collectorPerformance'])
            ->name('reports.collectors');
        Route::get('/reports/areas', [ReportController::class, 'areaPerformance'])
            ->name('reports.areas');
        Route::get('/reports/daily-trend', [ReportController::class, 'dailyTrend'])
            ->name('reports.daily-trend');
    });
    Route::middleware(['permission:reports.export'])->group(function () {
        Route::get('/reports/collectors/export', [ReportController::class, 'exportCollectorPerformance'])
            ->name('reports.collectors.export');
    });

    // ================================================================
    // AUDIT LOG
    // ================================================================
    Route::middleware(['permission:audit.export'])->group(function () {
        Route::get('/audit-logs/export', [AdminAuditLogController::class, 'export'])->name('audit-logs.export');
    });
    Route::middleware(['permission:audit.view'])->group(function () {
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/recent', [AdminAuditLogController::class, 'recent'])->name('audit-logs.recent');
        Route::get('/audit-logs/statistics', [AdminAuditLogController::class, 'statistics'])->name('audit-logs.statistics');
        Route::get('/audit-logs/{auditLog}', [AdminAuditLogController::class, 'show'])->name('audit-logs.show');
    });

    // ================================================================
    // ODP (Optical Distribution Point)
    // ================================================================
    Route::middleware(['permission:odps.create'])->group(function () {
        Route::get('/odps/create', [OdpController::class, 'create'])->name('odps.create');
        Route::post('/odps', [OdpController::class, 'store'])->name('odps.store');
    });
    Route::middleware(['permission:odps.view'])->group(function () {
        Route::get('/odps', [OdpController::class, 'index'])->name('odps.index');
        Route::get('/odps-select', [OdpController::class, 'getForSelect'])->name('odps.select');
        Route::get('/odps/{odp}', [OdpController::class, 'show'])->name('odps.show');
    });
    Route::middleware(['permission:odps.edit'])->group(function () {
        Route::get('/odps/{odp}/edit', [OdpController::class, 'edit'])->name('odps.edit');
        Route::put('/odps/{odp}', [OdpController::class, 'update'])->name('odps.update');
        Route::post('/odps/{odp}/toggle-active', [OdpController::class, 'toggleActive'])
            ->name('odps.toggle-active');
    });
    Route::middleware(['permission:odps.delete'])->group(function () {
        Route::delete('/odps/{odp}', [OdpController::class, 'destroy'])->name('odps.destroy');
    });

    // ================================================================
    // OLT (Optical Line Terminal)
    // ================================================================
    Route::middleware(['permission:olts.create'])->group(function () {
        Route::get('/olts/create', [OltController::class, 'create'])->name('olts.create');
        Route::post('/olts', [OltController::class, 'store'])->name('olts.store');
    });
    Route::middleware(['permission:olts.view'])->group(function () {
        Route::get('/olts', [OltController::class, 'index'])->name('olts.index');
        Route::get('/olts/{olt}', [OltController::class, 'show'])->name('olts.show');
        Route::post('/olts/{olt}/check-connection', [OltController::class, 'checkConnection'])
            ->name('olts.check-connection');
        Route::post('/olts/{olt}/update-status', [OltController::class, 'updateStatus'])
            ->name('olts.update-status');
    });
    Route::middleware(['permission:olts.edit'])->group(function () {
        Route::get('/olts/{olt}/edit', [OltController::class, 'edit'])->name('olts.edit');
        Route::put('/olts/{olt}', [OltController::class, 'update'])->name('olts.update');
    });
    Route::middleware(['permission:olts.delete'])->group(function () {
        Route::delete('/olts/{olt}', [OltController::class, 'destroy'])->name('olts.destroy');
    });

    // ================================================================
    // RADIUS SERVER (Placeholder)
    // ================================================================
    Route::middleware(['permission:radius.create'])->group(function () {
        Route::get('/radius-servers/create', [RadiusServerController::class, 'create'])->name('radius-servers.create');
        Route::post('/radius-servers', [RadiusServerController::class, 'store'])->name('radius-servers.store');
    });
    Route::middleware(['permission:radius.view'])->group(function () {
        Route::get('/radius-servers', [RadiusServerController::class, 'index'])->name('radius-servers.index');
        Route::get('/radius-servers/{radiusServer}', [RadiusServerController::class, 'show'])->name('radius-servers.show');
        Route::post('/radius-servers/{radiusServer}/test-connection', [RadiusServerController::class, 'testConnection'])
            ->name('radius-servers.test-connection');
    });
    Route::middleware(['permission:radius.edit'])->group(function () {
        Route::get('/radius-servers/{radiusServer}/edit', [RadiusServerController::class, 'edit'])->name('radius-servers.edit');
        Route::put('/radius-servers/{radiusServer}', [RadiusServerController::class, 'update'])->name('radius-servers.update');
    });
    Route::middleware(['permission:radius.delete'])->group(function () {
        Route::delete('/radius-servers/{radiusServer}', [RadiusServerController::class, 'destroy'])->name('radius-servers.destroy');
    });

    // ================================================================
    // ROUTER BRANDS (Customer Router Statistics)
    // ================================================================
    Route::middleware(['permission:customers.view'])->group(function () {
        Route::get('/router-brands', [RouterBrandController::class, 'index'])->name('router-brands.index');
        Route::get('/router-brands/{brand}', [RouterBrandController::class, 'show'])->name('router-brands.show');
    });

    // ================================================================
    // VPN CONFIG (Router VPN Client)
    // ================================================================
    Route::middleware(['permission:routers.vpn-config'])->group(function () {
        Route::get('/routers/{router}/vpn', [VpnController::class, 'index'])->name('routers.vpn');
        Route::post('/routers/{router}/vpn/{protocol}/generate', [VpnController::class, 'generate'])
            ->name('routers.vpn.generate');
        Route::get('/routers/{router}/vpn/{protocol}/download', [VpnController::class, 'download'])
            ->name('routers.vpn.download');
    });

    // ================================================================
    // VPN SERVER (Server-side VPN Management)
    // ================================================================
    Route::middleware(['permission:system.manage'])->prefix('vpn-server')->name('vpn-server.')->group(function () {
        // Dashboard & Client List
        Route::get('/', [VpnServerController::class, 'index'])->name('index');

        // Settings
        Route::get('/settings', [VpnServerController::class, 'settings'])->name('settings');
        Route::post('/settings', [VpnServerController::class, 'updateSettings'])->name('settings.update');

        // OpenVPN Setup
        Route::post('/openvpn/init-pki', [VpnServerController::class, 'initPki'])->name('openvpn.init-pki');
        Route::post('/openvpn/generate-ca', [VpnServerController::class, 'generateCa'])->name('openvpn.generate-ca');
        Route::post('/openvpn/generate-server', [VpnServerController::class, 'generateServerCert'])->name('openvpn.generate-server');
        Route::post('/openvpn/generate-dh', [VpnServerController::class, 'generateDh'])->name('openvpn.generate-dh');
        Route::post('/openvpn/generate-ta', [VpnServerController::class, 'generateTaKey'])->name('openvpn.generate-ta');

        // OpenVPN Service Control
        Route::post('/openvpn/start', [VpnServerController::class, 'startOpenVpn'])->name('openvpn.start');
        Route::post('/openvpn/stop', [VpnServerController::class, 'stopOpenVpn'])->name('openvpn.stop');
        Route::post('/openvpn/restart', [VpnServerController::class, 'restartOpenVpn'])->name('openvpn.restart');

        // WireGuard Setup & Control
        Route::post('/wireguard/generate-keys', [VpnServerController::class, 'generateWgKeys'])->name('wireguard.generate-keys');
        Route::post('/wireguard/start', [VpnServerController::class, 'startWireGuard'])->name('wireguard.start');
        Route::post('/wireguard/stop', [VpnServerController::class, 'stopWireGuard'])->name('wireguard.stop');
        Route::post('/wireguard/sync', [VpnServerController::class, 'syncWireGuard'])->name('wireguard.sync');

        // Client Management
        Route::get('/clients/create', [VpnServerController::class, 'createClient'])->name('clients.create');
        Route::post('/clients', [VpnServerController::class, 'storeClient'])->name('clients.store');
        Route::get('/clients/{client}', [VpnServerController::class, 'showClient'])->name('clients.show');
        Route::get('/clients/{client}/edit', [VpnServerController::class, 'editClient'])->name('clients.edit');
        Route::put('/clients/{client}', [VpnServerController::class, 'updateClient'])->name('clients.update');
        Route::delete('/clients/{client}', [VpnServerController::class, 'destroyClient'])->name('clients.destroy');
        Route::post('/clients/{client}/toggle', [VpnServerController::class, 'toggleClient'])->name('clients.toggle');
        Route::post('/clients/{client}/regenerate', [VpnServerController::class, 'regenerateClient'])->name('clients.regenerate');

        // Downloads
        Route::get('/clients/{client}/download-config', [VpnServerController::class, 'downloadConfig'])->name('clients.download-config');
        Route::get('/clients/{client}/download-script', [VpnServerController::class, 'downloadScript'])->name('clients.download-script');
        Route::get('/clients/{client}/download-certificates', [VpnServerController::class, 'downloadCertificatesZip'])->name('clients.download-certificates');
        Route::get('/clients/{client}/download-p12', [VpnServerController::class, 'downloadP12'])->name('clients.download-p12');

        // Status
        Route::post('/refresh-status', [VpnServerController::class, 'refreshStatus'])->name('refresh-status');
        Route::get('/live-status', [VpnServerController::class, 'liveStatus'])->name('live-status');
    });

    // ================================================================
    // MAPPING (Customer & ODP Map)
    // ================================================================
    Route::middleware(['permission:mapping.view'])->group(function () {
        Route::get('/mapping', [MappingController::class, 'index'])->name('mapping.index');
        Route::get('/mapping/customers', [MappingController::class, 'getCustomers'])->name('mapping.customers');
        Route::get('/mapping/odps', [MappingController::class, 'getOdps'])->name('mapping.odps');
    });
    Route::middleware(['permission:mapping.edit'])->group(function () {
        Route::post('/mapping/customers/{customer}/location', [MappingController::class, 'updateCustomerLocation'])
            ->name('mapping.customers.location');
        Route::post('/mapping/odps/{odp}/location', [MappingController::class, 'updateOdpLocation'])
            ->name('mapping.odps.location');
    });

    // ================================================================
    // BROADCAST (Notifications)
    // ================================================================
    Route::middleware(['permission:settings.edit'])->group(function () {
        Route::get('/broadcasts/create', [BroadcastController::class, 'create'])->name('broadcasts.create');
        Route::post('/broadcasts/maintenance', [BroadcastController::class, 'sendMaintenance'])
            ->name('broadcasts.maintenance');
    });

    // ================================================================
    // ROLES & PERMISSIONS
    // ================================================================
    Route::middleware(['permission:roles.view'])->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    });
    Route::middleware(['permission:roles.edit'])->group(function () {
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::post('/roles/{role}/reset', [RoleController::class, 'reset'])->name('roles.reset');
    });

    // ================================================================
    // SETTINGS (Additional)
    // ================================================================
    Route::middleware(['permission:settings.branding'])->group(function () {
        Route::post('/settings/upload-logo', [SettingsController::class, 'uploadLogo'])
            ->name('settings.upload-logo');
        Route::delete('/settings/delete-logo', [SettingsController::class, 'deleteLogo'])
            ->name('settings.delete-logo');
    });
    Route::middleware(['permission:settings.whatsapp'])->group(function () {
        Route::post('/settings/whatsapp', [SettingsController::class, 'updateWhatsApp'])
            ->name('settings.whatsapp');
        Route::post('/settings/whatsapp/test', [SettingsController::class, 'testWhatsApp'])
            ->name('settings.whatsapp.test');
        Route::get('/settings/whatsapp/status', [SettingsController::class, 'checkWhatsAppStatus'])
            ->name('settings.whatsapp.status');
    });

});
