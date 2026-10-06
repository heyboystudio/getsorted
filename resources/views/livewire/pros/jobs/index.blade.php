<main class="flex min-h-dvh items-start justify-center px-5 py-10">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('pros.welcome') }}" class="text-sm text-zinc-700 underline underline-offset-4">← {{ __('Sortd Pro') }}</a>
        <h1 class="mt-6 text-2xl font-semibold tracking-tight">{{ __('Your jobs') }}</h1>

        <div class="mt-6 grid grid-cols-2 rounded-lg bg-zinc-100 p-1 text-sm font-medium" role="tablist">
            <button type="button" role="tab" aria-selected="{{ $isNew ? 'true' : 'false' }}" wire:click="$set('tab', 'new')" @class(['rounded-md px-3 py-2', 'bg-white shadow-sm' => $isNew, 'text-zinc-600' => ! $isNew])>{{ __('New') }}</button>
            <button type="button" role="tab" aria-selected="{{ $isNew ? 'false' : 'true' }}" wire:click="$set('tab', 'past')" @class(['rounded-md px-3 py-2', 'bg-white shadow-sm' => ! $isNew, 'text-zinc-600' => $isNew])>{{ __('Past') }}</button>
        </div>

        <ul class="mt-6 space-y-3">
            @forelse ($invites as $invite)
                @php($job = $invite->serviceJob)
                <li wire:key="invite-{{ $invite->public_id }}">
                    <a href="{{ $isNew ? route('pros.jobs.show', $invite) : '#' }}" @class(['block rounded-xl border border-zinc-200 bg-white p-4', 'hover:border-emerald-700' => $isNew, 'pointer-events-none' => ! $isNew])>
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium">{{ $job->trade->name }}@if ($job->factTexts() !== []) · {{ $job->factTexts()[0] }}@endif</p>
                            @if ($job->urgency === \App\Domain\ServiceJobs\Enums\Urgency::Urgent)
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Urgent') }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-zinc-600">{{ $job->area_label }}@if ($invite->pro->base_location && $job->location) · {{ \App\Domain\Matching\Support\Distance::label($invite->pro->base_location, $job->location) }}@endif · {{ $job->time_window?->label() }}@if ($job->preferred_date && $job->time_window !== \App\Domain\ServiceJobs\Enums\TimeWindow::Today), {{ $job->preferred_date->translatedFormat('D j M') }}@endif</p>
                        <p class="mt-2 text-xs text-zinc-500">
                            @if ($isNew)
                                {{ __(':time left to reply', ['time' => $invite->expires_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}
                            @else
                                {{ $invite->status->isOpen() ? __('Expired') : $invite->status->label() }}
                            @endif
                        </p>
                    </a>
                </li>
            @empty
                <li class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-600">
                    {{ $isNew ? __("No new jobs right now. We'll WhatsApp you when one fits.") : __('No past jobs yet.') }}
                </li>
            @endforelse
        </ul>
    </section>
</main>
