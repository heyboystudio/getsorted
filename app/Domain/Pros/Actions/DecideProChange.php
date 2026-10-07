<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProChangeStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Domain\Pros\Support\Vetting;
use App\Models\Pro;
use App\Models\ProChangeRequest;
use App\Models\ProDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A vetting or super admin approves or rejects a pro's change request (spec 021, AC27). Approving
 * applies it: the service is added, and a registration replaces the old document as verified with
 * the expiry the admin entered. Rejecting leaves everything as it was and tells the pro why.
 */
final class DecideProChange
{
    public function approve(User $admin, ProChangeRequest $request, ?CarbonImmutable $expiresAt = null): void
    {
        $this->decide($admin, $request, function (Pro $pro, ProChangeRequest $locked) use ($admin, $expiresAt): void {
            if ($locked->document_type instanceof DocumentType) {
                $this->applyRegistration($admin, $pro, $locked, $expiresAt);
            }

            if ($locked->service_id !== null) {
                $this->applyService($pro, $locked);
            }

            $locked->forceFill(['status' => ProChangeStatus::Approved, 'decided_by' => $admin->id, 'decided_at' => now()])->save();
            Vetting::log($admin, $pro, 'pro_change_approved', $this->logProperties($locked));
        });
    }

    public function reject(User $admin, ProChangeRequest $request, string $reason): void
    {
        $reason = Vetting::reason($reason);

        $this->decide($admin, $request, function (Pro $pro, ProChangeRequest $locked) use ($admin, $reason): void {
            $locked->forceFill(['status' => ProChangeStatus::Rejected, 'decision_reason' => $reason, 'decided_by' => $admin->id, 'decided_at' => now()])->save();
            Vetting::log($admin, $pro, 'pro_change_rejected', $this->logProperties($locked));
        });
    }

    /**
     * What the activity log records about a change: which service and registration, nothing personal.
     *
     * @return array<string, string>
     */
    private function logProperties(ProChangeRequest $change): array
    {
        return [
            'service' => $change->service_id === null ? '' : (string) data_get($change, 'service.key', ''),
            'registration' => $change->document_type instanceof DocumentType ? $change->document_type->value : '',
        ];
    }

    /** @param  callable(Pro, ProChangeRequest): void  $apply */
    private function decide(User $admin, ProChangeRequest $request, callable $apply): void
    {
        $pro = Pro::query()->findOrFail($request->pro_id);

        // Same guard as every vetting action: a vetting or super admin, and never their own profile.
        Vetting::locked($admin, $pro, [ProStatus::Approved], function (Pro $locked) use ($request, $apply): void {
            $change = ProChangeRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $change->isPending()) {
                throw new CannotChangeApplication(__('This request was already decided. Reload to see its latest state.'));
            }

            DB::transaction(fn () => $apply($locked, $change));
        });
    }

    private function applyRegistration(User $admin, Pro $pro, ProChangeRequest $change, ?CarbonImmutable $expiresAt): void
    {
        $type = $change->document_type;
        $file = $change->file();

        if (! $type instanceof DocumentType || $file === null || $change->registration_number === null) {
            throw ValidationException::withMessages(['document' => __('This request has no registration file or number to approve.')]);
        }

        if (! $expiresAt instanceof CarbonImmutable || $expiresAt->isPast()) {
            throw ValidationException::withMessages(['expires_at' => __('Enter the date this registration expires.')]);
        }

        $document = $pro->documents()->firstOrNew(['type' => $type]);
        $document->forceFill([
            'status' => DocumentStatus::Verified,
            'number' => $change->registration_number,
            'verified_at' => now(),
            'verified_by' => $admin->id,
            'expires_at' => $expiresAt,
            'flag_message' => null,
        ])->save();

        $file->copy($document, ProDocument::FILE_COLLECTION, 'media');
    }

    private function applyService(Pro $pro, ProChangeRequest $change): void
    {
        $service = $change->service;

        if ($service === null || ! $service->is_active) {
            throw ValidationException::withMessages(['service' => __('That service is not offered any more.')]);
        }

        $pro->services()->syncWithoutDetaching([$service->id]);

        if ($service->requires_registration !== null) {
            $type = DocumentType::forRegistration($service->requires_registration);
            $valid = $pro->documents()->where('type', $type)->where('status', DocumentStatus::Verified)->whereNotNull('verified_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();

            if (! $valid) {
                throw ValidationException::withMessages(['document' => __('This service needs a verified :type first.', ['type' => $type->label()])]);
            }
        }
    }
}
