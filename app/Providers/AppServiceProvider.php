<?php

namespace App\Providers;

use App\Events\CustomerIsolated;
use App\Events\CustomerReopened;
use App\Events\InvoiceGenerated;
use App\Events\PaymentReceived;
use App\Listeners\CheckAndReopenCustomer;
use App\Listeners\LogInvoiceGeneration;
use App\Listeners\SendIsolationNotification;
use App\Listeners\SendReopenNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Register event listeners
        Event::listen(PaymentReceived::class, CheckAndReopenCustomer::class);
        Event::listen(CustomerIsolated::class, SendIsolationNotification::class);
        Event::listen(CustomerReopened::class, SendReopenNotification::class);
        Event::listen(InvoiceGenerated::class, LogInvoiceGeneration::class);
    }
}
