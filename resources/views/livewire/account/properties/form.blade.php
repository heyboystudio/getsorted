<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-sm">
        <a wire:navigate.hover href="{{ route('properties.index') }}" class="mb-6 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Saved properties') }}</a>
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

            <div>
                <label for="streetAddress" class="block text-sm font-medium">{{ __('Street address') }}</label>
                <input id="streetAddress" type="text" autocomplete="street-address" wire:model="streetAddress" placeholder="{{ __('12 Innes Road') }}" maxlength="200"
                    @class(['mt-1 block w-full rounded-lg border bg-white px-3 py-3 outline-none focus:ring-2 focus:ring-emerald-600', 'border-red-500' => $errors->has('streetAddress'), 'border-zinc-300' => ! $errors->has('streetAddress')])
                    aria-describedby="streetAddress-error" @error('streetAddress') aria-invalid="true" @enderror>
                @error('streetAddress') <p id="streetAddress-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="relative">
                <label for="suburbQuery" class="block text-sm font-medium">{{ __('Suburb') }}</label>
                <input id="suburbQuery" type="text" autocomplete="off" wire:model.live.debounce.250ms="suburbQuery" placeholder="{{ __('Start typing, e.g. Morningside') }}"
                    role="combobox" aria-controls="suburb-options" aria-expanded="{{ $this->suggestions->isNotEmpty() ? 'true' : 'false' }}"
                    @class(['mt-1 block w-full rounded-lg border bg-white px-3 py-3 outline-none focus:ring-2 focus:ring-emerald-600', 'border-red-500' => $errors->has('suburb'), 'border-zinc-300' => ! $errors->has('suburb')])
                    aria-describedby="suburb-error" @error('suburb') aria-invalid="true" @enderror>
                @if ($this->suggestions->isNotEmpty())
                    <ul id="suburb-options" role="listbox" class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg">
                        @foreach ($this->suggestions as $option)
                            <li role="option" aria-selected="false">
                                <button type="button" wire:click="selectSuburb('{{ $option->slug }}')" class="flex w-full items-center justify-between px-3 py-3 text-left hover:bg-zinc-50">
                                    <span>{{ $option->name }}</span>
                                    @unless ($option->is_active)
                                        <span class="text-xs text-amber-800">{{ __('Coming soon') }}</span>
                                    @endunless
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @error('suburb') <p id="suburb-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                @php($open = $selected && ($selected->is_active || ! config('sortd.coverage.require_pros')))
                @if ($open)
                    <p class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">{{ __('Good news, we cover :suburb.', ['suburb' => $selected->name]) }}</p>
                @endif
                @if ($selected && ! $open)
                    <p class="mt-2 text-sm text-amber-800">{{ __("Sortd isn't in :suburb yet — we'll let you know when we are.", ['suburb' => $selected->name]) }}</p>
                @endif
            </div>

            <div>
                <label for="postalCode" class="block text-sm font-medium">{{ __('Postal code') }} <span class="font-normal text-zinc-500">({{ __('optional') }})</span></label>
                <input id="postalCode" type="text" inputmode="numeric" autocomplete="postal-code" maxlength="4" wire:model="postalCode"
                    @class(['mt-1 block w-32 rounded-lg border bg-white px-3 py-3 outline-none focus:ring-2 focus:ring-emerald-600', 'border-red-500' => $errors->has('postalCode'), 'border-zinc-300' => ! $errors->has('postalCode')])
                    aria-describedby="postalCode-error" @error('postalCode') aria-invalid="true" @enderror>
                @error('postalCode') <p id="postalCode-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>

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
