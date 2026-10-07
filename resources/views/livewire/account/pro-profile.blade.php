<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('jobs.show', $job) }}" class="mb-6 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Back to your quotes') }}</a>
        <x-pro-profile :profile="$profile" />
        <p class="mt-4 text-xs text-zinc-500">{{ __('Contact details are shared only after you accept this pro’s quote.') }}</p>
    </section>
</main>
