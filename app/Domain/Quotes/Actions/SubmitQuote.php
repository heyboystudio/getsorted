<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Actions;

use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Quotes\Data\QuoteDraft;
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
use App\Models\ServiceJobInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** A pro sends their first quote on an invite (spec 010, AC1–AC4, AC6). */
final readonly class SubmitQuote
{
    public function __construct(private QuoteCalculator $calculator, private QuoteRules $rules, private QuoteWriter $writer) {}

    public function handle(User $user, ServiceJobInvite $invite, QuoteDraft $draft): Quote
    {
        Gate::forUser($user)->authorize('view', $invite);
        $pro = Pro::query()->findOrFail($invite->pro_id);
        $totals = $this->calculator->calculate($draft, $pro->isVatRegistered());
        $this->rules->check($draft, $totals);
        self::throttle($user);

        $quote = DB::transaction(function () use ($invite, $draft, $totals): Quote {
            $job = ServiceJob::query()->lockForUpdate()->findOrFail($invite->service_job_id);
            $locked = ServiceJobInvite::query()->lockForUpdate()->findOrFail($invite->id);
            $pro = Pro::query()->findOrFail($locked->pro_id);

            if ($job->quotes()->where('pro_id', $pro->id)->exists()) {
                throw new CannotQuote(__('You already sent a quote for this job. Revise it instead.'));
            }

            if ($job->status !== ServiceJobStatus::Open || $job->quotes_count >= QuoteFlow::MAX_QUOTES) {
                throw new CannotQuote(__('This job is full or no longer open. Thanks for your interest.'));
            }

            if (! $locked->isAvailable() || $pro->status !== ProStatus::Approved) {
                throw new CannotQuote(__('This job is no longer available.'));
            }

            $quote = $this->writer->write($job, $pro, $draft, $totals, version: 1, supersedes: null);
            $locked->forceFill(['status' => InviteStatus::Quoted, 'responded_at' => now()])->save();
            QuoteFlow::refreshCount($job);

            return $quote;
        });

        SendQuoteMessage::dispatch($quote->id, 'quote_received');

        return $quote;
    }

    public static function throttle(User $user): void
    {
        $key = 'quotes:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, (int) config('sortd.quotes.changes_per_hour'))) {
            throw ValidationException::withMessages(['quote' => __('Please try again later.')]);
        }

        RateLimiter::hit($key, 3600);
    }
}
