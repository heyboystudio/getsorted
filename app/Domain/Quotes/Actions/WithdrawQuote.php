<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Actions;

use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Quotes\Exceptions\CannotQuote;
use App\Domain\Quotes\Support\QuoteFlow;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Jobs\SendQuoteMessage;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** A pro takes back their sent quote with a reason; the slot frees up (spec 010, AC5). */
final class WithdrawQuote
{
    public function handle(User $user, Quote $quote, string $reason): void
    {
        if (Pro::query()->whereKey($quote->pro_id)->value('user_id') !== $user->id) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);
        Validator::make(['withdraw_reason' => $reason], ['withdraw_reason' => ['required', 'string', 'max:300']], [
            'withdraw_reason.required' => __('Tell the customer briefly why.'),
        ])->validate();
        SubmitQuote::throttle($user);

        DB::transaction(function () use ($quote, $reason): void {
            $job = ServiceJob::query()->lockForUpdate()->findOrFail($quote->service_job_id);
            $current = Quote::query()->lockForUpdate()->findOrFail($quote->id);

            if ($job->status !== ServiceJobStatus::Open || $current->status !== QuoteStatus::Submitted) {
                throw new CannotQuote(__('This quote can no longer be withdrawn.'));
            }

            $current->forceFill(['status' => QuoteStatus::Withdrawn, 'withdrawn_at' => now(), 'withdraw_reason' => $reason])->save();
            $job->invites()->where('pro_id', $current->pro_id)->update(['status' => InviteStatus::Closed->value, 'updated_at' => now()]);
            QuoteFlow::refreshCount($job);
        });

        SendQuoteMessage::dispatch($quote->id, 'quote_withdrawn');
    }
}
