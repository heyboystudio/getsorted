@php($S = \App\Domain\Pros\Enums\ProStatus::class)
<main class="flex min-h-dvh items-start justify-center px-5 py-10">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('pros.welcome') }}" class="text-sm text-zinc-700 underline underline-offset-4">← {{ __('Sortd Pro') }}</a>
        <h1 class="mt-6 text-2xl font-semibold tracking-tight">{{ __('Your application') }}</h1>
        <p class="mt-3">
            <span @class([
                'rounded-full px-3 py-1 text-sm font-medium',
                'bg-emerald-50 text-emerald-900' => $pro->status === $S::Approved,
                'bg-amber-50 text-amber-900' => in_array($pro->status, [$S::ChangesRequested, $S::Draft], true),
                'bg-red-50 text-red-900' => in_array($pro->status, [$S::Rejected, $S::Suspended], true),
                'bg-zinc-100 text-zinc-800' => $pro->status === $S::Submitted,
            ])>{{ $pro->status->label() }}</span>
        </p>

        @switch($pro->status)
            @case($S::Submitted)
                <p class="mt-4 text-zinc-700">{{ __('Our vetting team is checking your details and will phone your references. We will WhatsApp you when we decide.') }}</p>
                @break
            @case($S::Approved)
                <p class="mt-4 text-zinc-700">{{ __("You're approved. You will receive jobs in your suburbs for the services we could verify.") }}</p>
                @break
            @case($S::Draft)
                <p class="mt-4 text-zinc-700">{{ __('Your application is not sent yet.') }}</p>
                <a wire:navigate.hover href="{{ route('pros.apply') }}" class="mt-4 inline-block w-full rounded-lg bg-emerald-700 px-4 py-3 text-center font-medium text-white">{{ __('Continue your application') }}</a>
                @break
            @case($S::ChangesRequested)
            @case($S::Rejected)
            @case($S::Suspended)
                @if ($pro->decision_reason)
                    <div class="mt-4 rounded-lg bg-amber-50 p-4 text-amber-900"><p class="text-sm font-medium">{{ __('Message from our vetting team') }}</p><p class="mt-1">{{ $pro->decision_reason }}</p></div>
                @endif
                @if ($pro->status === $S::ChangesRequested)
                    <a wire:navigate.hover href="{{ route('pros.apply') }}" class="mt-4 inline-block w-full rounded-lg bg-emerald-700 px-4 py-3 text-center font-medium text-white">{{ __('Fix these items') }}</a>
                @elseif ($pro->status === $S::Rejected && $pro->reapply_after?->isFuture())
                    <p class="mt-4 text-zinc-700">{{ __('You can apply again from :date.', ['date' => $pro->reapply_after->format('j F Y')]) }}</p>
                @elseif ($pro->status === $S::Rejected)
                    <a wire:navigate.hover href="{{ route('pros.apply') }}" class="mt-4 inline-block w-full rounded-lg bg-emerald-700 px-4 py-3 text-center font-medium text-white">{{ __('Apply again') }}</a>
                @endif
                @break
        @endswitch

        <h2 class="mt-10 font-medium">{{ __('Checklist') }}</h2>
        <ul class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white text-sm">
            @foreach ($documentTypes as $type)
                @php($document = $pro->document($type))
                <li class="p-4">
                    <div class="flex justify-between gap-3">
                        <span>{{ $type->label() }}</span>
                        <span class="text-zinc-600">
                            @if (! $document?->file()) {{ $type->isRegistration() ? __('Not provided: those services are not offered') : __('Missing') }}
                            @elseif ($document->isExpired()) {{ __('Expired') }}
                            @else {{ $document->status->label() }}
                            @endif
                        </span>
                    </div>
                    @if ($document?->flag_message)
                        <p class="mt-1 text-amber-800">{{ $document->flag_message }}</p>
                    @endif
                </li>
            @endforeach
            @foreach ($pro->references as $reference)
                <li class="flex justify-between gap-3 p-4">
                    <span>{{ __('Reference: :name', ['name' => $reference->name]) }}</span>
                    <span class="text-zinc-600">{{ match ($reference->outcome) {
                        \App\Domain\Pros\Enums\ReferenceOutcome::Pending => __('Being checked'),
                        \App\Domain\Pros\Enums\ReferenceOutcome::Positive => __('Verified'),
                        default => __('Needs attention: please give another reference'),
                    } }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</main>
