<div class="mx-auto w-full max-w-3xl px-4 py-6 lg:px-8">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item :href="route('jobs.show', $job)" wire:navigate>{{ __('Back to your quotes') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <x-pro-profile :profile="$profile" />
    <p class="mt-4 text-xs text-zinc-500">{{ __('Contact details are shared only after you accept this pro’s quote.') }}</p>
</div>
