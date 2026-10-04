<x-layouts.app :title="__('Pro agreement')">
    <main class="flex min-h-dvh items-start justify-center px-5 py-12">
        <article class="w-full max-w-xl">
            <a href="{{ route('home') }}" class="mb-10 inline-block text-2xl font-semibold tracking-tight">{{ __('Sortd') }}<span aria-hidden="true" class="text-emerald-700">.</span></a>
            <p class="mb-4 inline-block rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-900">{{ __('Draft — not yet in force') }}</p>
            <h1 class="text-3xl font-semibold tracking-tight">{{ __('Pro agreement') }}</h1>
            <p class="mt-2 text-sm text-zinc-500">{{ __('Version') }} {{ config('sortd.legal.pro_agreement_version') }}</p>
            <p class="mt-6 text-zinc-700">{{ __('This is placeholder text while Sortd is being built. The final, lawyer-reviewed agreement will replace it before launch.') }}</p>
        </article>
    </main>
</x-layouts.app>
