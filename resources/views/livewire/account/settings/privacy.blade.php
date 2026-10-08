<x-workspace.account-shell current="account.privacy">
    <flux:heading size="xl" level="1">{{ __('Privacy and data') }}</flux:heading>
    <p class="mt-2 text-sm text-zinc-600">{{ __('You can ask for a copy of your personal data, or for your account to be deleted. Our team handles each request by hand, usually within 30 days. Records we must keep by law, such as payment records, are kept.') }}</p>

    <div class="mt-6 space-y-4">
        <flux:card>
            <p class="font-medium">{{ __('Download my data') }}</p>
            @if (in_array('download', $open, true))
                <p class="mt-1 text-sm text-green-700" role="status">{{ __('Requested. We will be in touch on your email.') }}</p>
            @else
                <flux:button class="mt-3" size="sm" wire:click="request('download')">{{ __('Ask for a copy') }}</flux:button>
            @endif
        </flux:card>

        <flux:card>
            <p class="font-medium">{{ __('Delete my account') }}</p>
            @if (in_array('deletion', $open, true))
                <p class="mt-1 text-sm text-green-700" role="status">{{ __('Requested. We will be in touch before anything is deleted.') }}</p>
            @else
                <flux:button class="mt-3" size="sm" variant="danger" wire:click="request('deletion')" wire:confirm="{{ __('Ask us to delete your account? Your jobs and saved properties will be removed once we process this.') }}">{{ __('Ask us to delete my account') }}</flux:button>
            @endif
        </flux:card>

        @if ($hasWaitlistRequests)
            <flux:card>
                <p class="font-medium">{{ __('Waitlist requests') }}</p>
                <p class="mt-1 text-sm text-zinc-600">{{ __('You can remove all requests linked to your verified phone number.') }}</p>
                <flux:button class="mt-3" size="sm" wire:click="removeWaitlistRequests" wire:confirm="{{ __('Remove your waitlist requests?') }}">{{ __('Remove my waitlist requests') }}</flux:button>
            </flux:card>
        @endif
        @if ($waitlistRemoved) <p class="text-sm text-green-700" role="status">{{ __('Your waitlist requests were removed.') }}</p> @endif
    </div>

    <p class="mt-6 text-sm"><flux:link :href="route('privacy')">{{ __('Read our privacy notice') }}</flux:link></p>
</x-workspace.account-shell>
