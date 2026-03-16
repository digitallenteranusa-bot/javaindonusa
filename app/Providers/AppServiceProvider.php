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
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Observers\CustomerObserver;
use App\Observers\InvoiceObserver;
use App\Observers\PaymentObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
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
        // Rate limiters
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Scramble API docs access: local → everyone, production → admin only
        Gate::define('viewApiDocs', function (?User $user) {
            if ($this->app->environment('local')) {
                return true;
            }
            return $user?->role === 'admin';
        });

        // Register Google Drive filesystem driver (OAuth2)
        try {
            Storage::extend('google', function ($app, $config) {
                $client = new \Google\Client();
                $client->setClientId($config['clientId']);
                $client->setClientSecret($config['clientSecret']);
                $client->refreshToken($config['refreshToken']);

                $service = new \Google\Service\Drive($client);
                $adapter = new \Masbug\Flysystem\GoogleDriveAdapter($service, $config['folder'] ?? '/');

                return new \Illuminate\Filesystem\FilesystemAdapter(
                    new \League\Flysystem\Filesystem($adapter),
                    $adapter,
                    $config
                );
            });
        } catch (\Exception $e) {
            // Silently fail if Google Drive is not configured
        }

        // Register observers
        Customer::observe(CustomerObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);

        // Register event listeners
        Event::listen(PaymentReceived::class, CheckAndReopenCustomer::class);
        Event::listen(CustomerIsolated::class, SendIsolationNotification::class);
        Event::listen(CustomerReopened::class, SendReopenNotification::class);
        Event::listen(InvoiceGenerated::class, LogInvoiceGeneration::class);
    }
}
