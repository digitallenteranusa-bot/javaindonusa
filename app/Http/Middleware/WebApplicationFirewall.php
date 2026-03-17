<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebApplicationFirewall
{
    /**
     * SQL injection patterns
     */
    protected array $sqlPatterns = [
        '/(\bunion\b.*\bselect\b)/i',
        '/(\bselect\b.*\bfrom\b.*\bwhere\b)/i',
        '/(\binsert\b.*\binto\b)/i',
        '/(\bdelete\b.*\bfrom\b)/i',
        '/(\bdrop\b.*\b(table|database)\b)/i',
        '/(\bupdate\b.*\bset\b)/i',
        '/(\/\*.*\*\/)/i',
        '/(;\s*(drop|alter|create|truncate|exec)\b)/i',
        '/(\bor\b\s+\d+\s*=\s*\d+)/i',
        '/(\band\b\s+\d+\s*=\s*\d+)/i',
        '/(sleep\s*\(\s*\d+\s*\))/i',
        '/(benchmark\s*\()/i',
        '/(waitfor\s+delay)/i',
    ];

    /**
     * XSS attack patterns
     */
    protected array $xssPatterns = [
        '/<script\b[^>]*>(.*?)<\/script>/is',
        '/on(error|load|click|mouseover|submit|focus|blur)\s*=/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/expression\s*\(/i',
        '/<\s*(iframe|object|embed|applet|form|meta|link)\b/i',
        '/(\beval\s*\()/i',
        '/(document\.(cookie|write|location))/i',
        '/(window\.(location|open))/i',
    ];

    /**
     * Path traversal patterns
     */
    protected array $pathPatterns = [
        '/\.\.\//i',
        '/\.\.\\\/i',
        '/%2e%2e/i',
        '/%252e%252e/i',
        '/etc\/(passwd|shadow|hosts)/i',
        '/proc\/self/i',
        '/\/?(wp-admin|wp-login|xmlrpc|wp-content|wp-includes)/i',
        '/\.(env|git|svn|htaccess|htpasswd|ini|log|bak|sql|swp)\b/i',
    ];

    /**
     * Malicious bot user agents
     */
    protected array $badBots = [
        'sqlmap',
        'nikto',
        'nessus',
        'openvas',
        'masscan',
        'nmap',
        'dirbuster',
        'gobuster',
        'wpscan',
        'havij',
        'acunetix',
        'zmeu',
        'python-urllib',  // Often used in basic scripts
    ];

    /**
     * Whitelisted paths that should skip WAF checks (e.g., admin text editors)
     */
    protected array $whitelistedPaths = [
        'admin/settings',
        'admin/broadcast',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Skip WAF for whitelisted paths
        foreach ($this->whitelistedPaths as $path) {
            if ($request->is($path, $path . '/*')) {
                return $next($request);
            }
        }

        // 1. Block malicious bots
        if ($this->isBadBot($request)) {
            return $this->blockRequest($request, 'bad_bot');
        }

        // 2. Check URI for path traversal
        if ($this->hasPathTraversal($request)) {
            return $this->blockRequest($request, 'path_traversal');
        }

        // 3. Check request input for SQL injection & XSS
        $input = $this->getRequestInput($request);

        if ($this->hasSqlInjection($input)) {
            return $this->blockRequest($request, 'sql_injection');
        }

        if ($this->hasXssAttack($input)) {
            return $this->blockRequest($request, 'xss_attack');
        }

        // 4. Block oversized requests (potential DoS)
        if ($this->isOversizedRequest($request)) {
            return $this->blockRequest($request, 'oversized_request');
        }

        return $next($request);
    }

    protected function isBadBot(Request $request): bool
    {
        $userAgent = strtolower($request->userAgent() ?? '');

        foreach ($this->badBots as $bot) {
            if (str_contains($userAgent, $bot)) {
                return true;
            }
        }

        return false;
    }

    protected function hasPathTraversal(Request $request): bool
    {
        $uri = urldecode($request->getRequestUri());

        foreach ($this->pathPatterns as $pattern) {
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    protected function hasSqlInjection(string $input): bool
    {
        foreach ($this->sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    protected function hasXssAttack(string $input): bool
    {
        foreach ($this->xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    protected function isOversizedRequest(Request $request): bool
    {
        // Block requests with more than 100 parameters
        $paramCount = count($request->all(), COUNT_RECURSIVE);
        return $paramCount > 500;
    }

    protected function getRequestInput(Request $request): string
    {
        $inputs = [];

        // Query string
        $inputs[] = urldecode($request->getQueryString() ?? '');

        // POST/PUT body (exclude file uploads)
        foreach ($request->except(['_token', '_method']) as $key => $value) {
            if (is_string($value)) {
                $inputs[] = $value;
            } elseif (is_array($value)) {
                array_walk_recursive($value, function ($item) use (&$inputs) {
                    if (is_string($item)) {
                        $inputs[] = $item;
                    }
                });
            }
        }

        return implode(' ', $inputs);
    }

    protected function blockRequest(Request $request, string $reason): Response
    {
        Log::channel('daily')->warning('WAF blocked request', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
        ]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Request blocked by security policy.',
            ], 403);
        }

        abort(403, 'Request blocked by security policy.');
    }
}
