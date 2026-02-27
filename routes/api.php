<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Customer\TripayController;
use App\Http\Controllers\Customer\XenditController;
use App\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Routes for external API callbacks (no CSRF, no session)
*/

Route::get('/health', HealthController::class)->name('api.health');

Route::post('/tripay/callback', [TripayController::class, 'callback'])
    ->name('api.tripay.callback');

Route::post('/xendit/callback', [XenditController::class, 'callback'])
    ->name('api.xendit.callback');
