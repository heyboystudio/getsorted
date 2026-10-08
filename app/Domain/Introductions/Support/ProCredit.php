<?php

declare(strict_types=1);

namespace App\Domain\Introductions\Support;

use App\Domain\Introductions\Enums\IntroductionKind;
use App\Models\Introduction;
use App\Models\Pro;
use App\Models\ProCreditEntry;
use App\Settings\IntroductionSettings;

/** A pro's credit balance and what the next introduction would cost (spec 023). */
final readonly class ProCredit
{
    public function __construct(private IntroductionSettings $settings) {}

    public function feeEnabled(): bool
    {
        return $this->settings->fee_enabled;
    }

    public function balanceCents(Pro $pro): int
    {
        return (int) ProCreditEntry::query()->where('pro_id', $pro->id)->sum('amount_cents');
    }

    public function introductionsSoFar(Pro $pro): int
    {
        return Introduction::query()->where('pro_id', $pro->id)->count();
    }

    public function freeLeft(Pro $pro): int
    {
        return max(0, $this->settings->free_introductions - $this->introductionsSoFar($pro));
    }

    /** The fee the next introduction would carry for this pro: nothing while the fee is off or the allowance lasts. */
    public function nextFeeCents(Pro $pro): int
    {
        if (! $this->settings->fee_enabled || $this->freeLeft($pro) > 0) {
            return 0;
        }

        return $this->settings->fee_cents;
    }

    public function nextKind(Pro $pro): IntroductionKind
    {
        return $this->nextFeeCents($pro) === 0 ? IntroductionKind::Free : IntroductionKind::Credit;
    }

    /** A pro can take on a new job (send an estimate) only while their credit covers one introduction. */
    public function canQuote(Pro $pro): bool
    {
        $fee = $this->nextFeeCents($pro);

        return $fee === 0 || $this->balanceCents($pro) >= $fee;
    }
}
