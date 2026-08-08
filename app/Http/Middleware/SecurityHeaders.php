<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // Landing page (welcome.blade.php) uses Google Fonts + a large inline <style> block
        // and a few style="" attributes. Production CSP must allow those or the page looks broken.
        $contentSecurityPolicy = app()->environment('local')
            ? "default-src 'self' data: http://localhost:5173; script-src 'self' 'unsafe-eval' http://localhost:5173; style-src 'self' 'unsafe-inline' http://localhost:5173 https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' http://localhost:5173 ws://localhost:5173; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'"
            : "default-src 'self'; script-src 'self' https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://cloudflareinsights.com https://static.cloudflareinsights.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";
        $response->headers->set('Content-Security-Policy', $contentSecurityPolicy);
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
