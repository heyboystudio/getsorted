<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\ProStatusMachine;
use App\Domain\Pros\Support\Vetting;
use App\Jobs\SendProStatusMessage;
use App\Models\Pro;
use App\Models\User;

/** Suspends or reinstates an approved pro (spec 008, AC11). Suspended pros are never eligible. */
final readonly class ChangeProStanding
{
    public function __construct(private ProStatusMachine $statuses) {}

    public function suspend(User $admin, Pro $pro, string $reason): void
    {
        $reason = Vetting::reason($reason);

        Vetting::locked($admin, $pro, [ProStatus::Approved], function (Pro $locked) use ($admin, $reason): void {
            $locked->forceFill(['suspended_at' => now(), 'decision_reason' => $reason, 'decided_by' => $admin->id, 'decided_at' => now()]);
            $this->statuses->transition($locked, ProStatus::Suspended, $admin, $reason);
            Vetting::log($admin, $locked, 'pro_suspended');
            SendProStatusMessage::dispatch($locked->id, 'pro_suspended');
        });
    }

    public function reinstate(User $admin, Pro $pro): void
    {
        Vetting::locked($admin, $pro, [ProStatus::Suspended], function (Pro $locked) use ($admin): void {
            $locked->forceFill(['suspended_at' => null, 'decision_reason' => null, 'decided_by' => $admin->id, 'decided_at' => now()]);
            $this->statuses->transition($locked, ProStatus::Approved, $admin);
            Vetting::log($admin, $locked, 'pro_reinstated');
            SendProStatusMessage::dispatch($locked->id, 'pro_approved');
        });
    }
}
