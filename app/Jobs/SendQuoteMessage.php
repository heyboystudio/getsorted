<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Data\MessageChannel;
use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Support\NotificationPreferences;
use App\Models\Quote;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Quote news by WhatsApp (spec 010): the customer hears about received,
 * revised and withdrawn quotes; pros hear whether they were chosen. Messages
 * carry service and business names and a link, never contact details.
 */
final class SendQuoteMessage implements ShouldQueue
{
    use Queueable;

    private const array TO_CUSTOMER = ['quote_received', 'quote_revised', 'quote_withdrawn'];

    public int $tries = 3;

    public function __construct(
        public readonly int $quoteId,
        public readonly string $template,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(MessagingChannel $messaging): void
    {
        $quote = Quote::query()->with(['pro.user', 'serviceJob.customer', 'serviceJob.service'])->find($this->quoteId);

        if (! $quote instanceof Quote) {
            return;
        }

        $toCustomer = in_array($this->template, self::TO_CUSTOMER, true);
        $customer = $quote->serviceJob->customer;
        $phone = $toCustomer ? $customer->phone_e164 : $quote->pro->user->phone_e164;

        // Customers choose which messages they get (spec 021, AC15); pros' messages are not optional.
        if ($phone === null || ($toCustomer && ! NotificationPreferences::allows($customer, 'quotes'))) {
            return;
        }

        $messaging->send(new OutgoingMessage($phone, $this->template, [
            'service' => $quote->serviceJob->service->name,
            'pro' => (string) $quote->pro->business_name,
            'link' => $toCustomer ? route('jobs.show', $quote->serviceJob) : route('pros.jobs'),
        ], $toCustomer ? NotificationPreferences::channel($customer) : MessageChannel::WhatsApp));
    }
}
