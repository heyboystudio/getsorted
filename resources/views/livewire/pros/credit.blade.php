<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('pros.profile') }}" class="mb-6 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Profile') }}</a>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Introduction credit') }}</h1>

        @unless ($feeEnabled)
            <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">{{ __('Introductions are free right now. When a client chooses you to visit, it costs you nothing, and you do not need credit.') }}</p>
        @else
            <p class="mt-2 text-sm text-zinc-600">{{ __('When a client chooses you to visit, we unlock their name, phone number and address and take R :fee from your credit. Clients never pay us.', ['fee' => number_format($feeCents / 100, 0, '.', ' ')]) }}</p>

            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-5">
                <p class="text-sm text-zinc-600">{{ __('Your credit') }}</p>
                <p class="mt-1 text-3xl font-semibold" data-testid="credit-balance">R {{ number_format($balanceCents / 100, 2, '.', ' ') }}</p>
                @if ($freeLeft > 0)
                    <p class="mt-2 text-sm text-emerald-900">{{ trans_choice(':count free introduction left.|:count free introductions left.', $freeLeft) }}</p>
                @endif
            </div>

            <h2 class="mt-8 font-semibold">{{ __('Add credit') }}</h2>
            <p class="text-sm text-zinc-600">{{ __('You pay on PayFast, South Africa\'s payment page. Your credit appears within a minute of paying.') }}</p>
            @error('pack') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <div class="mt-3 grid gap-3">
                @foreach ($packs as $pack)
                    <button type="button" wire:click="buy({{ $pack }})" wire:loading.attr="disabled" class="flex items-center justify-between rounded-xl border border-zinc-300 bg-white p-4 text-left hover:border-emerald-700">
                        <span class="font-medium">R {{ number_format($pack / 100, 0, '.', ' ') }}</span>
                        <span class="text-sm text-zinc-600">{{ trans_choice(':count introduction|:count introductions', intdiv($pack, max(1, $feeCents))) }}</span>
                    </button>
                @endforeach
            </div>
        @endunless

        @if ($entries->isNotEmpty())
            <h2 class="mt-8 font-semibold">{{ __('History') }}</h2>
            <ul class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white text-sm">
                @foreach ($entries as $entry)
                    <li class="flex items-center justify-between p-3">
                        <span>{{ $entry->created_at->timezone(config('getsorted.timezone'))->format('j M Y, H:i') }} · {{ $entry->note }}</span>
                        <span class="{{ $entry->amount_cents < 0 ? 'text-zinc-700' : 'text-emerald-800' }}">{{ $entry->amount_cents < 0 ? '-' : '+' }}R {{ number_format(abs($entry->amount_cents) / 100, 2, '.', ' ') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</main>
