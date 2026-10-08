<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Notifications\Notify;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Tells a pro their application or standing changed (spec 008, AC9–AC11). Details are on the status page, not in the message. */
final class SendProStatusMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $proId,
        public readonly string $template,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(): void
    {
        $pro = Pro::query()->with('user')->find($this->proId);

        if ($pro === null) {
            return;
        }

        // Skip a message overtaken by a later change (approved, then suspended a minute later).
        $expected = match ($this->template) {
            'pro_approved' => ProStatus::Approved,
            'pro_changes_requested' => ProStatus::ChangesRequested,
            'pro_rejected' => ProStatus::Rejected,
            'pro_suspended' => ProStatus::Suspended,
            default => null,
        };

        if ($expected !== null && $pro->status !== $expected) {
            return;
        }

        if ($this->attempts() === 1) {
            [$title, $body] = match ($this->template) {
                'pro_approved' => [__('You are approved'), __('Your application was approved. You will now be sent jobs near you.')],
                'pro_changes_requested' => [__('We need a few changes'), __('Open your application to see what to fix, then send it back.')],
                'pro_rejected' => [__('Your application was not approved'), __('Open your application to see why and when you can apply again.')],
                'pro_suspended' => [__('Your account is paused'), __('Please contact support if you have questions.')],
                default => [null, null],
            };

            if ($title !== null) {
                Notify::user($pro->user, $this->template, $title, $body, route('pros.status'), email: true);
            }
        }
    }
}
