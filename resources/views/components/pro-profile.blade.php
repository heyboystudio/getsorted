@props(['profile'])
{{-- One pro profile, exactly as a customer sees it (spec 021, AC17): used on the customer's page and the pro's preview. --}}
<article class="rounded-2xl border border-zinc-200 bg-white p-5">
    <div class="flex items-center gap-4">
        @if ($profile->photoUrl)
            <img src="{{ $profile->photoUrl }}" alt="" class="size-20 shrink-0 rounded-full object-cover">
        @else
            <span aria-hidden="true" class="flex size-20 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-2xl font-semibold text-emerald-900">{{ mb_strtoupper(mb_substr($profile->businessName, 0, 1)) }}</span>
        @endif
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold tracking-tight">{{ $profile->businessName }}</h1>
            @if ($profile->since)<p class="text-sm text-zinc-600">{{ __('On GetSorted since :date', ['date' => $profile->since->translatedFormat('M Y')]) }}</p>@endif
            <p class="mt-1 text-xs text-emerald-800">{{ __('Vetted by GetSorted') }}</p>
        </div>
    </div>

    @if ($profile->bio)
        <p class="mt-5 whitespace-pre-line text-zinc-800">{{ $profile->bio }}</p>
    @endif

    @if ($profile->registrations !== [])
        <section class="mt-6" aria-labelledby="pro-registrations">
            <h2 id="pro-registrations" class="text-sm font-semibold uppercase tracking-widest text-zinc-500">{{ __('Registrations') }}</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($profile->registrations as $registration)
                    <li @class(['text-emerald-800' => $registration['valid'], 'text-amber-800' => ! $registration['valid']])>
                        {{ $registration['valid'] ? '✓' : '!' }} {{ $registration['label'] }} · {{ $registration['valid'] ? __('Valid') : __('Expired') }}
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-6" aria-labelledby="pro-trades">
        <h2 id="pro-trades" class="text-sm font-semibold uppercase tracking-widest text-zinc-500">{{ __('Trades') }}</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($profile->trades as $trade)
                <li>{{ $trade['name'] }}@if ($trade['verified']) <span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-900">{{ __('Registration verified') }}</span>@endif</li>
            @endforeach
        </ul>
    </section>

    @if ($profile->area)
        <section class="mt-6" aria-labelledby="pro-area">
            <h2 id="pro-area" class="text-sm font-semibold uppercase tracking-widest text-zinc-500">{{ __('Works around') }}</h2>
            <p class="mt-2 text-sm text-zinc-700">{{ __(':area, up to about :km km away', ['area' => $profile->area, 'km' => $profile->radiusKm]) }}</p>
        </section>
    @endif
</article>
