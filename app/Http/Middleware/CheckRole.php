<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Inertia\Inertia;

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

        // Finance bisa akses admin routes dan finance routes
        if ($userRole === 'finance' && (in_array('admin', $roles) || in_array('finance', $roles))) {
            return $next($request);
        }

        // Technician bisa akses admin routes (read-only) dan technician routes
        if ($userRole === 'technician' && (in_array('admin', $roles) || in_array('technician', $roles))) {
            return $next($request);
        }

        // Cek role spesifik
        if (in_array($userRole, $roles)) {
            return $next($request);
        }

        // Redirect berdasarkan role user daripada 403
        $redirectRoute = match($userRole) {
            'penagih' => 'collector.dashboard',
            'technician' => 'admin.dashboard',
            'finance' => 'admin.dashboard',
            default => 'login',
        };

        return redirect()->route($redirectRoute)
            ->with('error', 'Anda tidak memiliki akses ke halaman tersebut.');
    }
}
