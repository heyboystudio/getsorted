@php($J = \App\Domain\ServiceJobs\Enums\ServiceJobStatus::class)
<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Your jobs') }}</h1>

        <div class="mt-6 grid grid-cols-4 rounded-lg bg-zinc-100 p-1 text-sm font-medium" role="tablist">
            @foreach (['invites' => __('Invites'), 'quoted' => __('Quoted'), 'booked' => __('Booked'), 'done' => __('Done')] as $key => $label)
                <button type="button" role="tab" aria-selected="{{ $current === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')" wire:key="tab-{{ $key }}" @class(['rounded-md px-2 py-2', 'bg-white shadow-sm' => $current === $key, 'text-zinc-600' => $current !== $key])>
                    {{ $label }}@if ($counts[$key] > 0 && $key !== 'done')<span class="ml-1 rounded-full bg-emerald-700 px-1.5 text-xs text-white">{{ $counts[$key] }}</span>@endif
                </button>
            @endforeach
        </div>

        <ul class="mt-6 space-y-3">
            @forelse ($rows as $row)
                @php($job = $row['job'])
                <li wire:key="row-{{ $row['invite']->public_id }}">
                    <a wire:navigate.hover href="{{ route('pros.jobs.show', $row['invite']) }}" class="block rounded-xl border border-zinc-200 bg-white p-4 hover:border-emerald-700">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium">{{ $job->trade->name }}@if ($job->factTexts() !== []) · {{ $job->factTexts()[0] }}@endif</p>
                            @if ($current === 'invites' && $job->urgency === \App\Domain\ServiceJobs\Enums\Urgency::Urgent)
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Urgent') }}</span>
                            @elseif ($current === 'done' && $row['note'])
                                <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs">{{ $row['note'] }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-zinc-600">{{ $job->area_label }}@if ($row['invite']->pro->base_location && $job->location) · {{ \App\Domain\Matching\Support\Distance::label($row['invite']->pro->base_location, $job->location) }}@endif · {{ $job->time_window?->label() }}@if ($job->preferred_date && $job->time_window !== \App\Domain\ServiceJobs\Enums\TimeWindow::Today), {{ $job->preferred_date->translatedFormat('D j M') }}@endif</p>
                        <p class="mt-2 text-xs text-zinc-500">
                            @if ($current === 'invites')
                                {{ __(':time left to reply', ['time' => $row['invite']->expires_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}
                            @elseif ($current === 'quoted')
                                {{ __('Quote sent: R :total', ['total' => number_format($row['quote']->total_cents / 100, 2, '.', ' ')]) }} · {{ __('waiting for the customer') }}
                            @elseif ($current === 'booked')
                                {{ $job->status === $J::AwaitingDeposit ? __('Waiting for the deposit') : __('Booked') }}@if ($job->scheduled_for) · {{ $job->scheduled_for->translatedFormat('D j M') }}@endif
                            @endif
                        </p>
                    </a>
                </li>
            @empty
                <li class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-600">
                    @switch ($current)
                        @case('invites') {{ __("No new jobs right now. We'll WhatsApp you when one fits.") }} @break
                        @case('quoted') {{ __('Quotes you have sent wait here until the customer chooses.') }} @break
                        @case('booked') {{ __('Jobs you win will appear here with the address and contact details.') }} @break
                        @default {{ __('No past jobs yet.') }}
                    @endswitch
                </li>
            @endforelse
        </ul>
    </section>
</main>
