<main class="flex min-h-dvh items-start justify-center px-5 pb-32 pt-10">
    <section class="w-full max-w-xl">
        <a href="{{ route('pros.jobs') }}" class="text-sm text-zinc-700 underline underline-offset-4">← {{ __('Your jobs') }}</a>

        @if ($job === null)
            <div class="mt-8 rounded-xl border border-zinc-200 bg-white p-6 text-center">
                <p class="text-lg font-medium">{{ __('This job is no longer available') }}</p>
                <p class="mt-2 text-zinc-600">{{ __('It may have expired, been closed, or you already answered it.') }}</p>
            </div>
        @else
            <p class="mt-6 text-sm font-medium uppercase tracking-widest text-emerald-800">{{ $job->service->trade->name }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $job->service->name }}</h1>
            <p class="mt-2 text-zinc-700">{{ $job->property?->suburb?->name }} · {{ $job->time_window?->label() }}@if ($job->preferred_date && $job->time_window !== \App\Domain\ServiceJobs\Enums\TimeWindow::Today), {{ $job->preferred_date->translatedFormat('D j M') }}@endif
                @if ($job->urgency === \App\Domain\ServiceJobs\Enums\Urgency::Urgent) <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Urgent') }}</span> @endif
            </p>
            <p class="mt-1 text-sm text-zinc-500">
                {{ trans_choice(':count pro invited|:count pros invited', $invitedCount, ['count' => $invitedCount]) }} · {{ trans_choice(':count quote in|:count quotes in', $quotesCount, ['count' => $quotesCount]) }} · {{ __(':time left to reply', ['time' => $invite->expires_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}
            </p>

            @if ($description)
                <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-4">
                    <h2 class="font-medium">{{ __('Job description') }}</h2>
                    <p class="mt-2 whitespace-pre-line text-sm text-zinc-700">{{ $description }}</p>
                </section>
            @endif

            <dl class="mt-4 space-y-3 rounded-xl border border-zinc-200 bg-white p-4 text-sm">
                @foreach ($answers as $answer)
                    <div><dt class="text-zinc-500">{{ $answer['prompt'] }}</dt><dd>{{ is_array($answer['answer']) ? implode(', ', $answer['answer']) : ($answer['type'] === 'yes_no' ? __(ucfirst((string) $answer['answer'])) : $answer['answer']) }}</dd></div>
                @endforeach
                @if ($notes)
                    <div><dt class="text-zinc-500">{{ __("Customer's notes") }}</dt><dd class="whitespace-pre-line">{{ $notes }}</dd></div>
                @endif
            </dl>

            @if ($photoUrls !== [])
                <div class="mt-4 grid grid-cols-2 gap-3">
                    @foreach ($photoUrls as $url)
                        <img src="{{ $url }}" alt="{{ __('Job photo :number', ['number' => $loop->iteration]) }}" class="aspect-square w-full rounded-xl object-cover" loading="lazy">
                    @endforeach
                </div>
            @endif

            <p class="mt-6 text-xs text-zinc-500">{{ __("You'll see the customer's name, contact details and address if they accept your quote.") }}</p>

            <div class="fixed inset-x-0 bottom-0 border-t border-zinc-200 bg-white px-5 py-4" x-data="{ open: false }">
                <div class="mx-auto max-w-xl">
                    <div x-show="open" x-cloak class="mb-4 space-y-2">
                        <p class="font-medium">{{ __('Why not this one?') }}</p>
                        @foreach ($reasons as $option)
                            <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model="reason" value="{{ $option->value }}" class="text-emerald-700"> {{ $option->label() }}</label>
                        @endforeach
                        <input type="text" wire:model="note" maxlength="300" placeholder="{{ __('Tell us briefly (optional unless Other)') }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                        @error('reason') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        @error('note') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        <button type="button" wire:click="decline" wire:loading.attr="disabled" wire:target="decline" class="w-full rounded-lg border border-zinc-400 px-4 py-2 text-sm font-medium disabled:opacity-60">{{ __('Decline this job') }}</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" x-on:click="open = ! open" class="rounded-lg border border-zinc-300 px-4 py-3 font-medium">{{ __('Not for me') }}</button>
                        <button type="button" disabled class="rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white opacity-60">{{ __('Quoting opens soon') }}</button>
                    </div>
                </div>
            </div>
        @endif
    </section>
</main>
