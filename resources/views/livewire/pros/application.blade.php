@php
    $position = array_search($step, $steps, true);
    $input = 'mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3 text-base focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/30';
@endphp
<main class="flex min-h-dvh items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <div class="mb-6 flex items-center justify-between text-sm">
            @if ($position > 0)
                <button type="button" wire:click="back" class="text-zinc-700 underline underline-offset-4">← {{ __('Back') }}</button>
            @else
                <a wire:navigate.hover href="{{ route('pros.welcome') }}" class="text-zinc-700 underline underline-offset-4">← {{ __('Sortd Pro') }}</a>
            @endif
            <span class="text-zinc-500">{{ __('Step :number of :total', ['number' => $position + 1, 'total' => count($steps)]) }}</span>
        </div>
        <div class="mb-6 h-2 rounded-full bg-zinc-200" aria-hidden="true">
            <div class="h-2 rounded-full bg-emerald-700" style="width: {{ (int) round((($position + 1) / count($steps)) * 100) }}%"></div>
        </div>

        @if ($pro->status === \App\Domain\Pros\Enums\ProStatus::ChangesRequested)
            <div class="mb-6 rounded-lg bg-amber-50 p-4 text-sm text-amber-900" role="status">
                <p class="font-medium">{{ __('Please fix these items') }}</p>
                <p class="mt-1">{{ $pro->decision_reason }}</p>
            </div>
        @endif

        @if ($step === 'business')
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Your business') }}</h1>
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

        @elseif ($step === 'trades')
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('What work do you do?') }}</h1>
            <p class="mt-2 text-zinc-600">{{ __('Choose every trade you want jobs for. You decide for each job whether you can help.') }}</p>
            <div class="mt-6 space-y-2">
                @foreach ($trades as $trade)
                    <label wire:key="trade-{{ $trade->id }}" class="flex items-start gap-3 rounded-lg border border-zinc-200 bg-white p-3 has-[:checked]:border-emerald-700">
                        <input type="checkbox" wire:model="tradeIds" value="{{ $trade->id }}" class="mt-1 size-5 rounded text-emerald-700">
                        <span>{{ $trade->name }}@if ($trade->registration) <span class="block text-xs text-zinc-500">{{ __('Optional: :registration, shown as a verified badge', ['registration' => $trade->registration->label()]) }}</span>@endif</span>
                    </label>
                @endforeach
            </div>
            @error('tradeIds') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror

        @elseif ($step === 'base')
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Where do you work from?') }}</h1>
            <p class="mt-2 text-zinc-600">{{ __('We send you jobs near this address. Customers only see the area name, never your street address.') }}</p>
            @if ($pro->base_location !== null && $pickedPlaceId === null)
                <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ __('Saved: :area. Search again to change it.', ['area' => $pro->base_area_label ?? __('your address')]) }}</p>
            @endif
            <div class="mt-4">
                @include('livewire.partials.address-search', ['addressLabel' => __('Your address')])
                @error('addressQuery') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                @error('address') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <label for="radiusKm" class="mt-6 block text-sm font-medium">{{ __('How far will you travel for a job?') }}</label>
            <div class="mt-1 flex items-center gap-3">
                <input id="radiusKm" type="range" min="5" max="50" step="1" wire:model.live="radiusKm" class="w-full accent-emerald-700">
                <span class="w-16 text-right font-medium">{{ $radiusKm }} km</span>
            </div>
            <p class="mt-1 text-xs text-zinc-500">{{ __('Jobs up to :soft km away may also reach you when fewer pros are closer.', ['soft' => $radiusKm + app(\App\Settings\MatchingSettings::class)->soft_edge_km]) }}</p>
            @error('radiusKm') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror

        @elseif ($step === 'documents')
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Your documents') }}</h1>
            <p class="mt-2 text-zinc-600">{{ __('Photos or PDFs up to 10 MB. Only our vetting team can see them.') }}</p>
            <div class="mt-6 space-y-4">
                @foreach ($documentTypes as $type)
                    @php($document = $pro->document($type))
                    <div wire:key="doc-{{ $type->value }}" class="rounded-xl border border-zinc-200 bg-white p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-medium">{{ $type->label() }}</p>
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

        @elseif ($step === 'registrations')
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Registrations') }}</h1>
            <p class="mt-2 text-zinc-600">{{ __('Optional. A verified registration shows as a badge on your quotes. Without it you can still receive jobs, shown to customers as not verified.') }}</p>
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

        @elseif ($step === 'references')
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('References') }}</h1>
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
                    ? __('This person agreed that Sortd may phone them about my work.')
                    : __('Both people agreed that Sortd may phone them about my work.') }}</span>
            </label>
            @error('refereesAgreed') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror

        @elseif ($step === 'about')
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('About you') }}</h1>
            <label for="bio" class="mt-6 block text-sm font-medium">{{ __('A short bio about your work') }}</label>
            <textarea id="bio" wire:model="bio" rows="5" maxlength="500" class="{{ $input }}" placeholder="{{ __('e.g. 15 years fixing leaks and geysers across Durban.') }}"></textarea>
            <p class="mt-1 text-right text-xs text-zinc-500" x-data x-text="$wire.bio.length + ' / 500'"></p>
            @error('bio') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror

            <label class="mt-6 flex gap-3 text-sm">
                <input type="checkbox" wire:model="consent" class="mt-0.5 size-5 shrink-0 rounded text-emerald-700">
                <span>{{ __('I agree that Sortd may check my identity document, proof of address, registrations and references to decide on my application.') }} <a href="{{ route('privacy') }}" target="_blank" class="underline underline-offset-4">{{ __('Privacy notice') }}</a></span>
            </label>
            @error('consent') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror

        @elseif ($step === 'review')
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Check and send') }}</h1>
            <dl class="mt-6 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white text-sm">
                <div class="p-4"><dt class="flex justify-between font-medium">{{ __('Business') }}@if (in_array('business', $steps, true))<button type="button" wire:click="goTo('business')" class="text-sm font-normal text-emerald-800 underline">{{ __('Change') }}</button>@endif</dt><dd class="mt-1 text-zinc-700">{{ $pro->business_name }} · {{ $pro->business_type?->label() }}@if ($pro->vat_number) · {{ __('VAT') }} {{ $pro->vat_number }}@endif</dd></div>
                <div class="p-4"><dt class="flex justify-between font-medium">{{ __('Trades') }}@if (in_array('trades', $steps, true))<button type="button" wire:click="goTo('trades')" class="text-sm font-normal text-emerald-800 underline">{{ __('Change') }}</button>@endif</dt><dd class="mt-1 text-zinc-700">{{ $pro->trades->pluck('name')->implode(', ') }}</dd></div>
                <div class="p-4"><dt class="flex justify-between font-medium">{{ __('Works from') }}@if (in_array('base', $steps, true))<button type="button" wire:click="goTo('base')" class="text-sm font-normal text-emerald-800 underline">{{ __('Change') }}</button>@endif</dt><dd class="mt-1 text-zinc-700">{{ $pro->base_area_label ?? '—' }} · {{ $pro->service_radius_km }} km</dd></div>
                <div class="p-4"><dt class="flex justify-between font-medium">{{ __('Documents') }}@if (in_array('documents', $steps, true))<button type="button" wire:click="goTo('documents')" class="text-sm font-normal text-emerald-800 underline">{{ __('Change') }}</button>@endif</dt>
                    <dd class="mt-1 text-zinc-700">
                        @foreach ([...\App\Domain\Pros\Enums\DocumentType::required(), ...$registrationTypes] as $type)
                            <p>{{ $type->label() }}: {{ $pro->document($type)?->file() ? $pro->document($type)->status->label() : __('Missing') }}</p>
                        @endforeach
                    </dd>
                </div>
                <div class="p-4"><dt class="flex justify-between font-medium">{{ __('References') }}@if (in_array('references', $steps, true))<button type="button" wire:click="goTo('references')" class="text-sm font-normal text-emerald-800 underline">{{ __('Change') }}</button>@endif</dt><dd class="mt-1 text-zinc-700">{{ $pro->references->pluck('name')->implode(', ') }}</dd></div>
                @if ($pro->bio)
                    <div class="p-4"><dt class="flex justify-between font-medium">{{ __('Bio') }}@if (in_array('about', $steps, true))<button type="button" wire:click="goTo('about')" class="text-sm font-normal text-emerald-800 underline">{{ __('Change') }}</button>@endif</dt><dd class="mt-1 whitespace-pre-line text-zinc-700">{{ $pro->bio }}</dd></div>
                @endif
            </dl>
            @error('application') <p class="mt-3 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-3 text-lg font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">{{ __('Submit for review') }}</span>
                <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
            </button>
        @endif

        @if ($step !== 'review')
            <button type="button" wire:click="next" wire:loading.attr="disabled" wire:target="next" class="mt-8 w-full rounded-lg bg-emerald-700 px-4 py-3 text-lg font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="next">{{ __('Save and continue') }}</span>
                <span wire:loading wire:target="next">{{ __('Saving…') }}</span>
            </button>
        @endif
    </section>
</main>
