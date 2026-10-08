<x-workspace.account-shell current="account.notifications">
    <flux:heading size="xl" level="1">{{ __('Notifications') }}</flux:heading>
    <p class="mt-2 text-sm text-zinc-600">{{ __('We tell you about your jobs by email and by pop-up. Choose what you want to hear about. Security messages always arrive.') }}</p>

    <x-push-switch class="mt-6" />

    <form wire:submit="save" class="mt-6">
        <flux:card>
            <flux:heading size="lg">{{ __('What to send') }}</flux:heading>
            <div class="mt-4 space-y-4">
                @foreach ($labels as $key => $label)
                    <flux:checkbox wire:model="groups.{{ $key }}" :label="$label" />
                @endforeach
            </div>
            <div class="mt-6 flex items-center gap-3">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                @if ($saved) <span class="text-sm text-green-700" role="status">{{ __('Saved.') }}</span> @endif
            </div>
        </flux:card>
    </form>
</x-workspace.account-shell>
