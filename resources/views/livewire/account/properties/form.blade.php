<main class="flex min-h-dvh items-start justify-center px-5 py-12">
    <section class="w-full max-w-sm">
        <a wire:navigate.hover href="{{ route('properties.index') }}" class="mb-10 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Saved properties') }}</a>
        <h1 class="text-2xl font-semibold tracking-tight">{{ $publicId === null ? __('Add property') : __('Edit property') }}</h1>
        <p class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ __('We only share your street address with the pro you choose.') }}</p>

        <form wire:submit="save" class="mt-6 space-y-4" novalidate>
            <div>
                <label for="label" class="block text-sm font-medium">{{ __('Name') }}</label>
                <input id="label" type="text" wire:model="label" placeholder="{{ __('Home') }}" maxlength="50"
                    @class(['mt-1 block w-full rounded-lg border bg-white px-3 py-3 outline-none focus:ring-2 focus:ring-emerald-600', 'border-red-500' => $errors->has('label'), 'border-zinc-300' => ! $errors->has('label')])
                    aria-describedby="label-error" @error('label') aria-invalid="true" @enderror>
                @error('label') <p id="label-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>

            @include('livewire.partials.address-search')
            @error('addressQuery') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror

            @if ($streetAddress !== '')
                <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">
                    <p class="font-medium">{{ $streetAddress }}@if ($areaLabel), {{ $areaLabel }}@endif</p>
                    <p class="mt-1">{{ $pickedPlaceId !== null ? __('Address confirmed.') : __('Saved address. Search above to change it.') }}</p>
                </div>
            @endif

            <fieldset>
                <legend class="block text-sm font-medium">{{ __('Type of property') }}</legend>
                <div class="mt-2 grid grid-cols-1 gap-2">
                    @foreach ($types as $type)
                        <label class="flex items-center gap-3 rounded-lg border border-zinc-300 bg-white px-3 py-3 has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                            <input type="radio" wire:model="propertyType" value="{{ $type->value }}" class="size-4 text-emerald-700">
                            {{ $type->label() }}
                        </label>
                    @endforeach
                </div>
                @error('propertyType') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </fieldset>

            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="flex w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="save">{{ __('Save property') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
            </button>
        </form>
    </section>
</main>
