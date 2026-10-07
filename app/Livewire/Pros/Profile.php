<?php

declare(strict_types=1);

namespace App\Livewire\Pros;

use App\Contracts\Data\GeocodedAddress;
use App\Domain\Pros\Actions\RequestProChange;
use App\Domain\Pros\Actions\UpdateProProfile;
use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProChangeStatus;
use App\Livewire\Concerns\SearchesAddresses;
use App\Livewire\Pros\Jobs\Concerns\EnsuresApprovedPro;
use App\Models\Pro;
use App\Models\Trade;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * A pro's shop front (spec 021, AC25–AC29): bio, weekly cap and work area save at once; trades and
 * registration badges go through review; documents show their expiry.
 */
#[Layout('components.layouts.panel', ['panel' => 'pro'])]
#[Title('Profile')]
final class Profile extends Component
{
    use EnsuresApprovedPro;
    use SearchesAddresses;
    use WithFileUploads;

    public string $bio = '';

    public ?string $weeklyCap = null;

    public int $radiusKm = 15;

    public bool $changingAddress = false;

    public ?string $pickedFormatted = null;

    public ?string $pickedArea = null;

    public ?string $saved = null;

    /** The "add a trade" request (spec 021, AC27). */
    public string $newTrade = '';

    public string $tradeNumber = '';

    public ?UploadedFile $tradeUpload = null;

    /** The "send or renew a registration" request. */
    public string $renewType = '';

    public string $renewNumber = '';

    public ?UploadedFile $renewUpload = null;

    public ?string $requested = null;

    public function mount(): void
    {
        if (! $this->ensureApprovedPro()) {
            return;
        }

        $pro = $this->currentPro();
        $this->bio = (string) $pro->bio;
        $this->weeklyCap = $pro->weekly_job_cap === null ? null : (string) $pro->weekly_job_cap;
        $this->radiusKm = $pro->service_radius_km;
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

    /** A newly picked address replaces the saved one; otherwise only the radius changes. */
    public function saveWorkArea(UpdateProProfile $update): void
    {
        $this->saved = null;
        $this->resetErrorBag();
        $address = null;

        if ($this->pickedPlaceId !== null && $this->pickedLatitude !== null && $this->pickedLongitude !== null) {
            $address = new GeocodedAddress((string) $this->pickedFormatted, $this->pickedArea, $this->pickedLatitude, $this->pickedLongitude, areaNames: $this->pickedArea === null ? [] : [$this->pickedArea]);
        }

        $update->workArea($this->currentUser(), $this->currentPro(), $address, $this->pickedPlaceId, $this->radiusKm);

        $this->forgetPickedAddress();
        $this->pickedFormatted = null;
        $this->pickedArea = null;
        $this->changingAddress = false;
        $this->addressQuery = '';
        $this->saved = 'area';
    }

    public function requestTrade(RequestProChange $request): void
    {
        $this->resetErrorBag();
        $this->requested = null;
        $pro = $this->currentPro();
        $trade = ctype_digit($this->newTrade) ? Trade::query()->find((int) $this->newTrade) : null;

        if (! $trade instanceof Trade) {
            throw ValidationException::withMessages(['newTrade' => __('Choose a trade to add.')]);
        }

        // A registration comes along only when the trade has one and the pro filled it in.
        $registration = $trade->registration !== null && ($this->tradeNumber !== '' || $this->tradeUpload instanceof UploadedFile)
            ? DocumentType::forRegistration($trade->registration)
            : null;

        try {
            $request->handle($this->currentUser(), $pro, $trade, $registration, $registration === null ? null : $this->tradeNumber, $registration === null ? null : $this->tradeUpload);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->forForm($exception, 'trade'));
        }

        $this->reset(['newTrade', 'tradeNumber', 'tradeUpload']);
        $this->requested = 'trade';
    }

    public function requestRegistration(RequestProChange $request): void
    {
        $this->resetErrorBag();
        $this->requested = null;
        $type = DocumentType::tryFrom($this->renewType);

        if (! $type instanceof DocumentType) {
            throw ValidationException::withMessages(['renewType' => __('Choose the registration you are sending.')]);
        }

        try {
            $request->handle($this->currentUser(), $this->currentPro(), null, $type, $this->renewNumber, $this->renewUpload);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->forForm($exception, 'renew'));
        }

        $this->reset(['renewType', 'renewNumber', 'renewUpload']);
        $this->requested = 'registration';
    }

    public function render(): View
    {
        $pro = $this->currentPro()->load(['trades', 'documents']);
        $photo = $pro->document(DocumentType::ProfilePhoto);
        $selected = ctype_digit($this->newTrade) ? Trade::query()->find((int) $this->newTrade) : null;

        return view('livewire.pros.profile', [
            'pro' => $pro,
            'photoUrl' => $photo?->file() !== null && $photo->status === DocumentStatus::Verified ? $photo->temporaryUrl() : null,
            'documents' => $pro->documents->sortBy(fn ($document): string => $document->type->value),
            'attention' => $pro->registrationsNeedingAttention()->pluck('id')->all(),
            'maxCap' => UpdateProProfile::MAX_WEEKLY_CAP,
            'offerable' => $this->offerableTrades($pro),
            'tradeRegistration' => $selected?->registration === null ? null : DocumentType::forRegistration($selected->registration),
            'registrationChoices' => $this->registrationChoices($pro),
            'changes' => $pro->changeRequests()->with('trade')->latest('id')->limit(8)->get(),
        ]);
    }

    protected function addressPicked(GeocodedAddress $address): void
    {
        $this->pickedFormatted = $address->formattedAddress;
        $this->pickedArea = $address->areaLabel();
        $this->resetErrorBag('addressQuery');
    }

    /**
     * Trades this pro does not offer and has not already asked for.
     *
     * @return Collection<int, Trade>
     */
    private function offerableTrades(Pro $pro): Collection
    {
        $taken = $pro->trades->pluck('id')
            ->merge($pro->changeRequests()->where('status', ProChangeStatus::Pending)->whereNotNull('trade_id')->pluck('trade_id'));

        return Trade::query()->where('is_active', true)->whereNotIn('id', $taken->all())->orderBy('name')->get();
    }

    /**
     * Registrations the pro can send: those they already gave us, and those their trades can verify.
     *
     * @return Collection<int, DocumentType>
     */
    private function registrationChoices(Pro $pro): Collection
    {
        return collect($pro->offeredRegistrations())
            ->merge($pro->documents->filter(fn ($document): bool => $document->type->isRegistration())->map(fn ($document): DocumentType => $document->type))
            ->unique(fn (DocumentType $type): string => $type->value)
            ->values();
    }

    /**
     * Shows the action's errors under the right form's fields.
     *
     * @return array<string, string>
     */
    private function forForm(ValidationException $exception, string $form): array
    {
        $map = $form === 'trade'
            ? ['trade' => 'newTrade', 'registration' => 'newTrade', 'number' => 'tradeNumber', 'upload' => 'tradeUpload']
            : ['trade' => 'renewType', 'registration' => 'renewType', 'number' => 'renewNumber', 'upload' => 'renewUpload'];

        $errors = [];
        foreach ($exception->errors() as $key => $messages) {
            $errors[$map[$key] ?? $key] = (string) $messages[0];
        }

        return $errors;
    }
}
