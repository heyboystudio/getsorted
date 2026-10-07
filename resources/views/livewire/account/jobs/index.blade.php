@php($S = \App\Domain\ServiceJobs\Enums\ServiceJobStatus::class)
<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Your jobs') }}</h1>
            <a href="{{ route('book') }}" wire:navigate class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('Book a pro') }}</a>
        </div>

        <div class="mt-6 grid grid-cols-3 rounded-lg bg-zinc-100 p-1 text-sm font-medium" role="tablist">
            @foreach (['active' => __('Active'), 'done' => __('Done'), 'ended' => __('Cancelled')] as $key => $label)
                <button type="button" role="tab" aria-selected="{{ $current === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')" wire:key="tab-{{ $key }}" @class(['rounded-md px-3 py-2', 'bg-white shadow-sm' => $current === $key, 'text-zinc-600' => $current !== $key])>{{ $label }}</button>
            @endforeach
        </div>

        <ul class="mt-6 space-y-3">
            @forelse ($jobs as $job)
                <li wire:key="job-{{ $job->public_id }}">
                    <a wire:navigate.hover href="{{ $job->status === $S::Draft ? route('booking.continue', $job) : route('jobs.show', $job) }}" class="block rounded-xl border border-zinc-200 bg-white p-4 hover:border-emerald-700">
                        <span class="flex items-start justify-between gap-3">
                            <span class="font-medium">{{ $job->service->name }}</span>
                            <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs">{{ $job->status->customerLabel() }}</span>
                        </span>
                        <span class="mt-1 block text-sm text-zinc-600">
                            {{ $job->property?->suburb->name ?? __('No address yet') }}@if ($job->scheduled_for && in_array($job->status, [$S::Scheduled, $S::InProgress, $S::AwaitingDeposit], true)) · {{ __('Booked for :date', ['date' => $job->scheduled_for->translatedFormat('D j M')]) }}@elseif ($job->posted_at) · {{ __('Posted :date', ['date' => $job->posted_at->translatedFormat('j M')]) }}@endif
                        </span>
                        @if ($job->accepted_quote_id && $job->acceptedQuote?->pro?->business_name)
                            <span class="mt-1 block text-sm text-zinc-600">{{ __('with :name', ['name' => $job->acceptedQuote->pro->business_name]) }}</span>
                        @endif
                    </a>
                </li>
            @empty
                <li class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-600">
                    @if ($current === 'active')
                        {{ __('No active jobs. Tell Siya what needs fixing and you will get quotes from vetted pros.') }}
                    @elseif ($current === 'done')
                        {{ __('Finished jobs will appear here.') }}
                    @else
                        {{ __('Nothing cancelled or expired.') }}
                    @endif
                </li>
            @endforelse
        </ul>
    </section>
</main>
