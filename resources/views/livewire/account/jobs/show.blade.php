<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('jobs.index') }}" class="mb-6 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Your jobs') }}</a>

        @if ($justPosted)
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900" role="status">
                <p class="text-lg font-semibold">{{ __('Your job is posted') }} ✓</p>
                <p class="mt-1 text-sm">{{ __("We're finding vetted pros near you. We'll email you as quotes come in.") }}</p>
            </div>
        @endif

        <p class="text-sm font-medium uppercase tracking-widest text-emerald-800">{{ $job->trade->name }}</p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ $job->factTexts()[0] ?? $job->trade->name }}</h1>
        <p class="mt-2 inline-block rounded-full bg-zinc-100 px-3 py-1 text-sm">{{ $job->status->customerLabel() }}@if ($job->urgency === \App\Domain\ServiceJobs\Enums\Urgency::Urgent) · {{ __('Urgent') }}@endif</p>

        @if ($stages)
            <ol class="mt-6 flex items-center gap-1 text-xs" aria-label="{{ __('Job progress') }}">
                @foreach ($stages as $stage)
                    <li class="flex flex-1 flex-col items-center gap-1 text-center" @if ($stage['state'] === 'current') aria-current="step" @endif>
                        <span @class(['h-1.5 w-full rounded-full', 'bg-emerald-700' => $stage['state'] !== 'upcoming', 'bg-zinc-200' => $stage['state'] === 'upcoming'])></span>
                        <span @class(['font-medium text-emerald-900' => $stage['state'] === 'current', 'text-zinc-700' => $stage['state'] === 'done', 'text-zinc-500' => $stage['state'] === 'upcoming'])>@if ($stage['state'] === 'done')<span class="sr-only">{{ __('Done:') }} </span>@endif{{ $stage['label'] }}</span>
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Open)
            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4" role="status">
                <p class="font-medium">{{ __('Finding your pros') }}</p>
                <p class="mt-1 text-sm text-zinc-600">
                    @if ($invitedCount > 0 && $quotes->isEmpty())
                        {{ __('Waiting for quotes.') }} {{ trans_choice(':count pro invited so far.|:count pros invited so far.', $invitedCount, ['count' => $invitedCount]) }}
                    @elseif ($invitedCount > 0)
                        {{ trans_choice(':count pro invited so far. Quotes will appear here.|:count pros invited so far. Quotes will appear here.', $invitedCount, ['count' => $invitedCount]) }}
                    @else
                        {{ __("We're still looking for a pro who can take this job. We'll email you as soon as quotes come in.") }}
                    @endif
                </p>
            </div>
        @endif

        @error('cancel') <p class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror

        @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Completed)
            <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950" role="status">{{ __('This job is done. Thanks for using GetSorted!') }}</div>
        @endif

        @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Cancelled)
            <div class="mt-6 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm" role="status">{{ __('This job was cancelled. You can post a new one any time.') }}</div>
        @endif

        <dl class="mt-6 space-y-4 rounded-xl border border-zinc-200 bg-white p-4 text-sm">
            @if ($job->facts !== [])
                <div><dt class="text-zinc-500">{{ __('What you told us') }}</dt><dd class="mt-1 flex flex-wrap gap-2">@foreach ($job->factTexts() as $fact)<span class="rounded-full bg-amber-100 px-3 py-1 text-amber-950">{{ $fact }}</span>@endforeach</dd></div>
            @endif
            @if ($job->customer_notes)
                <div><dt class="text-zinc-500">{{ __('Notes') }}</dt><dd class="whitespace-pre-line">{{ $job->customer_notes }}</dd></div>
            @endif
            @if ($job->property)
                <div><dt class="text-zinc-500">{{ __('Where') }}</dt><dd>{{ $job->property->label }} — {{ $job->property->street_address }}@if ($job->property->area_label), {{ $job->property->area_label }}@endif</dd></div>
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

        {{-- Spec 018: chat with each pro looking at the job. Invited pros stay anonymous until they reply or quote (spec 009 AC13). --}}
        @if ($chats->isNotEmpty())
            <section class="mt-6" id="chats">
                <h2 class="font-semibold">{{ $job->accepted_quote_id ? __('Chat with your pro') : __('Chat with your pros') }}</h2>
                @if ($chats->count() > 1 || ! $openChat)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($chats as $chat)
                            <button type="button" wire:key="chat-{{ $chat['pro']->public_id }}" wire:click="openChat(@js($chat['pro']->public_id))"
                                @class(['rounded-full border px-4 py-2 text-sm', 'border-emerald-700 bg-emerald-50' => $openChat && $openChat['pro']->is($chat['pro']), 'border-zinc-300 bg-white' => ! ($openChat && $openChat['pro']->is($chat['pro']))])>
                                {{ $chat['label'] }}
                                @if ($chat['unread'] > 0)<span class="ml-1 rounded-full bg-emerald-700 px-2 text-xs text-white">{{ $chat['unread'] }}</span>@endif
                                @unless ($chat['writable'])<span class="ml-1 text-xs text-zinc-500">· {{ __('closed') }}</span>@endunless
                            </button>
                        @endforeach
                    </div>
                @endif
                @if ($openChat)
                    <livewire:jobs.chat :job-public-id="$job->public_id" :pro-public-id="$openChat['pro']->public_id" :title="$openChat['label']" :key="'chat-'.$openChat['pro']->public_id.'-'.$openChat['label']" />
                @else
                    <p class="mt-2 text-sm text-zinc-600">{{ __('Ask a pro a question or send more photos before they send their estimate.') }}</p>
                @endif
            </section>
        @endif

        @if ($timeline->isNotEmpty())
            <section class="mt-8" aria-labelledby="timeline-title">
                <h2 id="timeline-title" class="font-semibold">{{ __('Timeline') }}</h2>
                <ol class="mt-3 space-y-3 border-l-2 border-zinc-200 pl-4 text-sm">
                    @foreach ($timeline as $entry)
                        <li wire:key="timeline-{{ $loop->index }}">
                            <span class="block">{{ $entry['text'] }}</span>
                            <time datetime="{{ $entry['at']->toIso8601String() }}" class="text-xs text-zinc-500">{{ $entry['at']->translatedFormat('D j M, H:i') }}</time>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if ($bookAgain)
            <a wire:navigate.hover href="{{ $bookAgain }}" class="mt-8 block w-full rounded-lg border border-emerald-700 px-4 py-3 text-center font-medium text-emerald-900 hover:bg-emerald-50">{{ __('Book :trade again', ['trade' => mb_strtolower($job->trade->name)]) }}</a>
        @endif

        @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Draft)
            <a wire:navigate.hover href="{{ route('booking.continue', $job) }}" class="mt-6 block w-full rounded-lg bg-emerald-700 px-4 py-3 text-center font-medium text-white hover:bg-emerald-800">{{ __('Finish your request') }}</a>
        @endif

        @can('cancel', $job)
            <section class="mt-10 border-t border-zinc-200 pt-6">
                @if ($confirmingCancel)
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4" role="alertdialog" aria-labelledby="cancel-title">
                        <p id="cancel-title" class="font-semibold text-red-900">{{ __('Cancel this job?') }}</p>
                        <p class="mt-1 text-sm text-red-900">{{ __('Pros who were sent the job will be told. You can post a new job later.') }}</p>
                        <label for="cancelReason" class="mt-3 block text-sm text-red-900">{{ __('Reason (optional)') }}</label>
                        <input id="cancelReason" type="text" wire:model="cancelReason" maxlength="300" class="mt-1 w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-base">
                        @error('cancelReason') <p class="mt-1 text-sm text-red-800">{{ $message }}</p> @enderror
                        <div class="mt-4 flex gap-3">
                            <button type="button" wire:click="cancelJob" wire:loading.attr="disabled" class="rounded-lg bg-red-700 px-4 py-3 font-medium text-white hover:bg-red-800">{{ __('Yes, cancel job') }}</button>
                            <button type="button" wire:click="keepJob" class="rounded-lg border border-zinc-300 bg-white px-4 py-3 font-medium">{{ __('Keep job') }}</button>
                        </div>
                    </div>
                @else
                    <button type="button" wire:click="confirmCancel" class="text-sm text-red-700 underline underline-offset-4">{{ __('Cancel this job') }}</button>
                @endif
            </section>
        @elseif (\App\Domain\ServiceJobs\Support\BookedJob::isOpen($job))
            <section class="mt-10 border-t border-zinc-200 pt-6" aria-label="{{ __('Finish or cancel this booking') }}">
                @error('finish') <p class="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror
                @if ($confirmingDone)
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4" role="alertdialog" aria-labelledby="done-title">
                        <p id="done-title" class="font-semibold text-emerald-950">{{ __('Is the work finished?') }}</p>
                        <p class="mt-1 text-sm text-emerald-950">{{ __('Your pro will be told. Make sure you are happy with the work and have a receipt.') }}</p>
                        <div class="mt-4 flex gap-3">
                            <button type="button" wire:click="markDone" wire:loading.attr="disabled" class="rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800">{{ __('Yes, it is done') }}</button>
                            <button type="button" wire:click="keepBooking" class="rounded-lg border border-zinc-300 bg-white px-4 py-3 font-medium">{{ __('Not yet') }}</button>
                        </div>
                    </div>
                @elseif ($confirmingBookedCancel)
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4" role="alertdialog" aria-labelledby="booked-cancel-title">
                        <p id="booked-cancel-title" class="font-semibold text-red-900">{{ __('Cancel this booking?') }}</p>
                        <p class="mt-1 text-sm text-red-900">{{ __('Your pro will be told. You can post the job again and choose someone else.') }}</p>
                        <label for="bookedCancelReason" class="mt-3 block text-sm text-red-900">{{ __('Why are you cancelling?') }}</label>
                        <input id="bookedCancelReason" type="text" wire:model="bookedCancelReason" maxlength="300" class="mt-1 w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-base">
                        @error('bookedCancelReason') <p class="mt-1 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror
                        <div class="mt-4 flex gap-3">
                            <button type="button" wire:click="cancelBooked" wire:loading.attr="disabled" class="rounded-lg bg-red-700 px-4 py-3 font-medium text-white hover:bg-red-800">{{ __('Yes, cancel booking') }}</button>
                            <button type="button" wire:click="keepBooking" class="rounded-lg border border-zinc-300 bg-white px-4 py-3 font-medium">{{ __('Keep booking') }}</button>
                        </div>
                    </div>
                @else
                    <button type="button" wire:click="confirmDone" class="w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800">{{ __('Mark as done') }}</button>
                    <button type="button" wire:click="confirmBookedCancel" class="mt-4 text-sm text-red-700 underline underline-offset-4">{{ __('Cancel this booking') }}</button>
                @endif
            </section>
        @endcan
    </section>
</main>
