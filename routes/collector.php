<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Collector\DashboardController;
use App\Http\Controllers\Collector\ExpenseController;
use App\Http\Controllers\Collector\ReportController;

/*
|--------------------------------------------------------------------------
| Collector Routes (Penagih)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:penagih'])->prefix('collector')->name('collector.')->group(function () {

    // Dashboard
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

    // Pelanggan
    Route::get('/customers', [DashboardController::class, 'customers'])->name('customers');
    Route::get('/customers/{customer}', [DashboardController::class, 'customerDetail'])->name('customer.detail');

    // Pembayaran
    Route::post('/customers/{customer}/payment/cash', [DashboardController::class, 'processCashPayment'])->name('payment.cash');
    Route::post('/customers/{customer}/payment/transfer', [DashboardController::class, 'processTransferPayment'])->name('payment.transfer');

    // Kunjungan
    Route::post('/customers/{customer}/visit', [DashboardController::class, 'logVisit'])->name('visit.log');
    Route::post('/customers/{customer}/whatsapp', [DashboardController::class, 'sendWhatsAppReminder'])->name('whatsapp');

    // Pengeluaran (Petty Cash)
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

    // Setoran
    Route::get('/settlement', [ExpenseController::class, 'settlement'])->name('settlement');
    Route::post('/settlement', [ExpenseController::class, 'requestSettlement'])->name('settlement.store');

    // Laporan
    Route::get('/reports/daily', [ReportController::class, 'dailyReport'])->name('reports.daily');
    Route::get('/reports/monthly', [ReportController::class, 'monthlyReport'])->name('reports.monthly');
    Route::get('/reports/settlement', [ReportController::class, 'settlementReport'])->name('reports.settlement');
    Route::get('/reports/print', [ReportController::class, 'printPreview'])->name('reports.print');
});
