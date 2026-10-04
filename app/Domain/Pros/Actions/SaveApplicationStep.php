<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Accounts\Support\PhoneNumbers;
use App\Domain\Pros\Data\BusinessDetails;
use App\Domain\Pros\Data\ReferenceData;
use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\ProReference;
use App\Models\Service;
use App\Models\Suburb;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Saves one step of a pro's application (spec 008, AC1, AC3, AC6, AC13). Most
 * steps change only while it is a draft; after changes are requested, only
 * flagged documents and references the vetting team could not use reopen.
 */
final class SaveApplicationStep
{
    public function business(User $user, Pro $pro, BusinessDetails $details): void
    {
        $data = ['business_name' => trim($details->businessName), 'vat_number' => $details->vatNumber === null ? null : str_replace(' ', '', $details->vatNumber)];

        Validator::make($data, [
            'business_name' => ['required', 'string', 'max:120'],
            'vat_number' => ['nullable', 'regex:/^4\d{9}$/'],
        ], ['vat_number.regex' => __('A South African VAT number has 10 digits and starts with 4.')])->validate();

        $this->whileDraft($user, $pro, fn (Pro $locked) => $locked->fill([...$data, 'business_type' => $details->businessType])->save());
    }

    /** @param  list<int>  $serviceIds */
    public function services(User $user, Pro $pro, array $serviceIds): void
    {
        $serviceIds = array_values(array_unique(array_map(intval(...), $serviceIds)));
        $valid = Service::query()->whereKey($serviceIds)->where('is_active', true)
            ->whereHas('trade', fn ($trade) => $trade->where('is_active', true))->count();

        if ($serviceIds === [] || $valid !== count($serviceIds)) {
            throw ValidationException::withMessages(['serviceIds' => __('Choose at least one service we offer.')]);
        }

        $this->whileDraft($user, $pro, fn (Pro $locked) => $locked->services()->sync($serviceIds));
    }

    /** @param  list<int>  $suburbIds */
    public function areas(User $user, Pro $pro, array $suburbIds): void
    {
        $suburbIds = array_values(array_unique(array_map(intval(...), $suburbIds)));

        if ($suburbIds === [] || Suburb::query()->whereKey($suburbIds)->where('is_active', true)->count() !== count($suburbIds)) {
            throw ValidationException::withMessages(['suburbIds' => __('Choose at least one suburb in our launch area.')]);
        }

        $this->whileDraft($user, $pro, fn (Pro $locked) => $locked->serviceAreas()->sync($suburbIds));
    }

    /** @param  list<ReferenceData>  $references */
    public function references(User $user, Pro $pro, array $references, bool $refereesAgreed): void
    {
        $rows = $this->validReferences($user, $references, $refereesAgreed);

        $this->whileDraft($user, $pro, function (Pro $locked) use ($rows): void {
            $locked->references()->delete();

            foreach ($rows as $row) {
                $locked->references()->create($row);
            }
        });
    }

    /** After changes are requested: swap a reference the vetting team could not use (AC6). */
    public function replaceReference(User $user, Pro $pro, ProReference $reference, ReferenceData $replacement): void
    {
        Gate::forUser($user)->authorize('update', $pro);
        $others = $pro->references()->whereKeyNot($reference->id)->get()
            ->map(fn (ProReference $other): ReferenceData => new ReferenceData($other->name, $other->phone_e164, $other->relationship))->all();
        $row = $this->validReferences($user, [...$others, $replacement], true)[count($others)];

        $this->locked($user, $pro, function (Pro $locked) use ($reference, $row): void {
            $current = $locked->references()->whereKey($reference->id)->firstOrFail();

            if ($locked->status === ProStatus::ChangesRequested && ! $current->outcome->needsReplacing()) {
                throw new AuthorizationException;
            }

            $current->fill($row)->forceFill(['outcome' => 'pending', 'note' => null, 'checked_by' => null, 'checked_at' => null])->save();
        });
    }

    public function bio(User $user, Pro $pro, string $bio): void
    {
        $bio = trim($bio);
        Validator::make(['bio' => $bio], ['bio' => ['required', 'string', 'max:500']])->validate();

        $this->whileDraft($user, $pro, fn (Pro $locked) => $locked->fill(['bio' => $bio])->save());
    }

    public function consent(User $user, Pro $pro, bool $consent): void
    {
        Validator::make(['consent' => $consent], ['consent' => ['accepted']], [
            'consent.accepted' => __('Please agree to the vetting checks to continue.'),
        ])->validate();

        $this->whileDraft($user, $pro, fn (Pro $locked) => $locked->forceFill(['vetting_consent_at' => $locked->vetting_consent_at ?? now()])->save());
    }

    /** A registration number for a chosen service that needs one, stored encrypted (AC3, AC13). */
    public function registration(User $user, Pro $pro, DocumentType $type, string $number): void
    {
        $number = mb_strtoupper(trim($number));
        Validator::make(['number' => $number], ['number' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9\/\-]+$/']], [
            'number.*' => __('Enter the registration number as it appears on your certificate.'),
        ])->validate();

        $this->locked($user, $pro, function (Pro $locked) use ($type, $number): void {
            abort_unless($type->isRegistration() && in_array($type, $locked->requiredRegistrations(), true), 404);
            $document = $locked->documents()->firstOrNew(['type' => $type]);

            if ($locked->status === ProStatus::ChangesRequested && $document->status !== DocumentStatus::Flagged) {
                throw new AuthorizationException;
            }

            $document->forceFill(['number' => $number])->save();
        });
    }

    /**
     * @param  list<ReferenceData>  $references
     * @return list<array{name: string, phone_e164: string, relationship: string}>
     */
    private function validReferences(User $user, array $references, bool $refereesAgreed): array
    {
        $rows = array_map(fn (ReferenceData $reference): array => [
            'name' => trim($reference->name),
            'phone_e164' => (string) PhoneNumbers::normaliseSaMobile($reference->phone),
            'relationship' => trim($reference->relationship),
        ], $references);

        Validator::make(['references' => $rows, 'agreed' => $refereesAgreed], [
            'references' => ['array', 'size:2'],
            'references.*.name' => ['required', 'string', 'max:120'],
            'references.*.phone_e164' => ['required', 'regex:/^\+27\d{9}$/', 'distinct', 'not_in:'.$user->phone_e164],
            'references.*.relationship' => ['required', 'string', 'max:120'],
            'agreed' => ['accepted'],
        ], [
            'references.size' => __('Add two references.'),
            'references.*.phone_e164.required' => __('Enter a South African mobile number.'),
            'references.*.phone_e164.regex' => __('Enter a South African mobile number.'),
            'references.*.phone_e164.distinct' => __('Each reference needs a different number.'),
            'references.*.phone_e164.not_in' => __('A reference must be someone other than you.'),
            'agreed.accepted' => __('Please confirm your references agreed to be contacted.'),
        ])->validate();

        return $rows;
    }

    /** @param  Closure(Pro): mixed  $change */
    private function whileDraft(User $user, Pro $pro, Closure $change): void
    {
        $this->locked($user, $pro, function (Pro $locked) use ($change): void {
            if ($locked->status !== ProStatus::Draft) {
                throw new AuthorizationException;
            }

            $change($locked);
        });
    }

    /** @param  Closure(Pro): mixed  $change */
    private function locked(User $user, Pro $pro, Closure $change): void
    {
        Gate::forUser($user)->authorize('update', $pro);

        DB::transaction(function () use ($user, $pro, $change): void {
            $locked = Pro::query()->lockForUpdate()->findOrFail($pro->id);
            Gate::forUser($user)->authorize('update', $locked);

            $change($locked);
            $locked->forceFill(['last_activity_at' => now()])->save();
        });
    }
}
