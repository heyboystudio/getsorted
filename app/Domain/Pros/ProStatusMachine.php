<?php

declare(strict_types=1);

namespace App\Domain\Pros;

use App\Domain\Pros\Enums\ProStatus as S;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Models\Pro;
use App\Models\ProEvent;
use App\Models\User;

/**
 * The single place a pro's status changes (spec 008). Callers are Actions that
 * already run in a transaction with the pro locked.
 */
final class ProStatusMachine
{
    /** @var array<string, list<S>> */
    private const array TRANSITIONS = [
        'draft' => [S::Submitted],
        'submitted' => [S::Approved, S::ChangesRequested, S::Rejected],
        'changes_requested' => [S::Submitted],
        'approved' => [S::Suspended, S::Paused],
        'suspended' => [S::Approved],
        'paused' => [S::Approved],
        // After the reapply wait (founder decision 4).
        'rejected' => [S::Draft],
    ];

    public function canTransition(S $from, S $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value], true);
    }

    /** @throws CannotChangeApplication */
    public function transition(Pro $pro, S $to, ?User $actor, ?string $reason = null): ProEvent
    {
        $from = $pro->status;

        if (! $this->canTransition($from, $to)) {
            throw new CannotChangeApplication(__('This application is :status, so it cannot be changed that way. Reload to see its latest state.', ['status' => mb_strtolower($from->label())]));
        }

        $pro->status = $to;
        $pro->save();

        $event = new ProEvent(['from_status' => $from, 'to_status' => $to, 'actor_id' => $actor?->id, 'reason' => $reason]);
        $event->pro()->associate($pro);
        $event->save();

        return $event;
    }
}
