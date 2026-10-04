<div>
    @if ($state !== 'none')
        <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-4" aria-live="polite" @if ($state === 'loading') wire:init="load" @endif>
            <div class="flex justify-between">
                <h2 class="font-medium">{{ __('Job description for pros') }}</h2>
                @if (in_array($state, ['ai', 'edited'], true) && ! $editing)
                    <button type="button" wire:click="edit" wire:loading.attr="disabled" wire:target="edit" class="text-sm text-emerald-800 underline disabled:opacity-60">{{ __('Edit') }}</button>
                @endif
            </div>
            @if ($state === 'loading')
                <div class="mt-3 space-y-2" role="status" aria-label="{{ __('Writing a description…') }}">
                    <div class="h-3 w-full animate-pulse rounded bg-zinc-200"></div>
                    <div class="h-3 w-5/6 animate-pulse rounded bg-zinc-200"></div>
                    <div class="h-3 w-2/3 animate-pulse rounded bg-zinc-200"></div>
                </div>
            @elseif ($editing)
                <label for="summary-text" class="sr-only">{{ __('Job description for pros') }}</label>
                <textarea id="summary-text" wire:model="text" rows="5" maxlength="{{ $maxLength }}"
                    class="mt-3 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/30"></textarea>
                <p class="mt-1 text-right text-xs text-zinc-500" x-data x-text="$wire.text.length + ' / {{ $maxLength }}'"></p>
                @error('text') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                <div class="mt-3 flex gap-2">
                    <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">{{ __('Save') }}</button>
                    <button type="button" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium disabled:opacity-60">{{ __('Cancel') }}</button>
                </div>
            @else
                <p class="mt-2 whitespace-pre-line text-sm text-zinc-700">{{ $summary }}</p>
                @if ($state === 'ai')
                    <p class="mt-2 text-xs text-zinc-500">{{ __('Written with AI help, please check it') }}.</p>
                @endif
                @if ($stale)
                    <p class="mt-2 text-xs text-amber-800">{{ __('You changed some details. Check the description still fits.') }}</p>
                @endif
            @endif
        </section>
    @endif
</div>
