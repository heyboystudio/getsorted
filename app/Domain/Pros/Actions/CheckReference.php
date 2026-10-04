<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Enums\ReferenceOutcome;
use App\Domain\Pros\Support\Vetting;
use App\Models\Pro;
use App\Models\ProReference;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Records the outcome of a reference call, with a private note (spec 008, AC8). */
final class CheckReference
{
    public function handle(User $admin, ProReference $reference, ReferenceOutcome $outcome, ?string $note): void
    {
        if ($outcome === ReferenceOutcome::Pending) {
            throw ValidationException::withMessages(['outcome' => __('Choose how the call went.')]);
        }

        if ($note !== null && mb_strlen($note) > 1000) {
            throw ValidationException::withMessages(['note' => __('Notes can be up to 1000 characters.')]);
        }

        Vetting::locked($admin, $reference->pro()->firstOrFail(), [ProStatus::Submitted], function (Pro $pro) use ($admin, $reference, $outcome, $note): void {
            $pro->references()->whereKey($reference->id)->lockForUpdate()->firstOrFail()->forceFill([
                'outcome' => $outcome,
                'note' => $note === null || trim($note) === '' ? null : trim($note),
                'checked_by' => $admin->id,
                'checked_at' => now(),
            ])->save();

            Vetting::log($admin, $pro, 'pro_reference_checked', ['outcome' => $outcome->value]);
        });
    }
}
