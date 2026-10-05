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

    /** Mobile numbers are saved without a code only on the test site or locally, when switched off (decision 041). */
    public static function skipsPhoneCodes(): bool
    {
        return app()->environment(['local', 'preview']) && ! config('sortd.otp.phone_codes_enabled');
    }

    public static function isPreview(): bool
    {
        return app()->environment('preview');
    }
}
