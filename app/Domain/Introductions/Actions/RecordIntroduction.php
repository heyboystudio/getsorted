<?php

declare(strict_types=1);

namespace App\Domain\Introductions\Actions;

use App\Domain\Introductions\Enums\CreditEntryType;
use App\Domain\Introductions\Support\ProCredit;
use App\Models\Introduction;
use App\Models\Pro;
use App\Models\ProCreditEntry;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;

/**
 * Writes the introduction when a client chooses a pro (spec 023, AC5–AC7) and, when the fee applies,
 * takes it from the pro's credit. Runs inside AcceptQuote's transaction, so it either happens with the
 * booking or not at all. The balance may dip below zero if a pro quoted on several jobs at once;
 * they then cannot send new estimates until they top up.
 */
final readonly class RecordIntroduction
{
    public function __construct(private ProCredit $credit) {}

    public function handle(ServiceJob $job, Quote $chosen, User $customer): Introduction
    {
        $pro = Pro::query()->lockForUpdate()->findOrFail($chosen->pro_id);
        $kind = $this->credit->nextKind($pro);
        $fee = $this->credit->nextFeeCents($pro);

        $introduction = new Introduction;
        $introduction->forceFill([
            'service_job_id' => $job->id,
            'quote_id' => $chosen->id,
            'pro_id' => $pro->id,
            'customer_id' => $customer->id,
            'kind' => $kind,
            'fee_cents' => $fee,
        ])->save();

        if ($fee > 0) {
            $entry = new ProCreditEntry;
            $entry->forceFill([
                'pro_id' => $pro->id,
                'type' => CreditEntryType::Introduction,
                'amount_cents' => -$fee,
                'introduction_id' => $introduction->id,
                'note' => 'Introduction fee',
                'idempotency_key' => 'introduction:'.$introduction->id,
            ])->save();
        }

        activity()->causedBy($customer)->performedOn($job)->withProperties(['introduction' => $introduction->public_id, 'kind' => $kind->value, 'fee_cents' => $fee])->log('introduction_recorded');

        return $introduction;
    }
}
