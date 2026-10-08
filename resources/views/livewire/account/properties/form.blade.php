<x-workspace.account-shell current="properties.index">
    <flux:breadcrumbs class="mb-3">
        <flux:breadcrumbs.item :href="route('properties.index')" wire:navigate>{{ __('Saved properties') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $publicId === null ? __('Add property') : __('Edit property') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:heading size="xl" level="1">{{ $publicId === null ? __('Add property') : __('Edit property') }}</flux:heading>
    <flux:callout icon="lock-closed" class="mt-4"><flux:callout.text>{{ __('We only share your street address with the pro you choose.') }}</flux:callout.text></flux:callout>

    <form wire:submit="save" class="mt-6 space-y-4" novalidate>
        <flux:card class="space-y-4">
            <div>
                <label for="label" class="block text-sm font-medium">{{ __('Name') }}</label>
                <input id="label" type="text" wire:model="label" placeholder="{{ __('Home') }}" maxlength="50"
                    @class(['mt-1 block w-full rounded-lg border bg-white px-3 py-3 outline-none focus:ring-2 focus:ring-zinc-400', 'border-red-500' => $errors->has('label'), 'border-zinc-300' => ! $errors->has('label')])
                    aria-describedby="label-error" @error('label') aria-invalid="true" @enderror>
                @error('label') <p id="label-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>

            @include('livewire.partials.address-search')
            @error('addressQuery') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror

            @if ($streetAddress !== '')
                <flux:callout variant="success" icon="map-pin" role="status">
                    <flux:callout.heading>{{ $streetAddress }}@if ($areaLabel), {{ $areaLabel }}@endif</flux:callout.heading>
                    <flux:callout.text>{{ $pickedPlaceId !== null ? __('Address confirmed.') : __('Saved address. Search above to change it.') }}</flux:callout.text>
                </flux:callout>
            @endif
        </flux:card>

        <flux:card>
            <fieldset>
                <legend class="block text-sm font-medium">{{ __('Type of property') }}</legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($types as $type)
                        <label class="flex items-center gap-3 rounded-lg border border-zinc-300 bg-white px-3 py-3 has-[:checked]:border-zinc-900 has-[:checked]:bg-zinc-100">
                            <input type="radio" wire:model="propertyType" value="{{ $type->value }}" class="size-4 accent-zinc-900">
                            {{ $type->label() }}
                        </label>
                    @endforeach
                </div>
                @error('propertyType') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </fieldset>
        </flux:card>

        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ __('Save property') }}</span>
            <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
        </flux:button>
    </form>
</x-workspace.account-shell>
