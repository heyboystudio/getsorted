<div class="mx-auto w-full max-w-3xl px-4 py-6 lg:px-8" wire:poll.15s.visible>
    <div class="flex items-end justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Notifications') }}</flux:heading>
        @if ($unread > 0)
            <flux:button size="sm" variant="subtle" wire:click="markAllRead">{{ __('Mark all as read') }}</flux:button>
        @endif
    </div>

    <div class="mt-6 space-y-3">
        @forelse ($notifications as $notification)
            <button type="button" wire:key="n-{{ $notification->id }}" wire:click="open(@js($notification->id))"
                @class(['block w-full rounded-xl border p-4 text-left transition hover:border-zinc-400', 'border-zinc-300 bg-white shadow-xs' => $notification->read_at === null, 'border-zinc-200 bg-zinc-50' => $notification->read_at !== null])>
                <span class="flex items-start justify-between gap-3">
                    <span class="flex items-center gap-2 font-medium">@if ($notification->read_at === null)<span class="size-2 shrink-0 rounded-full bg-zinc-900" aria-label="{{ __('Unread') }}"></span>@endif{{ $notification->data['title'] ?? '' }}</span>
                    <span class="shrink-0 text-xs text-zinc-500">{{ $notification->created_at->diffForHumans() }}</span>
                </span>
                <span class="mt-1 block text-sm text-zinc-600">{{ $notification->data['body'] ?? '' }}</span>
            </button>
        @empty
            <p class="rounded-xl border border-zinc-200 bg-white p-4 text-sm text-zinc-600">{{ __('Nothing yet. We will tell you here when something happens with your jobs.') }}</p>
        @endforelse
    </div>
</div>
