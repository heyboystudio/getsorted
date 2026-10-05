<main class="flex min-h-dvh items-start justify-center px-5 py-12">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('account.home') }}" class="mb-10 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Your account') }}</a>

        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Saved properties') }}</h1>
            @if ($properties->count() < $limit)
                <a wire:navigate.hover href="{{ route('properties.create') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('Add property') }}</a>
            @endif
        </div>

        @forelse ($properties as $property)
            <article wire:key="{{ $property->public_id }}" class="mt-6 rounded-xl border border-zinc-200 bg-white p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="font-medium">{{ $property->label }}</h2>
                        <p class="mt-1 break-words text-zinc-600">{{ $property->street_address }}</p>
                        <p class="text-zinc-600">{{ $property->suburb->name }}@if ($property->postal_code), {{ $property->postal_code }}@endif</p>
                        @unless ($property->suburb->is_active)
                            <p class="mt-2 text-sm text-amber-800">{{ __("Sortd isn't in :suburb yet — we'll let you know when we are.", ['suburb' => $property->suburb->name]) }}</p>
                        @endunless
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-2 text-sm">
                        <a wire:navigate.hover href="{{ route('properties.edit', $property) }}" class="text-emerald-800 underline underline-offset-4">{{ __('Edit') }}</a>
                        <button type="button" class="text-red-700 underline underline-offset-4"
                            wire:click="delete('{{ $property->public_id }}')"
                            wire:confirm="{{ __('Delete :label? Past jobs keep the address.', ['label' => $property->label]) }}">{{ __('Delete') }}</button>
                    </div>
                </div>
            </article>
        @empty
            <div class="mt-8 rounded-xl border border-dashed border-zinc-300 p-6 text-center">
                <p class="font-medium">{{ __('No saved properties yet') }}</p>
                <p class="mt-1 text-sm text-zinc-600">{{ __('Save your address once and use it for every job.') }}</p>
            </div>
        @endforelse
    </section>
</main>
