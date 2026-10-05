<?php

declare(strict_types=1);

use App\Models\AiUsage;
use App\Models\JobMessage;
use App\Models\PhoneOtp;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// POPIA retention: delete login codes older than the configured period.
Schedule::command('model:prune', ['--model' => [PhoneOtp::class]])->daily();

// Spec 005: abandoned booking drafts expire.
Schedule::command('sortd:cancel-stale-drafts')->daily();
Schedule::command('model:prune', ['--model' => [WaitlistEntry::class]])->daily();

// Spec 007: AI usage records (no customer text) kept for the configured period.
Schedule::command('model:prune', ['--model' => [AiUsage::class]])->daily();

// Spec 018, decision 6: chats are kept 24 months after the job ends, photos included.
Schedule::command('model:prune', ['--model' => [JobMessage::class]])->daily();

// Spec 008: vetting records of rejected or abandoned applications (POPIA retention).
Schedule::command('sortd:prune-vetting-records')->daily();

// Spec 009: invite waves, expiry and closing.
Schedule::command('sortd:run-matching')->everyFiveMinutes()->withoutOverlapping();

// Spec 010: quote validity and the job quote window.
Schedule::command('sortd:expire-quotes')->everyFiveMinutes()->withoutOverlapping();
