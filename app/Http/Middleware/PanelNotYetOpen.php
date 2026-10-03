<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides a Filament panel completely until its feature ships.
 * The pro panel opens with pro phone login (Phase 1/3).
 */
final class PanelNotYetOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        abort(404);
    }
}
