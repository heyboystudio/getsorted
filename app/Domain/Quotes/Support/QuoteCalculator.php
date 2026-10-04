<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Support;

use App\Domain\Quotes\Data\QuoteDraft;
use App\Domain\Quotes\Data\QuoteTotals;
use App\Domain\Quotes\Enums\LineKind;
use App\Settings\MoneySettings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Turns quote lines into amounts. Rounding happens once per line total and
 * once for VAT, deposit and commission, always half-up to the cent (spec 010, AC2).
 */
final readonly class QuoteCalculator
{
    public function __construct(private MoneySettings $settings) {}

    public function calculate(QuoteDraft $draft, bool $vatRegistered): QuoteTotals
    {
        $lineTotals = [];
        $byKind = [LineKind::Labour->value => Money::zero('ZAR'), LineKind::Materials->value => Money::zero('ZAR'), LineKind::Callout->value => Money::zero('ZAR')];

        foreach ($draft->lines as $line) {
            $total = Money::ofMinor($line->unitPriceCents, 'ZAR')->multipliedBy(BigDecimal::of($line->quantity), RoundingMode::HalfUp);
            $lineTotals[] = $total->getMinorAmount()->toInt();
            $byKind[$line->kind->value] = $byKind[$line->kind->value]->plus($total);
        }

        $subtotal = $byKind['labour']->plus($byKind['materials'])->plus($byKind['callout']);
        $vat = $vatRegistered ? $subtotal->multipliedBy(BigDecimal::of($this->settings->vat_percent)->dividedBy(100, 4), RoundingMode::HalfUp) : Money::zero('ZAR');
        $total = $subtotal->plus($vat);
        $deposit = $total->multipliedBy(BigDecimal::of($draft->depositPercent)->dividedBy(100, 4), RoundingMode::HalfUp);
        $commission = $byKind['labour']->plus($byKind['callout'])
            ->multipliedBy(BigDecimal::of($this->settings->commission_percent)->dividedBy(100, 4), RoundingMode::HalfUp);

        return new QuoteTotals(
            lineTotalsCents: $lineTotals,
            labourCents: $byKind['labour']->getMinorAmount()->toInt(),
            materialsCents: $byKind['materials']->getMinorAmount()->toInt(),
            calloutCents: $byKind['callout']->getMinorAmount()->toInt(),
            vatCents: $vat->getMinorAmount()->toInt(),
            totalCents: $total->getMinorAmount()->toInt(),
            depositCents: $deposit->getMinorAmount()->toInt(),
            commissionEstimateCents: $commission->getMinorAmount()->toInt(),
            payoutEstimateCents: $total->minus($commission)->getMinorAmount()->toInt(),
        );
    }
}
