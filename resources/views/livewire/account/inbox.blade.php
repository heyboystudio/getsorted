<main class="flex min-h-dvh items-start justify-center px-5 py-12">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ $home }}" class="mb-10 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Back') }}</a>

        <div class="flex items-end justify-between gap-4">
            <h1 class="text-3xl font-semibold tracking-tight">{{ __('Notifications') }}</h1>
            @if ($unread > 0)
                <button type="button" wire:click="markAllRead" class="text-sm text-emerald-800 underline underline-offset-4">{{ __('Mark all as read') }}</button>
            @endif
        </div>

        @forelse ($notifications as $notification)
            <button type="button" wire:key="n-{{ $notification->id }}" wire:click="open(@js($notification->id))"
                @class(['mt-3 block w-full rounded-xl border p-4 text-left', 'border-emerald-300 bg-emerald-50' => $notification->read_at === null, 'border-zinc-200 bg-white' => $notification->read_at !== null])>
                <span class="flex items-start justify-between gap-3">
                    <span class="font-medium">{{ $notification->data['title'] ?? '' }}</span>
                    <span class="shrink-0 text-xs text-zinc-500">{{ $notification->created_at->diffForHumans() }}</span>
                </span>
                <span class="mt-1 block text-sm text-zinc-600">{{ $notification->data['body'] ?? '' }}</span>
            </button>
        @empty
            <p class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 text-sm text-zinc-600">{{ __('Nothing yet. We will tell you here when something happens with your jobs.') }}</p>
        @endforelse
    </section>
</main>
