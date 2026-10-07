<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('pros.profile') }}" class="mb-4 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Back to your profile') }}</a>
        <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950" role="note">{{ __('This is how customers see you when you send a quote. Your phone number and address are never shown.') }}</p>
        <x-pro-profile :profile="$profile" />
    </section>
</main>
