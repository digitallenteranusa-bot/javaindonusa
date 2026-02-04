<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions  One or more permission names to check (user needs ANY of them)
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // If no permissions specified, allow
        if (empty($permissions)) {
            return $next($request);
        }

        // Admin bypasses all permission checks
        if ($request->user()->isAdmin()) {
            return $next($request);
        }

        // Check if user has any of the required permissions
        if ($request->user()->hasAnyPermission($permissions)) {
            return $next($request);
        }

        // Handle unauthorized access
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk aksi ini.',
            ], 403);
        }

        return back()->with('error', 'Anda tidak memiliki izin untuk aksi ini.');
    }
}
