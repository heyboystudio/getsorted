<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Domain\Pros\ProStatusMachine;
use App\Models\Pro;
use App\Models\ProReference;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Sends a complete application (or its fixes) to the vetting team, once (spec 008, AC4, AC6). */
final readonly class SubmitApplication
{
    public function __construct(private ProStatusMachine $statuses) {}

    public function handle(User $user, Pro $pro): Pro
    {
        if ($pro->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($user, $pro): Pro {
            $locked = Pro::query()->with(['services', 'serviceAreas', 'documents.media', 'references'])->lockForUpdate()->findOrFail($pro->id);

            if (! $locked->status->isEditable()) {
                throw new CannotChangeApplication(__('Your application has already been sent for review.'));
            }

            $missing = $locked->status === ProStatus::Draft ? $this->missing($locked) : $this->unresolved($locked);

            if ($missing !== []) {
                throw ValidationException::withMessages(['application' => __('Still needed: :items.', ['items' => implode(', ', $missing)])]);
            }

            $locked->forceFill(['submitted_at' => now(), 'last_activity_at' => now()]);
            $this->statuses->transition($locked, ProStatus::Submitted, $user);

            return $locked;
        });
    }

    /** @return list<string> */
    private function missing(Pro $pro): array
    {
        $missing = [];

        if ($pro->business_name === null || $pro->business_type === null) {
            $missing[] = __('business details');
        }

        if ($pro->services->isEmpty()) {
            $missing[] = __('services');
        }

        if ($pro->serviceAreas->isEmpty()) {
            $missing[] = __('suburbs');
        }

        foreach (DocumentType::required() as $type) {
            if ($pro->document($type)?->file() === null) {
                $missing[] = mb_strtolower($type->label());
            }
        }

        if ($pro->references->count() !== 2) {
            $missing[] = __('two references');
        }

        if ($pro->bio === null || trim($pro->bio) === '') {
            $missing[] = __('a short bio');
        }

        if ($pro->vetting_consent_at === null) {
            $missing[] = __('consent to vetting checks');
        }

        return $missing;
    }

    /** @return list<string> */
    private function unresolved(Pro $pro): array
    {
        $flagged = $pro->documents->where('status', DocumentStatus::Flagged)->map(fn ($document): string => mb_strtolower($document->type->label()))->values()->all();
        $references = $pro->references->filter(fn (ProReference $reference): bool => $reference->outcome->needsReplacing())->isNotEmpty() ? [__('a new reference')] : [];

        return [...$flagged, ...$references];
    }
}
