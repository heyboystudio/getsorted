<?php

declare(strict_types=1);

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/** Initials on an ink circle, drawn inline: the security headers only allow images from the site itself or data URLs. */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = $record instanceof Model && method_exists($record, 'getFilamentName') ? (string) $record->getFilamentName() : (string) ($record->name ?? '');
        $initials = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $initials = htmlspecialchars($initials === '' ? '?' : $initials, ENT_QUOTES);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#131311"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="Arial, sans-serif" font-size="26" font-weight="700" fill="#C6FD50">'.$initials.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
