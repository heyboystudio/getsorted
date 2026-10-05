<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Filament\Admin\Pages\Auth\Login;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin and customer logins share the `web` guard. Filament challenges MFA only
 * on its own login page, so a session started any other way (phone login, a
 * remember-me cookie, or a customer later given an admin role) is signed out
 * and sent to the admin login, where password (and MFA, if switched on) is required.
 */
final class EnsureAdminSignedInThroughPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = Filament::auth()->id();

        if ($userId !== null && $request->session()->get(Login::SESSION_KEY) !== $userId) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to(Filament::getLoginUrl() ?? '/admin/login');
        }

        return $next($request);
    }
}
