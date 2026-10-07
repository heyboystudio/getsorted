@include('livewire.auth.partials.shell-start')
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Create your client account') }}</h1>
        <p class="mt-2 text-zinc-600">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-medium text-emerald-800 underline underline-offset-4">{{ __('Sign in') }}</a></p>

        <p class="mt-1 text-sm text-zinc-500">{{ __('Looking for work as a tradesperson?') }} <a href="{{ route('pros.register') }}" class="underline underline-offset-4">{{ __('Pros sign up here') }}</a></p>

        @include('livewire.auth.partials.register-form')
    </section>
</main>
