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

    // Rate limited OTP endpoints
    // Request OTP: max 5 requests per minute per IP
    Route::post('/login', [PortalController::class, 'requestOTP'])
        ->middleware('throttle:5,1')
        ->name('request-otp');

    // Verify OTP: max 10 attempts per minute per IP
    Route::post('/verify-otp', [PortalController::class, 'verifyOTP'])
        ->middleware('throttle:10,1')
        ->name('verify-otp');

    Route::get('/auth/{token}', [PortalController::class, 'loginWithToken'])->name('auth.token');

    // Halaman Isolir (Public - Tanpa Login, rate limited)
    Route::get('/isolation/{customerId}', [PortalController::class, 'isolationPage'])
        ->middleware('throttle:30,1')
        ->name('isolation');

    // Logout (Tanpa Auth - agar bisa logout meski session expired)
    Route::post('/logout', [PortalController::class, 'logout'])->name('logout');

    // Authenticated Customer Routes
    Route::middleware(['customer.auth'])->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/invoices', [PortalController::class, 'invoices'])->name('invoices');
        Route::get('/payments', [PortalController::class, 'payments'])->name('payments');
        Route::get('/payment-info', [PortalController::class, 'paymentInfo'])->name('payment-info');
    });
});
