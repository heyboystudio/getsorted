@php($S = \App\Domain\ServiceJobs\Enums\ServiceJobStatus::class)
<div class="mx-auto w-full max-w-4xl px-4 py-6 lg:px-8">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Your jobs') }}</flux:heading>
        <flux:button variant="primary" :href="route('book')" wire:navigate>{{ __('Book a pro') }}</flux:button>
    </div>

    <div class="mt-6 inline-grid grid-cols-3 rounded-lg bg-zinc-200/60 p-1 text-sm font-medium" role="tablist">
        @foreach (['active' => __('Active'), 'done' => __('Done'), 'ended' => __('Cancelled')] as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $current === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')" wire:key="tab-{{ $key }}" @class(['rounded-md px-5 py-2', 'bg-white text-zinc-900 shadow-xs' => $current === $key, 'text-zinc-600 hover:text-zinc-900' => $current !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    <ul class="mt-6 space-y-3">
        @forelse ($jobs as $job)
            <li wire:key="job-{{ $job->public_id }}">
                <x-workspace.job-row :job="$job" :href="$job->status === $S::Draft ? route('booking.continue', $job) : route('jobs.show', $job)">
                    {{ \App\Domain\ServiceJobs\Support\JobLabel::area($job) }}@if ($job->scheduled_for && in_array($job->status, [$S::Scheduled, $S::InProgress, $S::AwaitingDeposit], true)) · {{ __('Booked for :date', ['date' => $job->scheduled_for->translatedFormat('D j M')]) }}@elseif ($job->posted_at) · {{ __('Posted :date', ['date' => $job->posted_at->translatedFormat('j M')]) }}@endif
                    @if ($job->accepted_quote_id && $job->acceptedQuote?->pro?->business_name) · {{ __('with :name', ['name' => $job->acceptedQuote->pro->business_name]) }}@endif
                </x-workspace.job-row>
            </li>
        @empty
            <li class="rounded-lg border border-dashed border-zinc-300 p-8 text-center text-zinc-600">
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
</div>
