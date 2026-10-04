@include('livewire.auth.partials.shell-start')
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Reset your password') }}</h1>
        @if ($sent)
            <p class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-emerald-900" role="status">{{ __('If that email has an account, we\'ve sent a link to reset your password. It works for 60 minutes.') }}</p>
        @else
            <p class="mt-2 text-zinc-600">{{ __('Enter your email and we\'ll send you a link.') }}</p>
            <form wire:submit="send" class="mt-8 space-y-4" novalidate>
                @include('livewire.auth.partials.field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'autocomplete' => 'email'])
                <button type="submit" wire:loading.attr="disabled" wire:target="send" class="flex w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">{{ __('Send reset link') }}</button>
            </form>
        @endif
        <a href="{{ route('login') }}" class="mt-6 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Back to sign in') }}</a>
    </section>
</main>
