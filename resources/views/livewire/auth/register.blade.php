@include('livewire.auth.partials.shell-start')
        <span class="auth-kicker"><i class="ph-bold ph-house-line" aria-hidden="true"></i>{{ __('Client account') }}</span>
        <h1 class="auth-title">{{ __('Create your client account') }}</h1>
        <p class="auth-sub">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Sign in') }}</a></p>

        @include('livewire.auth.partials.register-form')

        <p class="auth-foot"><span>{{ __('Looking for work as a tradesperson?') }}</span> <a class="auth-link" href="{{ route('pros.register') }}">{{ __('Pros sign up here') }}</a></p>
    </section>
</main>
