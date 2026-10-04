<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The customer area (`/app`) is for customers; pro-only accounts go to the pro area (spec 011). */
final class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->hasRole(Role::Customer->value)) {
            return redirect()->route($user->homeRoute());
        }

        return $next($request);
    }
}
