<?php

declare(strict_types=1);

/*
| Sortd business and security values. Product values that admins change at
| runtime belong in spatie/laravel-settings instead.
*/

return [

    // Customers' local time for "today" and booking dates; storage stays UTC.
    'timezone' => 'Africa/Johannesburg',

    'otp' => [
        // Security baseline §1: 6 digits, 10-minute expiry, 5 attempts, single use.
        'length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        // The first channel for codes; the other is offered after the wait below.
        // SMS first until the WhatsApp sender is set up (founder, 2026-10-05).
        'default_channel' => env('OTP_DEFAULT_CHANNEL', 'sms'),
        // Test site only (decision 041): when false, the mobile is saved without
        // a code. Ignored everywhere except local and preview.
        'phone_codes_enabled' => (bool) env('PHONE_CODES_ENABLED', true),
        'sms_fallback_after_seconds' => 30,
        // POPIA checklist: OTP records kept 90 days.
        'retention_days' => 90,
        // How long a verified phone stays valid for finishing sign-up.
        'verified_phone_ttl_minutes' => 15,
        // Rate limits (security baseline §1). The daily cap per number blunts
        // slow guessing from rotating IP addresses.
        'send_per_phone' => ['max' => 3, 'minutes' => 15],
        'send_per_phone_daily' => ['max' => 10, 'minutes' => 24 * 60],
        // Temporarily off on local development machines only, for testing (founder, 2026-10-04).
        // Always enforced in testing, staging and production. Set true to turn it back on locally.
        'daily_cap_in_local' => false,
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

    'job_photos' => [
        'max_count' => 5,
        'max_kilobytes' => 10_240,
    ],

    // Decision 044: before launch, take requests from every eThekwini suburb even without pros.
    'coverage' => [
        'require_pros' => (bool) env('SORTD_REQUIRE_PROS', false),
    ],

    'waitlist' => [
        'retention_months' => 12,
        'submissions_per_hour' => 5,
        'submissions_per_ip_hour' => 20,
        'checks_per_hour' => 60,
        'searches_per_hour' => 120,
    ],

    'quotes' => [
        // Abuse protection for sending, revising and withdrawing quotes (spec 010).
        'changes_per_hour' => 30,
    ],

    'matching' => [
        // Abuse protection for pros turning invites down (spec 009).
        'declines_per_hour' => 30,
    ],

    'pros' => [
        // Abuse protection for the pro application (spec 008).
        'uploads_per_hour' => 30,
        'submissions_per_hour' => 5,
    ],

    'ai' => [
        // Spec 007. Switch, confidence threshold and daily budget are admin settings (AiSettings).
        // anthropic (direct API) or bedrock (Amazon Bedrock, EU; decision 043). Bedrock uses the server's IAM role.
        'provider' => env('SORTD_AI_PROVIDER', 'anthropic'),
        'model' => env('SORTD_AI_MODEL', 'claude-haiku-4-5-20251001'),
        'timeout_seconds' => 8,
        'suggestions_per_hour' => 10,
        'summaries_per_hour' => 5,
        // Spec 016 (Siya): messages per conversation and per visitor per hour.
        'chat_messages_per_conversation' => 30,
        'chat_messages_per_hour' => 60,
        'chat_timeout_seconds' => 15,
        // How long an unused home-page description waits in the session for a booking.
        'description_ttl_minutes' => 30,
    ],

    'legal' => [
        // Placeholder documents until lawyer-reviewed text exists (spec 001 decision 1).
        'terms_version' => '2026-10-draft',
        'privacy_version' => '2026-10-draft',
        'pro_agreement_version' => '2026-10-draft',
    ],

];
