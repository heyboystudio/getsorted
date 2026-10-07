<?php

declare(strict_types=1);

namespace App\Livewire\Pros;

use App\Domain\Pros\Actions\RequestProChange;
use App\Domain\Pros\Actions\UpdateProProfile;
use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProChangeStatus;
use App\Livewire\Pros\Jobs\Concerns\EnsuresApprovedPro;
use App\Models\Pro;
use App\Models\Service;
use App\Models\Suburb;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * A pro's shop front (spec 021, AC25–AC29): bio, weekly cap and suburbs save at once; services
 * and registrations go through review; documents show their expiry.
 */
#[Layout('components.layouts.panel', ['panel' => 'pro'])]
#[Title('Profile')]
final class Profile extends Component
{
    use EnsuresApprovedPro;
    use WithFileUploads;

    public string $bio = '';

    public ?string $weeklyCap = null;

    /** @var list<int|string> */
    public array $suburbIds = [];

    public ?string $saved = null;

    /** The "add a service" request (spec 021, AC27). */
    public string $newService = '';

    public string $serviceNumber = '';

    public ?UploadedFile $serviceUpload = null;

    /** The "renew a registration" request. */
    public string $renewType = '';

    public string $renewNumber = '';

    public ?UploadedFile $renewUpload = null;

    public ?string $requested = null;

    public function mount(): void
    {
        if (! $this->ensureApprovedPro()) {
            return;
        }

        $pro = $this->currentPro()->load('serviceAreas');
        $this->bio = (string) $pro->bio;
        $this->weeklyCap = $pro->weekly_job_cap === null ? null : (string) $pro->weekly_job_cap;
        $this->suburbIds = $pro->serviceAreas->pluck('id')->all();
    }

    public function saveBio(UpdateProProfile $update): void
    {
        $this->saved = null;
        $update->bio($this->currentUser(), $this->currentPro(), $this->bio);
        $this->saved = 'bio';
    }

    public function saveCap(UpdateProProfile $update): void
    {
        $this->saved = null;
        $cap = trim((string) $this->weeklyCap);
        $update->weeklyCap($this->currentUser(), $this->currentPro(), $cap === '' ? null : (ctype_digit($cap) ? (int) $cap : 0));
        $this->saved = 'cap';
    }

    public function saveAreas(UpdateProProfile $update): void
    {
        $this->saved = null;
        $update->areas($this->currentUser(), $this->currentPro(), array_map(intval(...), $this->suburbIds));
        $this->saved = 'areas';
    }

    public function requestService(RequestProChange $request): void
    {
        $this->resetErrorBag();
        $this->requested = null;
        $pro = $this->currentPro();
        $service = ctype_digit($this->newService) ? Service::query()->find((int) $this->newService) : null;

        if (! $service instanceof Service) {
            throw ValidationException::withMessages(['newService' => __('Choose a service to add.')]);
        }

        $needed = $this->registrationFor($pro, $service);

        try {
            $request->handle($this->currentUser(), $pro, $service, $needed, $needed === null ? null : $this->serviceNumber, $needed === null ? null : $this->serviceUpload);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->forForm($exception, 'service'));
        }

        $this->reset(['newService', 'serviceNumber', 'serviceUpload']);
        $this->requested = 'service';
    }

    public function requestRenewal(RequestProChange $request): void
    {
        $this->resetErrorBag();
        $this->requested = null;
        $type = DocumentType::tryFrom($this->renewType);

        if (! $type instanceof DocumentType) {
            throw ValidationException::withMessages(['renewType' => __('Choose the registration you are renewing.')]);
        }

        try {
            $request->handle($this->currentUser(), $this->currentPro(), null, $type, $this->renewNumber, $this->renewUpload);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->forForm($exception, 'renew'));
        }

        $this->reset(['renewType', 'renewNumber', 'renewUpload']);
        $this->requested = 'renewal';
    }

    public function render(): View
    {
        $pro = $this->currentPro()->load(['services.trade', 'documents']);
        $photo = $pro->document(DocumentType::ProfilePhoto);

        return view('livewire.pros.profile', [
            'pro' => $pro,
            'photoUrl' => $photo?->file() !== null && $photo->status->value === 'verified' ? $photo->temporaryUrl() : null,
            'services' => $pro->services->sortBy('name')->groupBy(fn ($service): string => $service->trade->name)->sortKeys(),
            'suburbsByRegion' => Suburb::query()->where('is_active', true)->orderBy('region')->orderBy('name')->get()->groupBy('region'),
            'documents' => $pro->documents->sortBy(fn ($document): string => $document->type->value),
            'attention' => $pro->registrationsNeedingAttention()->pluck('id')->all(),
            'maxCap' => UpdateProProfile::MAX_WEEKLY_CAP,
            'offerable' => $this->offerableServices($pro),
            'needsRegistration' => ctype_digit($this->newService) && ($selected = Service::query()->find((int) $this->newService)) instanceof Service ? $this->registrationFor($pro, $selected) : null,
            'heldRegistrations' => $pro->documents->filter(fn ($document): bool => $document->type->isRegistration())->map(fn ($document): DocumentType => $document->type)->values(),
            'changes' => $pro->changeRequests()->with('service')->latest('id')->limit(8)->get(),
            'pendingServiceIds' => $pro->changeRequests()->where('status', ProChangeStatus::Pending)->whereNotNull('service_id')->pluck('service_id')->all(),
        ]);
    }

    /** The registration a service needs that this pro does not currently hold in a valid state. */
    private function registrationFor(Pro $pro, Service $service): ?DocumentType
    {
        if ($service->requires_registration === null) {
            return null;
        }

        $type = DocumentType::forRegistration($service->requires_registration);
        $valid = $pro->documents->contains(fn ($document): bool => $document->type === $type
            && $document->status === DocumentStatus::Verified
            && ! $document->isExpired());

        return $valid ? null : $type;
    }

    /**
     * Active services this pro does not offer and has not already asked for, by trade.
     *
     * @return Collection<(int|string), \Illuminate\Database\Eloquent\Collection<int, Service>>
     */
    private function offerableServices(Pro $pro): Collection
    {
        $taken = $pro->services->pluck('id')
            ->merge($pro->changeRequests()->where('status', ProChangeStatus::Pending)->whereNotNull('service_id')->pluck('service_id'));

        return Service::query()->with('trade')->where('is_active', true)
            ->whereHas('trade', fn ($trade) => $trade->where('is_active', true))
            ->whereNotIn('id', $taken->all())
            ->orderBy('name')->get()
            ->groupBy(fn (Service $service): string => $service->trade->name)->sortKeys();
    }

    /**
     * Shows the action's errors under the right form's fields.
     *
     * @return array<string, string>
     */
    private function forForm(ValidationException $exception, string $form): array
    {
        $map = $form === 'service'
            ? ['service' => 'newService', 'registration' => 'newService', 'number' => 'serviceNumber', 'upload' => 'serviceUpload']
            : ['service' => 'renewType', 'registration' => 'renewType', 'number' => 'renewNumber', 'upload' => 'renewUpload'];

        $errors = [];
        foreach ($exception->errors() as $key => $messages) {
            $errors[$map[$key] ?? $key] = (string) $messages[0];
        }

        return $errors;
    }
}
