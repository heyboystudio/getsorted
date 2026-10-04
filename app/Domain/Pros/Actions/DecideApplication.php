<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Enums\ReferenceOutcome;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Domain\Pros\ProStatusMachine;
use App\Domain\Pros\Support\Vetting;
use App\Jobs\SendProStatusMessage;
use App\Models\Pro;
use App\Models\ProReference;
use App\Models\Service;
use App\Models\User;
use App\Settings\VettingSettings;

/** Approve, request changes or reject a submitted application (spec 008, AC9, AC10). */
final readonly class DecideApplication
{
    public function __construct(private ProStatusMachine $statuses, private VettingSettings $settings) {}

    public function approve(User $admin, Pro $pro): void
    {
        Vetting::locked($admin, $pro, [ProStatus::Submitted], function (Pro $locked) use ($admin): void {
            $locked->load(['documents', 'references', 'services']);
            $this->guardApproval($locked);

            $locked->forceFill(['approved_at' => $locked->approved_at ?? now(), 'decided_by' => $admin->id, 'decided_at' => now(), 'decision_reason' => null]);
            $this->statuses->transition($locked, ProStatus::Approved, $admin);
            Vetting::log($admin, $locked, 'pro_approved');
            SendProStatusMessage::dispatch($locked->id, 'pro_approved');
        });
    }

    public function requestChanges(User $admin, Pro $pro, string $reason): void
    {
        $reason = Vetting::reason($reason);

        Vetting::locked($admin, $pro, [ProStatus::Submitted], function (Pro $locked) use ($admin, $reason): void {
            $locked->forceFill(['decided_by' => $admin->id, 'decided_at' => now(), 'decision_reason' => $reason, 'last_activity_at' => now()]);
            $this->statuses->transition($locked, ProStatus::ChangesRequested, $admin, $reason);
            Vetting::log($admin, $locked, 'pro_changes_requested');
            SendProStatusMessage::dispatch($locked->id, 'pro_changes_requested');
        });
    }

    public function reject(User $admin, Pro $pro, string $reason): void
    {
        $reason = Vetting::reason($reason);

        Vetting::locked($admin, $pro, [ProStatus::Submitted], function (Pro $locked) use ($admin, $reason): void {
            $locked->forceFill([
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'decision_reason' => $reason,
                'reapply_after' => now()->addDays($this->settings->reapply_after_days),
            ]);
            $this->statuses->transition($locked, ProStatus::Rejected, $admin, $reason);
            Vetting::log($admin, $locked, 'pro_rejected');
            SendProStatusMessage::dispatch($locked->id, 'pro_rejected');
        });
    }

    /** @throws CannotChangeApplication */
    private function guardApproval(Pro $pro): void
    {
        foreach ([DocumentType::IdDocument, DocumentType::ProofOfAddress] as $type) {
            if ($pro->document($type)?->status !== DocumentStatus::Verified) {
                throw new CannotChangeApplication(__('Verify the :document before approving.', ['document' => mb_strtolower($type->label())]));
            }
        }

        if ($pro->references->count() < 2 || $pro->references->contains(fn (ProReference $reference): bool => $reference->outcome !== ReferenceOutcome::Positive)) {
            throw new CannotChangeApplication(__('Both references must be contacted with a positive outcome before approving.'));
        }

        $workable = $pro->services->contains(function (Service $service) use ($pro): bool {
            if ($service->requires_registration === null) {
                return true;
            }

            $registration = $pro->document(DocumentType::forRegistration($service->requires_registration));

            return $registration?->status === DocumentStatus::Verified && ! $registration->isExpired();
        });

        if (! $workable) {
            throw new CannotChangeApplication(__('Verify a registration for at least one service before approving.'));
        }
    }
}
