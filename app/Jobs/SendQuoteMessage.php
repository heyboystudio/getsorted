<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Data\MessageChannel;
use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Support\NotificationPreferences;
use App\Domain\Notifications\Notify;
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

    /**
     * Twilio rate-limits bursts (429), so retry after a pause rather than at once.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly int $quoteId,
        public readonly string $template,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(MessagingChannel $messaging): void
    {
        $quote = Quote::query()->with(['pro.user', 'serviceJob.customer', 'serviceJob.trade'])->find($this->quoteId);

        if (! $quote instanceof Quote) {
            return;
        }

        $toCustomer = in_array($this->template, self::TO_CUSTOMER, true);

        if ($this->attempts() === 1) {
            $trade = mb_strtolower($quote->serviceJob->trade->name);
            $pro = (string) $quote->pro->business_name;
            [$title, $body] = match ($this->template) {
                'quote_received' => [__('New quote for your :trade job', ['trade' => $trade]), __(':pro sent you a quote. Compare quotes and choose the one you like.', ['pro' => $pro])],
                'quote_revised' => [__('A quote was updated', []), __(':pro updated their quote for your :trade job.', ['pro' => $pro, 'trade' => $trade])],
                'quote_withdrawn' => [__('A quote was withdrawn', []), __(':pro withdrew their quote for your :trade job.', ['pro' => $pro, 'trade' => $trade])],
                'quote_accepted' => [__('Your quote was accepted'), __('The client chose your quote for the :trade job. Open it to see the details.', ['trade' => $trade])],
                'quote_not_chosen' => [__('The client chose another pro'), __('Thanks for quoting on the :trade job. The client went with someone else this time.', ['trade' => $trade])],
                default => [null, null],
            };

            if ($title !== null) {
                Notify::user(
                    $toCustomer ? $quote->serviceJob->customer : $quote->pro->user,
                    $this->template,
                    $title,
                    $body,
                    $toCustomer ? route('jobs.show', $quote->serviceJob) : route('pros.jobs'),
                    email: in_array($this->template, ['quote_received', 'quote_accepted'], true),
                    group: $toCustomer ? 'quotes' : null,
                );
            }
        }

        $customer = $quote->serviceJob->customer;
        $phone = $toCustomer ? $customer->phone_e164 : $quote->pro->user->phone_e164;

        // Customers choose which texts they get (spec 021, AC15); pros' messages are not optional.
        if ($phone === null || ($toCustomer && ! NotificationPreferences::allows($customer, 'quotes'))) {
            return;
        }

        $messaging->send(new OutgoingMessage($phone, $this->template, [
            'service' => $quote->serviceJob->trade->name,
            'pro' => (string) $quote->pro->business_name,
            'link' => $toCustomer ? route('jobs.show', $quote->serviceJob) : route('pros.jobs'),
        ], $toCustomer ? NotificationPreferences::channel($customer) : MessageChannel::WhatsApp));
    }
}
