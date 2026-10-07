{{-- Spec 018, AC9–AC13: price changes on a booked job. --}}
@php($R = \App\Support\Rand::class)
@php($P = \App\Domain\Quotes\Enums\ProposalStatus::class)
<section class="mt-6 rounded-xl border border-zinc-200 bg-white p-4" aria-labelledby="price-title">
    <h2 id="price-title" class="font-semibold">{{ __('Price') }}</h2>
    <p class="mt-1 text-sm">{{ __('Agreed final amount: :amount', ['amount' => $R::format($agreedCents)]) }}@if ($agreedCents !== $estimateCents) <span class="text-zinc-500">({{ __('estimate was :amount', ['amount' => $R::format($estimateCents)]) }})</span>@endif</p>

    @if ($pending)
        @php($diff = $pending->differenceCents())
        <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-4" role="status">
            <p class="font-medium text-amber-950">{{ __('Your pro proposed a new final amount') }}</p>
            <p class="mt-1 text-lg">{{ $R::format($pending->previous_total_cents) }} → <strong>{{ $R::format($pending->total_cents) }}</strong> <span class="text-sm font-medium text-red-700">(+{{ $R::format($diff) }})</span></p>
            <p class="mt-2 whitespace-pre-line text-sm text-amber-950">{{ $pending->reason }}</p>
            @if (($changed = $previousLines($pending)) !== [])
                <p class="mt-2 text-sm text-amber-950">{{ __('New or changed:') }} {{ implode(', ', $changed) }}</p>
            @endif
            <details class="mt-2 text-sm">
                <summary class="cursor-pointer text-emerald-800">{{ __('See every line') }}</summary>
                <ul class="mt-2 space-y-1">
                    @foreach ($pending->lines as $line)
                        <li class="flex justify-between gap-3"><span>{{ $line['description'] }} <span class="text-zinc-500">({{ $line['quantity'] }})</span></span><span>{{ $R::format($line['line_total_cents']) }}</span></li>
                    @endforeach
                </ul>
            </details>
            @error('decision') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            @if ($declining)
                <label class="mt-3 block text-sm">{{ __('Tell your pro why (optional)') }}
                    <textarea wire:model="note" rows="2" maxlength="500" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2"></textarea>
                </label>
                <button type="button" wire:click="decline(@js($pending->public_id))" class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-4 py-3 font-medium">{{ __('Decline the new amount') }}</button>
            @else
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <button type="button" wire:click="startDecline" class="rounded-lg border border-zinc-300 bg-white px-4 py-3 font-medium">{{ __('Decline') }}</button>
                    <button type="button" wire:click="approve(@js($pending->public_id))" wire:confirm="{{ __('Approve :amount as the final amount?', ['amount' => $R::format($pending->total_cents)]) }}" wire:loading.attr="disabled" class="rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white disabled:opacity-60">{{ __('Approve') }}</button>
                </div>
            @endif
        </div>
    @elseif ($latest && $latest->status === $P::Applied)
        <p class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">{{ __('Good news: your pro lowered the final amount from :old to :new.', ['old' => $R::format($latest->previous_total_cents), 'new' => $R::format($latest->total_cents)]) }} {{ $latest->reason }}</p>
    @elseif ($latest && $latest->status === $P::Declined)
        <p class="mt-3 text-sm text-zinc-600">{{ __('You declined :amount. The agreed amount stays :agreed unless your pro sends a new proposal.', ['amount' => $R::format($latest->total_cents), 'agreed' => $R::format($agreedCents)]) }}</p>
    @endif
</section>
