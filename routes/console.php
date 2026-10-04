<?php

declare(strict_types=1);

use App\Models\PhoneOtp;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// POPIA retention: delete login codes older than the configured period.
Schedule::command('model:prune', ['--model' => [PhoneOtp::class]])->daily();
