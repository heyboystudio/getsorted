{{-- Spec 018: one chat between a job's customer and a pro. All text is escaped; nothing is rendered as HTML. --}}
@php
    $R = \App\Support\Rand::class;
    $Customer = \App\Domain\ServiceJobs\Enums\MessageSender::Customer;
    $lastMine = $items->filter(fn (array $item): bool => $item['type'] === 'message' && $item['mine'] && $item['message']->deleted_at === null)->last();
    $lastMineId = $lastMine['message']->id ?? null;
    $previous = null;
@endphp
<section
    id="chat"
    class="mt-4 flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm"
    wire:poll.4s.visible
    x-data="{
        pending: '',
        stuck: true,
        unseen: 0,
        lightbox: null,
        count: {{ $items->count() }},
        el() { return this.$refs.thread },
        toBottom(smooth = false) { this.el().scrollTo({ top: this.el().scrollHeight, behavior: smooth ? 'smooth' : 'auto' }); this.stuck = true; this.unseen = 0 },
        onScroll() { this.stuck = this.el().scrollHeight - this.el().scrollTop - this.el().clientHeight < 80; if (this.stuck) this.unseen = 0 },
        grow(t) { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 128) + 'px' },
        fill(text) { $wire.message = text; this.$nextTick(() => { this.$refs.box.focus(); this.grow(this.$refs.box) }) },
    }"
    x-init="$nextTick(() => toBottom())"
    x-on:keydown.escape.window="lightbox = null"
>
    <header class="flex items-center gap-3 border-b border-zinc-100 bg-zinc-50/60 px-4 py-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-base font-semibold text-emerald-900" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) preg_replace('/^(the|pro)\s+/i', '', $title)) ?: '?', 0, 1)) }}</span>
        <div class="min-w-0 flex-1">
            <h3 class="truncate font-semibold leading-tight">{{ __('Chat with :name', ['name' => $title]) }}</h3>
            <p class="flex items-center gap-1.5 text-xs text-zinc-500">
                @if ($otherOnline)
                    <span class="size-2 rounded-full bg-emerald-500" aria-hidden="true"></span>{{ __('Looking at this chat now') }}
                @elseif ($otherReadAt)
                    {{ __('Last seen :time', ['time' => $otherReadAt->diffForHumans()]) }}
                @else
                    {{ __('Replies appear here') }}
                @endif
            </p>
        </div>
    </header>

    @if ($masked)
        <p class="flex items-start gap-2 bg-amber-50 px-4 py-2 text-xs text-amber-950">
            <span aria-hidden="true">🔒</span>
            <span>{{ __('Keep chats and payments on Sortd. Contact details are shared once you accept a quote.') }}</span>
        </p>
    @endif

    <div class="relative">
        <ol x-ref="thread" x-on:scroll.passive="onScroll()" class="h-[min(60vh,30rem)] overflow-y-auto bg-stone-50 px-3 py-4 sm:px-4" aria-live="polite" aria-label="{{ __('Messages') }}"
            x-effect="if ({{ $items->count() }} !== count) { const added = {{ $items->count() }} - count; count = {{ $items->count() }}; $nextTick(() => stuck ? toBottom(true) : unseen += Math.max(added, 1)) }">
            @forelse ($items as $item)
                @php
                    $at = $item['at'];
                    $newDay = $previous === null || ! $previous['at']->isSameDay($at);
                    $sameGroup = $previous !== null && ! $newDay && $previous['type'] === 'message' && $item['type'] === 'message'
                        && $previous['mine'] === $item['mine'] && $previous['at']->diffInMinutes($at, true) < 5;
                    $next = $items->get($loop->index + 1);
                    $endsGroup = $next === null || ! $next['at']->isSameDay($at) || $next['type'] !== 'message' || $item['type'] !== 'message'
                        || $next['mine'] !== $item['mine'] || $at->diffInMinutes($next['at'], true) >= 5;
                @endphp

                @if ($newDay)
                    <li wire:key="day-{{ $at->format('Ymd') }}" class="my-3 flex justify-center first:mt-0">
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-zinc-500 shadow-sm ring-1 ring-zinc-200">
                            {{ $at->isToday() ? __('Today') : ($at->isYesterday() ? __('Yesterday') : $at->translatedFormat('D j M')) }}
                        </span>
                    </li>
                @endif

                @if ($item['type'] === 'quote')
                    @php($quote = $item['quote'])
                    <li wire:key="quote-{{ $quote->public_id }}" class="my-3 flex justify-center">
                        <div class="w-full max-w-sm rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm shadow-sm">
                            <p class="flex items-center gap-2 font-medium text-emerald-900"><span aria-hidden="true">🧾</span>{{ $quote->version > 1 ? __('Revised estimate') : __('Estimate') }} · {{ $R::format($quote->total_cents) }}</p>
                            <p class="mt-0.5 text-xs text-zinc-500">{{ \App\Livewire\Jobs\Chat::quoteStatus($quote) }} · {{ $quote->submitted_at?->translatedFormat('D j M, H:i') }}</p>
                            @if ($side === $Customer)
                                <p class="mt-1 text-xs text-zinc-600">{{ __('See the full estimate above to compare and accept.') }}</p>
                            @endif
                        </div>
                    </li>
                @else
                    @php($message = $item['message'])
                    <li wire:key="msg-{{ $message->public_id }}" @class(['flex flex-col', 'items-end' => $item['mine'], 'items-start' => ! $item['mine'], 'mt-1' => $sameGroup, 'mt-3' => ! $sameGroup && ! $newDay])>
                        <div @class([
                            'max-w-[85%] px-3.5 py-2 text-[15px] leading-snug shadow-sm sm:max-w-[75%]',
                            'rounded-2xl',
                            'rounded-br-md' => $item['mine'] && $endsGroup,
                            'rounded-bl-md' => ! $item['mine'] && $endsGroup,
                            'bg-emerald-700 text-white' => $item['mine'] && $message->deleted_at === null,
                            'bg-white text-zinc-900 ring-1 ring-zinc-200' => ! $item['mine'] && $message->deleted_at === null,
                            'bg-zinc-100 italic text-zinc-500' => $message->deleted_at !== null,
                        ])>
                            @if ($message->deleted_at !== null)
                                {{ __('Message deleted') }}
                            @else
                                @if ($item['photos'] !== [])
                                    <div @class(['grid gap-1.5', 'grid-cols-1' => count($item['photos']) === 1, 'grid-cols-2' => count($item['photos']) > 1, 'mb-1.5' => $message->body !== null])>
                                        @foreach ($item['photos'] as $uuid => $url)
                                            <button type="button" wire:key="photo-{{ $uuid }}" x-on:click="lightbox = @js($url)" class="block overflow-hidden rounded-xl" aria-label="{{ __('Open photo :number', ['number' => $loop->iteration]) }}">
                                                <img src="{{ $url }}" alt="{{ __('Photo :number', ['number' => $loop->iteration]) }}" class="{{ count($item['photos']) === 1 ? 'max-h-64 w-full' : 'aspect-square w-full' }} object-cover" loading="lazy">
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($message->body !== null)<p class="whitespace-pre-line break-words">{{ $message->body }}</p>@endif
                            @endif
                        </div>

                        @if ($endsGroup || $reporting === $message->public_id)
                            <p class="mt-1 flex items-center gap-2 px-1 text-[11px] text-zinc-500">
                                <time datetime="{{ $at->toIso8601String() }}" title="{{ $at->translatedFormat('D j M Y, H:i') }}">{{ $at->translatedFormat('H:i') }}</time>
                                @if ($item['mine'] && $message->id === $lastMineId)
                                    @if ($otherReadAt && $otherReadAt->gte($message->created_at))
                                        <span class="font-medium text-emerald-700">✓✓ {{ __('Seen') }}</span>
                                    @else
                                        <span>✓ {{ __('Sent') }}</span>
                                    @endif
                                @endif
                                @if ($message->deleted_at === null && ! $item['mine'] && $message->reported_at === null)
                                    <button type="button" wire:click="startReport(@js($message->public_id))" class="underline decoration-zinc-300 underline-offset-2 hover:text-zinc-800">{{ __('Report') }}</button>
                                @elseif ($message->reported_at !== null && ! $item['mine'])
                                    <span>{{ __('Reported') }}</span>
                                @endif
                            </p>
                        @endif

                        @if ($reporting === $message->public_id)
                            <div class="mt-2 w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-3 text-sm shadow-sm">
                                <p class="font-medium">{{ __('Why are you reporting this?') }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($reasons as $reason)
                                        <button type="button" wire:click="report(@js($reason->value))" class="rounded-full border border-zinc-300 px-3 py-1 hover:bg-zinc-50">{{ $reason->label() }}</button>
                                    @endforeach
                                    <button type="button" wire:click="cancelReport" class="px-3 py-1 underline">{{ __('Cancel') }}</button>
                                </div>
                            </div>
                        @endif
                    </li>
                @endif
                @php($previous = $item)
            @empty
                <li class="flex h-full flex-col items-center justify-center px-4 py-8 text-center">
                    <span class="flex size-12 items-center justify-center rounded-full bg-emerald-100 text-xl" aria-hidden="true">💬</span>
                    <p class="mt-3 max-w-xs text-sm text-zinc-600">
                        {{ $side === $Customer ? __('No messages yet. Ask :name anything about the job, or send more photos.', ['name' => $title]) : __('No messages yet. Ask the customer anything you need to know before you send your estimate.') }}
                    </p>
                    @if ($writable)
                        <div class="mt-4 flex flex-wrap justify-center gap-2">
                            @foreach ($starters as $starter)
                                <button type="button" x-on:click="fill(@js($starter))" class="rounded-full border border-emerald-200 bg-white px-3 py-1.5 text-sm text-emerald-900 hover:bg-emerald-50">{{ $starter }}</button>
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforelse

            <li wire:loading.flex wire:target="send" class="mt-1 hidden justify-end" x-show="pending !== ''">
                <p class="max-w-[85%] whitespace-pre-line break-words rounded-2xl rounded-br-md bg-emerald-700/70 px-3.5 py-2 text-[15px] text-white" x-text="pending"></p>
            </li>
        </ol>

        <button type="button" x-show="unseen > 0 && ! stuck" x-cloak x-transition x-on:click="toBottom(true)"
            class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-emerald-700 px-4 py-2 text-sm font-medium text-white shadow-lg">
            ↓ <span x-text="unseen === 1 ? @js(__('1 new message')) : unseen + ' ' + @js(__('new messages'))"></span>
        </button>
    </div>

    @if ($writable)
        <form wire:submit="send" x-on:submit="pending = $wire.message; $nextTick(() => { $refs.box.style.height = 'auto'; toBottom(true) })" class="border-t border-zinc-100 bg-white px-3 py-3 sm:px-4">
            @if ($photos !== [])
                <div class="mb-2 flex flex-wrap gap-2">
                    @foreach ($photos as $index => $photo)
                        <div wire:key="upload-{{ $index }}" class="relative">
                            @if ($photo->isPreviewable())
                                <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 rounded-lg object-cover ring-1 ring-zinc-200">
                            @else
                                <span class="flex size-16 items-center justify-center rounded-lg bg-zinc-100 text-xs">{{ __('Photo') }}</span>
                            @endif
                            <button type="button" wire:click="removePhoto({{ $index }})" class="absolute -right-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-zinc-900 text-xs text-white shadow" aria-label="{{ __('Remove photo') }}">×</button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex items-end gap-2">
                <label class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full border border-zinc-300 text-lg hover:bg-zinc-50" title="{{ __('Add photos') }}">
                    <input type="file" multiple accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" wire:model="photos" class="sr-only">
                    <span aria-hidden="true">📷</span><span class="sr-only">{{ __('Add photos') }}</span>
                </label>
                <label for="chat-{{ $proPublicId }}" class="sr-only">{{ __('Message') }}</label>
                <textarea id="chat-{{ $proPublicId }}" x-ref="box" wire:model="message" rows="1" maxlength="{{ $maxLength }}" placeholder="{{ __('Type a message…') }}"
                    x-on:input="grow($el)"
                    x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing && window.matchMedia('(min-width: 640px)').matches) { $event.preventDefault(); $el.form.requestSubmit() }"
                    class="block max-h-32 min-h-11 w-full resize-none rounded-3xl border border-zinc-300 bg-zinc-50 px-4 py-2.5 leading-snug outline-none focus:border-emerald-600 focus:bg-white focus:ring-2 focus:ring-emerald-600/30"></textarea>
                <button type="submit" wire:loading.attr="disabled" wire:target="send,photos" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-lg font-medium text-white hover:bg-emerald-800 disabled:opacity-60" aria-label="{{ __('Send') }}">↑</button>
            </div>

            <div class="mt-1 flex items-center justify-between gap-3 px-1 text-xs text-zinc-500">
                <span class="hidden sm:inline">{{ __('Enter to send · Shift+Enter for a new line') }}</span>
                <span wire:loading wire:target="photos">{{ __('Uploading photo…') }}</span>
                <span x-show="$wire.message.length > {{ (int) ($maxLength * 0.8) }}" x-cloak class="ml-auto" x-text="$wire.message.length + ' / {{ $maxLength }}'"></span>
            </div>
            @error('message') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            @error('photos') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            @error('photos.*') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
        </form>
    @elseif ($closedNote)
        <p class="border-t border-zinc-100 bg-zinc-50 px-4 py-3 text-center text-sm text-zinc-600">{{ $closedNote }}</p>
    @endif

    <div x-show="lightbox" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" x-on:click="lightbox = null" role="dialog" aria-modal="true" aria-label="{{ __('Photo') }}">
        <img :src="lightbox" alt="" class="max-h-full max-w-full rounded-lg object-contain">
        <button type="button" class="absolute right-4 top-4 flex size-10 items-center justify-center rounded-full bg-white/90 text-xl" x-on:click="lightbox = null" aria-label="{{ __('Close') }}">×</button>
    </div>
</section>
