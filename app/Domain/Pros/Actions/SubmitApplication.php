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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Sends a complete application (or its fixes) to the vetting team, once (spec 008, AC4, AC6). */
final readonly class SubmitApplication
{
    public function __construct(private ProStatusMachine $statuses) {}

    public function handle(User $user, Pro $pro): Pro
    {
        Gate::forUser($user)->authorize('view', $pro);

        if ($pro->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $limitKey = 'pro-submissions:'.$user->id;

        if (RateLimiter::tooManyAttempts($limitKey, (int) config('getsorted.pros.submissions_per_hour'))) {
            throw ValidationException::withMessages(['application' => __('Please try again later.')]);
        }

        RateLimiter::hit($limitKey, 3600);

        return DB::transaction(function () use ($user, $pro): Pro {
            $locked = Pro::query()->with(['trades', 'documents.media', 'references'])->lockForUpdate()->findOrFail($pro->id);

            if (! $locked->status->isEditable()) {
                throw new CannotChangeApplication(__('Your application has already been sent for review.'));
            }

            // Everything must still be there (a prune may have emptied an old application), and every flag dealt with.
            $missing = [...$this->missing($locked), ...($locked->status === ProStatus::ChangesRequested ? $this->unresolved($locked) : [])];

            if ($missing !== []) {
                throw ValidationException::withMessages(['application' => __('Still needed: :items.', ['items' => implode(', ', $missing)])]);
            }

            $locked->forceFill(['submitted_at' => now(), 'last_activity_at' => now()]);
            $this->statuses->transition($locked, ProStatus::Submitted, $user);
            $locked->documents()->whereNotNull('flag_message')->update(['flag_message' => null]);

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

        if ($pro->trades->isEmpty()) {
            $missing[] = __('trades');
        }

        if ($pro->base_location === null) {
            $missing[] = __('the address you work from');
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
