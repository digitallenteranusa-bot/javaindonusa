<?php

use App\Exceptions\Billing\InvoiceDuplicateException;
use App\Exceptions\Billing\InvoiceStateException;
use App\Exceptions\Billing\NoPayableInvoiceException;
use App\Exceptions\Billing\PaymentCancellationException;
use App\Exceptions\Billing\PaymentGatewayException;
use App\Exceptions\CannotDeleteWithDependentsException;
use App\Exceptions\Collector\UnauthorizedCustomerAccessException;
use App\Exceptions\Customer\CustomerHasUnpaidInvoicesException;
use App\Exceptions\Customer\IsolationStateException;
use App\Exceptions\InvalidFileException;
use App\Exceptions\Mikrotik\RouterConnectionException;
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
        // Custom exception rendering — typed exceptions return proper HTTP status codes
        $exceptions->renderable(function (UnauthorizedCustomerAccessException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 403);
            }
            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (InvoiceDuplicateException|InvoiceStateException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (PaymentCancellationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (NoPayableInvoiceException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (PaymentGatewayException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 502);
            }
            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (RouterConnectionException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 503);
            }
            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (CustomerHasUnpaidInvoicesException|CannotDeleteWithDependentsException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }
            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (IsolationStateException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        });

        $exceptions->renderable(function (InvalidFileException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        });

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
