<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
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

    public function handle(MessagingChannel $messaging): void
    {
        $pro = Pro::query()->with('user')->find($this->proId);

        if ($pro === null || $pro->user->phone_e164 === null) {
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

        $messaging->send(new OutgoingMessage($pro->user->phone_e164, $this->template, ['first_name' => (string) $pro->user->first_name]));
    }
}
