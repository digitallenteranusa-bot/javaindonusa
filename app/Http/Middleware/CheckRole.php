<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Jika tidak ada role yang ditentukan, lewatkan saja
        if (empty($roles)) {
            return $next($request);
        }

        // Cek apakah user memiliki salah satu role yang diizinkan
        $userRole = $request->user()->role;

        // Admin memiliki akses ke semua
        if ($userRole === 'admin') {
            return $next($request);
        }

        // Finance bisa akses admin routes
        if ($userRole === 'finance' && in_array('admin', $roles)) {
            return $next($request);
        }

        // Cek role spesifik
        if (in_array($userRole, $roles)) {
            return $next($request);
        }

        // Jika tidak memiliki akses
        abort(403, 'Anda tidak memiliki akses ke halaman ini.');
    }
}
