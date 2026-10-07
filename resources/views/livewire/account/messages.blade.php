<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Messages') }}</h1>
        <p class="mt-2 text-sm text-zinc-600">{{ __('Chats with the pros looking at your jobs.') }}</p>

        <ul class="mt-6 space-y-3">
            @forelse ($chats as $chat)
                <li wire:key="chat-{{ $chat['job']->public_id }}-{{ $loop->index }}">
                    <a wire:navigate.hover href="{{ route('jobs.show', $chat['job']) }}#chats" class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-4 hover:border-emerald-700">
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $chat['label'] }}</span>
                            <span class="block truncate text-sm text-zinc-600">{{ $chat['job']->service->name }}@if ($chat['closed']) · {{ __('closed') }}@endif</span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1 text-xs text-zinc-500">
                            @if ($chat['last_at'])<time datetime="{{ $chat['last_at']->toIso8601String() }}">{{ $chat['last_at']->translatedFormat('j M, H:i') }}</time>@endif
                            @if ($chat['unread'] > 0)<span class="rounded-full bg-emerald-700 px-2 py-0.5 text-white"><span class="sr-only">{{ __('Unread:') }} </span>{{ $chat['unread'] }}</span>@endif
                        </span>
                    </a>
                </li>
            @empty
                <li class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-600">{{ __('Chats start once a pro is looking at your job. Ask a question or send more photos before they quote.') }}</li>
            @endforelse
        </ul>
    </section>
</main>
