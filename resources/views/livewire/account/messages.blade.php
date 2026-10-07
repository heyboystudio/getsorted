<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl" wire:poll.10s.visible>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Messages') }}</h1>

        <ul class="mt-6 divide-y divide-zinc-100 overflow-hidden rounded-2xl border border-zinc-200 bg-white">
            @forelse ($rows as $row)
                @php($last = $row['last'])
                <li wire:key="conv-{{ $row['conversation']->public_id }}">
                    <a wire:navigate href="{{ $row['url'] }}" class="flex items-center gap-3 px-4 py-3 hover:bg-zinc-50">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-semibold text-emerald-900" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) preg_replace('/^(the|pro)\s+/i', '', $row['label'])) ?: '?', 0, 1)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-3">
                                <span @class(['truncate', 'font-semibold' => $row['unread'] > 0, 'font-medium' => $row['unread'] === 0])>{{ $row['label'] }}</span>
                                @if ($last)<time class="shrink-0 text-xs text-zinc-500" datetime="{{ $last->created_at->toIso8601String() }}">{{ $last->created_at->isToday() ? $last->created_at->translatedFormat('H:i') : $last->created_at->translatedFormat('j M') }}</time>@endif
                            </span>
                            <span class="block truncate text-xs text-zinc-500">{{ $row['job']->trade->name }} · {{ $row['job']->factTexts()[0] ?? $row['job']->area_label }}</span>
                            <span @class(['block truncate text-sm', 'text-zinc-900' => $row['unread'] > 0, 'text-zinc-600' => $row['unread'] === 0])>
                                @if ($last)
                                    {{ $last->deleted_at !== null ? __('Message deleted') : ($last->body ?? '📷 '.__('Photo')) }}
                                @endif
                            </span>
                        </span>
                        @if ($row['unread'] > 0)
                            <span class="rounded-full bg-emerald-700 px-2 py-0.5 text-xs font-medium text-white" aria-label="{{ trans_choice(':count unread message|:count unread messages', $row['unread'], ['count' => $row['unread']]) }}">{{ $row['unread'] }}</span>
                        @endif
                    </a>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-zinc-600">{{ __('No conversations yet. When you message a pro or a client, it shows up here.') }}</li>
            @endforelse
        </ul>
    </section>
</main>
