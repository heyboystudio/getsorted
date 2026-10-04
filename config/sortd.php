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
        // Rate limits (security baseline §1). The daily cap per number blunts
        // slow guessing from rotating IP addresses.
        'send_per_phone' => ['max' => 3, 'minutes' => 15],
        'send_per_phone_daily' => ['max' => 10, 'minutes' => 24 * 60],
        'send_per_ip' => ['max' => 10, 'minutes' => 60],
        'verify_per_ip' => ['max' => 10, 'minutes' => 15],
        'register_per_ip' => ['max' => 5, 'minutes' => 15],
    ],

    'auth' => [
        // "Keep me logged in" for customers and pros (security baseline §1).
        'remember_days' => 30,
    ],

    'places' => [
        'municipality' => 'eThekwini',
        // Rough bounding box used to sanity-check admin-entered suburb centres.
        'latitude' => ['min' => -30.5, 'max' => -29.3],
        'longitude' => ['min' => 30.5, 'max' => 31.3],
    ],

    'properties' => [
        // Saved properties per customer; prevents abuse of the address book.
        'max_per_customer' => 10,
    ],

    'jobs' => [
        // Abuse protection for the booking flow (spec 005).
        'max_drafts' => 5,
        'posts_per_day' => 10,
        // How far ahead customers may book.
        'booking_days_ahead' => 30,
        'notes_max_length' => 1000,
    ],

    'legal' => [
        // Placeholder documents until lawyer-reviewed text exists (spec 001 decision 1).
        'terms_version' => '2026-10-draft',
        'privacy_version' => '2026-10-draft',
        'pro_agreement_version' => '2026-10-draft',
    ],

];
