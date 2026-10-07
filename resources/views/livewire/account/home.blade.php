<main class="flex min-h-dvh items-start justify-center px-5 py-12">
    <section class="w-full max-w-xl">
        <div class="mb-10 flex items-center justify-between">
            <a wire:navigate.hover href="{{ route('account.home') }}" class="text-2xl font-semibold tracking-tight">{{ __('Sortd') }}<span aria-hidden="true" class="text-emerald-700">.</span></a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Log out') }}</button>
            </form>
        </div>

        <h1 class="text-3xl font-semibold tracking-tight">{{ __('Hi :name', ['name' => $firstName]) }} <span aria-hidden="true">👋</span></h1>

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
            <h2 class="text-lg font-semibold">{{ __('Your jobs') }}</h2>
            <a href="{{ route('book') }}" wire:navigate class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('Book a pro') }}</a>
        </div>
        @forelse ($jobs as $job)
            <div class="relative" wire:key="row-{{ $job->public_id }}">
            <a href="{{ $job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Draft ? route('booking.continue', $job) : route('jobs.show', $job) }}" wire:key="{{ $job->public_id }}"
               class="mt-3 block rounded-xl border border-zinc-200 bg-white p-4 hover:border-emerald-700">
                <span class="flex items-start justify-between gap-3">
                    <span class="font-medium">{{ $job->trade->name }}</span>
                    <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs">{{ $job->status->customerLabel() }}</span>
                </span>
                <span class="mt-1 block text-sm text-zinc-600">
                    {{ $job->property?->area_label ?? __('No address yet') }}@if ($job->posted_at) · {{ __('Posted :date', ['date' => $job->posted_at->translatedFormat('j M')]) }}@endif
                </span>
            </a>
            @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::Draft)
                <button type="button" wire:click="removeDraft('{{ $job->public_id }}')" wire:confirm="{{ __('Remove this unfinished request?') }}"
                    class="absolute bottom-4 right-4 text-xs text-red-700 underline underline-offset-4">{{ __('Remove') }}</button>
            @endif
            </div>
        @empty
            <p class="mt-3 rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-600">{{ __('Your jobs will appear here.') }}</p>
        @endforelse

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
