<x-layouts.app :title="__('Join as a pro')">
    <main class="flex min-h-dvh items-start justify-center px-5 py-12 sm:items-center">
        <section class="w-full max-w-md">
            <a href="{{ route('home') }}" class="mb-10 inline-block text-2xl font-semibold tracking-tight">{{ __('Sortd') }}<span aria-hidden="true" class="text-emerald-700">.</span></a>
            <p class="mb-3 text-sm font-medium uppercase tracking-widest text-emerald-800">{{ __('For tradespeople') }}</p>
            <h1 class="text-3xl font-semibold leading-tight tracking-tight">{{ __('Get jobs from Durban households') }}</h1>

            <ul class="mt-8 space-y-4 text-zinc-700">
                <li class="flex gap-3"><span aria-hidden="true" class="mt-2 size-2 shrink-0 rounded-full bg-emerald-700"></span>{{ __('Clearly scoped jobs near you, sent to at most three pros.') }}</li>
                <li class="flex gap-3"><span aria-hidden="true" class="mt-2 size-2 shrink-0 rounded-full bg-emerald-700"></span>{{ __('You set your own prices and choose which jobs to quote.') }}</li>
                <li class="flex gap-3"><span aria-hidden="true" class="mt-2 size-2 shrink-0 rounded-full bg-emerald-700"></span>{{ __('Sortd checks every pro’s ID, registrations and references, so customers can trust you.') }}</li>
            </ul>

            <p class="mt-8 inline-block rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-900">{{ __('Free to join') }}</p>

            @auth
                <a href="{{ route('pros.become') }}" class="mt-6 flex w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800">{{ __('Get started') }}</a>
            @else
                <a href="{{ route('login', ['as' => 'pro']) }}" class="mt-6 flex w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800">{{ __('Get started') }}</a>
            @endauth
        </section>
    </main>
</x-layouts.app>
