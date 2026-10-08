<?php

declare(strict_types=1);

/*
| GetSorted business and security values. Product values that admins change at
| runtime belong in spatie/laravel-settings instead.
*/

return [

    // Host the admin panel lives on (e.g. dashboard.usesorted.co.za). Null keeps it at /admin on the main host (local and tests).
    'admin_domain' => env('GETSORTED_ADMIN_DOMAIN') ?: null,

    // Set to true once the site sits behind the Cloudflare proxy, so visitors' real IPs are used.
    'behind_cloudflare' => (bool) env('GETSORTED_BEHIND_CLOUDFLARE', false),

    // Customers' local time for "today" and booking dates; storage stays UTC.
    'timezone' => 'Africa/Johannesburg',

    'auth' => [
        // "Keep me logged in" for customers and pros (security baseline §1).
        'remember_days' => 30,
        // Sign-up attempts per IP address (security baseline §1).
        'register_per_ip' => ['max' => 5, 'minutes' => 15],
    ],

    'places' => [
        'municipality' => 'eThekwini',
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

    // Spec 018: chat between customers and pros.
    'chat' => [
        'max_length' => 1000,
        'photos_per_message' => 5,
        'photos_per_day' => 20,
        'messages_per_hour' => 30,
        // At most one "new message" WhatsApp per conversation and person in this time.
        'notify_every_minutes' => 15,
        // Someone who looked at the chat this recently is treated as "on the page": no notification.
        'online_seconds' => 30,
        'retention_months' => 24,
    ],

    // Decision 044: before launch, take requests from every eThekwini suburb even without pros.
    'coverage' => [
        'require_pros' => (bool) env('GETSORTED_REQUIRE_PROS', false),
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
        // anthropic (direct API), bedrock (Amazon Bedrock, EU; decision 043) or gemini (Google Gemini API; decision 049).
        // Bedrock uses the server's IAM role; Gemini needs GEMINI_API_KEY.
        'provider' => env('GETSORTED_AI_PROVIDER', 'anthropic'),
        'model' => env('GETSORTED_AI_MODEL', 'claude-haiku-4-5-20251001'),
        'timeout_seconds' => 8,
        'suggestions_per_hour' => 10,
        'summaries_per_hour' => 5,
        // Spec 016 (Siya): messages per conversation and per visitor per hour.
        'chat_messages_per_conversation' => 30,
        'chat_messages_per_hour' => 60,
        'chat_timeout_seconds' => 25,
        // Gemini 3 reasoning effort for Siya: minimal, low, medium or high; empty leaves the model's default (slower).
        'thinking_level' => env('GETSORTED_AI_THINKING_LEVEL', 'minimal'),
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
