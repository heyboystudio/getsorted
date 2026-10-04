<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Support;

use App\Domain\Quotes\Data\QuoteDraft;
use App\Domain\Quotes\Data\QuoteTotals;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\ServiceJob;
use App\Support\LocalTime;

/** Stores one quote version with its lines, masking contact details (spec 010, AC2, AC6). Callers hold the job lock. */
final class QuoteWriter
{
    public function write(ServiceJob $job, Pro $pro, QuoteDraft $draft, QuoteTotals $totals, int $version, ?Quote $supersedes): Quote
    {
        [$notes, $masked] = $draft->notes === null || trim($draft->notes) === '' ? [null, false] : ContactMasker::mask($draft->notes);

        $quote = new Quote;
        $quote->forceFill([
            'service_job_id' => $job->id,
            'pro_id' => $pro->id,
            'version' => $version,
            'status' => QuoteStatus::Submitted,
            'labour_cents' => $totals->labourCents,
            'materials_cents' => $totals->materialsCents,
            'callout_cents' => $totals->calloutCents,
            'vat_cents' => $totals->vatCents,
            'total_cents' => $totals->totalCents,
            'deposit_percent' => $draft->depositPercent,
            'deposit_cents' => $totals->depositCents,
            'earliest_start_date' => $draft->earliestStartDate->setTimezone(LocalTime::timezone())->toDateString(),
            'valid_until' => LocalTime::today()->addDays($draft->validityDays)->toDateString(),
            'notes' => $notes,
            'submitted_at' => now(),
            'supersedes_quote_id' => $supersedes?->id,
        ])->save();

        foreach ($draft->lines as $index => $line) {
            [$description, $lineMasked] = ContactMasker::mask(trim($line->description));
            $masked = $masked || $lineMasked;

            $row = new QuoteLine;
            $row->forceFill([
                'quote_id' => $quote->id,
                'kind' => $line->kind,
                'description' => mb_substr($description, 0, 120),
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unitPriceCents,
                'line_total_cents' => $totals->lineTotalsCents[$index],
                'sort' => $index,
            ])->save();
        }

        if ($masked) {
            Pro::query()->whereKey($pro->id)->increment('contact_masking_count');
        }

        return $quote->load('lines');
    }
}
