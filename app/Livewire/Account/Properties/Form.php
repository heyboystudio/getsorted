<?php

declare(strict_types=1);

namespace App\Livewire\Account\Properties;

use App\Domain\Properties\Actions\SaveProperty;
use App\Domain\Properties\Enums\PropertyType;
use App\Domain\Properties\Exceptions\PropertyLimitReached;
use App\Domain\Properties\Queries\SuburbSearchQuery;
use App\Domain\ServiceJobs\Support\BookingReturn;
use App\Models\Property;
use App\Models\Suburb;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Add or edit a saved property (spec 004). */
#[Layout('components.layouts.app')]
final class Form extends Component
{
    #[Locked]
    public ?string $publicId = null;

    public string $label = '';

    public string $streetAddress = '';

    public string $suburbQuery = '';

    #[Locked]
    public ?string $suburb = null;

    public string $postalCode = '';

    public string $propertyType = '';

    #[Locked]
    public ?string $returnTo = null;

    public function mount(?Property $property = null): void
    {
        $this->returnTo = BookingReturn::sanitise(request()->query('return'));

        if (! $property instanceof Property || ! $property->exists) {
            return;
        }

        // 404 rather than 403 so other customers' properties are not revealed (AC9).
        abort_unless($this->user()->can('update', $property), 404);

        $this->publicId = $property->public_id;
        $this->label = $property->label;
        $this->streetAddress = $property->street_address;
        $this->suburb = $property->suburb->slug;
        $this->suburbQuery = $property->suburb->name;
        $this->postalCode = (string) $property->postal_code;
        $this->propertyType = $property->property_type->value;
    }

    /** @return Collection<int, Suburb> */
    #[Computed]
    public function suggestions(): Collection
    {
        $selected = $this->selectedSuburb();

        if ($selected instanceof Suburb && $selected->name === $this->suburbQuery) {
            return new Collection;
        }

        return app(SuburbSearchQuery::class)->handle($this->suburbQuery);
    }

    public function selectSuburb(string $slug): void
    {
        $suburb = Suburb::query()->where('slug', $slug)->first();

        if ($suburb instanceof Suburb) {
            $this->suburb = $suburb->slug;
            $this->suburbQuery = $suburb->name;
        }
    }

    public function updatedSuburbQuery(): void
    {
        // Typing again clears the chosen suburb until one is picked from the list.
        if ($this->selectedSuburb()?->name !== $this->suburbQuery) {
            $this->suburb = null;
        }
    }

    public function save(SaveProperty $saveProperty): void
    {
        $this->label = trim($this->label);
        $this->streetAddress = trim($this->streetAddress);
        $this->postalCode = trim($this->postalCode);

        $this->validate([
            'label' => ['required', 'string', 'max:50'],
            'streetAddress' => ['required', 'string', 'max:200'],
            'suburb' => ['required', Rule::exists('suburbs', 'slug')],
            'postalCode' => ['nullable', 'digits:4'],
            'propertyType' => ['required', Rule::enum(PropertyType::class)],
        ], [
            'label.required' => __('Give this property a name, like "Home".'),
            'streetAddress.required' => __('Enter the street address.'),
            'suburb.required' => __('Choose your suburb from the list.'),
            'postalCode.digits' => __('Postal codes have 4 digits.'),
            'propertyType.required' => __('Choose the type of property.'),
        ]);

        $property = null;

        if ($this->publicId !== null) {
            $property = $this->user()->properties()->where('public_id', $this->publicId)->firstOrFail();
        }

        try {
            $saveProperty->handle(
                $this->user(),
                $property,
                $this->label,
                $this->streetAddress,
                Suburb::query()->where('slug', $this->suburb)->firstOrFail(),
                $this->postalCode === '' ? null : $this->postalCode,
                PropertyType::from($this->propertyType),
            );
        } catch (PropertyLimitReached) {
            throw ValidationException::withMessages(['label' => __('You can save up to :count properties. Delete one to add another.', ['count' => config('sortd.properties.max_per_customer')])]);
        }

        $this->returnTo === null ? $this->redirectRoute('properties.index') : $this->redirect($this->returnTo);
    }

    public function render(): View
    {
        return view('livewire.account.properties.form', [
            'types' => PropertyType::cases(),
            'selected' => $this->selectedSuburb(),
        ])->title($this->publicId === null ? __('Add property') : __('Edit property'));
    }

    private function selectedSuburb(): ?Suburb
    {
        return $this->suburb === null ? null : Suburb::query()->where('slug', $this->suburb)->first();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
