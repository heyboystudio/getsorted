{{-- Spec 016: Siya, the booking chat. Every message is escaped text; nothing is rendered as HTML. --}}
<main class="flex min-h-dvh justify-center px-4 py-6">
    <section class="flex w-full max-w-lg flex-col">
        <header class="flex items-center justify-between gap-3 border-b border-zinc-200 pb-3">
            <div class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-full bg-emerald-700 font-semibold text-white" aria-hidden="true">S</span>
                <div>
                    <h1 class="font-semibold leading-tight">{{ __('Siya') }}</h1>
                    <p class="text-xs text-zinc-500">{{ __('Sortd’s AI assistant · can make mistakes') }}</p>
                </div>
            </div>
            <button type="button" wire:click="restart" wire:confirm="{{ __('Start over? This clears the chat.') }}" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Restart') }}</button>
        </header>

        @unless ($available)
            <div class="mt-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
                {{ __('Siya is switched off right now.') }}
                <a href="{{ route('trades.index') }}" class="font-medium underline underline-offset-4">{{ __('Choose a service yourself') }}</a>
            </div>
        @endunless

        <ol class="mt-4 flex-1 space-y-3" aria-live="polite" aria-label="{{ __('Conversation') }}">
            @foreach ($messages as $item)
                @php($kind = $item['kind'] ?? null)
                <li wire:key="msg-{{ $loop->index }}" @class(['flex', 'justify-end' => $item['role'] === 'customer'])>
                    @if ($kind === 'emergency')
                        <div class="w-full rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert"><strong>{{ __('Safety first') }}:</strong> {{ $item['text'] }}</div>
                    @elseif ($kind === 'safety')
                        <div class="w-full rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950"><strong>{{ __('Safety advice') }}:</strong> {{ $item['text'] }} <span class="block pt-1 text-xs">{{ __('This is guidance, not a guarantee.') }}</span></div>
                    @elseif ($kind === 'error')
                        <div class="rounded-lg bg-zinc-100 px-4 py-3 text-sm text-zinc-700">{{ $item['text'] }}</div>
                    @else
                        <p @class([
                            'max-w-[85%] whitespace-pre-line rounded-2xl px-4 py-2 text-[15px]',
                            'bg-emerald-700 text-white' => $item['role'] === 'customer',
                            'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-200' => $item['role'] === 'assistant',
                        ])>{{ $item['text'] }}</p>
                    @endif
                </li>
            @endforeach
            <li wire:loading.flex wire:target="send" class="hidden text-sm text-zinc-500">{{ __('Siya is typing…') }}</li>
        </ol>

        @if ($failures >= 2)
            <p class="mt-3 rounded-lg bg-zinc-100 px-4 py-3 text-sm">{{ __('Siya is having trouble.') }}
                @if ($service)
                    <a href="{{ route('booking.start', ['trade' => $service->trade, 'service' => $service->key]) }}" class="font-medium underline underline-offset-4">{{ __('Continue booking without Siya') }}</a>
                @else
                    <a href="{{ route('trades.index') }}" class="font-medium underline underline-offset-4">{{ __('Choose a service yourself') }}</a>
                @endif
            </p>
        @endif

        @if ($suggested)
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm">{{ __('Sounds like') }} <strong>{{ $suggested->name }}</strong> ({{ $suggested->trade->name }}). {{ __('Is that right?') }}</p>
                <div class="mt-3 flex gap-2">
                    <button type="button" wire:click="confirmService" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">{{ __('Yes') }}</button>
                    <button type="button" wire:click="rejectService" class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm">{{ __('Something else') }}</button>
                </div>
            </div>
        @endif

        @if ($question && in_array($question->type->value, ['single_choice', 'multi_choice', 'yes_no'], true))
            <div class="mt-4">
                <p class="text-sm font-medium">{{ $question->prompt }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($question->type->value === 'yes_no' ? ['yes', 'no'] : $question->options as $option)
                        <button type="button" wire:key="chip-{{ $question->key }}-{{ $loop->index }}" wire:click="answer(@js($question->key), @js($option))"
                            class="rounded-full border border-zinc-300 bg-white px-3 py-1.5 text-sm hover:border-emerald-700">{{ $option === 'yes' ? __('Yes') : ($option === 'no' ? __('No') : $option) }}</button>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($ready)
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm">{{ __('That’s everything I need for now. Next: your address, any photos, and a final check before it goes to pros.') }}</p>
                <button type="button" wire:click="continueBooking" class="mt-3 w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white">{{ __('Continue booking') }}</button>
            </div>
        @endif

        @if ($available)
            <form wire:submit="send" class="sticky bottom-0 mt-4 flex gap-2 bg-stone-50 py-3">
                <label for="siya-message" class="sr-only">{{ __('Message Siya') }}</label>
                <input id="siya-message" type="text" wire:model="message" maxlength="1000" autocomplete="off" @disabled($limitReached)
                    placeholder="{{ __('Type your message…') }}" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3 outline-none focus:ring-2 focus:ring-emerald-600">
                <button type="submit" wire:loading.attr="disabled" wire:target="send" @disabled($limitReached) class="rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white disabled:opacity-60">{{ __('Send') }}</button>
            </form>
            @error('message') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
        @endif

        <p class="mt-2 text-center text-xs text-zinc-500">
            <a href="{{ route('trades.index') }}" class="underline underline-offset-4">{{ __('Book without Siya') }}</a>
            · {{ __('Don’t share phone numbers or addresses here.') }}
        </p>
    </section>
</main>
