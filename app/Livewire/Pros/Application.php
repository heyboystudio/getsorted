<?php

declare(strict_types=1);

namespace App\Livewire\Pros;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Pros\Actions\SaveApplicationStep;
use App\Domain\Pros\Actions\StartApplication;
use App\Domain\Pros\Actions\StoreProDocument;
use App\Domain\Pros\Actions\SubmitApplication;
use App\Domain\Pros\Data\BusinessDetails;
use App\Domain\Pros\Data\ReferenceData;
use App\Domain\Pros\Enums\BusinessType;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Models\Pro;
use App\Models\ProReference;
use App\Models\Trade;
use App\Models\User;
use App\Contracts\Data\GeocodedAddress;
use App\Livewire\Concerns\SearchesAddresses;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The pro application, one step per screen (spec 008, AC1–AC4, AC6). Thin:
 * every save goes through a domain action that checks ownership and status.
 */
#[Layout('components.layouts.app')]
#[Title('Your application')]
final class Application extends Component
{
    use SearchesAddresses;
    use WithFileUploads;

    #[Locked]
    public string $step = 'business';

    public string $businessName = '';

    public string $businessType = '';

    public string $vatNumber = '';

    /** @var list<int|string> */
    public array $tradeIds = [];

    /** How far the pro travels for a job, in km (spec 020). */
    public int $radiusKm = 15;

    /** The address picked this session, kept until it is saved with the base step. */
    #[Locked]
    public ?string $pickedFormatted = null;

    #[Locked]
    public ?string $pickedArea = null;

    /** @var array<string, TemporaryUploadedFile|null> one slot per document type, saved as soon as a file is chosen */
    public array $uploads = [];

    /** @var array<string, string> registration document type => number */
    public array $registrationNumbers = [];

    /** @var list<array{name: string, phone: string, relationship: string}> */
    public array $references = [
        ['name' => '', 'phone' => '', 'relationship' => ''],
        ['name' => '', 'phone' => '', 'relationship' => ''],
    ];

    public bool $refereesAgreed = false;

    public string $bio = '';

    public bool $consent = false;

    public function mount(StartApplication $startApplication): void
    {
        if (! $this->user()->hasRole(Role::Pro->value)) {
            $this->redirectRoute('pros.join');
            $this->skipRender();

            return;
        }

        try {
            $pro = $startApplication->handle($this->user());
        } catch (CannotChangeApplication) {
            $this->redirectRoute('pros.status');
            $this->skipRender();

            return;
        }

        if (! $pro->status->isEditable()) {
            $this->redirectRoute('pros.status');
            $this->skipRender();

            return;
        }

        $this->fillFrom($pro);
        $this->step = $this->steps($pro)[0];
    }

    public function next(SaveApplicationStep $save): void
    {
        $this->resetErrorBag();
        $pro = $this->pro();
        $before = $this->steps($pro);

        match ($this->step) {
            'business' => $this->saveBusiness($save, $pro),
            'trades' => $save->trades($this->user(), $pro, array_map(intval(...), $this->tradeIds)),
            'base' => $this->saveBase($save, $pro),
            'registrations' => $this->saveRegistrations($save, $pro),
            'references' => $this->saveReferences($save, $pro),
            'about' => $this->saveAbout($save, $pro),
            default => null,
        };

        // Saving can change which steps apply (a registration step appears; a fixed item drops out).
        $index = array_search($this->step, $before, true);
        $target = $before[min(($index === false ? -1 : $index) + 1, count($before) - 1)];
        $this->step = in_array($target, $this->steps($pro->refresh()), true) ? $target : 'review';
    }

    public function back(): void
    {
        $this->resetErrorBag();
        $steps = $this->steps($this->pro());
        $index = array_search($this->step, $steps, true);
        $this->step = $steps[max(($index === false ? 0 : $index) - 1, 0)];
    }

    public function goTo(string $step): void
    {
        if (in_array($step, $this->steps($this->pro()), true)) {
            $this->step = $step;
        }
    }

    /** A chosen file is stored straight away under the document it was chosen for. */
    public function updatedUploads(mixed $file, string $type): void
    {
        $this->resetErrorBag(['upload', 'uploads.'.$type]);
        $documentType = DocumentType::tryFrom($type);
        abort_unless($documentType instanceof DocumentType, 404);

        try {
            if (! $file instanceof TemporaryUploadedFile) {
                throw ValidationException::withMessages(['upload' => __('Choose a file.')]);
            }

            app(StoreProDocument::class)->handle($this->user(), $this->pro(), $documentType, $file);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['uploads.'.$type => $exception->validator->errors()->first('upload')]);
        } finally {
            $this->uploads[$type] = null;
        }
    }

    public function submit(SubmitApplication $submitApplication): void
    {
        try {
            $submitApplication->handle($this->user(), $this->pro());
        } catch (CannotChangeApplication $exception) {
            throw ValidationException::withMessages(['application' => $exception->getMessage()]);
        }

        $this->redirectRoute('pros.status');
    }

    public function render(): View
    {
        $pro = $this->pro()->load(['trades', 'documents.media', 'references']);

        return view('livewire.pros.application', [
            'pro' => $pro,
            'steps' => $this->steps($pro),
            'trades' => Trade::query()->where('is_active', true)->orderBy('sort')->get(),
            'documentTypes' => $pro->status === ProStatus::ChangesRequested
                ? $pro->documents->whereNotNull('flag_message')->pluck('type')->filter(fn (DocumentType $type): bool => ! $type->isRegistration())->values()->all()
                : DocumentType::required(),
            'registrationTypes' => $pro->status === ProStatus::ChangesRequested
                ? $pro->documents->whereNotNull('flag_message')->pluck('type')->filter(fn (DocumentType $type): bool => $type->isRegistration())->values()->all()
                : $pro->offeredRegistrations(),
            'referencesToReplace' => $pro->references->filter(fn (ProReference $reference): bool => $reference->outcome->needsReplacing())->values(),
            'businessTypes' => BusinessType::cases(),
        ]);
    }

    /**
     * The steps this application shows: everything for a draft; only what was
     * flagged once changes are requested (AC6).
     *
     * @return list<string>
     */
    private function steps(Pro $pro): array
    {
        $pro->loadMissing(['trades', 'documents', 'references']);

        if ($pro->status === ProStatus::ChangesRequested) {
            // Flag messages stay until resubmission, so a fixed item can be fixed again (code review).
            $flagged = $pro->documents->whereNotNull('flag_message');

            return array_values(array_filter([
                $flagged->contains(fn ($document): bool => ! $document->type->isRegistration()) ? 'documents' : null,
                $flagged->contains(fn ($document): bool => $document->type->isRegistration()) ? 'registrations' : null,
                $pro->references->contains(fn (ProReference $reference): bool => $reference->outcome->needsReplacing()) ? 'references' : null,
                'review',
            ]));
        }

        return array_values(array_filter([
            'business', 'trades', 'base', 'documents',
            $pro->offeredRegistrations() === [] ? null : 'registrations',
            'references', 'about', 'review',
        ]));
    }

    private function saveBusiness(SaveApplicationStep $save, Pro $pro): void
    {
        $this->validate([
            'businessName' => ['required', 'string', 'max:120'],
            'businessType' => ['required', 'in:'.implode(',', array_column(BusinessType::cases(), 'value'))],
        ], [
            'businessName.required' => __('Enter your business name.'),
            'businessType.*' => __('Choose how your business trades.'),
        ]);

        $this->remap(fn () => $save->business($this->user(), $pro, new BusinessDetails(
            $this->businessName,
            BusinessType::from($this->businessType),
            $this->vatNumber === '' ? null : $this->vatNumber,
        )), ['business_name' => 'businessName', 'vat_number' => 'vatNumber']);
    }

    private function saveRegistrations(SaveApplicationStep $save, Pro $pro): void
    {
        $types = $pro->status === ProStatus::ChangesRequested
            ? $pro->documents->whereNotNull('flag_message')->pluck('type')->filter(fn (DocumentType $type): bool => $type->isRegistration())->all()
            : $pro->offeredRegistrations();

        foreach ($types as $type) {
            $number = trim($this->registrationNumbers[$type->value] ?? '');

            // A registration is optional: without it the pro is simply shown as not verified (spec 020).
            if ($number !== '') {
                $this->remap(fn () => $save->registration($this->user(), $pro, $type, $number), ['number' => 'registrationNumbers.'.$type->value]);
            }
        }
    }

    private function saveReferences(SaveApplicationStep $save, Pro $pro): void
    {
        $map = [];
        foreach ([0, 1] as $index) {
            $map["references.{$index}.phone_e164"] = "references.{$index}.phone";
        }

        if ($pro->status === ProStatus::ChangesRequested) {
            $toReplace = $pro->references->filter(fn (ProReference $reference): bool => $reference->outcome->needsReplacing())->values();

            foreach ($toReplace as $index => $reference) {
                $row = $this->references[$index] ?? ['name' => '', 'phone' => '', 'relationship' => ''];
                $this->remap(fn () => $save->replaceReference($this->user(), $pro, $reference, new ReferenceData($row['name'], $row['phone'], $row['relationship']), $this->refereesAgreed), [
                    'references.1.name' => "references.{$index}.name", 'references.1.phone_e164' => "references.{$index}.phone", 'references.1.relationship' => "references.{$index}.relationship",
                    'agreed' => 'refereesAgreed',
                ]);
            }

            return;
        }

        $this->remap(fn () => $save->references(
            $this->user(),
            $pro,
            array_map(fn (array $row): ReferenceData => new ReferenceData($row['name'], $row['phone'], $row['relationship']), array_slice($this->references, 0, 2)),
            $this->refereesAgreed,
        ), [...$map, 'agreed' => 'refereesAgreed']);
    }

    /** A newly picked address replaces the saved one; otherwise only the radius can change. */
    private function saveBase(SaveApplicationStep $save, Pro $pro): void
    {
        $map = ['radiusKm' => 'radiusKm', 'address' => 'addressQuery'];

        if ($this->pickedPlaceId !== null && $this->pickedLatitude !== null && $this->pickedLongitude !== null) {
            $address = new GeocodedAddress((string) $this->pickedFormatted, $this->pickedArea, $this->pickedLatitude, $this->pickedLongitude, areaNames: $this->pickedArea === null ? [] : [$this->pickedArea]);
            $this->remap(fn () => $save->base($this->user(), $pro, $address, $this->pickedPlaceId, $this->radiusKm), $map);
            $this->forgetPickedAddress();
            $this->pickedFormatted = null;
            $this->pickedArea = null;

            return;
        }

        if ($pro->base_location === null) {
            throw ValidationException::withMessages(['addressQuery' => __('Search for your address and choose it from the list.')]);
        }

        $this->remap(fn () => $save->radius($this->user(), $pro, $this->radiusKm), $map);
    }

    /** Keeps what Places returned until the step is saved. */
    protected function addressPicked(GeocodedAddress $address): void
    {
        $this->pickedFormatted = $address->formattedAddress;
        $this->pickedArea = $address->areaLabel();
        $this->resetErrorBag('addressQuery');
    }

    private function saveAbout(SaveApplicationStep $save, Pro $pro): void
    {
        $save->bio($this->user(), $pro, $this->bio);
        $save->consent($this->user(), $pro, $this->consent);
    }

    /**
     * Runs a domain save and renames its error keys to this form's fields.
     *
     * @param  callable(): mixed  $save
     * @param  array<string, string>  $map
     */
    private function remap(callable $save, array $map): void
    {
        try {
            $save();
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors[$map[$key] ?? $key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }

    private function fillFrom(Pro $pro): void
    {
        $pro->load(['trades', 'documents', 'references']);
        $this->businessName = (string) $pro->business_name;
        $this->businessType = (string) $pro->business_type?->value;
        $this->vatNumber = (string) $pro->vat_number;
        $this->tradeIds = $pro->trades->pluck('id')->all();
        $this->radiusKm = $pro->service_radius_km;
        $this->bio = (string) $pro->bio;
        $this->consent = $pro->vetting_consent_at !== null;

        foreach ($pro->documents->filter(fn ($document): bool => $document->type->isRegistration()) as $document) {
            $this->registrationNumbers[$document->type->value] = (string) $document->number;
        }

        if ($pro->status === ProStatus::Draft && $pro->references->count() === 2) {
            $this->references = $pro->references->map(fn (ProReference $reference): array => [
                'name' => $reference->name, 'phone' => $reference->phone_e164, 'relationship' => $reference->relationship,
            ])->values()->all();
            $this->refereesAgreed = true;
        }
    }

    private function pro(): Pro
    {
        return Pro::query()->where('user_id', $this->user()->id)->firstOrFail();
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
