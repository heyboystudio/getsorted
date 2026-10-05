<main class="flex min-h-dvh items-start justify-center px-5 py-12 sm:items-center">
    <section class="w-full max-w-sm">
        <a wire:navigate.hover href="{{ route('home') }}" class="mb-10 inline-block text-2xl font-semibold tracking-tight">{{ __('Sortd') }}<span aria-hidden="true" class="text-emerald-700">.</span></a>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Become a pro') }}</h1>
        <p class="mt-2 text-zinc-600">{{ __('Hi :name — you can use the same account as a customer and as a pro.', ['name' => $firstName]) }}</p>

        <form wire:submit="confirm" class="mt-8 space-y-4" novalidate>
            <label class="flex gap-3 text-sm">
                <input type="checkbox" wire:model="acceptProAgreement" class="mt-0.5 size-5 shrink-0 rounded border-zinc-300 text-emerald-700"
                    aria-describedby="acceptProAgreement-error" @error('acceptProAgreement') aria-invalid="true" @enderror>
                <span>{{ __('I accept the') }} <a href="{{ route('pros.agreement') }}" target="_blank" class="underline underline-offset-4">{{ __('pro agreement') }}</a></span>
            </label>
            @error('acceptProAgreement') <p id="acceptProAgreement-error" class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror

            <button type="submit" wire:loading.attr="disabled" wire:target="confirm"
                class="flex w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="confirm">{{ __('Continue') }}</span>
                <span wire:loading wire:target="confirm">{{ __('Saving…') }}</span>
            </button>
        </form>
    </section>
</main>
