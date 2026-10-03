<?php

declare(strict_types=1);

/*
| Sortd business and security values. Product values that admins change at
| runtime belong in spatie/laravel-settings instead.
*/

return [

    'otp' => [
        // Security baseline §1: 6 digits, 10-minute expiry, 5 attempts, single use.
        'length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'sms_fallback_after_seconds' => 30,
        // POPIA checklist: OTP records kept 90 days.
        'retention_days' => 90,
        // How long a verified phone stays valid for finishing sign-up.
        'verified_phone_ttl_minutes' => 15,
    ],

    'auth' => [
        // "Keep me logged in" for customers and pros (security baseline §1).
        'remember_days' => 30,
    ],

    'legal' => [
        // Placeholder documents until lawyer-reviewed text exists (spec 001 decision 1).
        'terms_version' => '2026-10-draft',
        'privacy_version' => '2026-10-draft',
    ],

];
