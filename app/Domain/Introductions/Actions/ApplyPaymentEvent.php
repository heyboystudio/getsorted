<?php

declare(strict_types=1);

namespace App\Domain\Introductions\Actions;

use App\Contracts\Data\PaymentEvent;
use App\Contracts\Data\PaymentEventType;
use App\Domain\Introductions\Enums\CreditEntryType;
use App\Domain\Introductions\Enums\CreditPurchaseStatus;
use App\Models\CreditPurchase;
use App\Models\ProCreditEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Credit is added only from a verified PayFast notification, never from the pro's browser returning
 * (money-flow principle 3). Safe to run twice for the same payment: the ledger key is unique.
 */
final class ApplyPaymentEvent
{
    public function handle(PaymentEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $purchase = CreditPurchase::query()->where('public_id', $event->providerReference)->lockForUpdate()->first();

            if (! $purchase instanceof CreditPurchase) {
                Log::warning('Payment notification for an unknown credit purchase', ['reference' => $event->providerReference]);

                return;
            }

            if ($purchase->status !== CreditPurchaseStatus::Pending) {
                return;
            }

            if ($event->type === PaymentEventType::PaymentFailed) {
                $purchase->forceFill(['status' => CreditPurchaseStatus::Failed, 'provider_reference' => $event->eventId])->save();

                return;
            }

            if ($event->type !== PaymentEventType::PaymentSucceeded) {
                return;
            }

            if ($event->amount->getMinorAmount()->toInt() !== $purchase->amount_cents) {
                Log::warning('Payment notification amount does not match the credit purchase', ['purchase' => $purchase->public_id]);

                return;
            }

            $purchase->forceFill(['status' => CreditPurchaseStatus::Complete, 'provider_reference' => $event->eventId, 'completed_at' => now()])->save();

            $entry = new ProCreditEntry;
            $entry->forceFill([
                'pro_id' => $purchase->pro_id,
                'type' => CreditEntryType::Purchase,
                'amount_cents' => $purchase->amount_cents,
                'credit_purchase_id' => $purchase->id,
                'note' => 'PayFast payment',
                'idempotency_key' => 'purchase:'.$purchase->id,
            ])->save();

            activity()->performedOn($purchase)->withProperties(['amount_cents' => $purchase->amount_cents])->log('credit_purchased');
        });
    }
}
