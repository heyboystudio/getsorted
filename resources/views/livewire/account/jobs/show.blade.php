<main class="flex min-h-dvh items-start justify-center px-5 py-12">
    <section class="w-full max-w-xl">
        <a href="{{ route('account.home') }}" class="mb-10 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Your account') }}</a>

        @if ($justPosted)
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900" role="status">
                <p class="text-lg font-semibold">{{ __('Your job is posted') }} ✓</p>
                <p class="mt-1 text-sm">{{ __("We're finding vetted pros near you. We'll WhatsApp you as quotes come in.") }}</p>
            </div>
        @endif

        <p class="text-sm font-medium uppercase tracking-widest text-emerald-800">{{ $job->service->trade->name }}</p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ $job->service->name }}</h1>
        <p class="mt-2 inline-block rounded-full bg-zinc-100 px-3 py-1 text-sm">{{ $job->status->customerLabel() }}@if ($job->urgency === \App\Domain\ServiceJobs\Enums\Urgency::Urgent) · {{ __('Urgent') }}@endif</p>

        @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Open)
            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4" role="status">
                <p class="font-medium">{{ __('Finding your pros') }}</p>
                <p class="mt-1 text-sm text-zinc-600">
                    @if ($invitedCount > 0 && $quotes->isEmpty())
                        {{ __('Waiting for quotes.') }} {{ trans_choice(':count pro invited so far.|:count pros invited so far.', $invitedCount, ['count' => $invitedCount]) }}
                    @elseif ($invitedCount > 0)
                        {{ trans_choice(':count pro invited so far. Quotes will appear here.|:count pros invited so far. Quotes will appear here.', $invitedCount, ['count' => $invitedCount]) }}
                    @else
                        {{ __("We're still looking for a pro who can take this job. We'll WhatsApp you as soon as quotes come in.") }}
                    @endif
                </p>
            </div>
        @endif

        <dl class="mt-6 space-y-4 rounded-xl border border-zinc-200 bg-white p-4 text-sm">
            @foreach ($job->orderedAnswers() as $answer)
                <div><dt class="text-zinc-500">{{ $answer['prompt'] }}</dt><dd>{{ is_array($answer['answer']) ? implode(', ', $answer['answer']) : ($answer['type'] === 'yes_no' ? __(ucfirst((string) $answer['answer'])) : $answer['answer']) }}</dd></div>
            @endforeach
            @if ($job->customer_notes)
                <div><dt class="text-zinc-500">{{ __('Notes') }}</dt><dd class="whitespace-pre-line">{{ $job->customer_notes }}</dd></div>
            @endif
            @if ($job->property)
                <div><dt class="text-zinc-500">{{ __('Where') }}</dt><dd>{{ $job->property->label }} — {{ $job->property->street_address }}, {{ $job->property->suburb->name }}</dd></div>
            @endif
            @if ($job->time_window)
                <div><dt class="text-zinc-500">{{ __('When') }}</dt><dd>{{ $job->time_window->label() }}@if ($job->preferred_date && $job->time_window !== \App\Domain\ServiceJobs\Enums\TimeWindow::Today), {{ $job->preferred_date->translatedFormat('D j M') }}@endif</dd></div>
            @endif
        </dl>

        @if ($job->getMedia(\App\Models\ServiceJob::PHOTO_COLLECTION)->isNotEmpty())
            <section class="mt-6">
                <h2 class="font-semibold">{{ __('Photos') }}</h2>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    @foreach ($job->getMedia(\App\Models\ServiceJob::PHOTO_COLLECTION) as $photo)
                        <img src="{{ $job->photoUrl($photo) }}" alt="{{ __('Job photo :number', ['number' => $loop->iteration]) }}" class="aspect-square w-full rounded-xl object-cover" loading="lazy">
                    @endforeach
                </div>
            </section>
        @endif

        @include('livewire.account.jobs.partials.quotes')

        @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Draft)
            <a href="{{ route('booking.continue', $job) }}" class="mt-6 block w-full rounded-lg bg-emerald-700 px-4 py-3 text-center font-medium text-white hover:bg-emerald-800">{{ __('Finish your request') }}</a>
        @endif
    </section>
</main>
