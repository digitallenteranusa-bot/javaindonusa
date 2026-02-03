<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Security headers to add to all responses
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS filter in browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (formerly Feature-Policy)
        $response->headers->set('Permissions-Policy', 'geolocation=(self), microphone=(), camera=()');

        // Content Security Policy - adjust as needed for your application
        if (app()->environment('production')) {
            $response->headers->set('Content-Security-Policy', $this->getCSP());
        }

        // Strict Transport Security (HTTPS only) - only in production
        if (app()->environment('production') && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Get Content Security Policy header value
     */
    protected function getCSP(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // Required for Vue.js
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net", // Required for Tailwind + Bunny Fonts
            "img-src 'self' data: blob: https://*.tile.openstreetmap.org https://*.openstreetmap.org https://server.arcgisonline.com https://*.arcgisonline.com https://*.basemaps.cartocdn.com", // Map tiles (OSM + ESRI Satellite + Carto)
            "font-src 'self' data: https://fonts.bunny.net", // Bunny Fonts
            "connect-src 'self' https://*.tile.openstreetmap.org https://server.arcgisonline.com https://*.arcgisonline.com https://*.basemaps.cartocdn.com", // Map tile requests
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
        ]);
    }
}
