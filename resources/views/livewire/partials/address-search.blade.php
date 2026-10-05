{{-- Spec 015: address search with Google suggestions; hidden once manual entry is used. --}}
@unless ($addressManual)
    <div class="relative">
        <label for="addressQuery" class="block text-sm font-medium">{{ __('Find your address') }}</label>
        <input id="addressQuery" type="text" autocomplete="off" wire:model.live.debounce.300ms="addressQuery"
            placeholder="{{ __('Start typing, e.g. 12 Innes Road') }}" maxlength="200"
            role="combobox" aria-autocomplete="list" aria-controls="address-options" aria-expanded="{{ $addressSuggestions !== [] ? 'true' : 'false' }}"
            class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3 outline-none focus:ring-2 focus:ring-emerald-600">
        <p wire:loading wire:target="addressQuery,pickAddress" class="mt-2 text-sm text-zinc-500">{{ __('Looking up addresses…') }}</p>
        @if ($addressSuggestions !== [])
            <ul id="address-options" role="listbox" class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg">
                @foreach ($addressSuggestions as $option)
                    <li role="option" aria-selected="false" wire:key="address-{{ $loop->index }}">
                        <button type="button" wire:click="pickAddress(@js($option['id']))" class="block w-full px-3 py-3 text-left hover:bg-zinc-50">{{ $option['text'] }}</button>
                    </li>
                @endforeach
                <li class="px-3 py-2 text-right text-xs text-zinc-500">{{ __('powered by Google') }}</li>
            </ul>
        @elseif (mb_strlen(trim($addressQuery)) >= \App\Domain\Properties\Support\AddressLookup::MIN_QUERY_LENGTH && $pickedPlaceId === null)
            <p class="mt-2 text-sm text-zinc-600" wire:loading.remove wire:target="addressQuery">{{ __('No matches. You can enter your address manually.') }}</p>
        @endif
        <button type="button" wire:click="enterAddressManually" class="mt-2 text-sm text-zinc-600 underline underline-offset-4">{{ __('Enter address manually') }}</button>
    </div>
@endunless
