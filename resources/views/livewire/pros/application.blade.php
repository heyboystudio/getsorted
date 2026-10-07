@php
    $input = 'mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3 text-base focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/30';
    $changes = $pro->status === \App\Domain\Pros\Enums\ProStatus::ChangesRequested;
@endphp
<main class="flex min-h-dvh items-start justify-center px-5 py-8 pb-32">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('pros.welcome') }}" class="text-sm text-zinc-700 underline underline-offset-4">← {{ __('GetSorted Pro') }}</a>
        <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $changes ? __('Update your application') : __('Tell us about your business') }}</h1>
        <p class="mt-2 text-zinc-600">{{ $changes ? __('Fix what is listed below, then send it back to us.') : __('Fill this in at your own pace. Everything is saved when you tap Save progress, and your uploads save as soon as you add them.') }}</p>

        @if ($pro->status === \App\Domain\Pros\Enums\ProStatus::ChangesRequested)
            <div class="mb-6 rounded-lg bg-amber-50 p-4 text-sm text-amber-900" role="status">
                <p class="font-medium">{{ __('Please fix these items') }}</p>
                <p class="mt-1">{{ $pro->decision_reason }}</p>
            </div>
        @endif

        @if (in_array('business', $sections, true))
        <div class="mt-10">
            <h2 class="text-2xl font-semibold tracking-tight">{{ __('Your business') }}</h2>
            <label for="businessName" class="mt-6 block text-sm font-medium">{{ __('Business name') }}</label>
            <input id="businessName" type="text" wire:model="businessName" maxlength="120" class="{{ $input }}" autocomplete="organization">
            @error('businessName') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror

            <fieldset class="mt-6">
                <legend class="text-sm font-medium">{{ __('How do you trade?') }}</legend>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @foreach ($businessTypes as $type)
                        <label class="flex items-center gap-2 rounded-lg border border-zinc-300 bg-white p-3 has-[:checked]:border-emerald-700">
                            <input type="radio" wire:model="businessType" value="{{ $type->value }}" class="text-emerald-700"> {{ $type->label() }}
                        </label>
                    @endforeach
                </div>
                @error('businessType') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </fieldset>

            <label for="vatNumber" class="mt-6 block text-sm font-medium">{{ __('VAT number (optional)') }}</label>
            <input id="vatNumber" type="text" inputmode="numeric" wire:model="vatNumber" maxlength="12" class="{{ $input }}">
            @error('vatNumber') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror

        </div>
        @endif
        @if (in_array('trades', $sections, true))
        <div class="mt-12">
            <h2 class="text-2xl font-semibold tracking-tight">{{ __('What work do you do?') }}</h2>
            <p class="mt-2 text-zinc-600">{{ __('Choose every trade you want jobs for. You decide for each job whether you can help.') }}</p>
            <div class="mt-6 space-y-2">
                @foreach ($trades as $trade)
                    <label wire:key="trade-{{ $trade->id }}" class="flex items-start gap-3 rounded-lg border border-zinc-200 bg-white p-3 has-[:checked]:border-emerald-700">
                        <input type="checkbox" wire:model="tradeIds" value="{{ $trade->id }}" class="mt-1 size-5 rounded text-emerald-700">
                        <span>{{ $trade->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('tradeIds') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror

        </div>
        @endif
        @if (in_array('base', $sections, true))
        <div class="mt-12">
            <h2 class="text-2xl font-semibold tracking-tight">{{ __('Where do you work from?') }}</h2>
            <p class="mt-2 text-zinc-600">{{ __('We send you jobs near this address. Clients only ever see the area, never your street address.') }}</p>
            @php($hasAddress = $pro->base_location !== null)
            @if ($pickedPlaceId !== null)
                <p class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-950" role="status">{{ $pickedFormatted }}</p>
            @elseif ($hasAddress && ! $changingAddress)
                <div class="mt-4 flex items-start justify-between gap-4 rounded-lg border border-zinc-200 bg-white px-4 py-3">
                    <p>{{ $pro->base_address }}</p>
                    <button type="button" wire:click="$set('changingAddress', true)" class="shrink-0 text-sm text-emerald-800 underline underline-offset-4">{{ __('Change') }}</button>
                </div>
            @endif
            @if (! $hasAddress || $changingAddress || $pickedPlaceId !== null)
                <div class="mt-4">
                    @include('livewire.partials.address-search', ['addressLabel' => $pickedPlaceId !== null ? __('Search for a different address') : __('Your address')])
                </div>
            @endif
            @error('addressQuery') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            @error('address') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            <label for="radiusKm" class="mt-6 block text-sm font-medium">{{ __('How far will you travel for a job?') }}</label>
            <div class="mt-1 flex items-center gap-3">
                <input id="radiusKm" type="range" min="5" max="50" step="1" wire:model.live="radiusKm" class="w-full accent-emerald-700">
                <span class="w-16 text-right font-medium">{{ $radiusKm }} km</span>
            </div>
            <p class="mt-1 text-xs text-zinc-500">{{ __('You will be sent jobs within about :km km of your address.', ['km' => $radiusKm]) }}</p>
            @error('radiusKm') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror

        </div>
        @endif
        @if (in_array('documents', $sections, true))
        <div class="mt-12">
            <h2 class="text-2xl font-semibold tracking-tight">{{ __('Your documents') }}</h2>
            <p class="mt-2 text-zinc-600">{{ __('Up to 10 MB each. Only our vetting team can see them. Your profile photo must be a photo; the other documents can be a photo or a PDF.') }}</p>
            <div class="mt-6 space-y-4">
                @foreach ($documentTypes as $type)
                    @php($document = $pro->document($type))
                    <div wire:key="doc-{{ $type->value }}" class="rounded-xl border border-zinc-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-medium">{{ $type->label() }} <span class="text-sm font-normal text-zinc-500">· {{ $type->acceptsPdf() ? __('photo or PDF') : __('photo only') }}</span></p>
                            @if ($document?->file())
                                <span class="text-sm {{ $document->status === \App\Domain\Pros\Enums\DocumentStatus::Flagged ? 'text-amber-800' : 'text-emerald-800' }}">{{ $document->status->label() }}</span>
                            @endif
                        </div>
                        @if ($document?->flag_message)
                            <p class="mt-1 text-sm text-amber-800">{{ $document->flag_message }}</p>
                        @endif
                        @if ($document?->file())
                            <a href="{{ $document->temporaryUrl() }}" target="_blank" rel="noopener" class="mt-1 inline-block text-sm text-emerald-800 underline">{{ __('View what you uploaded') }}</a>
                        @endif
                        <label class="mt-3 block text-sm">
                            <span class="font-medium text-emerald-800">{{ $document?->file() ? __('Replace file') : __('Choose file') }}</span>
                            <input type="file" wire:model="uploads.{{ $type->value }}" accept="{{ $type->acceptsPdf() ? 'image/*,application/pdf' : 'image/*' }}" class="mt-1 block w-full text-sm">
                        </label>
                        <p wire:loading wire:target="uploads.{{ $type->value }}" class="mt-2 text-sm text-zinc-600">{{ __('Uploading…') }}</p>
                        @error('uploads.'.$type->value) <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>

        </div>
        @endif
        @if (in_array('registrations', $sections, true))
        <div class="mt-12">
            <h2 class="text-2xl font-semibold tracking-tight">{{ __('Your registration') }}</h2>
            <p class="mt-2 text-zinc-600">{{ __('If you hold one of these registrations, add the number and a copy of your certificate. You can skip this and add it later.') }}</p>
            @foreach ($registrationTypes as $type)
                @php($document = $pro->document($type))
                <div wire:key="reg-{{ $type->value }}" class="mt-6 rounded-xl border border-zinc-200 bg-white p-4">
                    <p class="font-medium">{{ $type->label() }}</p>
                    @if ($document?->flag_message)
                        <p class="mt-1 text-sm text-amber-800">{{ $document->flag_message }}</p>
                    @endif
                    <label for="reg-{{ $type->value }}" class="mt-3 block text-sm">{{ __('Registration number') }}</label>
                    <input id="reg-{{ $type->value }}" type="text" wire:model="registrationNumbers.{{ $type->value }}" maxlength="40" class="{{ $input }}">
                    @error('registrationNumbers.'.$type->value) <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    <label class="mt-3 block text-sm">
                        <span class="font-medium text-emerald-800">{{ $document?->file() ? __('Replace certificate') : __('Add certificate') }}</span>
                        <input type="file" wire:model="uploads.{{ $type->value }}" accept="image/*,application/pdf" class="mt-1 block w-full text-sm">
                    </label>
                    @if ($document?->file())
                        <p class="mt-1 text-sm text-emerald-800">{{ __('Certificate received') }}</p>
                    @endif
                    <p wire:loading wire:target="uploads.{{ $type->value }}" class="mt-2 text-sm text-zinc-600">{{ __('Uploading…') }}</p>
                    @error('uploads.'.$type->value) <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            @endforeach

        </div>
        @endif
        @if (in_array('references', $sections, true))
        <div class="mt-12">
            <h2 class="text-2xl font-semibold tracking-tight">{{ __('References') }}</h2>
            <p class="mt-2 text-zinc-600">
                {{ $pro->status === \App\Domain\Pros\Enums\ProStatus::ChangesRequested
                    ? __('We could not use one of your references. Please give someone else who knows your work.')
                    : __('Two people who know your work, such as past customers or suppliers. Our vetting team will phone them.') }}
            </p>
            @foreach ($pro->status === \App\Domain\Pros\Enums\ProStatus::ChangesRequested ? $referencesToReplace->keys() : [0, 1] as $index)
                <fieldset wire:key="ref-{{ $index }}" class="mt-6 rounded-xl border border-zinc-200 bg-white p-4">
                    <legend class="px-1 font-medium">{{ __('Reference :number', ['number' => $index + 1]) }}</legend>
                    <label class="block text-sm">{{ __('Full name') }}<input type="text" wire:model="references.{{ $index }}.name" maxlength="120" class="{{ $input }}"></label>
                    @error('references.'.$index.'.name') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    <label class="mt-3 block text-sm">{{ __('Mobile number') }}<input type="tel" inputmode="tel" wire:model="references.{{ $index }}.phone" class="{{ $input }}"></label>
                    @error('references.'.$index.'.phone') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    <label class="mt-3 block text-sm">{{ __('How do they know your work?') }}<input type="text" wire:model="references.{{ $index }}.relationship" maxlength="120" class="{{ $input }}"></label>
                    @error('references.'.$index.'.relationship') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </fieldset>
            @endforeach
            @error('references') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            <label class="mt-6 flex gap-3 text-sm">
                <input type="checkbox" wire:model="refereesAgreed" class="mt-0.5 size-5 shrink-0 rounded text-emerald-700">
                <span>{{ $pro->status === \App\Domain\Pros\Enums\ProStatus::ChangesRequested
                    ? __('This person agreed that GetSorted may phone them about my work.')
                    : __('Both people agreed that GetSorted may phone them about my work.') }}</span>
            </label>
            @error('refereesAgreed') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror

        </div>
        @endif
        @if (in_array('about', $sections, true))
        <div class="mt-12">
            <h2 class="text-2xl font-semibold tracking-tight">{{ __('About you') }}</h2>
            <label for="bio" class="mt-6 block text-sm font-medium">{{ __('A short bio about your work') }}</label>
            <textarea id="bio" wire:model="bio" rows="5" maxlength="500" class="{{ $input }}" placeholder="{{ __('e.g. 15 years fixing leaks and geysers across Durban.') }}"></textarea>
            <p class="mt-1 text-right text-xs text-zinc-500" x-data x-text="$wire.bio.length + ' / 500'"></p>
            @error('bio') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror

            <label class="mt-6 flex gap-3 text-sm">
                <input type="checkbox" wire:model="consent" class="mt-0.5 size-5 shrink-0 rounded text-emerald-700">
                <span>{{ __('I agree that GetSorted may check my identity document, proof of address, registrations and references to decide on my application.') }} <a href="{{ route('privacy') }}" target="_blank" class="underline underline-offset-4">{{ __('Privacy notice') }}</a></span>
            </label>
            @error('consent') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror

        </div>
        @endif

        @if ($errors->any())
            <div class="mt-8 rounded-lg bg-red-50 p-4 text-sm text-red-800" role="alert">{{ __('A few things need another look. They are marked above.') }}</div>
        @endif
        @error('application') <p class="mt-8 rounded-lg bg-red-50 p-4 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror
        @if ($justSaved && ! $errors->any())
            <p class="mt-8 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-900" role="status">{{ __('Saved. You can come back to this any time.') }}</p>
        @endif

        <div class="fixed inset-x-0 bottom-0 z-10 border-t border-zinc-200 bg-white/95 px-5 py-3 backdrop-blur">
            <div class="mx-auto flex max-w-xl gap-3">
                <button type="button" wire:click="saveProgress" wire:loading.attr="disabled" wire:target="saveProgress,submit" class="flex-1 rounded-lg border border-zinc-300 bg-white px-4 py-3 font-medium hover:border-emerald-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="saveProgress">{{ __('Save progress') }}</span>
                    <span wire:loading wire:target="saveProgress">{{ __('Saving…') }}</span>
                </button>
                <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="saveProgress,submit" class="flex-1 rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">{{ __('Send for review') }}</span>
                    <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
                </button>
            </div>
        </div>
    </section>
</main>
