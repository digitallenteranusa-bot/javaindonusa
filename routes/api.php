<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Customer\TripayController;
use App\Http\Controllers\Customer\XenditController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Routes for external API callbacks (no CSRF, no session)
*/

Route::post('/tripay/callback', [TripayController::class, 'callback'])
    ->name('api.tripay.callback');

Route::post('/xendit/callback', [XenditController::class, 'callback'])
    ->name('api.xendit.callback');
