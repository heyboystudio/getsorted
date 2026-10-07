@php($input = 'mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3 text-base focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/30')
<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <div class="flex items-center gap-4">
            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt="" class="size-16 shrink-0 rounded-full object-cover">
            @else
                <span aria-hidden="true" class="flex size-16 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xl font-semibold text-emerald-900">{{ mb_strtoupper(mb_substr((string) $pro->business_name, 0, 1)) }}</span>
            @endif
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-semibold tracking-tight">{{ $pro->business_name }}</h1>
                <a wire:navigate.hover href="{{ route('pros.profile.preview') }}" class="text-sm text-emerald-800 underline underline-offset-4">{{ __('Preview as customers see it') }}</a>
            </div>
        </div>

        @if ($attention !== [])
            <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="alert">
                <p class="font-medium">{{ __('A registration needs attention') }}</p>
                <p class="mt-1">{{ __('An expired registration loses its "Registration verified" badge. Check the documents below.') }}</p>
            </div>
        @endif

        <form wire:submit="saveBio" class="mt-8" novalidate>
            <label for="bio" class="font-semibold">{{ __('About your business') }}</label>
            <p class="text-sm text-zinc-600">{{ __('Customers read this when they compare quotes. Do not add phone numbers or email addresses.') }}</p>
            <textarea id="bio" wire:model="bio" rows="4" maxlength="500" class="{{ $input }}"></textarea>
            @error('bio') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="mt-3 rounded-lg bg-emerald-700 px-4 py-2.5 font-medium text-white hover:bg-emerald-800">{{ __('Save') }}</button>
            @if ($saved === 'bio') <span class="ml-3 text-sm text-emerald-800" role="status">{{ __('Saved.') }}</span> @endif
        </form>

        <form wire:submit="saveCap" class="mt-8" novalidate>
            <label for="cap" class="font-semibold">{{ __('Jobs per week') }}</label>
            <p class="text-sm text-zinc-600">{{ __('The most new jobs you want to be matched with in a week. Leave empty for no limit.') }}</p>
            <input id="cap" type="number" inputmode="numeric" min="1" max="{{ $maxCap }}" wire:model="weeklyCap" class="{{ $input }} max-w-40">
            @error('cap') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="mt-3 rounded-lg bg-emerald-700 px-4 py-2.5 font-medium text-white hover:bg-emerald-800">{{ __('Save') }}</button>
            @if ($saved === 'cap') <span class="ml-3 text-sm text-emerald-800" role="status">{{ __('Saved.') }}</span> @endif
        </form>

        <form wire:submit="saveWorkArea" class="mt-8" novalidate>
            <p class="font-semibold">{{ __('Where you work from') }}</p>
            <p class="text-sm text-zinc-600">{{ __('You get jobs near this address. Customers only ever see the area, never your street address. Changes apply to new invites straight away.') }}</p>
            @if ($pickedPlaceId !== null)
                <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-950" role="status">{{ $pickedFormatted }}</p>
            @elseif ($pro->base_location !== null && ! $changingAddress)
                <div class="mt-3 flex items-start justify-between gap-4 rounded-lg border border-zinc-200 bg-white px-4 py-3">
                    <p>{{ $pro->base_area_label ?? __('Saved address') }}</p>
                    <button type="button" wire:click="$set('changingAddress', true)" class="shrink-0 text-sm text-emerald-800 underline underline-offset-4">{{ __('Change') }}</button>
                </div>
            @endif
            @if ($pro->base_location === null || $changingAddress || $pickedPlaceId !== null)
                <div class="mt-3">
                    @include('livewire.partials.address-search', ['addressLabel' => $pickedPlaceId !== null ? __('Search for a different address') : __('Your address')])
                </div>
            @endif
            @error('address') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <label for="radiusKm" class="mt-5 block text-sm font-medium">{{ __('How far will you travel for a job?') }}</label>
            <div class="mt-1 flex items-center gap-3">
                <input id="radiusKm" type="range" min="5" max="50" step="1" wire:model.live="radiusKm" class="w-full accent-emerald-700">
                <span class="w-16 text-right font-medium">{{ $radiusKm }} km</span>
            </div>
            @error('radiusKm') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="mt-3 rounded-lg bg-emerald-700 px-4 py-2.5 font-medium text-white hover:bg-emerald-800">{{ __('Save work area') }}</button>
            @if ($saved === 'area') <span class="ml-3 text-sm text-emerald-800" role="status">{{ __('Saved.') }}</span> @endif
        </form>

        <section class="mt-10" aria-labelledby="profile-trades">
            <h2 id="profile-trades" class="font-semibold">{{ __('Trades') }}</h2>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($pro->trades->sortBy('name') as $trade)
                    <li>{{ $trade->name }}@if ($pro->isVerifiedFor($trade)) <span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-900">{{ __('Registration verified') }}</span>@endif</li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-zinc-500">{{ __('New trades and registrations are checked by our team before they go live. What you offer now stays active while we check.') }}</p>

            @if ($changes->isNotEmpty())
                <ul class="mt-4 space-y-2 text-sm" aria-label="{{ __('Your requests') }}">
                    @foreach ($changes as $change)
                        <li wire:key="change-{{ $change->public_id }}" @class(['rounded-lg border p-3', 'border-amber-200 bg-amber-50' => $change->status === \App\Domain\Pros\Enums\ProChangeStatus::Pending, 'border-emerald-200 bg-emerald-50' => $change->status === \App\Domain\Pros\Enums\ProChangeStatus::Approved, 'border-red-200 bg-red-50' => $change->status === \App\Domain\Pros\Enums\ProChangeStatus::Rejected])>
                            <span class="font-medium">{{ $change->summary() }}</span> · {{ $change->status->label() }}
                            @if ($change->decision_reason)<span class="block text-zinc-700">{{ $change->decision_reason }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($requested) <p class="mt-3 text-sm text-emerald-800" role="status">{{ __('Sent. We will review it and you will see the answer here.') }}</p> @endif

            @if ($offerable->isNotEmpty())
                <form wire:submit="requestTrade" class="mt-6 rounded-xl border border-zinc-200 bg-white p-4" novalidate>
                    <label for="new-trade" class="font-medium">{{ __('Add a trade') }}</label>
                    <select id="new-trade" wire:model.live="newTrade" class="{{ $input }}">
                        <option value="">{{ __('Choose a trade') }}</option>
                        @foreach ($offerable as $trade)
                            <option value="{{ $trade->id }}">{{ $trade->name }}</option>
                        @endforeach
                    </select>
                    @error('newTrade') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror

                    @if ($tradeRegistration)
                        <div class="mt-4 rounded-lg bg-zinc-50 p-3">
                            <p class="text-sm">{{ __('Optional: add your :type to get a "Registration verified" badge. You can add it later.', ['type' => $tradeRegistration->label()]) }}</p>
                            <label for="trade-number" class="mt-3 block text-sm font-medium">{{ __('Registration number') }}</label>
                            <input id="trade-number" type="text" wire:model="tradeNumber" autocomplete="off" class="{{ $input }}">
                            @error('tradeNumber') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                            <label for="trade-upload" class="mt-3 block text-sm font-medium">{{ __('Photo or PDF of the certificate') }}</label>
                            <input id="trade-upload" type="file" wire:model="tradeUpload" accept="image/*,application/pdf" class="mt-1 block w-full text-sm">
                            <p wire:loading wire:target="tradeUpload" class="mt-1 text-sm text-zinc-600">{{ __('Uploading…') }}</p>
                            @error('tradeUpload') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <button type="submit" class="mt-4 rounded-lg border border-emerald-700 px-4 py-2.5 font-medium text-emerald-900 hover:bg-emerald-50">{{ __('Send for review') }}</button>
                </form>
            @endif

            @if ($registrationChoices->isNotEmpty())
                <form wire:submit="requestRegistration" class="mt-4 rounded-xl border border-zinc-200 bg-white p-4" novalidate>
                    <label for="renew-type" class="font-medium">{{ __('Send or renew a registration') }}</label>
                    <select id="renew-type" wire:model.live="renewType" class="{{ $input }}">
                        <option value="">{{ __('Choose a registration') }}</option>
                        @foreach ($registrationChoices as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('renewType') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    @if ($renewType !== '')
                        <label for="renew-number" class="mt-3 block text-sm font-medium">{{ __('Registration number') }}</label>
                        <input id="renew-number" type="text" wire:model="renewNumber" autocomplete="off" class="{{ $input }}">
                        @error('renewNumber') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                        <label for="renew-upload" class="mt-3 block text-sm font-medium">{{ __('Photo or PDF of the certificate') }}</label>
                        <input id="renew-upload" type="file" wire:model="renewUpload" accept="image/*,application/pdf" class="mt-1 block w-full text-sm">
                        <p wire:loading wire:target="renewUpload" class="mt-1 text-sm text-zinc-600">{{ __('Uploading…') }}</p>
                        @error('renewUpload') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    @endif
                    <button type="submit" class="mt-4 rounded-lg border border-emerald-700 px-4 py-2.5 font-medium text-emerald-900 hover:bg-emerald-50">{{ __('Send for review') }}</button>
                </form>
            @endif
        </section>

        <section class="mt-10" aria-labelledby="profile-documents">
            <h2 id="profile-documents" class="font-semibold">{{ __('Documents') }}</h2>
            <ul class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white text-sm">
                @foreach ($documents as $document)
                    <li class="flex items-start justify-between gap-3 p-4" wire:key="doc-{{ $document->public_id }}">
                        <span>{{ $document->type->label() }}</span>
                        <span @class(['text-right', 'text-amber-800' => in_array($document->id, $attention, true) || $document->status === \App\Domain\Pros\Enums\DocumentStatus::Flagged, 'text-zinc-600' => ! in_array($document->id, $attention, true)])>
                            @if ($document->isExpired()) {{ __('Expired :date', ['date' => $document->expires_at->translatedFormat('j M Y')]) }}
                            @elseif (in_array($document->id, $attention, true)) {{ __('Expires :date', ['date' => $document->expires_at->translatedFormat('j M Y')]) }}
                            @elseif ($document->expires_at && $document->status === \App\Domain\Pros\Enums\DocumentStatus::Verified) {{ __('Valid until :date', ['date' => $document->expires_at->translatedFormat('j M Y')]) }}
                            @else {{ $document->status->label() }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
            <a wire:navigate.hover href="{{ route('pros.status') }}" class="mt-3 inline-block text-sm text-emerald-800 underline underline-offset-4">{{ __('Your application and documents') }}</a>
        </section>
    </section>
</main>
