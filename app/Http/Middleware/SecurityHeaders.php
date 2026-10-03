<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every response (security baseline §6).
 *
 * The CSP still allows inline scripts/styles and eval because Livewire,
 * Alpine and Filament rely on them; a nonce-based policy is a later
 * hardening step. Framing is blocked entirely and HSTS is sent only over HTTPS.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        // The Vite dev server serves assets and hot reload from another port locally.
        $dev = app()->environment('local') ? ' http://127.0.0.1:5173 http://localhost:5173' : '';
        $devSocket = app()->environment('local') ? ' ws://127.0.0.1:5173 ws://localhost:5173' : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'".$dev,
            "style-src 'self' 'unsafe-inline'".$dev,
            "img-src 'self' data: blob:",
            "font-src 'self' data:".$dev,
            "connect-src 'self'".$dev.$devSocket,
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
