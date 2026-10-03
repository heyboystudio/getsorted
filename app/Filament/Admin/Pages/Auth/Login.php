<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;

/**
 * Admin login without "remember me": admin sessions must expire after the
 * 2-hour idle timeout (security baseline §1), so long-lived cookies are not offered.
 */
final class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
            ]);
    }
}
