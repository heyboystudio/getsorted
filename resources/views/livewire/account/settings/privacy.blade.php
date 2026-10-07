<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('account.settings') }}" class="mb-6 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Account') }}</a>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Privacy and data') }}</h1>
        <p class="mt-2 text-sm text-zinc-600">{{ __('You can ask for a copy of your personal data, or for your account to be deleted. Our team handles each request by hand, usually within 30 days. Records we must keep by law, such as payment records, are kept.') }}</p>

        <div class="mt-6 space-y-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                <p class="font-medium">{{ __('Download my data') }}</p>
                @if (in_array('download', $open, true))
                    <p class="mt-1 text-sm text-emerald-800" role="status">{{ __('Requested. We will be in touch on your email.') }}</p>
                @else
                    <button type="button" wire:click="request('download')" class="mt-2 text-sm text-emerald-800 underline underline-offset-4">{{ __('Ask for a copy') }}</button>
                @endif
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                <p class="font-medium">{{ __('Delete my account') }}</p>
                @if (in_array('deletion', $open, true))
                    <p class="mt-1 text-sm text-emerald-800" role="status">{{ __('Requested. We will be in touch before anything is deleted.') }}</p>
                @else
                    <button type="button" wire:click="request('deletion')" wire:confirm="{{ __('Ask us to delete your account? Your jobs and saved properties will be removed once we process this.') }}" class="mt-2 text-sm text-red-700 underline underline-offset-4">{{ __('Ask us to delete my account') }}</button>
                @endif
            </div>

            @if ($hasWaitlistRequests)
                <div class="rounded-xl border border-zinc-200 bg-white p-4">
                    <p class="font-medium">{{ __('Waitlist requests') }}</p>
                    <p class="mt-1 text-sm text-zinc-600">{{ __('You can remove all requests linked to your verified phone number.') }}</p>
                    <button type="button" wire:click="removeWaitlistRequests" wire:confirm="{{ __('Remove your waitlist requests?') }}" class="mt-2 text-sm text-red-700 underline underline-offset-4">{{ __('Remove my waitlist requests') }}</button>
                </div>
            @endif
            @if ($waitlistRemoved) <p class="text-sm text-emerald-800" role="status">{{ __('Your waitlist requests were removed.') }}</p> @endif
        </div>

        <p class="mt-6 text-sm"><a href="{{ route('privacy') }}" class="text-emerald-800 underline underline-offset-4">{{ __('Read our privacy notice') }}</a></p>
    </section>
</main>
