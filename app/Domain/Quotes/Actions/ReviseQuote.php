<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Actions;

use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Quotes\Data\QuoteDraft;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Quotes\Exceptions\CannotQuote;
use App\Domain\Quotes\Support\QuoteCalculator;
use App\Domain\Quotes\Support\QuoteFlow;
use App\Domain\Quotes\Support\QuoteRules;
use App\Domain\Quotes\Support\QuoteWriter;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Jobs\SendQuoteMessage;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * A new version of a pro's quote: replaces a sent one without using another
 * slot, or renews an expired one while the job is open (spec 010, AC5, AC12).
 */
final readonly class ReviseQuote
{
    public function __construct(private QuoteCalculator $calculator, private QuoteRules $rules, private QuoteWriter $writer) {}

    public function handle(User $user, Quote $quote, QuoteDraft $draft): Quote
    {
        $pro = Pro::query()->findOrFail($quote->pro_id);

        if ($pro->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $totals = $this->calculator->calculate($draft, $pro->vat_number !== null);
        $this->rules->check($draft, $totals);
        SubmitQuote::throttle($user);

        $revised = DB::transaction(function () use ($quote, $draft, $totals): Quote {
            $job = ServiceJob::query()->lockForUpdate()->findOrFail($quote->service_job_id);
            $current = Quote::query()->lockForUpdate()->findOrFail($quote->id);
            $pro = Pro::query()->findOrFail($current->pro_id);

            if ($job->status !== ServiceJobStatus::Open || $pro->status !== ProStatus::Approved) {
                throw new CannotQuote(__('This job is no longer open for quotes.'));
            }

            if ($current->latestVersion()->id !== $current->id || ! in_array($current->status, [QuoteStatus::Submitted, QuoteStatus::Expired], true)) {
                throw new CannotQuote(__('Only your latest sent or expired quote can be revised.'));
            }

            if ($current->status === QuoteStatus::Expired && $job->quotes_count >= QuoteFlow::MAX_QUOTES) {
                throw new CannotQuote(__('This job is full. Thanks for your interest.'));
            }

            $current->forceFill(['status' => QuoteStatus::Superseded])->save();
            $revised = $this->writer->write($job, $pro, $draft, $totals, $current->version + 1, $current);
            QuoteFlow::refreshCount($job);

            return $revised;
        });

        SendQuoteMessage::dispatch($revised->id, 'quote_revised');

        return $revised;
    }
}
