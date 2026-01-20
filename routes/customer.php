<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Customer\PortalController;

/*
|--------------------------------------------------------------------------
| Customer Portal Routes
|--------------------------------------------------------------------------
*/

Route::prefix('portal')->name('customer.')->group(function () {

    // Login (Tanpa Auth)
    Route::get('/login', [PortalController::class, 'showLogin'])->name('login');
    Route::post('/login', [PortalController::class, 'requestOTP'])->name('request-otp');
    Route::post('/verify-otp', [PortalController::class, 'verifyOTP'])->name('verify-otp');
    Route::get('/auth/{token}', [PortalController::class, 'loginWithToken'])->name('auth.token');

    // Halaman Isolir (Public - Tanpa Login)
    Route::get('/isolation/{customerId}', [PortalController::class, 'isolationPage'])->name('isolation');

    // Authenticated Customer Routes
    Route::middleware(['customer.auth'])->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/invoices', [PortalController::class, 'invoices'])->name('invoices');
        Route::get('/payments', [PortalController::class, 'payments'])->name('payments');
        Route::get('/payment-info', [PortalController::class, 'paymentInfo'])->name('payment-info');
        Route::post('/logout', [PortalController::class, 'logout'])->name('logout');
    });
});
