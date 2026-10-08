<x-workspace.account-shell current="account.profile">
    <flux:heading size="xl" level="1">{{ __('Profile') }}</flux:heading>

    @if ($emailChanged)
        <flux:callout variant="success" icon="check-circle" class="mt-4" role="status">
            <flux:callout.text>{{ __('Your email address is updated.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:card class="mt-6">
        <flux:heading size="lg">{{ __('Name') }}</flux:heading>
        <form wire:submit="saveName" class="mt-4 space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="firstName" :label="__('First name')" autocomplete="given-name" />
                <flux:input wire:model="lastName" :label="__('Surname')" autocomplete="family-name" />
            </div>
            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Save name') }}</flux:button>
                @if ($saved) <span class="text-sm text-green-700" role="status">{{ __('Saved.') }}</span> @endif
            </div>
        </form>
    </flux:card>

    <flux:card class="mt-6">
        <flux:heading size="lg">{{ __('Email') }}</flux:heading>
        <p class="mt-1 text-sm text-zinc-700">{{ $user->email }}</p>
        @if ($user->pending_email)
            <flux:callout variant="warning" icon="clock" class="mt-3" role="status">
                <flux:callout.text>{{ __('We sent a link to :email. Your address changes once you open it.', ['email' => $user->pending_email]) }}</flux:callout.text>
                <x-slot name="actions"><flux:button size="sm" wire:click="cancelEmailChange">{{ __('Cancel') }}</flux:button></x-slot>
            </flux:callout>
        @endif
        <form wire:submit="changeEmail" class="mt-4 space-y-3" novalidate>
            <flux:input type="email" wire:model="newEmail" :label="__('New email address')" autocomplete="email" />
            <flux:button type="submit">{{ __('Send confirmation link') }}</flux:button>
            @if ($emailSent) <p class="text-sm text-green-700" role="status">{{ __('Check your new inbox for the link.') }}</p> @endif
        </form>
    </flux:card>

    <flux:card class="mt-6">
        <flux:heading size="lg">{{ __('Mobile number') }}</flux:heading>
        <p class="mt-1 text-sm text-zinc-700">{{ $user->phone_e164 ?? __('Not added yet') }}</p>
        <flux:button class="mt-3" size="sm" :href="route('verification.phone')" wire:navigate>{{ __('Change mobile number') }}</flux:button>
        <p class="mt-2 text-xs text-zinc-500">{{ __('Pros use this number to reach you once you choose them. We do not send you SMS or WhatsApp messages.') }}</p>
    </flux:card>
</x-workspace.account-shell>
