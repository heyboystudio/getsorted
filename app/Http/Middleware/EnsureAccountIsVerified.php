<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A signed-in customer or pro can use nothing but the verification steps,
 * sign-out and the legal pages until their email and mobile are verified
 * (spec 014, AC6, AC10). Where they were going is remembered for afterwards.
 */
final class EnsureAccountIsVerified
{
    /** Route names reachable before verification. */
    private const array ALLOWED = [
        'verification.email', 'verification.email.verify', 'verification.phone',
        'logout', 'terms', 'privacy', 'pros.agreement',
    ];

    /**
     * Livewire's own requests pass: their pages were only reachable once
     * verified, and each component still checks the user itself.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $step = $user instanceof User ? $user->pendingVerificationRoute() : null;
        $route = $request->route()?->getName();

        if ($step === null || $route === null || in_array($route, self::ALLOWED, true) || str_contains($route, 'livewire.') || str_starts_with($route, 'filament.')) {
            return $next($request);
        }

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route($step);
    }
}
