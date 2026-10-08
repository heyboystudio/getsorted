<x-workspace.account-shell current="properties.index">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Saved properties') }}</flux:heading>
        @if ($properties->count() < $limit)
            <flux:button variant="primary" :href="route('properties.create')" wire:navigate>{{ __('Add property') }}</flux:button>
        @endif
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($properties as $property)
            <flux:card wire:key="{{ $property->public_id }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="font-medium">{{ $property->label }}</h2>
                        <p class="mt-1 break-words text-zinc-600">{{ $property->street_address }}</p>
                        <p class="text-zinc-600">{{ $property->area_label }}@if ($property->postal_code), {{ $property->postal_code }}@endif</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <flux:button size="sm" :href="route('properties.edit', $property)" wire:navigate>{{ __('Edit') }}</flux:button>
                        <flux:button size="sm" variant="subtle" class="!text-red-700"
                            wire:click="delete('{{ $property->public_id }}')"
                            wire:confirm="{{ __('Delete :label? Past jobs keep the address.', ['label' => $property->label]) }}">{{ __('Delete') }}</flux:button>
                    </div>
                </div>
            </flux:card>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center">
                <p class="font-medium">{{ __('No saved properties yet') }}</p>
                <p class="mt-1 text-sm text-zinc-600">{{ __('Save your address once and use it for every job.') }}</p>
            </div>
        @endforelse
    </div>
</x-workspace.account-shell>
