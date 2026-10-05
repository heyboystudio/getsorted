@php
    $R = \App\Support\Rand::class;
    $S = \App\Domain\ServiceJobs\Enums\ServiceJobStatus::class;
    $acceptedQuote = $quotes->firstWhere('status', \App\Domain\Quotes\Enums\QuoteStatus::Accepted);
@endphp

@if ($acceptedQuote)
    <section class="mt-6 rounded-xl border border-emerald-700 bg-emerald-50 p-4">
        <h2 class="font-semibold text-emerald-900">{{ __('Booked with :pro', ['pro' => $acceptedQuote->pro->business_name]) }}</h2>
        <p class="mt-2 text-sm">{{ __('Mobile') }}: <a href="tel:{{ $acceptedQuote->pro->user->phone_e164 }}" class="font-medium underline">{{ $acceptedQuote->pro->user->phone_e164 }}</a></p>
        <p class="mt-1 text-sm">{{ __('Starting') }}: {{ $job->scheduled_for?->translatedFormat('D j M Y') }} · {{ __('Total') }} {{ $R::format($acceptedQuote->total_cents) }}</p>
        @if ($job->status === $S::AwaitingDeposit)
            <p class="mt-3 rounded-lg bg-white p-3 text-sm">{{ __('Deposit due: :amount. Payment opens soon. We\'ll WhatsApp you when you can pay.', ['amount' => $R::format($acceptedQuote->deposit_cents)]) }}</p>
        @endif
        <p class="mt-3 text-sm text-emerald-900">{{ __('Keep payments on Sortd. It protects you and the pro.') }}</p>
    </section>
@elseif ($job->status === $S::Open && $quotes->isNotEmpty())
    <section class="mt-6">
        <h2 class="font-semibold">{{ __('Estimates (:count of 3)', ['count' => $quotes->count()]) }}</h2>
        <p class="mt-1 text-sm text-zinc-600">{{ __('Your pro can adjust the final amount after seeing the job. You’ll approve any change.') }}</p>
        @error('accept') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
        <div class="mt-3 grid gap-4 lg:grid-cols-3">
            @foreach ($quotes as $quote)
                @php
                    $pro = $quote->pro;
                    $registrations = $pro->documents->filter(fn ($document): bool => $document->type->isRegistration() && $document->status === \App\Domain\Pros\Enums\DocumentStatus::Verified && ! $document->isExpired());
                @endphp
                <article wire:key="quote-{{ $quote->public_id }}" class="rounded-xl border border-zinc-200 bg-white p-4">
                    <div class="flex items-center gap-3">
                        @if ($quote->hasProPhoto())
                            <img src="{{ $quote->proPhotoUrl() }}" alt="" class="size-12 rounded-full object-cover">
                        @endif
                        <div>
                            <p class="font-semibold">{{ $pro->business_name }}</p>
                            <p class="text-xs text-zinc-500">{{ __('On Sortd since :date', ['date' => $pro->approved_at?->translatedFormat('M Y')]) }}</p>
                            @foreach ($registrations as $registration)
                                <p class="text-xs text-emerald-800">✓ {{ $registration->type->label() }}</p>
                            @endforeach
                        </div>
                    </div>
                    <dl class="mt-4 space-y-1 text-sm">
                        @if ($quote->labour_cents > 0)<div class="flex justify-between"><dt>{{ __('Labour') }}</dt><dd>{{ $R::format($quote->labour_cents) }}</dd></div>@endif
                        @if ($quote->materials_cents > 0)<div class="flex justify-between"><dt>{{ __('Materials') }}</dt><dd>{{ $R::format($quote->materials_cents) }}</dd></div>@endif
                        @if ($quote->callout_cents > 0)<div class="flex justify-between"><dt>{{ __('Call-out') }}</dt><dd>{{ $R::format($quote->callout_cents) }}</dd></div>@endif
                        @if ($quote->vat_cents > 0)<div class="flex justify-between text-zinc-600"><dt>{{ __('VAT') }}</dt><dd>{{ $R::format($quote->vat_cents) }}</dd></div>@endif
                        <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold"><dt>{{ __('Total') }}</dt><dd>{{ $R::format($quote->total_cents) }}</dd></div>
                        <div class="flex justify-between"><dt>{{ __('Deposit') }}</dt><dd>{{ $R::format($quote->deposit_cents) }}</dd></div>
                        <div class="flex justify-between text-zinc-600"><dt>{{ __('Earliest start') }}</dt><dd>{{ $quote->earliest_start_date->translatedFormat('D j M') }}</dd></div>
                        <div class="flex justify-between text-zinc-600"><dt>{{ __('Valid until') }}</dt><dd>{{ $quote->valid_until->translatedFormat('D j M') }}</dd></div>
                    </dl>
                    @if ($quote->notes)
                        <p class="mt-3 whitespace-pre-line text-sm text-zinc-700">{{ $quote->notes }}</p>
                    @endif
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-emerald-800">{{ __('See every line') }}</summary>
                        <ul class="mt-2 space-y-1">
                            @foreach ($quote->lines as $line)
                                <li class="flex justify-between gap-3"><span>{{ $line->description }} <span class="text-zinc-500">({{ rtrim(rtrim((string) $line->quantity, '0'), '.') }})</span></span><span>{{ $R::format($line->line_total_cents) }}</span></li>
                            @endforeach
                        </ul>
                    </details>
                    @if ($quote->isPastValidity())
                        <p class="mt-4 text-sm text-zinc-600">{{ __('This estimate has expired.') }}</p>
                    @elseif ($pro->status !== \App\Domain\Pros\Enums\ProStatus::Approved)
                        <p class="mt-4 text-sm text-zinc-600">{{ __('This pro is unavailable.') }}</p>
                    @else
                        <button type="button" wire:click="confirmAccept('{{ $quote->public_id }}')" class="mt-4 w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800">{{ __('Accept this estimate') }}</button>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    @if ($accepting)
        <div class="fixed inset-0 z-10 flex items-end justify-center bg-black/40 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="accept-title">
            <div class="w-full max-w-md rounded-xl bg-white p-5">
                <h2 id="accept-title" class="text-lg font-semibold">{{ __('Accept this estimate for :total?', ['total' => $R::format($accepting->total_cents)]) }}</h2>
                <p class="mt-2 text-sm text-zinc-600">
                    {{ $accepting->deposit_cents > 0
                        ? __('A deposit of :deposit will be due. The other pros will be told you chose someone else.', ['deposit' => $R::format($accepting->deposit_cents)])
                        : __('The other pros will be told you chose someone else.') }}
                </p>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button type="button" wire:click="cancelAccept" class="rounded-lg border border-zinc-300 px-4 py-3 font-medium">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="accept" wire:loading.attr="disabled" wire:target="accept" class="rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white disabled:opacity-60">{{ __('Accept') }}</button>
                </div>
            </div>
        </div>
    @endif
@elseif ($job->status === $S::Expired)
    <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-4">
        <p class="font-medium">{{ __('No quote was accepted in time') }}</p>
        <a href="{{ route('booking.start', ['trade' => $job->service->trade, 'service' => $job->service->key]) }}" class="mt-3 inline-block text-emerald-800 underline">{{ __('Post this job again') }}</a>
    </section>
@endif
