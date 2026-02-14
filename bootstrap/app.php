<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Web middleware group
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Redirect authenticated users yang akses halaman guest (login)
        $middleware->redirectUsersTo(fn (Request $request) => route('admin.dashboard'));

        // Redirect guests yang akses halaman protected
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));

        // Alias middleware
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'customer.auth' => \App\Http\Middleware\CustomerAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle common HTTP errors with Inertia error pages
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // Session expired / CSRF mismatch - redirect to login
            if ($status === 419) {
                // Regenerate session & CSRF token untuk menghindari redirect loop
                if ($request->hasSession()) {
                    $request->session()->regenerate();
                    $request->session()->regenerateToken();
                }

                $path = $request->path();
                if (str_starts_with($path, 'portal')) {
                    return redirect('/portal/login')->with('error', 'Sesi telah berakhir, silakan login kembali.');
                }
                return redirect('/login')->with('error', 'Sesi telah berakhir, silakan login kembali.');
            }

            // Server error - show Inertia error page if available
            if (in_array($status, [500, 503, 404, 403]) && $request->header('X-Inertia')) {
                return inertia('Error', [
                    'status' => $status,
                    'message' => match ($status) {
                        404 => 'Halaman tidak ditemukan.',
                        403 => 'Akses ditolak.',
                        503 => 'Server sedang maintenance.',
                        default => 'Terjadi kesalahan pada server.',
                    },
                ])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
