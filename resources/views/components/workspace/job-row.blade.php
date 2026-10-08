@props(['job', 'href'])
{{-- One job in a list (client home and jobs list, spec 028): who, what, where, status. The slot is the detail line. --}}
<a wire:navigate.hover href="{{ $href }}" {{ $attributes->class('flex items-center gap-3 rounded-lg border border-zinc-200 bg-white p-4 transition hover:border-zinc-400 hover:shadow-xs') }}>
    @if ($job->acceptedQuote?->pro)
        @if ($job->acceptedQuote->hasProPhoto())
            <img src="{{ $job->acceptedQuote->proPhotoUrl() }}" alt="" class="size-11 shrink-0 rounded-full object-cover">
        @else
            <span aria-hidden="true" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-zinc-100 font-semibold text-zinc-700">{{ mb_strtoupper(mb_substr((string) $job->acceptedQuote->pro->business_name, 0, 1)) }}</span>
        @endif
    @endif
    <span class="min-w-0 flex-1">
        <span class="flex items-start justify-between gap-3">
            <span class="font-medium text-zinc-900">{{ \App\Domain\ServiceJobs\Support\JobLabel::for($job) }}</span>
            <flux:badge size="sm" :color="match (true) { $job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Completed => 'green', in_array($job->status, \App\Domain\ServiceJobs\Enums\ServiceJobStatus::ended(), true) => 'zinc', default => 'blue' }">{{ $job->status->customerLabel() }}</flux:badge>
        </span>
        <span class="mt-1 block truncate text-sm text-zinc-600">{{ $slot }}</span>
    </span>
</a>
