<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Support;

use App\Domain\Quotes\Data\QuoteDraft;
use App\Domain\Quotes\Data\QuoteTotals;
use App\Domain\Quotes\Enums\LineKind;
use App\Settings\QuoteSettings;
use App\Support\LocalTime;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

/** What a quote may contain (spec 010, AC1, rules). Errors are keyed for the builder's fields. */
final readonly class QuoteRules
{
    public function __construct(private QuoteSettings $settings) {}

    /** @throws ValidationException */
    public function check(QuoteDraft $draft, QuoteTotals $totals): void
    {
        $errors = [];

        if ($draft->lines === []) {
            $errors['lines'] = __('Add at least one line.');
        }

        if (count(array_filter($draft->lines, fn ($line): bool => $line->kind === LineKind::Callout)) > 1) {
            $errors['lines'] = __('Add the call-out fee once.');
        }

        if (count($draft->lines) > 30) {
            $errors['lines'] = __('A quote can have up to 30 lines.');
        }

        foreach ($draft->lines as $index => $line) {
            $description = trim($line->description);

            if ($description === '' || mb_strlen($description) > 120) {
                $errors["lines.{$index}.description"] = __('Describe the line in up to 120 characters.');
            }

            if (preg_match('/^\d{1,4}(\.\d{1,2})?$/', $line->quantity) !== 1
                || BigDecimal::of($line->quantity)->isLessThan('0.01')
                || BigDecimal::of($line->quantity)->isGreaterThan(9999)) {
                $errors["lines.{$index}.quantity"] = __('Enter a quantity from 0.01 to 9 999.');
            }

            if ($line->unitPriceCents < 0) {
                $errors["lines.{$index}.unit_price"] = __('Prices cannot be negative.');
            }
        }

        if ($draft->depositPercent < 0 || $draft->depositPercent > $this->settings->max_deposit_percent) {
            $errors['deposit_percent'] = __('The deposit can be 0 to :max%.', ['max' => $this->settings->max_deposit_percent]);
        }

        $today = LocalTime::today();
        $start = $draft->earliestStartDate->setTimezone(LocalTime::timezone())->startOfDay();

        if ($start->lt($today) || $start->gt($today->addDays(60))) {
            $errors['earliest_start_date'] = __('Choose a start date in the next 60 days.');
        }

        if ($draft->validityDays < 1 || $draft->validityDays > 30) {
            $errors['validity_days'] = __('A quote can be valid for 1 to 30 days.');
        }

        if ($draft->notes !== null && mb_strlen($draft->notes) > 1000) {
            $errors['notes'] = __('Notes can be up to 1 000 characters.');
        }

        if ($totals->totalCents > $this->settings->max_total_cents) {
            $errors['total'] = __('A quote cannot be more than R :max.', ['max' => number_format($this->settings->max_total_cents / 100, 0, '.', ' ')]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
