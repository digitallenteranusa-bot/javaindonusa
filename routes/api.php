<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Customer\TripayController;
use App\Http\Controllers\Customer\XenditController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController as ApiCustomerController;
use App\Http\Controllers\Api\V1\InvoiceController as ApiInvoiceController;
use App\Http\Controllers\Api\V1\PaymentController as ApiPaymentController;
use App\Http\Controllers\Api\V1\PackageController as ApiPackageController;
use App\Http\Controllers\Api\V1\AreaController as ApiAreaController;
use App\Http\Controllers\Api\V1\DashboardController as ApiDashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Routes for external API callbacks (no CSRF, no session)
*/

Route::get('/health', HealthController::class)->name('api.health');

Route::post('/tripay/callback', [TripayController::class, 'callback'])
    ->middleware('throttle:webhook')
    ->name('api.tripay.callback');

Route::post('/xendit/callback', [XenditController::class, 'callback'])
    ->middleware('throttle:webhook')
    ->name('api.xendit.callback');

/*
|--------------------------------------------------------------------------
| REST API v1
|--------------------------------------------------------------------------
| Mobile app API — Sanctum token auth, rate limited 60/min per user
*/

Route::prefix('v1')->group(function () {
    // Auth (public)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:api');

    // Protected routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Customers
        Route::get('/customers', [ApiCustomerController::class, 'index']);
        Route::get('/customers/{customer}', [ApiCustomerController::class, 'show']);

        // Invoices
        Route::get('/invoices', [ApiInvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [ApiInvoiceController::class, 'show']);

        // Payments
        Route::get('/payments', [ApiPaymentController::class, 'index']);
        Route::post('/payments', [ApiPaymentController::class, 'store']);

        // Reference data
        Route::get('/packages', [ApiPackageController::class, 'index']);
        Route::get('/areas', [ApiAreaController::class, 'index']);

        // Dashboard
        Route::get('/dashboard/stats', [ApiDashboardController::class, 'stats']);
    });
});
