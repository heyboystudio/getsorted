<?php

declare(strict_types=1);

namespace App\Livewire\Account\Properties;

use App\Contracts\Data\GeocodedAddress;
use App\Domain\Properties\Actions\SaveProperty;
use App\Domain\Properties\Enums\PropertyType;
use App\Domain\Properties\Exceptions\PropertyLimitReached;
use App\Domain\ServiceJobs\Support\BookingReturn;
use App\Livewire\Concerns\SearchesAddresses;
use App\Models\Property;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Add or edit a saved property (spec 004), with Google address search (spec 015, 020). */
#[Layout('components.layouts.panel', ['panel' => 'customer'])]
final class Form extends Component
{
    use SearchesAddresses;

    #[Locked]
    public ?string $publicId = null;

    public string $label = '';

    /** The street line of the address picked this session (or the saved one when editing). */
    #[Locked]
    public string $streetAddress = '';

    #[Locked]
    public ?string $areaLabel = null;

    #[Locked]
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
        $this->areaLabel = $property->area_label;
        $this->postalCode = (string) $property->postal_code;
        $this->propertyType = $property->property_type->value;
    }

    protected function addressPicked(GeocodedAddress $address): void
    {
        $this->streetAddress = mb_substr($address->streetLine ?? $address->formattedAddress, 0, 200);
        $this->areaLabel = $address->areaLabel();
        $this->postalCode = $address->postalCode ?? '';
        $this->resetValidation(['streetAddress', 'postalCode']);
    }

    public function save(SaveProperty $saveProperty): void
    {
        $this->label = trim($this->label);

        $this->validate([
            'label' => ['required', 'string', 'max:50'],
            'propertyType' => ['required', Rule::enum(PropertyType::class)],
        ], [
            'label.required' => __('Give this property a name, like "Home".'),
            'propertyType.required' => __('Choose the type of property.'),
        ]);

        $property = null;

        if ($this->publicId !== null) {
            $property = $this->user()->properties()->where('public_id', $this->publicId)->firstOrFail();
        }

        if ($property === null && $this->pickedPlaceId === null) {
            throw ValidationException::withMessages(['addressQuery' => __('Search for your address and choose it from the list.')]);
        }

        $picked = $this->pickedPlaceId !== null && $this->pickedLatitude !== null && $this->pickedLongitude !== null;

        try {
            $saveProperty->handle(
                $this->user(),
                $property,
                $this->label,
                $picked ? $this->streetAddress : null,
                $picked ? $this->areaLabel : null,
                $this->postalCode === '' ? null : $this->postalCode,
                PropertyType::from($this->propertyType),
                $picked ? Point::makeGeodetic($this->pickedLatitude, $this->pickedLongitude) : null,
                $this->pickedPlaceId,
            );
        } catch (PropertyLimitReached) {
            throw ValidationException::withMessages(['label' => __('You can save up to :count properties. Delete one to add another.', ['count' => config('getsorted.properties.max_per_customer')])]);
        }

        $this->returnTo === null ? $this->redirectRoute('properties.index') : $this->redirect($this->returnTo);
    }

    public function render(): View
    {
        return view('livewire.account.properties.form', [
            'types' => PropertyType::cases(),
        ])->title($this->publicId === null ? __('Add property') : __('Edit property'));
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
