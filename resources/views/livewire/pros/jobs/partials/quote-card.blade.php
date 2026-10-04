@php($R = \App\Support\Rand::class)
<section class="mt-6 rounded-xl border border-zinc-200 bg-white p-4">
    <div class="flex items-start justify-between gap-3">
        <h2 class="font-semibold">{{ $title }}</h2>
        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs">{{ $quote->status->label() }}</span>
    </div>
    <dl class="mt-3 space-y-1 text-sm">
        @foreach ($quote->lines as $line)
            <div class="flex justify-between gap-3"><dt>{{ $line->kind->label() }}: {{ $line->description }} <span class="text-zinc-500">({{ rtrim(rtrim((string) $line->quantity, '0'), '.') }})</span></dt><dd>{{ $R::format($line->line_total_cents) }}</dd></div>
        @endforeach
        @if ($quote->vat_cents > 0)
            <div class="flex justify-between gap-3 text-zinc-600"><dt>{{ __('VAT') }}</dt><dd>{{ $R::format($quote->vat_cents) }}</dd></div>
        @endif
        <div class="flex justify-between gap-3 border-t border-zinc-200 pt-2 font-semibold"><dt>{{ __('Total') }}</dt><dd>{{ $R::format($quote->total_cents) }}</dd></div>
        <div class="flex justify-between gap-3"><dt>{{ __('Deposit') }}</dt><dd>{{ $R::format($quote->deposit_cents) }}</dd></div>
        <div class="flex justify-between gap-3 text-zinc-600"><dt>{{ __('Earliest start') }}</dt><dd>{{ $quote->earliest_start_date->translatedFormat('D j M') }}</dd></div>
        <div class="flex justify-between gap-3 text-zinc-600"><dt>{{ __('Valid until') }}</dt><dd>{{ $quote->valid_until->translatedFormat('D j M') }}</dd></div>
    </dl>
    @if ($quote->notes)
        <p class="mt-3 whitespace-pre-line text-sm text-zinc-700">{{ $quote->notes }}</p>
    @endif
</section>
