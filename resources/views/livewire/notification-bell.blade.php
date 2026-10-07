<a href="{{ route('notifications') }}" wire:navigate wire:poll.15s.visible
   class="relative inline-flex size-10 items-center justify-center rounded-full text-zinc-700 hover:bg-zinc-100"
   aria-label="{{ $unread > 0 ? trans_choice('Notifications, :count unread|Notifications, :count unread', $unread, ['count' => $unread]) : __('Notifications') }}">
    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21a2 2 0 0 0 4 0"/></svg>
    @if ($unread > 0)
        <span class="absolute -right-0.5 -top-0.5 min-w-5 rounded-full bg-emerald-700 px-1.5 text-center text-xs font-medium leading-5 text-white" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>
    @endif
</a>
