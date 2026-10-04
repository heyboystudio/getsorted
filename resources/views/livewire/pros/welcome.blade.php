<main class="flex min-h-dvh items-start justify-center px-5 py-12">
    <section class="w-full max-w-xl">
        <div class="mb-10 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-2xl font-semibold tracking-tight">{{ __('Sortd') }}<span aria-hidden="true" class="text-emerald-700">.</span></a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Log out') }}</button>
            </form>
        </div>

        <p class="mb-3 text-sm font-medium uppercase tracking-widest text-emerald-800">{{ __('Sortd Pro') }}</p>
        <h1 class="text-3xl font-semibold tracking-tight">{{ __('Thanks, :name', ['name' => $firstName]) }} 👋</h1>
        <p class="mt-3 text-lg text-zinc-600">{{ __("Your application form opens soon. We'll WhatsApp you when it does.") }}</p>

        @if ($isCustomer)
            <a href="{{ route('account.home') }}" class="mt-8 inline-block text-emerald-800 underline underline-offset-4">{{ __('Go to my customer account') }}</a>
        @endif
    </section>
</main>
