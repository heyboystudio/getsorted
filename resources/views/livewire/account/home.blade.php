<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <h1 class="text-3xl font-semibold tracking-tight">{{ __('Hi :name', ['name' => $firstName]) }} <span aria-hidden="true">👋</span></h1>

        <x-push-card class="mt-4" />

        @if ($attention->isNotEmpty())
            <section class="mt-6" aria-labelledby="needs-you">
                <h2 id="needs-you" class="text-sm font-semibold uppercase tracking-widest text-amber-800">{{ __('Needs you') }}</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($attention as $item)
                        <li class="relative" wire:key="need-{{ $loop->index }}-{{ $item['kind'] }}">
                            <a wire:navigate.hover href="{{ $item['url'] }}" class="block rounded-xl border border-amber-200 bg-amber-50 p-4 pr-20 hover:border-amber-400">
                                <span class="block font-medium text-amber-950">{{ $item['title'] }}</span>
                                <span class="block text-sm text-amber-900">{{ $item['detail'] }}</span>
                            </a>
                            @if ($item['draft'])
                                <button type="button" wire:click="removeDraft('{{ $item['draft'] }}')" wire:confirm="{{ __('Remove this unfinished request?') }}"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-red-700 underline underline-offset-4">{{ __('Remove') }}</button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Spec 017 AC1: describe the problem here and Siya picks it up in the booking thread. --}}
        <div class="mt-6 rounded-2xl bg-emerald-800 p-5 text-white">
            <h2 class="text-xl font-semibold">{{ __('What’s going on at home?') }}</h2>
            <p class="mt-1 text-sm text-emerald-100">{{ __('Tell Siya what’s wrong and get matched with vetted Durban pros.') }}</p>
            <form wire:submit="describe" class="mt-4 flex gap-2">
                <label for="problem" class="sr-only">{{ __('Describe the problem') }}</label>
                <input id="problem" type="text" wire:model="problem" maxlength="1000" autocomplete="off" placeholder="{{ __('e.g. my DB board keeps tripping') }}"
                    class="block w-full rounded-full border-0 bg-white px-4 py-3 text-zinc-900 outline-none focus:ring-2 focus:ring-emerald-300">
                <button type="submit" class="rounded-full bg-white px-5 py-3 font-medium text-emerald-900" aria-label="{{ __('Ask Siya') }}">→</button>
            </form>
            @error('problem') <p class="mt-2 text-sm text-red-100" role="alert">{{ $message }}</p> @enderror
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ([__('Blocked drain'), __('No power'), __('No hot water'), __('Leaking geyser')] as $quick)
                    <button type="button" wire:click="describe(@js($quick))" class="rounded-full border border-emerald-500 px-3 py-1.5 text-sm hover:bg-emerald-700">{{ $quick }}</button>
                @endforeach
            </div>
        </div>

        <div class="mt-8 flex items-center justify-between">
            <h2 class="text-lg font-semibold">{{ __('Active jobs') }}</h2>
            <div class="flex items-center gap-4">
                <a href="{{ route('jobs.index') }}" wire:navigate class="text-sm text-emerald-800 underline underline-offset-4">{{ __('All jobs') }}</a>
                <a href="{{ route('book') }}" wire:navigate class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('Book a pro') }}</a>
            </div>
        </div>
        @forelse ($activeJobs as $job)
            <a wire:navigate.hover href="{{ route('jobs.show', $job) }}" wire:key="active-{{ $job->public_id }}" class="mt-3 flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4 hover:border-emerald-700">
                @if ($job->acceptedQuote?->pro)
                    @if ($job->acceptedQuote->hasProPhoto())
                        <img src="{{ $job->acceptedQuote->proPhotoUrl() }}" alt="" class="size-12 shrink-0 rounded-full object-cover">
                    @else
                        <span aria-hidden="true" class="flex size-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-semibold text-emerald-900">{{ mb_strtoupper(mb_substr((string) $job->acceptedQuote->pro->business_name, 0, 1)) }}</span>
                    @endif
                @endif
                <span class="min-w-0 flex-1">
                    <span class="flex items-start justify-between gap-3">
                        <span class="font-medium">{{ \App\Domain\ServiceJobs\Support\JobLabel::for($job) }}</span>
                        <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs">{{ $job->status->customerLabel() }}</span>
                    </span>
                    <span class="mt-1 block truncate text-sm text-zinc-600">
                        @if ($job->acceptedQuote?->pro?->business_name){{ $job->acceptedQuote->pro->business_name }} · @endif{{ \App\Domain\ServiceJobs\Support\JobLabel::area($job) }}@if ($job->scheduled_for && $job->accepted_quote_id) · {{ $job->scheduled_for->translatedFormat('D j M') }}@endif
                    </span>
                </span>
            </a>
        @empty
            <p class="mt-3 rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-600">{{ __('Jobs you have booked will appear here.') }}</p>
        @endforelse

        @if ($activity->isNotEmpty())
            <h2 class="mt-8 text-lg font-semibold">{{ __('Recent activity') }}</h2>
            <ul class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white text-sm">
                @foreach ($activity as $entry)
                    <li class="flex items-start justify-between gap-3 p-4" wire:key="activity-{{ $loop->index }}">
                        <a wire:navigate.hover href="{{ route('jobs.show', $entry['job']) }}" class="hover:underline"><span class="block">{{ $entry['text'] }}</span><span class="block text-zinc-600">{{ $entry['service'] }}</span></a>
                        <time datetime="{{ $entry['at']->toIso8601String() }}" class="shrink-0 text-xs text-zinc-500">{{ $entry['at']->translatedFormat('j M') }}</time>
                    </li>
                @endforeach
            </ul>
        @endif

        <a wire:navigate.hover href="{{ route('properties.index') }}" class="mt-8 flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 hover:border-emerald-700">
            <span class="font-medium">{{ __('Saved properties') }}</span>
            <span aria-hidden="true">→</span>
        </a>
        @if ($hasWaitlistRequests)
            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4">
                <p class="font-medium">{{ __('Waitlist requests') }}</p>
                <p class="mt-1 text-sm text-zinc-600">{{ __('You can remove all requests linked to your verified phone number.') }}</p>
                <button type="button" wire:click="removeWaitlistRequests" wire:confirm="{{ __('Remove your waitlist requests?') }}" class="mt-3 text-sm text-red-700 underline">{{ __('Remove my waitlist requests') }}</button>
            </div>
        @endif
        @if ($waitlistRemoved) <p class="mt-3 text-sm text-emerald-800" role="status">{{ __('Your waitlist requests were removed.') }}</p> @endif
    </section>
</main>
