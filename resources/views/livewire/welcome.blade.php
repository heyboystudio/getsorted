<main class="flex min-h-dvh items-start justify-center px-5 py-12">
    <section aria-labelledby="welcome-title" class="w-full max-w-xl">
        <div class="mb-10 flex items-center justify-between">
            <p class="text-2xl font-semibold tracking-tight">{{ __('Sortd') }}<span aria-hidden="true" class="text-emerald-700">.</span></p>
            @auth
                <a href="{{ route('account.home') }}" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Your account') }}</a>
            @endauth
        </div>
        <h1 id="welcome-title" class="text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">{{ __('What do you need help with?') }}</h1>
        <p class="mt-3 text-lg text-zinc-600">{{ __('Vetted Durban tradespeople. Up to three quotes, one place to track it all.') }}</p>

        <form wire:submit="find" class="mt-8">
            <label for="describe" class="block text-sm font-medium text-zinc-800">{{ __('Describe the problem') }}</label>
            <textarea id="describe" wire:model="description" rows="3" maxlength="500"
                placeholder="{{ __('e.g. kitchen sink is blocked') }}"
                class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3 text-base focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/30"></textarea>
            @error('description') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            <button type="submit" wire:loading.attr="disabled" wire:target="find" class="mt-3 w-full rounded-lg bg-emerald-700 px-4 py-3 text-lg font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="find">{{ __('Find a pro') }}</span>
                <span wire:loading wire:target="find">{{ __('Checking…') }}</span>
            </button>
        </form>

        @if ($suggestion)
            <div class="mt-6 rounded-xl border border-emerald-700 bg-emerald-50 p-4" aria-live="polite">
                <p class="text-sm font-medium text-emerald-900">{{ __('Is this what you need?') }}</p>
                <p class="mt-1 text-lg font-semibold">{{ $suggestion->name }}</p>
                <p class="mt-1 text-sm text-zinc-700">{{ $suggestion->description }}</p>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <a href="{{ route('booking.start', ['trade' => $suggestion->trade, 'service' => $suggestion->key]) }}" class="rounded-lg bg-emerald-700 px-4 py-3 text-center font-medium text-white hover:bg-emerald-800">{{ __('Yes, book this') }}</a>
                    <button type="button" wire:click="chooseSomethingElse" class="rounded-lg border border-zinc-300 bg-white px-4 py-3 font-medium hover:border-emerald-700">{{ __('Choose something else') }}</button>
                </div>
            </div>
        @elseif ($showFallback)
            <p class="mt-6 text-zinc-700" aria-live="polite">{{ __('Choose the closest service') }}:</p>
        @endif

        <div class="mt-6 grid grid-cols-2 gap-3">
            @forelse ($trades as $trade)
                <a href="{{ route('trades.show', $trade) }}" class="flex min-h-24 items-end rounded-xl border border-zinc-200 bg-white p-4 text-lg font-medium hover:border-emerald-700">
                    {{ $trade->name }}
                </a>
            @empty
                <p class="col-span-2 text-zinc-600">{{ __("We're getting things ready.") }}</p>
            @endforelse
        </div>

        <p class="mt-12 text-sm text-zinc-600">{{ __('Are you a tradesperson?') }} <a href="{{ route('pros.join') }}" class="font-medium text-emerald-800 underline underline-offset-4">{{ __('Join as a pro') }}</a></p>
    </section>
</main>
