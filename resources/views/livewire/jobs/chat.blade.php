{{-- Spec 018: one chat between a job's customer and a pro. All text is escaped; nothing is rendered as HTML. --}}
@php($R = \App\Support\Rand::class)
<section class="mt-4 rounded-xl border border-zinc-200 bg-white" wire:poll.5s.visible x-data="{ pending: '' }">
    <header class="flex items-center justify-between border-b border-zinc-100 px-4 py-3">
        <h3 class="font-semibold">{{ __('Chat with :name', ['name' => $title]) }}</h3>
        <span class="text-xs text-zinc-500">{{ __('Updates every few seconds') }}</span>
    </header>

    @if ($masked)
        <p class="bg-amber-50 px-4 py-2 text-xs text-amber-950">{{ __('Keep chats and payments on Sortd. Contact details are shared once you accept a quote.') }}</p>
    @endif

    <ol class="max-h-[28rem] space-y-3 overflow-y-auto px-4 py-4" aria-live="polite" aria-label="{{ __('Messages') }}">
        @forelse ($items as $item)
            @if ($item['type'] === 'quote')
                @php($quote = $item['quote'])
                <li wire:key="quote-{{ $quote->public_id }}" class="flex justify-center">
                    <div class="w-full max-w-sm rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">
                        <p class="font-medium text-emerald-900">{{ $quote->version > 1 ? __('Revised estimate') : __('Estimate') }} · {{ $R::format($quote->total_cents) }}</p>
                        <p class="text-xs text-emerald-800">{{ \App\Livewire\Jobs\Chat::quoteStatus($quote) }} · {{ $quote->submitted_at?->translatedFormat('D j M, H:i') }}</p>
                        @if ($side === \App\Domain\ServiceJobs\Enums\MessageSender::Customer)
                            <p class="mt-1 text-xs text-emerald-900">{{ __('See the full estimate above to compare and accept.') }}</p>
                        @endif
                    </div>
                </li>
            @else
                @php($message = $item['message'])
                <li wire:key="msg-{{ $message->public_id }}" @class(['flex flex-col', 'items-end' => $item['mine'], 'items-start' => ! $item['mine']])>
                    <div @class([
                        'max-w-[85%] rounded-2xl px-4 py-2 text-[15px]',
                        'bg-emerald-700 text-white' => $item['mine'] && $message->deleted_at === null,
                        'bg-zinc-100 text-zinc-900' => ! $item['mine'] && $message->deleted_at === null,
                        'bg-zinc-50 italic text-zinc-500' => $message->deleted_at !== null,
                    ])>
                        @if ($message->deleted_at !== null)
                            {{ __('Message deleted') }}
                        @else
                            @if ($message->body !== null)<p class="whitespace-pre-line break-words">{{ $message->body }}</p>@endif
                            @if ($item['photos'] !== [])
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    @foreach ($item['photos'] as $uuid => $url)
                                        <a href="{{ $url }}" target="_blank" rel="noopener" wire:key="photo-{{ $uuid }}"><img src="{{ $url }}" alt="{{ __('Photo :number', ['number' => $loop->iteration]) }}" class="aspect-square w-full rounded-lg object-cover" loading="lazy"></a>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                    <p class="mt-1 flex gap-3 text-xs text-zinc-500">
                        <span>{{ $message->created_at->translatedFormat('D j M, H:i') }}</span>
                        @if ($message->deleted_at === null && $item['mine'] && $message->created_at->gt($deleteWithin))
                            <button type="button" wire:click="deleteMessage(@js($message->public_id))" wire:confirm="{{ __('Delete this message?') }}" class="underline">{{ __('Delete') }}</button>
                        @elseif ($message->deleted_at === null && ! $item['mine'] && $message->reported_at === null)
                            <button type="button" wire:click="startReport(@js($message->public_id))" class="underline">{{ __('Report') }}</button>
                        @elseif ($message->reported_at !== null && ! $item['mine'])
                            <span>{{ __('Reported') }}</span>
                        @endif
                    </p>
                    @if ($reporting === $message->public_id)
                        <div class="mt-2 w-full max-w-sm rounded-lg border border-zinc-200 bg-white p-3 text-sm">
                            <p class="font-medium">{{ __('Why are you reporting this?') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($reasons as $reason)
                                    <button type="button" wire:click="report(@js($reason->value))" class="rounded-full border border-zinc-300 px-3 py-1">{{ $reason->label() }}</button>
                                @endforeach
                                <button type="button" wire:click="cancelReport" class="px-3 py-1 underline">{{ __('Cancel') }}</button>
                            </div>
                        </div>
                    @endif
                </li>
            @endif
        @empty
            <li class="py-6 text-center text-sm text-zinc-500">
                {{ $side === \App\Domain\ServiceJobs\Enums\MessageSender::Customer ? __('No messages yet. Ask :name anything about the job, or send more photos.', ['name' => $title]) : __('No messages yet. Ask the customer anything you need to know before you send your estimate.') }}
            </li>
        @endforelse
        <li wire:loading.flex wire:target="send" class="hidden justify-end" x-show="pending !== ''">
            <p class="max-w-[85%] rounded-2xl bg-emerald-700/70 px-4 py-2 text-[15px] text-white" x-text="pending"></p>
        </li>
        <li wire:key="end-{{ $items->count() }}" x-init="$el.parentElement.scrollTop = $el.parentElement.scrollHeight"></li>
    </ol>

    @if ($writable)
        <form wire:submit="send" x-on:submit="pending = $wire.message" class="border-t border-zinc-100 px-4 py-3">
            @if ($photos !== [])
                <div class="mb-2 flex flex-wrap gap-2">
                    @foreach ($photos as $index => $photo)
                        <div wire:key="upload-{{ $index }}" class="relative">
                            @if ($photo->isPreviewable())
                                <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-16 rounded-lg object-cover">
                            @else
                                <span class="flex size-16 items-center justify-center rounded-lg bg-zinc-100 text-xs">{{ __('Photo') }}</span>
                            @endif
                            <button type="button" wire:click="removePhoto({{ $index }})" class="absolute -right-1 -top-1 rounded-full bg-white px-1.5 text-sm shadow" aria-label="{{ __('Remove photo') }}">×</button>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="flex items-end gap-2">
                <label class="cursor-pointer rounded-full border border-zinc-300 px-3 py-3 text-sm" title="{{ __('Add photos') }}">
                    <input type="file" multiple accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" wire:model="photos" class="sr-only">
                    <span aria-hidden="true">📷</span><span class="sr-only">{{ __('Add photos') }}</span>
                </label>
                <label for="chat-{{ $proPublicId }}" class="sr-only">{{ __('Message') }}</label>
                <textarea id="chat-{{ $proPublicId }}" wire:model="message" rows="1" maxlength="{{ config('sortd.chat.max_length') }}" placeholder="{{ __('Type a message…') }}"
                    class="block max-h-32 w-full resize-y rounded-2xl border border-zinc-300 px-4 py-3 outline-none focus:ring-2 focus:ring-emerald-600"></textarea>
                <button type="submit" wire:loading.attr="disabled" wire:target="send,photos" class="rounded-full bg-emerald-700 px-5 py-3 font-medium text-white disabled:opacity-60" aria-label="{{ __('Send') }}">↑</button>
            </div>
            <p wire:loading wire:target="photos" class="mt-1 text-xs text-zinc-500">{{ __('Uploading photo…') }}</p>
            @error('message') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            @error('photos') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            @error('photos.*') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
        </form>
    @elseif ($closedNote)
        <p class="border-t border-zinc-100 px-4 py-3 text-sm text-zinc-600">{{ $closedNote }}</p>
    @endif
</section>
