<main class="flex min-h-dvh items-start justify-center px-5 py-12">
    <section class="w-full max-w-xl">
        <div class="mb-10 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-2xl font-semibold tracking-tight">{{ __('Sortd') }}<span aria-hidden="true" class="text-emerald-700">.</span></a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Log out') }}</button>
            </form>
        </div>

        <h1 class="text-3xl font-semibold tracking-tight">{{ __('Hi :name', ['name' => $firstName]) }} <span aria-hidden="true">👋</span></h1>
        <div class="mt-6 flex items-center justify-between">
            <h2 class="text-lg font-semibold">{{ __('Your jobs') }}</h2>
            <a href="{{ route('home') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('Book a pro') }}</a>
        </div>
        @forelse ($jobs as $job)
            <a href="{{ $job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Draft ? route('booking.continue', $job) : route('jobs.show', $job) }}" wire:key="{{ $job->public_id }}"
               class="mt-3 block rounded-xl border border-zinc-200 bg-white p-4 hover:border-emerald-700">
                <span class="flex items-start justify-between gap-3">
                    <span class="font-medium">{{ $job->service->name }}</span>
                    <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs">{{ $job->status->customerLabel() }}</span>
                </span>
                <span class="mt-1 block text-sm text-zinc-600">
                    {{ $job->property?->suburb->name ?? __('No address yet') }}@if ($job->posted_at) · {{ __('Posted :date', ['date' => $job->posted_at->translatedFormat('j M')]) }}@endif
                </span>
            </a>
        @empty
            <p class="mt-3 rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-600">{{ __('Your jobs will appear here.') }}</p>
        @endforelse

        <a href="{{ route('properties.index') }}" class="mt-8 flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 hover:border-emerald-700">
            <span class="font-medium">{{ __('Saved properties') }}</span>
            <span aria-hidden="true">→</span>
        </a>
    </section>
</main>
