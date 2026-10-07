<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Models\FinalAmountProposal;
use App\Support\Rand;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Final-amount news by WhatsApp/SMS (spec 018): the customer hears about a proposed
 * increase, a lowered price or a cancellation; the pro hears the customer's answer.
 */
final class SendFinalAmountMessage implements ShouldQueue
{
    use Queueable;

    private const array TO_CUSTOMER = ['final_amount_proposed', 'final_amount_lowered', 'cancelled_price_not_agreed'];

    public int $tries = 3;

    public function __construct(
        public readonly int $proposalId,
        public readonly string $template,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(MessagingChannel $messaging): void
    {
        $proposal = FinalAmountProposal::query()->with(['serviceJob.customer', 'serviceJob.service', 'pro.user'])->find($this->proposalId);

        if (! $proposal instanceof FinalAmountProposal) {
            return;
        }

        $toCustomer = in_array($this->template, self::TO_CUSTOMER, true);
        $phone = $toCustomer ? $proposal->serviceJob->customer->phone_e164 : $proposal->pro->user->phone_e164;

        if ($phone === null) {
            return;
        }

        $messaging->send(new OutgoingMessage($phone, $this->template, [
            'service' => $proposal->serviceJob->service->name,
            'pro' => (string) $proposal->pro->business_name,
            'amount' => Rand::format($proposal->total_cents),
            'link' => $toCustomer ? route('jobs.show', $proposal->serviceJob) : route('pros.jobs'),
        ]));
    }
}
