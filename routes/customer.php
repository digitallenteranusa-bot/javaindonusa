<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Customer\PortalController;
use App\Http\Controllers\Customer\TripayController;

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

    // Halaman input OTP (GET)
    Route::get('/verify-otp', [PortalController::class, 'showVerifyOTP'])->name('show-verify-otp');

    // Verify OTP: max 10 attempts per minute per IP
    Route::post('/verify-otp', [PortalController::class, 'verifyOTP'])
        ->middleware('throttle:10,1')
        ->name('verify-otp');

    Route::get('/auth/{token}', [PortalController::class, 'loginWithToken'])->name('auth.token');

    // Auto-detect customer isolir dari IP (Captive Portal)
    Route::get('/isolation/detect', [PortalController::class, 'detectIsolation'])
        ->middleware('throttle:30,1')
        ->name('isolation.detect');

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

        // Tripay Online Payment
        Route::get('/pay', [TripayController::class, 'payPage'])->name('tripay.pay');
        Route::get('/tripay/channels', [TripayController::class, 'getChannels'])->name('tripay.channels');
        Route::post('/tripay/pay', [TripayController::class, 'createTransaction'])->name('tripay.create');
        Route::get('/tripay/status/{transaction}', [TripayController::class, 'checkStatus'])->name('tripay.status');
    });
});
