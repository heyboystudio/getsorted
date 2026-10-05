@php($R = \App\Support\Rand::class)
<main class="flex min-h-dvh items-start justify-center px-5 pb-40 pt-10">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('pros.jobs') }}" class="text-sm text-zinc-700 underline underline-offset-4">← {{ __('Your jobs') }}</a>

        @if ($job === null)
            <div class="mt-8 rounded-xl border border-zinc-200 bg-white p-6 text-center">
                @if ($full ?? false)
                    <p class="text-lg font-medium">{{ __('This job is full') }}</p>
                    <p class="mt-2 text-zinc-600">{{ __('The customer already has three quotes. We\'ll WhatsApp you about the next job that fits.') }}</p>
                @else
                    <p class="text-lg font-medium">{{ __('This job is no longer available') }}</p>
                    <p class="mt-2 text-zinc-600">{{ __('It may have expired, been filled, or you already answered it.') }}</p>
                @endif
            </div>
        @else
            <p class="mt-6 text-sm font-medium uppercase tracking-widest text-emerald-800">{{ $job->service->trade->name }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $job->service->name }}</h1>
            <p class="mt-2 text-zinc-700">{{ $job->property?->suburb?->name }} · {{ $job->time_window?->label() }}@if ($job->preferred_date && $job->time_window !== \App\Domain\ServiceJobs\Enums\TimeWindow::Today), {{ $job->preferred_date->translatedFormat('D j M') }}@endif
                @if ($job->urgency === \App\Domain\ServiceJobs\Enums\Urgency::Urgent) <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Urgent') }}</span> @endif
            </p>
            @unless ($accepted)
                <p class="mt-1 text-sm text-zinc-500">
                    {{ trans_choice(':count pro invited|:count pros invited', $invitedCount, ['count' => $invitedCount]) }} · {{ trans_choice(':count quote in|:count quotes in', $quotesCount, ['count' => $quotesCount]) }}@if ($canQuote) · {{ __(':time left to reply', ['time' => $invite->expires_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}@endif
                </p>
            @endunless

            @if ($accepted && $contact)
                <section class="mt-6 rounded-xl border border-emerald-700 bg-emerald-50 p-4">
                    <h2 class="font-semibold text-emerald-900">{{ __('Your quote was accepted') }}</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div><dt class="text-zinc-600">{{ __('Customer') }}</dt><dd class="font-medium">{{ $contact['name'] }}</dd></div>
                        <div><dt class="text-zinc-600">{{ __('Mobile') }}</dt><dd><a href="tel:{{ $contact['phone'] }}" class="font-medium underline">{{ $contact['phone'] }}</a></dd></div>
                        <div><dt class="text-zinc-600">{{ __('Address') }}</dt><dd>{{ $contact['label'] }} — {{ $contact['address'] }}, {{ $contact['suburb'] }}</dd></div>
                        <div><dt class="text-zinc-600">{{ __('Starting') }}</dt><dd>{{ $job->scheduled_for?->translatedFormat('D j M Y') }}</dd></div>
                    </dl>
                    @if ($job->status === \App\Domain\ServiceJobs\Enums\ServiceJobStatus::AwaitingDeposit)
                        <p class="mt-3 text-sm text-emerald-900">{{ __('Waiting for the customer\'s deposit of :amount. Payment opens soon.', ['amount' => $R::format($quote->deposit_cents)]) }}</p>
                    @endif
                    <p class="mt-3 text-sm text-emerald-900">{{ __('Keep payments on Sortd. It protects you and the customer.') }}</p>
                </section>
            @endif

            @if ($description)
                <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-4">
                    <h2 class="font-medium">{{ __('Job description') }}</h2>
                    <p class="mt-2 whitespace-pre-line text-sm text-zinc-700">{{ $description }}</p>
                </section>
            @endif

            <dl class="mt-4 space-y-3 rounded-xl border border-zinc-200 bg-white p-4 text-sm">
                @foreach ($answers as $answer)
                    <div><dt class="text-zinc-500">{{ $answer['prompt'] }}</dt><dd>{{ is_array($answer['answer']) ? implode(', ', $answer['answer']) : ($answer['type'] === 'yes_no' ? __(ucfirst((string) $answer['answer'])) : $answer['answer']) }}</dd></div>
                @endforeach
                @if ($customerNotes)
                    <div><dt class="text-zinc-500">{{ __("Customer's notes") }}</dt><dd class="whitespace-pre-line">{{ $customerNotes }}</dd></div>
                @endif
            </dl>

            @if ($photoUrls !== [] && ! $accepted)
                <div class="mt-4 grid grid-cols-2 gap-3">
                    @foreach ($photoUrls as $url)
                        <img src="{{ $url }}" alt="{{ __('Job photo :number', ['number' => $loop->iteration]) }}" class="aspect-square w-full rounded-xl object-cover" loading="lazy">
                    @endforeach
                </div>
            @endif

            @if ($sent)
                <p class="mt-6 rounded-lg bg-emerald-50 p-3 text-sm font-medium text-emerald-900" role="status">{{ __('Quote sent. We\'ll WhatsApp you when the customer decides.') }}</p>
            @endif

            @if ($quote && ! $building)
                @include('livewire.pros.jobs.partials.quote-card', ['quote' => $quote, 'title' => __('Your quote')])
                @if (! $accepted && in_array($quote->status, [\App\Domain\Quotes\Enums\QuoteStatus::Submitted, \App\Domain\Quotes\Enums\QuoteStatus::Expired], true))
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <button type="button" wire:click="startRevision" class="rounded-lg border border-emerald-700 px-4 py-3 font-medium text-emerald-800">{{ $quote->status === \App\Domain\Quotes\Enums\QuoteStatus::Expired ? __('Send a fresh quote') : __('Revise') }}</button>
                        @if ($quote->status === \App\Domain\Quotes\Enums\QuoteStatus::Submitted)
                            <div x-data="{ open: false }">
                                <button type="button" x-on:click="open = ! open" class="w-full rounded-lg border border-zinc-300 px-4 py-3 font-medium">{{ __('Withdraw') }}</button>
                                <div x-show="open" x-cloak class="mt-2 space-y-2">
                                    <input type="text" wire:model="withdrawReason" maxlength="300" placeholder="{{ __('Why are you withdrawing?') }}" class="block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                                    @error('withdrawReason') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                    @error('quote') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                    <button type="button" wire:click="withdraw" wire:loading.attr="disabled" wire:target="withdraw" class="w-full rounded-lg bg-zinc-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60">{{ __('Withdraw quote') }}</button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            @endif

            @if ($building)
                <section class="mt-6 rounded-xl border border-zinc-200 bg-white p-4" aria-labelledby="builder-title">
                    <h2 id="builder-title" class="font-semibold">{{ $quote ? __('Revise your quote') : __('Your quote') }}</h2>
                    @if (! $previewing)
                        <div class="mt-4 space-y-4">
                            @foreach ($lines as $index => $line)
                                <fieldset wire:key="line-{{ $index }}" class="rounded-lg border border-zinc-200 p-3">
                                    <legend class="sr-only">{{ __('Line :number', ['number' => $index + 1]) }}</legend>
                                    <div class="flex gap-2">
                                        <select wire:model="lines.{{ $index }}.kind" aria-label="{{ __('Kind') }}" class="rounded-lg border border-zinc-300 px-2 py-2 text-sm">
                                            @foreach ($lineKinds as $kind) <option value="{{ $kind->value }}">{{ $kind->label() }}</option> @endforeach
                                        </select>
                                        <input type="text" wire:model="lines.{{ $index }}.description" maxlength="120" placeholder="{{ __('What is it?') }}" aria-label="{{ __('Description') }}" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <input type="text" inputmode="decimal" wire:model="lines.{{ $index }}.quantity" aria-label="{{ __('Quantity') }}" class="w-20 rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                                        <span class="text-sm text-zinc-500">× R</span>
                                        <input type="text" inputmode="decimal" wire:model="lines.{{ $index }}.unitPrice" placeholder="0.00" aria-label="{{ __('Unit price in rand') }}" class="min-w-0 flex-1 rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                                        @if (count($lines) > 1)
                                            <button type="button" wire:click="removeLine({{ $index }})" class="text-sm text-zinc-600 underline">{{ __('Remove') }}</button>
                                        @endif
                                    </div>
                                    @foreach (['kind', 'description', 'quantity', 'unitPrice'] as $field)
                                        @error('lines.'.$index.'.'.$field) <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                                    @endforeach
                                </fieldset>
                            @endforeach
                            @error('lines') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                            <button type="button" wire:click="addLine" class="text-sm font-medium text-emerald-800 underline">+ {{ __('Add a line') }}</button>

                            <label class="block text-sm">{{ __('Deposit (0 to :max%)', ['max' => $maxDeposit]) }}
                                <input type="number" min="0" max="{{ $maxDeposit }}" wire:model="depositPercent" class="mt-1 block w-28 rounded-lg border border-zinc-300 px-3 py-2">
                            </label>
                            @error('depositPercent') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                            <label class="block text-sm">{{ __('Earliest start date') }}
                                <input type="date" wire:model="earliestStartDate" class="mt-1 block rounded-lg border border-zinc-300 px-3 py-2">
                            </label>
                            @error('earliestStartDate') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                            <label class="block text-sm">{{ __('Valid for (days)') }}
                                <input type="number" min="1" max="30" wire:model="validityDays" class="mt-1 block w-28 rounded-lg border border-zinc-300 px-3 py-2">
                            </label>
                            @error('validityDays') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                            <label class="block text-sm">{{ __('Notes for the customer (optional)') }}
                                <textarea wire:model="notes" rows="3" maxlength="1000" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2"></textarea>
                            </label>
                            @error('notes') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                            @error('total') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                            <button type="button" wire:click="preview" wire:loading.attr="disabled" wire:target="preview" class="w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white disabled:opacity-60">{{ __('Preview') }}</button>
                        </div>
                    @elseif ($previewTotals)
                        <p class="mt-2 text-sm text-zinc-600">{{ __('This is what the customer will see.') }}</p>
                        <dl class="mt-3 space-y-1 text-sm">
                            @foreach (array_values($lines) as $index => $line)
                                <div class="flex justify-between gap-3"><dt>{{ \App\Domain\Quotes\Enums\LineKind::tryFrom((string) $line['kind'])?->label() }}: {{ $previewText['lines'][$index] }} <span class="text-zinc-500">({{ $line['quantity'] }})</span></dt><dd>{{ $R::format($previewTotals->lineTotalsCents[$index]) }}</dd></div>
                            @endforeach
                            <div class="flex justify-between gap-3 border-t border-zinc-200 pt-2 text-zinc-600"><dt>{{ __('Labour') }}</dt><dd>{{ $R::format($previewTotals->labourCents) }}</dd></div>
                            <div class="flex justify-between gap-3 text-zinc-600"><dt>{{ __('Materials') }}</dt><dd>{{ $R::format($previewTotals->materialsCents) }}</dd></div>
                            @if ($previewTotals->calloutCents > 0)
                                <div class="flex justify-between gap-3 text-zinc-600"><dt>{{ __('Call-out') }}</dt><dd>{{ $R::format($previewTotals->calloutCents) }}</dd></div>
                            @endif
                            @if ($previewTotals->vatCents > 0)
                                <div class="flex justify-between gap-3 text-zinc-600"><dt>{{ __('VAT') }}</dt><dd>{{ $R::format($previewTotals->vatCents) }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-3 border-t border-zinc-200 pt-2 font-semibold"><dt>{{ __('Total') }}</dt><dd>{{ $R::format($previewTotals->totalCents) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>{{ __('Deposit') }}</dt><dd>{{ $R::format($previewTotals->depositCents) }}</dd></div>
                            @if ($previewText['start'])
                                <div class="flex justify-between gap-3 text-zinc-600"><dt>{{ __('Earliest start') }}</dt><dd>{{ $previewText['start'] }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-3 text-zinc-600"><dt>{{ __('Valid until') }}</dt><dd>{{ $previewText['validUntil'] }}</dd></div>
                        </dl>
                        @if ($previewText['notes'])
                            <p class="mt-3 whitespace-pre-line text-sm text-zinc-700">{{ $previewText['notes'] }}</p>
                        @endif
                        <div class="mt-4 rounded-lg bg-zinc-50 p-3 text-sm">
                            <p class="font-medium">{{ __('Estimated payout: :amount', ['amount' => $R::format($previewTotals->payoutEstimateCents)]) }}</p>
                            <p class="mt-1 text-zinc-600">{{ __('After Sortd\'s commission of about :amount on labour and call-out. This is an estimate.', ['amount' => $R::format($previewTotals->commissionEstimateCents)]) }}</p>
                        </div>
                        @error('quote') <p class="mt-3 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <button type="button" wire:click="editQuote" class="rounded-lg border border-zinc-300 px-4 py-3 font-medium">{{ __('Edit') }}</button>
                            <button type="button" wire:click="submitQuote" wire:loading.attr="disabled" wire:target="submitQuote" class="rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white disabled:opacity-60">
                                <span wire:loading.remove wire:target="submitQuote">{{ __('Send quote') }}</span>
                                <span wire:loading wire:target="submitQuote">{{ __('Sending…') }}</span>
                            </button>
                        </div>
                    @endif
                </section>
            @endif

            @if ($canQuote && ! $building)
                <p class="mt-6 text-xs text-zinc-500">{{ __("You'll see the customer's name, contact details and address if they accept your quote.") }}</p>
                <div class="fixed inset-x-0 bottom-0 border-t border-zinc-200 bg-white px-5 py-4" x-data="{ open: false }">
                    <div class="mx-auto max-w-xl">
                        <div x-show="open" x-cloak class="mb-4 space-y-2">
                            <p class="font-medium">{{ __('Why not this one?') }}</p>
                            @foreach ($reasons as $option)
                                <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model="reason" value="{{ $option->value }}" class="text-emerald-700"> {{ $option->label() }}</label>
                            @endforeach
                            <input type="text" wire:model="note" maxlength="300" placeholder="{{ __('Tell us briefly (optional unless Other)') }}" class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm">
                            @error('reason') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                            @error('note') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                            <button type="button" wire:click="decline" wire:loading.attr="disabled" wire:target="decline" class="w-full rounded-lg border border-zinc-400 px-4 py-2 text-sm font-medium disabled:opacity-60">{{ __('Decline this job') }}</button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" x-on:click="open = ! open" class="rounded-lg border border-zinc-300 px-4 py-3 font-medium">{{ __('Not for me') }}</button>
                            <button type="button" wire:click="startQuote" class="rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white">{{ __('Send a quote') }}</button>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </section>
</main>
