<div class="mx-auto w-full max-w-7xl px-4 py-6 lg:px-8">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item :href="route('jobs.index')" wire:navigate>{{ __('Your jobs') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $job->trade->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if ($justPosted)
        <flux:callout variant="success" icon="check-circle" class="mb-6" role="status">
            <flux:callout.heading>{{ __('Your job is posted') }} ✓</flux:callout.heading>
            <flux:callout.text>{{ __("We're finding vetted pros near you. We'll email you as quotes come in.") }}</flux:callout.text>
        </flux:callout>
    @endif

    <header id="job-status" class="rounded-xl">
        <p class="text-xs font-medium uppercase tracking-widest text-zinc-500">{{ $job->trade->name }}</p>
        <flux:heading size="xl" level="1" class="mt-1">{{ $job->factTexts()[0] ?? $job->trade->name }}</flux:heading>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <flux:badge size="lg" :color="$job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Completed ? 'green' : ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Cancelled ? 'zinc' : 'blue')">{{ $job->status->customerLabel() }}</flux:badge>
            @if ($job->urgency === \App\Domain\ServiceJobs\Enums\Urgency::Urgent)<flux:badge size="lg" color="red">{{ __('Urgent') }}</flux:badge>@endif
        </div>

        @if ($stages)
            <ol class="mt-6 grid max-w-2xl grid-cols-5 gap-2 text-xs" aria-label="{{ __('Job progress') }}">
                @foreach ($stages as $stage)
                    <li class="flex flex-col gap-1.5" @if ($stage['state'] === 'current') aria-current="step" @endif>
                        <span @class(['h-1.5 w-full rounded-full', 'bg-zinc-900' => $stage['state'] !== 'upcoming', 'bg-zinc-200' => $stage['state'] === 'upcoming'])></span>
                        <span @class(['font-semibold text-zinc-900' => $stage['state'] === 'current', 'text-zinc-700' => $stage['state'] === 'done', 'text-zinc-500' => $stage['state'] === 'upcoming'])>@if ($stage['state'] === 'done')<span class="sr-only">{{ __('Done:') }} </span>@endif{{ $stage['label'] }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </header>

    @error('cancel') <flux:callout variant="danger" icon="exclamation-triangle" class="mt-4" :heading="$message" /> @enderror

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- What you can do next comes first on phones and sits in the side column on desktop. --}}
        <div class="order-1 space-y-4 lg:order-none lg:col-start-3 lg:row-start-1">
            @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Draft)
                <flux:button variant="primary" class="w-full" :href="route('booking.continue', $job)" wire:navigate>{{ __('Finish your request') }}</flux:button>
            @endif

            @can('cancel', $job)
                <flux:card class="space-y-3">
                    @if ($confirmingCancel)
                        <flux:callout variant="danger" icon="exclamation-triangle" role="alertdialog" aria-labelledby="cancel-title">
                            <flux:callout.heading id="cancel-title">{{ __('Cancel this job?') }}</flux:callout.heading>
                            <flux:callout.text>{{ __('Pros who were sent the job will be told. You can post a new job later.') }}</flux:callout.text>
                            <div class="mt-3">
                                <flux:field>
                                    <flux:label for="cancelReason">{{ __('Reason (optional)') }}</flux:label>
                                    <flux:input id="cancelReason" wire:model="cancelReason" maxlength="300" />
                                    <flux:error name="cancelReason" />
                                </flux:field>
                            </div>
                            <x-slot name="actions">
                                <flux:button variant="danger" wire:click="cancelJob" wire:loading.attr="disabled">{{ __('Yes, cancel job') }}</flux:button>
                                <flux:button wire:click="keepJob">{{ __('Keep job') }}</flux:button>
                            </x-slot>
                        </flux:callout>
                    @else
                        <flux:button variant="subtle" size="sm" class="!text-red-700" wire:click="confirmCancel">{{ __('Cancel this job') }}</flux:button>
                    @endif
                </flux:card>
            @else
                @include('livewire.shared.booked-actions', ['job' => $job, 'audience' => 'client'])
            @endcan

            @if ($bookAgain)
                <flux:button class="w-full" :href="$bookAgain" wire:navigate>{{ __('Book :trade again', ['trade' => mb_strtolower($job->trade->name)]) }}</flux:button>
            @endif
        </div>

        <div class="order-2 space-y-6 lg:order-none lg:col-span-2 lg:col-start-1 lg:row-span-3 lg:row-start-1">
            @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Open)
                <flux:callout icon="magnifying-glass" role="status">
                    <flux:callout.heading>{{ __('Finding your pros') }}</flux:callout.heading>
                    <flux:callout.text>
                        @if ($invitedCount > 0 && $quotes->isEmpty())
                            {{ __('Waiting for quotes.') }} {{ trans_choice(':count pro invited so far.|:count pros invited so far.', $invitedCount, ['count' => $invitedCount]) }}
                        @elseif ($invitedCount > 0)
                            {{ trans_choice(':count pro invited so far. Quotes will appear here.|:count pros invited so far. Quotes will appear here.', $invitedCount, ['count' => $invitedCount]) }}
                        @else
                            {{ __("We're still looking for a pro who can take this job. We'll email you as soon as quotes come in.") }}
                        @endif
                    </flux:callout.text>
                </flux:callout>
            @endif

            @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Completed)
                <flux:callout variant="success" icon="check-circle" role="status">
                    <flux:callout.text>{{ __('This job is done. Thanks for using GetSorted!') }}</flux:callout.text>
                </flux:callout>

                <div id="review-card" class="rounded-xl">
            @if ($review)
                <flux:card aria-labelledby="your-review">
                    <flux:heading id="your-review" size="lg">{{ __('Your review') }}</flux:heading>
                    <p class="mt-1 text-amber-700" aria-label="{{ trans_choice(':count star|:count stars', $review->rating) }}">{{ str_repeat('★', $review->rating).str_repeat('☆', 5 - $review->rating) }}</p>
                    @if ($review->comment)<p class="mt-2 whitespace-pre-line text-sm text-zinc-800">{{ $review->comment }}</p>@endif
                    @if ($review->reply)<p class="mt-3 border-l-2 border-zinc-200 pl-3 text-sm text-zinc-700"><span class="font-medium">{{ __('Reply from your pro') }}:</span> {{ $review->reply }}</p>@endif
                </flux:card>
            @elseif ($reviewable)
                <flux:card aria-labelledby="leave-review">
                    <flux:heading id="leave-review" size="lg">{{ __('How was the work?') }}</flux:heading>
                    <p class="text-sm text-zinc-600">{{ __('Your review helps other households choose. Your first name is shown with it.') }}</p>
                    <div class="mt-3 flex gap-1" role="radiogroup" aria-label="{{ __('Stars') }}">
                        @foreach (range(1, 5) as $star)
                            <button type="button" wire:click="setRating({{ $star }})" role="radio" aria-checked="{{ $rating === $star ? 'true' : 'false' }}" aria-label="{{ trans_choice(':count star|:count stars', $star) }}" class="text-3xl leading-none {{ $star <= $rating ? 'text-amber-600' : 'text-zinc-300' }}">★</button>
                        @endforeach
                    </div>
                    <label for="reviewComment" class="mt-4 block text-sm">{{ __('Add a comment (optional)') }}</label>
                    <textarea id="reviewComment" wire:model="reviewComment" rows="3" maxlength="1000" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 text-base"></textarea>
                    <p class="text-xs text-zinc-500">{{ __('Please leave out phone numbers and email addresses.') }}</p>
                    @error('review') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    <button type="button" wire:click="submitReview" wire:loading.attr="disabled" @disabled($rating === 0) class="mt-3 rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-50">{{ __('Send review') }}</button>
                </flux:card>
            @endif

                </div>
            @endif

            @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Cancelled)
                <flux:callout icon="x-circle" role="status">
                    <flux:callout.text>{{ __('This job was cancelled. You can post a new one any time.') }}</flux:callout.text>
                </flux:callout>
            @endif

            <div id="estimates">
                @include('livewire.account.jobs.partials.quotes')
            </div>

            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Your request') }}</flux:heading>
                <dl class="space-y-4 text-sm">

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
            </flux:card>

            @if ($job->getMedia(\App\Models\ServiceJob::PHOTO_COLLECTION)->isNotEmpty())
                <flux:card>
                    <flux:heading size="lg">{{ __('Photos') }}</flux:heading>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($job->getMedia(\App\Models\ServiceJob::PHOTO_COLLECTION) as $photo)
                            <img src="{{ $job->photoUrl($photo) }}" alt="{{ __('Job photo :number', ['number' => $loop->iteration]) }}" class="aspect-square w-full rounded-lg object-cover" loading="lazy">
                        @endforeach
                    </div>
                </flux:card>
            @endif
        </div>

        {{-- Spec 018: chat with each pro looking at the job. Invited pros stay anonymous until they reply or quote (spec 009 AC13). --}}
        @if ($chats->isNotEmpty())
            <flux:card class="order-3 lg:order-none lg:col-start-3" id="chats">
                <flux:heading size="lg">{{ $job->accepted_quote_id ? __('Chat with your pro') : __('Chat with your pros') }}</flux:heading>
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

            </flux:card>
        @endif

        @if ($timeline->isNotEmpty())
            <flux:card class="order-4 lg:order-none lg:col-start-3" aria-labelledby="timeline-title">
                <flux:heading size="lg" id="timeline-title">{{ __('Timeline') }}</flux:heading>
                <ol class="mt-3 space-y-3 border-l-2 border-zinc-200 pl-4 text-sm">
                    @foreach ($timeline as $entry)
                        <li wire:key="timeline-{{ $loop->index }}">
                            <span class="block">{{ $entry['text'] }}</span>
                            <time datetime="{{ $entry['at']->toIso8601String() }}" class="text-xs text-zinc-500">{{ $entry['at']->translatedFormat('D j M, H:i') }}</time>
                        </li>
                    @endforeach
                </ol>
            </flux:card>
        @endif
    </div>
</div>
