<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Support;

use App\Contracts\Data\AssistantUsage;
use App\Contracts\Data\ChatReply;
use App\Contracts\Data\ScopingSummaryReply;
use App\Contracts\Exceptions\AssistantUnavailable;
use App\Contracts\ScopingAssistant;
use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Models\AiUsage;
use App\Settings\AiSettings;
use App\Support\LocalTime;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * The one way the domain talks to the assistant: checks the switch, the rate
 * limit and the daily budget, times the call, validates the reply and records
 * usage without customer text (spec 007, AC13–AC15).
 */
final readonly class AssistantCalls
{
    public function __construct(private AiSettings $settings) {}

    public function available(): bool
    {
        return $this->settings->enabled && app()->bound(ScopingAssistant::class);
    }

    /**
     * @template TReply of ScopingSummaryReply|ChatReply
     *
     * @param  Closure(ScopingAssistant): TReply  $request
     * @param  Closure(TReply): bool  $isUsable
     * @return array{AiOutcome, TReply|null} the reply only when the outcome is Ok
     */
    public function call(AiPurpose $purpose, string $rateKey, int $perHour, Closure $request, Closure $isUsable, ?int $serviceJobId = null): array
    {
        if (RateLimiter::tooManyAttempts($rateKey, $perHour)) {
            $this->recordThrottled($purpose, 'rate:'.$rateKey, $serviceJobId);

            return [AiOutcome::Throttled, null];
        }

        if (! $this->reserveBudget()) {
            $this->recordThrottled($purpose, 'budget:'.$this->budgetKey(), $serviceJobId);

            return [AiOutcome::Throttled, null];
        }

        RateLimiter::hit($rateKey, 3600);
        $started = hrtime(true);

        try {
            $reply = $request(app(ScopingAssistant::class));
        } catch (AssistantUnavailable $exception) {
            $this->record($purpose, $exception->timedOut ? AiOutcome::Timeout : AiOutcome::Error, null, $this->elapsedMs($started), $serviceJobId);

            return [$exception->timedOut ? AiOutcome::Timeout : AiOutcome::Error, null];
        } catch (Throwable $exception) {
            // Log the failure class only: messages and stack-frame arguments could echo customer text.
            Log::warning('Scoping assistant call failed.', ['exception' => $exception::class]);
            $this->record($purpose, AiOutcome::Error, null, $this->elapsedMs($started), $serviceJobId);

            return [AiOutcome::Error, null];
        }

        $outcome = $isUsable($reply) ? AiOutcome::Ok : AiOutcome::Invalid;
        $this->record($purpose, $outcome, $reply->usage, $this->elapsedMs($started), $serviceJobId);

        return [$outcome, $outcome === AiOutcome::Ok ? $reply : null];
    }

    /**
     * Atomically reserves one call from today's budget (founder decision 2). The
     * counter starts from today's recorded calls so a cache flush cannot reset it.
     */
    private function reserveBudget(): bool
    {
        $key = $this->budgetKey();

        Cache::add($key, AiUsage::query()
            ->where('created_at', '>=', LocalTime::today()->utc())
            ->where('outcome', '!=', AiOutcome::Throttled)
            ->count(), now()->addDay());

        if ((int) Cache::increment($key) > $this->settings->daily_call_budget) {
            Cache::decrement($key);

            return false;
        }

        return true;
    }

    /** One counter per Durban day, so the budget resets at local midnight. */
    private function budgetKey(): string
    {
        return 'assistant:budget:'.LocalTime::today()->toDateString();
    }

    /** At most one throttled row per limit per hour, so anonymous floods cannot fill the table. */
    private function recordThrottled(AiPurpose $purpose, string $limit, ?int $serviceJobId): void
    {
        if (Cache::add('assistant:throttled-logged:'.hash('sha256', $limit), true, 3600)) {
            $this->record($purpose, AiOutcome::Throttled, null, 0, $serviceJobId);
        }
    }

    private function record(AiPurpose $purpose, AiOutcome $outcome, ?AssistantUsage $usage, int $latencyMs, ?int $serviceJobId): void
    {
        AiUsage::query()->create([
            'purpose' => $purpose,
            'provider' => $usage === null ? 'n/a' : mb_substr($usage->provider, 0, 50),
            'model' => $usage === null ? 'n/a' : mb_substr($usage->model, 0, 100),
            'input_tokens' => max(0, $usage === null ? 0 : $usage->inputTokens),
            'output_tokens' => max(0, $usage === null ? 0 : $usage->outputTokens),
            'latency_ms' => $latencyMs,
            'outcome' => $outcome,
            'service_job_id' => $serviceJobId,
        ]);
    }

    private function elapsedMs(int|float $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
