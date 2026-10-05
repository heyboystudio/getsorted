<main class="flex min-h-dvh items-start justify-center px-5 py-12">
    <section class="w-full max-w-xl">
        <a href="{{ route('account.home') }}" class="mb-10 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Your jobs') }}</a>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Book a pro') }}</h1>

        <a href="{{ route('assistant') }}" class="mt-6 flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 hover:border-emerald-700">
            <span>
                <span class="block font-medium">{{ __('Not sure what you need? Ask Siya') }}</span>
                <span class="mt-1 block text-sm text-zinc-600">{{ __('Describe the problem and our AI assistant finds the right service.') }}</span>
            </span>
            <span aria-hidden="true">→</span>
        </a>

        <h2 class="mt-8 text-lg font-semibold">{{ __('Or choose a service') }}</h2>
        @forelse ($trades as $trade)
            <div class="mt-4" wire:key="trade-{{ $trade->key }}">
                <h3 class="text-sm font-medium text-zinc-500">{{ $trade->name }}</h3>
                <div class="mt-2 grid gap-2">
                    @foreach ($trade->services as $service)
                        <a href="{{ route('booking.start', [$trade, $service->key]) }}" wire:key="service-{{ $service->key }}"
                           class="block rounded-lg border border-zinc-200 bg-white px-4 py-3 hover:border-emerald-700">{{ $service->name }}</a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="mt-4 text-sm text-zinc-600">{{ __('We’re getting our services ready.') }}</p>
        @endforelse
    </section>
</main>
