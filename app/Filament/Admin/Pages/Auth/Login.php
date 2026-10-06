<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Admin login without "remember me": admin sessions must expire after the
 * 2-hour idle timeout (security baseline §1), so long-lived cookies are not offered.
 */
final class Login extends BaseLogin
{
    /** Marks a session as signed in through this page (password + MFA). */
    public const string SESSION_KEY = 'admin.signed_in_user_id';

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        // Null means another step (the MFA challenge) is still pending.
        if ($response instanceof LoginResponse && Filament::auth()->check()) {
            session()->put(self::SESSION_KEY, Filament::auth()->id());
        }

        return $response;
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
            ]);
    }
}
