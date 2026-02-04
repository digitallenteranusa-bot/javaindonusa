<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Collector\DashboardController;
use App\Http\Controllers\Collector\CustomerController;
use App\Http\Controllers\Collector\ExpenseController;
use App\Http\Controllers\Collector\ReportController;

/*
|--------------------------------------------------------------------------
| Collector Routes (Penagih)
|--------------------------------------------------------------------------
| Routes protected by permission middleware.
| Permissions are set in Admin > Roles & Permission
*/

Route::middleware(['auth', 'role:penagih'])->prefix('collector')->name('collector.')->group(function () {

    // Dashboard - always accessible for collector
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Serve receipt images (protected)
    Route::get('/receipts/{path}', function ($path) {
        $fullPath = storage_path('app/public/receipts/' . $path);

        if (!file_exists($fullPath)) {
            abort(404);
        }

        $mime = mime_content_type($fullPath);
        return response()->file($fullPath, ['Content-Type' => $mime]);
    })->where('path', '.*')->name('receipt');

    // ================================================================
    // PELANGGAN - View (customers.view)
    // ================================================================
    Route::middleware(['permission:customers.view'])->group(function () {
        Route::get('/customers', [DashboardController::class, 'customers'])->name('customers');
        Route::get('/customers/{customer}', [DashboardController::class, 'customerDetail'])->name('customer.detail');
    });

    // ================================================================
    // PELANGGAN - Create (customers.create)
    // ================================================================
    Route::middleware(['permission:customers.create'])->group(function () {
        Route::get('/customers-create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    });

    // ================================================================
    // PELANGGAN - Edit (customers.edit)
    // ================================================================
    Route::middleware(['permission:customers.edit'])->group(function () {
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });

    // ================================================================
    // PEMBAYARAN - Create (payments.create)
    // ================================================================
    Route::middleware(['permission:payments.create'])->group(function () {
        Route::post('/customers/{customer}/payment/cash', [DashboardController::class, 'processCashPayment'])->name('payment.cash');
        Route::post('/customers/{customer}/payment/transfer', [DashboardController::class, 'processTransferPayment'])->name('payment.transfer');
    });

    // ================================================================
    // PEMBAYARAN - View (payments.view)
    // ================================================================
    Route::middleware(['permission:payments.view'])->group(function () {
        Route::get('/payments', [DashboardController::class, 'payments'])->name('payments');
    });

    // ================================================================
    // KUNJUNGAN & WHATSAPP - Collect (customers.collect)
    // ================================================================
    Route::middleware(['permission:customers.collect'])->group(function () {
        Route::post('/customers/{customer}/visit', [DashboardController::class, 'logVisit'])->name('visit.log');
        Route::post('/customers/{customer}/whatsapp', [DashboardController::class, 'sendWhatsAppReminder'])->name('whatsapp');
    });

    // ================================================================
    // PENGELUARAN (Petty Cash) - Always accessible for collector
    // ================================================================
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

    // ================================================================
    // SETORAN - Always accessible for collector
    // ================================================================
    Route::get('/settlement', [ExpenseController::class, 'settlement'])->name('settlement');
    Route::post('/settlement', [ExpenseController::class, 'requestSettlement'])->name('settlement.store');

    // ================================================================
    // LAPORAN (reports.view)
    // ================================================================
    Route::middleware(['permission:reports.view'])->group(function () {
        Route::get('/reports/daily', [ReportController::class, 'dailyReport'])->name('reports.daily');
        Route::get('/reports/monthly', [ReportController::class, 'monthlyReport'])->name('reports.monthly');
        Route::get('/reports/settlement', [ReportController::class, 'settlementReport'])->name('reports.settlement');
        Route::get('/reports/print', [ReportController::class, 'printPreview'])->name('reports.print');
    });
});
