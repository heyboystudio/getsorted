{{-- Spec 018, AC9–AC12: the accepted pro proposes a final amount. Totals shown here are previews; the server recalculates. --}}
@php($R = \App\Support\Rand::class)
@php($P = \App\Domain\Quotes\Enums\ProposalStatus::class)
<section class="mt-6 rounded-xl border border-zinc-200 bg-white p-4" aria-labelledby="final-amount-title">
    <h2 id="final-amount-title" class="font-semibold">{{ __('Final amount') }}</h2>
    <p class="mt-1 text-sm text-zinc-700">{{ __('Agreed: :amount', ['amount' => $R::format($agreedCents)]) }}@if ($agreedCents !== $estimateCents) <span class="text-zinc-500">({{ __('estimate was :amount', ['amount' => $R::format($estimateCents)]) }})</span>@endif</p>

    @if ($pending)
        <div class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-950">
            <p class="font-medium">{{ __('You proposed :amount. Waiting for the customer to approve.', ['amount' => $R::format($pending->total_cents)]) }}</p>
            <button type="button" wire:click="withdraw(@js($pending->public_id))" wire:confirm="{{ __('Withdraw this proposal?') }}" class="mt-2 underline">{{ __('Withdraw') }}</button>
        </div>
    @endif

    @php($latest = $proposals->first())
    @if ($latest && $latest->status === $P::Declined)
        <div class="mt-3 rounded-lg bg-zinc-50 p-3 text-sm">
            <p class="font-medium">{{ __('The customer declined :amount.', ['amount' => $R::format($latest->total_cents)]) }}</p>
            @if ($latest->customer_note)<p class="mt-1 text-zinc-700">“{{ $latest->customer_note }}”</p>@endif
            <p class="mt-2 text-zinc-700">{{ $increaseBlocked
                ? __('You can do the job at the agreed amount, or cancel.')
                : __('You can do the job at the agreed amount, send one more proposal, or cancel.') }}</p>
            @if ($canCancel)
                <button type="button" wire:click="cancelJob" wire:confirm="{{ __('Cancel this job because the price wasn’t agreed? The customer gets any deposit back in full.') }}" class="mt-2 font-medium text-red-700 underline">{{ __('Cancel the job: price not agreed') }}</button>
            @elseif ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::InProgress)
                <p class="mt-2 text-zinc-600">{{ __('Work has started, so contact Sortd support if you can’t agree.') }}</p>
            @endif
            @error('cancel') <p class="mt-1 text-red-700" role="alert">{{ $message }}</p> @enderror
        </div>
    @endif

    @if ($building)
        <div class="mt-4 space-y-3">
            @foreach ($lines as $index => $line)
                <fieldset wire:key="final-line-{{ $index }}" class="rounded-lg border border-zinc-200 p-3">
                    <legend class="sr-only">{{ __('Line :number', ['number' => $index + 1]) }}</legend>
                    <div class="flex gap-2">
                        <select wire:model.live="lines.{{ $index }}.kind" aria-label="{{ __('Kind') }}" class="rounded-lg border border-zinc-300 px-2 py-2 text-sm">
                            @foreach ($lineKinds as $kind) <option value="{{ $kind->value }}">{{ $kind->label() }}</option> @endforeach
                        </select>
                        <input type="text" wire:model="lines.{{ $index }}.description" maxlength="120" placeholder="{{ __('What is it?') }}" aria-label="{{ __('Description') }}" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="lines.{{ $index }}.quantity" aria-label="{{ __('Quantity') }}" class="w-20 rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                        <span class="text-sm text-zinc-500">× R</span>
                        <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="lines.{{ $index }}.unitPrice" placeholder="0.00" aria-label="{{ __('Unit price in rand') }}" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                        @if (count($lines) > 1)
                            <button type="button" wire:click="removeLine({{ $index }})" class="text-sm text-zinc-600 underline">{{ __('Remove') }}</button>
                        @endif
                    </div>
                    @foreach (['kind', 'description', 'quantity', 'unit_price'] as $field)
                        @error('lines.'.$index.'.'.$field) <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    @endforeach
                </fieldset>
            @endforeach
            @error('lines') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            <button type="button" wire:click="addLine" class="text-sm font-medium text-emerald-800 underline">+ {{ __('Add a line') }}</button>

            @if ($preview !== null)
                @php($diff = $preview - $agreedCents)
                <p class="rounded-lg bg-zinc-50 p-3 text-sm">
                    {{ __('New total: :amount', ['amount' => $R::format($preview)]) }}
                    <span @class(['font-medium', 'text-red-700' => $diff > 0, 'text-emerald-800' => $diff < 0])>({{ $diff > 0 ? '+' : '−' }}{{ $R::format(abs($diff)) }})</span>
                    <span class="block text-xs text-zinc-600">{{ $diff > 0 ? __('The customer must approve an increase.') : ($diff < 0 ? __('A lower amount applies straight away.') : __('That’s the same as now.')) }}</span>
                </p>
            @endif

            <label class="block text-sm">{{ __('Why is the price changing?') }}
                <textarea wire:model="reason" rows="3" maxlength="500" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2" placeholder="{{ __('e.g. The geyser valve also needs replacing.') }}"></textarea>
            </label>
            @error('reason') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            @error('total') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <div class="grid grid-cols-2 gap-3">
                <button type="button" wire:click="cancelBuilding" class="rounded-lg border border-zinc-300 px-4 py-3 font-medium">{{ __('Cancel') }}</button>
                <button type="button" wire:click="propose" wire:loading.attr="disabled" wire:target="propose" class="rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white disabled:opacity-60">{{ __('Send') }}</button>
            </div>
        </div>
    @elseif ($canPropose)
        <button type="button" wire:click="start" class="mt-3 w-full rounded-lg border border-emerald-700 px-4 py-3 font-medium text-emerald-800">{{ __('Propose final amount') }}</button>
        <p class="mt-1 text-xs text-zinc-500">{{ __('If the job is bigger or smaller than estimated. The customer approves any increase.') }}</p>
    @endif
    @if (! $building)
        @error('total') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
    @endif

    @if ($proposals->isNotEmpty())
        <details class="mt-4 text-sm">
            <summary class="cursor-pointer text-emerald-800">{{ __('Price history') }}</summary>
            <ul class="mt-2 space-y-1">
                @foreach ($proposals as $proposal)
                    <li wire:key="history-{{ $proposal->public_id }}" class="flex justify-between gap-3"><span>{{ $proposal->created_at->translatedFormat('j M H:i') }} · {{ $proposal->status->label() }}</span><span>{{ $R::format($proposal->previous_total_cents) }} → {{ $R::format($proposal->total_cents) }}</span></li>
                @endforeach
            </ul>
        </details>
    @endif
</section>
