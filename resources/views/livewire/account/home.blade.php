<div class="mx-auto w-full max-w-7xl px-4 py-8 lg:px-8">
    <h1 class="text-3xl font-semibold tracking-tight text-zinc-900">{{ __('Hi :name', ['name' => $firstName]) }} <span aria-hidden="true">👋</span></h1>
    <p class="mt-1 text-zinc-600">{{ __('Here is what is happening with your jobs.') }}</p>

    {{-- Spec 017 AC1: describe the problem here and Siya picks it up in the booking thread. Booking starts here, with Siya (spec 028). --}}
    <section class="ws-siya mt-8 rounded-2xl px-6 py-10 sm:px-12 sm:py-14" aria-labelledby="siya-title">
        <h2 id="siya-title" class="text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl">{{ __('What’s going on at home?') }}</h2>
        <p class="mt-2 max-w-2xl text-base text-zinc-600 sm:text-lg">{{ __('Tell Siya what’s wrong and get matched with vetted Durban pros.') }}</p>
        <form wire:submit="describe" class="mt-8 flex flex-col gap-3 sm:flex-row">
            <label for="problem" class="sr-only">{{ __('Describe the problem') }}</label>
            <input id="problem" type="text" wire:model="problem" maxlength="1000" autocomplete="off" autofocus placeholder="{{ __('e.g. my DB board keeps tripping') }}"
                class="block h-16 w-full rounded-xl border border-zinc-300 bg-zinc-50 px-6 text-lg text-zinc-900 outline-none placeholder:text-zinc-400 focus:border-[#c6fd50] focus:ring-4 focus:ring-[#c6fd50]/25">
            <button type="submit" aria-label="{{ __('Ask Siya') }}" class="inline-flex h-16 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#c6fd50] px-8 text-lg font-semibold text-[#131311] hover:bg-[#d6ff7d]">
                {{ __('Ask Siya') }} <flux:icon name="arrow-right" class="size-5" />
            </button>
        </form>
        @error('problem') <p class="mt-3 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
    </section>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-workspace.stat icon="wrench-screwdriver" :label="__('Active jobs')" :value="$stats['active']" :note="trans_choice(':count job in progress|:count jobs in progress', $stats['active'])" :href="route('jobs.index')" :action="__('View jobs')" />
        <x-workspace.stat icon="clock" :label="__('Waiting for estimates')" :value="$stats['open']" :note="__('Posted, pros are replying')" :href="route('jobs.index')" :action="__('View jobs')" />
        <x-workspace.stat icon="calendar-days" :label="__('Booked')" :value="$stats['booked']" :note="__('A pro is coming')" :href="route('jobs.index')" :action="__('View jobs')" />
        <x-workspace.stat icon="check-badge" :label="__('Done')" :value="$stats['done']" :note="__('Finished jobs')" :href="route('jobs.index', ['tab' => 'done'])" :action="__('See finished jobs')" />
    </div>

    @if ($attention->isNotEmpty())
        <section class="mt-8" aria-labelledby="needs-you">
            <h2 id="needs-you" class="text-sm font-semibold uppercase tracking-widest text-amber-800">{{ __('Needs you') }}</h2>
            <ul class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($attention as $item)
                    <li class="relative" wire:key="need-{{ $loop->index }}-{{ $item['kind'] }}">
                        <a wire:navigate.hover href="{{ $item['url'] }}" class="block h-full rounded-xl border border-amber-200 bg-amber-50 p-4 pr-20 hover:border-amber-400">
                            <span class="block font-medium text-amber-950">{{ $item['title'] }}</span>
                            <span class="block text-sm text-amber-900">{{ $item['detail'] }}</span>
                        </a>
                        @if ($item['draft'])
                            <button type="button" wire:click="removeDraft('{{ $item['draft'] }}')" wire:confirm="{{ __('Remove this unfinished request?') }}"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-red-600 underline underline-offset-4">{{ __('Remove') }}</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-3 lg:items-start">
        <section class="space-y-3 lg:col-span-2" aria-labelledby="active-jobs">
            <div class="flex items-center justify-between">
                <flux:heading id="active-jobs" size="lg">{{ __('Active jobs') }}</flux:heading>
                <a wire:navigate href="{{ route('jobs.index') }}" class="ws-link text-sm hover:underline">{{ __('All jobs') }}</a>
            </div>
            @forelse ($activeJobs as $job)
                <x-workspace.job-row :job="$job" :href="route('jobs.show', $job)" wire:key="active-{{ $job->public_id }}">
                    @if ($job->acceptedQuote?->pro?->business_name){{ $job->acceptedQuote->pro->business_name }} · @endif{{ \App\Domain\ServiceJobs\Support\JobLabel::area($job) }}@if ($job->scheduled_for && $job->accepted_quote_id) · {{ $job->scheduled_for->translatedFormat('D j M') }}@endif
                </x-workspace.job-row>
            @empty
                <p class="rounded-xl border border-dashed border-zinc-300 p-8 text-center text-zinc-600">{{ __('Jobs you have booked will appear here.') }}</p>
            @endforelse
        </section>

        <aside class="space-y-6">
            <x-push-card />

            @if ($activity->isNotEmpty())
                <flux:card>
                    <flux:heading size="lg">{{ __('Recent activity') }}</flux:heading>
                    <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                        @foreach ($activity as $entry)
                            <li class="flex items-start justify-between gap-3 py-3" wire:key="activity-{{ $loop->index }}">
                                <a wire:navigate.hover href="{{ route('jobs.show', $entry['job']) }}" class="hover:underline"><span class="block">{{ $entry['text'] }}</span><span class="block text-zinc-600">{{ $entry['service'] }}</span></a>
                                <time datetime="{{ $entry['at']->toIso8601String() }}" class="shrink-0 text-xs text-zinc-500">{{ $entry['at']->translatedFormat('j M') }}</time>
                            </li>
                        @endforeach
                    </ul>
                </flux:card>
            @endif

            <a wire:navigate.hover href="{{ route('properties.index') }}" class="flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 hover:border-zinc-400">
                <span class="font-medium">{{ __('Saved properties') }}</span>
                <flux:icon name="arrow-right" class="size-4 text-zinc-500" />
            </a>

            @if ($hasWaitlistRequests)
                <flux:card>
                    <p class="font-medium">{{ __('Waitlist requests') }}</p>
                    <p class="mt-1 text-sm text-zinc-600">{{ __('You can remove all requests linked to your verified phone number.') }}</p>
                    <button type="button" wire:click="removeWaitlistRequests" wire:confirm="{{ __('Remove your waitlist requests?') }}" class="mt-3 text-sm text-red-600 underline">{{ __('Remove my waitlist requests') }}</button>
                </flux:card>
            @endif
            @if ($waitlistRemoved) <p class="text-sm text-zinc-700" role="status">{{ __('Your waitlist requests were removed.') }}</p> @endif
        </aside>
    </div>
</div>
