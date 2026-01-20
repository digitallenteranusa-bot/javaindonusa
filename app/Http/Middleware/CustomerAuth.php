<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\CustomerToken;
use Symfony\Component\HttpFoundation\Response;

class CustomerAuth
{
    /**
     * Handle an incoming request.
     * Middleware untuk autentikasi pelanggan via token session
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = session('customer_token');
        $customerId = session('customer_id');

        if (!$token || !$customerId) {
            return redirect()->route('customer.login');
        }

        // Verifikasi token masih valid
        $customerToken = CustomerToken::where('token', $token)
            ->where('customer_id', $customerId)
            ->where('expires_at', '>', now())
            ->first();

        if (!$customerToken) {
            // Token expired atau tidak valid
            session()->forget(['customer_token', 'customer_id']);
            return redirect()->route('customer.login')
                ->with('error', 'Sesi telah berakhir, silakan login kembali');
        }

        // Update last used
        $customerToken->touch('last_used_at');

        return $next($request);
    }
}
