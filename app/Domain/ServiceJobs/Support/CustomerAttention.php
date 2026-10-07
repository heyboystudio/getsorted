<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Support;

use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Support\Collection;

/**
 * Things on a customer's jobs that wait for them (spec 021, AC5): quotes to compare, a
 * deposit or final payment, unread chat, and unfinished requests. Most urgent first.
 */
final class CustomerAttention
{
    /**
     * @return Collection<int, array{kind: string, title: string, detail: string, url: string, draft: ?string}>
     */
    public static function for(User $customer): Collection
    {
        $jobs = ServiceJob::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ServiceJobStatus::inFlight())
            ->with('trade')
            ->latest('updated_at')
            ->get();

        /** @var array<int, int> $waiting Quotes still open to accept, per job. */
        $waiting = Quote::query()
            ->whereIn('service_job_id', $jobs->modelKeys())
            ->where('status', QuoteStatus::Submitted)
            ->where('valid_until', '>=', LocalTime::today()->toDateString())
            ->selectRaw('service_job_id, count(*) as total')
            ->groupBy('service_job_id')
            ->pluck('total', 'service_job_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $items = collect();

        foreach ($jobs as $job) {
            $name = JobLabel::for($job);
            $quotes = $waiting[$job->id] ?? 0;
            $url = route('jobs.show', $job);

            $item = match ($job->status) {
                ServiceJobStatus::AwaitingFinalPayment => ['kind' => 'pay', 'title' => __('Check the work, then pay'), 'detail' => $name, 'url' => $url, 'draft' => null],
                ServiceJobStatus::AwaitingDeposit => ['kind' => 'deposit', 'title' => __('Pay the deposit to confirm'), 'detail' => $name, 'url' => $url, 'draft' => null],
                ServiceJobStatus::Open => $quotes > 0
                    ? ['kind' => 'quotes', 'title' => trans_choice(':count quote to compare|:count quotes to compare', $quotes, ['count' => $quotes]), 'detail' => $name, 'url' => $url, 'draft' => null]
                    : null,
                ServiceJobStatus::Draft => ['kind' => 'draft', 'title' => __('Finish your request'), 'detail' => $name, 'url' => route('booking.continue', $job), 'draft' => $job->public_id],
                default => null,
            };

            if ($item !== null) {
                $items->push($item);
            }

            $unread = self::unreadMessages($job);

            if ($unread > 0) {
                $items->push(['kind' => 'message', 'title' => trans_choice(':count new message|:count new messages', $unread, ['count' => $unread]), 'detail' => $name, 'url' => $url.'#chats', 'draft' => null]);
            }
        }

        $order = ['pay' => 0, 'deposit' => 1, 'quotes' => 2, 'message' => 3, 'draft' => 4];

        return $items->sortBy(fn (array $item): int => $order[$item['kind']])->values();
    }

    private static function unreadMessages(ServiceJob $job): int
    {
        if ($job->status === ServiceJobStatus::Draft) {
            return 0;
        }

        return (int) JobChat::customerList($job)->sum('unread');
    }
}
