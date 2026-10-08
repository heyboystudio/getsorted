<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs;

use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus as S;
use App\Domain\ServiceJobs\Exceptions\TransitionNotAllowed;
use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;

/**
 * The single place job status changes (docs/product/job-lifecycle.md).
 * Callers are Actions that already run in a transaction with the job locked.
 */
final class ServiceJobStateMachine
{
    /**
     * Every allowed transition, from the lifecycle diagram.
     *
     * @var array<string, list<S>>
     */
    private const array TRANSITIONS = [
        'draft' => [S::Open, S::Cancelled],
        'open' => [S::AwaitingDeposit, S::Scheduled, S::Expired, S::Cancelled],
        'awaiting_deposit' => [S::Scheduled, S::Open, S::Cancelled],
        'scheduled' => [S::InProgress, S::Completed, S::Cancelled, S::Disputed],
        'in_progress' => [S::AwaitingFinalPayment, S::Completed, S::Cancelled, S::Disputed],
        'awaiting_final_payment' => [S::Completed, S::Disputed],
        'completed' => [S::Disputed, S::Closed],
        'disputed' => [S::InProgress, S::Completed, S::Cancelled],
        'closed' => [],
        'cancelled' => [],
        'expired' => [],
    ];

    /** @return array<string, list<S>> */
    public static function transitions(): array
    {
        return self::TRANSITIONS;
    }

    public function canTransition(S $from, S $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value], true);
    }

    /**
     * Moves the job and records the event. Does not save other job changes.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws TransitionNotAllowed
     */
    public function transition(ServiceJob $job, S $to, string $eventType, ActorType $actorType, ?int $actorId, array $payload = []): ServiceJobEvent
    {
        $from = $job->status;

        if (! $this->canTransition($from, $to)) {
            throw TransitionNotAllowed::between($from, $to);
        }

        $job->status = $to;
        $job->save();

        $event = new ServiceJobEvent([
            'from_status' => $from,
            'to_status' => $to,
            'event_type' => $eventType,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'payload' => $payload,
        ]);
        $event->serviceJob()->associate($job);
        $event->save();

        return $event;
    }
}
