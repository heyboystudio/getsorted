<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProChangeStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\ProChangeRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * An approved pro asks to add a service, add a registration or renew one (spec 021, AC27).
 * Nothing changes on their profile yet: the request waits for an admin, and the approved state
 * they have now stays in force until then. A service that needs a registration the pro does not
 * hold must come with that registration's number and a photo or PDF of it.
 */
final readonly class RequestProChange
{
    public function __construct(private StoreProDocument $documents) {}

    public function handle(User $user, Pro $pro, ?Service $service, ?DocumentType $registration, ?string $number, ?UploadedFile $file): ProChangeRequest
    {
        abort_unless($pro->user_id === $user->id && $pro->status === ProStatus::Approved, 403);

        if (! $service instanceof Service && ! $registration instanceof DocumentType) {
            throw ValidationException::withMessages(['service' => __('Choose a service to add, or a registration to renew.')]);
        }

        $registration = $this->registrationNeeded($pro, $service, $registration);
        $bytes = null;

        if ($registration instanceof DocumentType) {
            $number = $this->validNumber($number);

            if (! $file instanceof UploadedFile) {
                throw ValidationException::withMessages(['upload' => __('Add a photo or PDF of the registration.')]);
            }

            $limitKey = 'pro-uploads:'.$user->id;

            if (RateLimiter::tooManyAttempts($limitKey, (int) config('sortd.pros.uploads_per_hour'))) {
                throw ValidationException::withMessages(['upload' => __('You have uploaded a lot of files. Please try again later.')]);
            }

            RateLimiter::hit($limitKey, 3600);

            if ($file->getSize() === false || $file->getSize() > StoreProDocument::MAX_KILOBYTES * 1024) {
                throw ValidationException::withMessages(['upload' => __('Each file must be 10 MB or smaller.')]);
            }

            $bytes = $this->documents->process($registration, $file);
        }

        return DB::transaction(function () use ($user, $pro, $service, $registration, $number, $bytes): ProChangeRequest {
            $locked = Pro::query()->with('services')->lockForUpdate()->findOrFail($pro->id);
            abort_unless($locked->status === ProStatus::Approved, 403);

            $this->guardAgainstDuplicates($locked, $service, $registration);

            $request = new ProChangeRequest;
            $request->forceFill([
                'pro_id' => $locked->id,
                'service_id' => $service?->id,
                'document_type' => $registration,
                'registration_number' => $registration instanceof DocumentType ? $number : null,
                'status' => ProChangeStatus::Pending,
            ])->save();

            if ($bytes !== null) {
                [$content, $extension, $mime] = $bytes;
                $request->addMediaFromString($content)
                    ->usingFileName('document.'.$extension)
                    ->usingName($registration->label())
                    ->withCustomProperties(['mime' => $mime])
                    ->toMediaCollection(ProChangeRequest::FILE_COLLECTION, 'media');
            }

            $locked->forceFill(['last_activity_at' => now()])->save();
            activity()->performedOn($locked)->causedBy($user)->withProperties([
                'service' => $service?->key,
                'registration' => $registration?->value,
            ])->log('pro change requested');

            return $request->refresh();
        });
    }

    /**
     * The registration this request has to carry, or null when it needs none. A service that needs
     * a registration the pro already holds (valid) goes through without one.
     */
    private function registrationNeeded(Pro $pro, ?Service $service, ?DocumentType $registration): ?DocumentType
    {
        if ($registration instanceof DocumentType && ! $registration->isRegistration()) {
            throw ValidationException::withMessages(['registration' => __('Choose a registration, such as PIRB or electrical.')]);
        }

        $required = null;

        if ($service instanceof Service) {
            if (! $service->is_active || ! $service->trade()->value('is_active')) {
                throw ValidationException::withMessages(['service' => __('That service is not offered right now.')]);
            }

            if ($pro->services()->whereKey($service->id)->exists()) {
                throw ValidationException::withMessages(['service' => __('You already offer this service.')]);
            }

            $required = $service->requires_registration === null ? null : DocumentType::forRegistration($service->requires_registration);

            if ($required instanceof DocumentType && ! $this->holdsValidRegistration($pro, $required)) {
                if ($registration !== $required) {
                    throw ValidationException::withMessages(['registration' => __('This service needs your :type. Add its number and a photo or PDF.', ['type' => $required->label()])]);
                }

                return $required;
            }
        }

        if ($registration instanceof DocumentType) {
            $holds = $pro->documents()->where('type', $registration)->exists();

            if (! $holds && $registration !== $required) {
                throw ValidationException::withMessages(['registration' => __('You can renew a registration you already gave us, or add the one a new service needs.')]);
            }
        }

        return $registration;
    }

    private function holdsValidRegistration(Pro $pro, DocumentType $type): bool
    {
        return $pro->documents()->where('type', $type)->where('status', DocumentStatus::Verified)->whereNotNull('verified_at')
            ->where(fn ($valid) => $valid->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists();
    }

    private function guardAgainstDuplicates(Pro $pro, ?Service $service, ?DocumentType $registration): void
    {
        $pending = $pro->changeRequests()->where('status', ProChangeStatus::Pending);

        if ($service instanceof Service && (clone $pending)->where('service_id', $service->id)->exists()) {
            throw ValidationException::withMessages(['service' => __('You already asked for this service. It is waiting for review.')]);
        }

        if ($registration instanceof DocumentType && (clone $pending)->where('document_type', $registration)->exists()) {
            throw ValidationException::withMessages(['registration' => __('You already sent this registration. It is waiting for review.')]);
        }
    }

    private function validNumber(?string $number): string
    {
        $number = mb_strtoupper(trim((string) $number));

        if ($number === '' || mb_strlen($number) > 40 || preg_match('/^[A-Z0-9\/\-]+$/', $number) !== 1) {
            throw ValidationException::withMessages(['number' => __('Enter the registration number as it appears on your certificate.')]);
        }

        return $number;
    }
}
