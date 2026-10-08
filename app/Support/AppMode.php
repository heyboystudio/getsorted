<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Which environments may fake third parties and show login codes on screen.
 * "preview" is the private test site with fake data only (decision 037);
 * staging and production never do either.
 */
final class AppMode
{
    public static function usesFakeIntegrations(): bool
    {
        return app()->environment(['local', 'testing', 'preview']);
    }

    public static function showsLoginCodes(): bool
    {
        return app()->environment(['local', 'preview']);
    }

    public static function isPreview(): bool
    {
        return app()->environment('preview');
    }
}
