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
                <p class="mt-1">{{ __('Expired registrations stop invites for the services that need them. Check the documents below.') }}</p>
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

        <form wire:submit="saveAreas" class="mt-8" novalidate>
            <p class="font-semibold">{{ __('Suburbs you work in') }}</p>
            <p class="text-sm text-zinc-600">{{ __('Changes apply to new invites straight away.') }}</p>
            <div class="mt-3 space-y-2">
                @foreach ($suburbsByRegion as $region => $suburbs)
                    <details wire:key="region-{{ $region }}" class="rounded-xl border border-zinc-200 bg-white" @if (collect($suburbs)->pluck('id')->intersect($suburbIds)->isNotEmpty()) open @endif>
                        <summary class="cursor-pointer p-3 font-medium">{{ \App\Domain\Properties\Enums\Region::tryFrom((string) $region)?->label() ?? $region }}</summary>
                        <div class="grid grid-cols-2 gap-2 p-3 pt-0">
                            @foreach ($suburbs as $suburb)
                                <label wire:key="suburb-{{ $suburb->id }}" class="flex items-center gap-2 rounded-lg border border-zinc-200 p-3 text-sm has-[:checked]:border-emerald-700">
                                    <input type="checkbox" wire:model="suburbIds" value="{{ $suburb->id }}" class="size-5 rounded text-emerald-700"> {{ $suburb->name }}
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
            @error('suburbIds') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="mt-3 rounded-lg bg-emerald-700 px-4 py-2.5 font-medium text-white hover:bg-emerald-800">{{ __('Save suburbs') }}</button>
            @if ($saved === 'areas') <span class="ml-3 text-sm text-emerald-800" role="status">{{ __('Saved.') }}</span> @endif
        </form>

        <section class="mt-10" aria-labelledby="profile-services">
            <h2 id="profile-services" class="font-semibold">{{ __('Services') }}</h2>
            <div class="mt-3 space-y-3 text-sm">
                @foreach ($services as $trade => $list)
                    <div><p class="font-medium">{{ $trade }}</p><p class="text-zinc-700">{{ $list->pluck('name')->implode(', ') }}</p></div>
                @endforeach
            </div>
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
