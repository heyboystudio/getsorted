@include('livewire.auth.partials.shell-start')
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Join Get Sorted as a pro') }}</h1>
        <p class="mt-2 text-zinc-600">{{ __('Already have an account?') }} <a href="{{ route('login', ['as' => 'pro']) }}" class="font-medium text-emerald-800 underline underline-offset-4">{{ __('Sign in') }}</a></p>

        <p class="mt-1 text-sm text-zinc-500">{{ __('A pro account is separate from a client account. Use an email address that is not already registered as a client.') }}</p>

        @include('livewire.auth.partials.register-form')
    </section>
</main>
