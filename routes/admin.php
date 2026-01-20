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
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/stats', [DashboardController::class, 'stats'])->name('stats');
    Route::get('/top-paying', [DashboardController::class, 'topPayingCustomers'])->name('top-paying');
    Route::get('/top-debtors', [DashboardController::class, 'topDebtors'])->name('top-debtors');

    // ================================================================
    // CUSTOMERS
    // ================================================================
    Route::resource('customers', CustomerController::class);
    Route::post('/customers/{customer}/adjust-debt', [CustomerController::class, 'adjustDebt'])
        ->name('customers.adjust-debt');
    Route::post('/customers/{customer}/write-off-debt', [CustomerController::class, 'writeOffDebt'])
        ->name('customers.write-off-debt');
    Route::post('/customers/{customer}/recalculate-debt', [CustomerController::class, 'recalculateDebt'])
        ->name('customers.recalculate-debt');

    // ================================================================
    // PACKAGES (Master Data)
    // ================================================================
    Route::resource('packages', PackageController::class);
    Route::post('/packages/{package}/toggle-active', [PackageController::class, 'toggleActive'])
        ->name('packages.toggle-active');

    // ================================================================
    // AREAS (Master Data)
    // ================================================================
    Route::resource('areas', AreaController::class);
    Route::post('/areas/{area}/toggle-active', [AreaController::class, 'toggleActive'])
        ->name('areas.toggle-active');

    // ================================================================
    // ROUTERS (Master Data)
    // ================================================================
    Route::resource('routers', RouterController::class);
    Route::post('/routers/{router}/test-connection', [RouterController::class, 'testConnection'])
        ->name('routers.test-connection');
    Route::post('/routers/{router}/sync-info', [RouterController::class, 'syncInfo'])
        ->name('routers.sync-info');
    Route::post('/routers/{router}/toggle-active', [RouterController::class, 'toggleActive'])
        ->name('routers.toggle-active');

    // ================================================================
    // DEVICES (GenieACS / TR-069)
    // ================================================================
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/status', [DeviceController::class, 'status'])->name('devices.status');
    Route::post('/devices/sync', [DeviceController::class, 'sync'])->name('devices.sync');
    Route::get('/devices/firmware-files', [DeviceController::class, 'getFirmwareFiles'])
        ->name('devices.firmware-files');
    Route::post('/devices/upload-firmware', [DeviceController::class, 'uploadFirmware'])
        ->name('devices.upload-firmware');
    Route::delete('/devices/delete-firmware', [DeviceController::class, 'deleteFirmware'])
        ->name('devices.delete-firmware');
    Route::get('/devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    Route::post('/devices/{device}/link', [DeviceController::class, 'linkCustomer'])
        ->name('devices.link');
    Route::post('/devices/{device}/unlink', [DeviceController::class, 'unlinkCustomer'])
        ->name('devices.unlink');
    Route::post('/devices/{device}/reboot', [DeviceController::class, 'reboot'])
        ->name('devices.reboot');
    Route::post('/devices/{device}/refresh', [DeviceController::class, 'refresh'])
        ->name('devices.refresh');
    Route::post('/devices/{device}/factory-reset', [DeviceController::class, 'factoryReset'])
        ->name('devices.factory-reset');
    Route::post('/devices/{device}/wifi', [DeviceController::class, 'updateWifi'])
        ->name('devices.update-wifi');
    Route::get('/devices/{device}/tasks', [DeviceController::class, 'getTasks'])
        ->name('devices.tasks');
    Route::post('/devices/{device}/install-update', [DeviceController::class, 'installUpdate'])
        ->name('devices.install-update');

    // ================================================================
    // INVOICES
    // ================================================================
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])
        ->name('invoices.mark-paid');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
        ->name('invoices.cancel');
    Route::post('/invoices/update-overdue', [InvoiceController::class, 'updateOverdueStatus'])
        ->name('invoices.update-overdue');
    Route::get('/invoices-export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])
        ->name('invoices.pdf');
    Route::get('/invoices/{invoice}/pdf/preview', [InvoiceController::class, 'streamPdf'])
        ->name('invoices.pdf.preview');
    Route::post('/invoices/bulk-pdf', [InvoiceController::class, 'bulkExportPdf'])
        ->name('invoices.bulk-pdf');

    // ================================================================
    // PAYMENTS
    // ================================================================
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])
        ->name('payments.cancel');
    Route::get('/payments/daily-summary', [PaymentController::class, 'dailySummary'])
        ->name('payments.daily-summary');
    Route::get('/payments-export', [PaymentController::class, 'export'])->name('payments.export');
    Route::get('/payments/{payment}/pdf', [PaymentController::class, 'downloadPdf'])
        ->name('payments.pdf');
    Route::get('/payments/{payment}/pdf/preview', [PaymentController::class, 'streamPdf'])
        ->name('payments.pdf.preview');

    // ================================================================
    // EXPENSES (Collector Expenses)
    // ================================================================
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/pending', [ExpenseController::class, 'pending'])->name('expenses.pending');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])
        ->name('expenses.approve');
    Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])
        ->name('expenses.reject');
    Route::post('/expenses/bulk-approve', [ExpenseController::class, 'bulkApprove'])
        ->name('expenses.bulk-approve');
    Route::get('/expenses/collector-summary', [ExpenseController::class, 'collectorSummary'])
        ->name('expenses.collector-summary');

    // ================================================================
    // SETTLEMENTS (Collector Settlements)
    // ================================================================
    Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements.index');
    Route::get('/settlements/pending', [SettlementController::class, 'pending'])->name('settlements.pending');
    Route::get('/settlements/{settlement}', [SettlementController::class, 'show'])->name('settlements.show');
    Route::post('/settlements/{settlement}/verify', [SettlementController::class, 'verify'])
        ->name('settlements.verify');
    Route::post('/settlements/{settlement}/reject', [SettlementController::class, 'reject'])
        ->name('settlements.reject');
    Route::get('/settlements/collector-balance', [SettlementController::class, 'collectorBalance'])
        ->name('settlements.collector-balance');
    Route::get('/settlements/daily-summary', [SettlementController::class, 'dailySummary'])
        ->name('settlements.daily-summary');

    // ================================================================
    // USERS
    // ================================================================
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])
        ->name('users.toggle-active');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->name('users.reset-password');
    Route::get('/collectors', [UserController::class, 'collectors'])->name('collectors');

    // ================================================================
    // SETTINGS
    // ================================================================
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
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

    // ================================================================
    // SYSTEM INFO
    // ================================================================
    Route::get('/system', [SettingsController::class, 'system'])->name('system.index');
    Route::post('/system/check-update', [SettingsController::class, 'checkUpdate'])
        ->name('system.check-update');
    Route::post('/system/clear-cache', [SettingsController::class, 'clearCache'])
        ->name('system.clear-cache');

    // ================================================================
    // UPDATE & BACKUP
    // ================================================================
    Route::post('/system/backup', [SettingsController::class, 'createBackup'])
        ->name('system.backup');
    Route::post('/system/install-update', [SettingsController::class, 'installUpdate'])
        ->name('system.install-update');
    Route::post('/system/download-install', [SettingsController::class, 'downloadAndInstall'])
        ->name('system.download-install');
    Route::get('/system/backups', [SettingsController::class, 'getBackups'])
        ->name('system.backups');
    Route::post('/system/restore-backup', [SettingsController::class, 'restoreBackup'])
        ->name('system.restore-backup');
    Route::delete('/system/delete-backup', [SettingsController::class, 'deleteBackup'])
        ->name('system.delete-backup');

    // ================================================================
    // REPORTS
    // ================================================================
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/collectors', [ReportController::class, 'collectorPerformance'])
        ->name('reports.collectors');
    Route::get('/reports/areas', [ReportController::class, 'areaPerformance'])
        ->name('reports.areas');
    Route::get('/reports/daily-trend', [ReportController::class, 'dailyTrend'])
        ->name('reports.daily-trend');
    Route::get('/reports/collectors/export', [ReportController::class, 'exportCollectorPerformance'])
        ->name('reports.collectors.export');

    // ================================================================
    // AUDIT LOG
    // ================================================================
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/export', [AdminAuditLogController::class, 'export'])->name('audit-logs.export');
    Route::get('/audit-logs/recent', [AdminAuditLogController::class, 'recent'])->name('audit-logs.recent');
    Route::get('/audit-logs/statistics', [AdminAuditLogController::class, 'statistics'])->name('audit-logs.statistics');
    Route::get('/audit-logs/{auditLog}', [AdminAuditLogController::class, 'show'])->name('audit-logs.show');

});
