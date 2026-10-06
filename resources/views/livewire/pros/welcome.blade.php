<main class="flex min-h-dvh items-start justify-center px-5 py-12">
    <section class="w-full max-w-xl">
        <div class="mb-10 flex items-center justify-between">
            <a wire:navigate.hover href="{{ route('home') }}" class="text-2xl font-semibold tracking-tight">{{ __('Sortd') }}<span aria-hidden="true" class="text-emerald-700">.</span></a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Log out') }}</button>
            </form>
        </div>

        <p class="mb-3 text-sm font-medium uppercase tracking-widest text-emerald-800">{{ __('Sortd Pro') }}</p>
        <h1 class="text-3xl font-semibold tracking-tight">{{ __('Welcome to Sortd Pro') }} <span aria-hidden="true">👋</span></h1>
        @if ($status === null || $status === \App\Domain\Pros\Enums\ProStatus::Draft)
            <p class="mt-3 text-lg text-zinc-600">{{ __('Thanks, :name — tell us about your business so we can check your details.', ['name' => $firstName]) }}</p>
            <p class="mt-2 text-zinc-600">{{ __('It takes about 10 minutes. Have your ID, a proof of address and two references ready. You can save and come back.') }}</p>
            <a wire:navigate.hover href="{{ route('pros.apply') }}" class="mt-6 inline-block w-full rounded-lg bg-emerald-700 px-4 py-3 text-center text-lg font-medium text-white hover:bg-emerald-800">
                {{ $status === null ? __('Start your application') : __('Continue your application') }}
            </a>
        @elseif ($status === \App\Domain\Pros\Enums\ProStatus::Approved)
            <p class="mt-3 text-lg text-zinc-600">{{ __("Hi :name, you're approved.", ['name' => $firstName]) }}</p>
            <a wire:navigate.hover href="{{ route('pros.jobs') }}" class="mt-6 inline-block w-full rounded-lg bg-emerald-700 px-4 py-3 text-center text-lg font-medium text-white hover:bg-emerald-800">{{ __('Your jobs') }}</a>
            <a wire:navigate.hover href="{{ route('pros.status') }}" class="mt-3 inline-block text-sm text-emerald-800 underline underline-offset-4">{{ __('Your application and documents') }}</a>
        @else
            <p class="mt-3 text-lg text-zinc-600">{{ __('Thanks, :name. Your application is :status.', ['name' => $firstName, 'status' => mb_strtolower($status->label())]) }}</p>
            <a wire:navigate.hover href="{{ route($canReapply ? 'pros.apply' : 'pros.status') }}" class="mt-6 inline-block w-full rounded-lg bg-emerald-700 px-4 py-3 text-center text-lg font-medium text-white hover:bg-emerald-800">{{ $canReapply ? __('Apply again') : __('Check your application') }}</a>
        @endif

    </section>
</main>
