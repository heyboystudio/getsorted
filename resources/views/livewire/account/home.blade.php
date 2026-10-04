<main class="flex min-h-dvh items-start justify-center px-5 py-12">
    <section class="w-full max-w-xl">
        <div class="mb-10 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-2xl font-semibold tracking-tight">{{ __('Sortd') }}<span aria-hidden="true" class="text-emerald-700">.</span></a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Log out') }}</button>
            </form>
        </div>

        <h1 class="text-3xl font-semibold tracking-tight">{{ __('Hi :name', ['name' => $firstName]) }} <span aria-hidden="true">👋</span></h1>
        <p class="mt-3 text-lg text-zinc-600">{{ __('Your jobs will appear here.') }}</p>
    </section>
</main>
