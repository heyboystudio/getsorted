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

        <div class="mt-8 grid grid-cols-2 gap-3">
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
