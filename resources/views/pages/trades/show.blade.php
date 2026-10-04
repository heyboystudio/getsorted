<x-layouts.app :title="$trade->name">
    <main class="flex min-h-dvh items-start justify-center px-5 py-12">
        <section class="w-full max-w-xl">
            <a href="{{ route('home') }}" class="mb-10 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('All trades') }}</a>
            <h1 class="text-3xl font-semibold tracking-tight">{{ $trade->name }}</h1>
            <p class="mt-2 text-zinc-600">{{ __('What do you need?') }}</p>
            <ul class="mt-6 space-y-3">
                @foreach ($services as $service)
                    <li>
                        <a href="{{ route('booking.start', [$trade, $service]) }}" class="block rounded-xl border border-zinc-200 bg-white p-4 hover:border-emerald-700">
                            <span class="block font-medium">{{ $service->name }}</span>
                            @if ($service->description)
                                <span class="mt-1 block text-sm text-zinc-600">{{ $service->description }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </main>
</x-layouts.app>
