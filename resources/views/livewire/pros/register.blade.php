@include('livewire.auth.partials.shell-start')
        <span class="auth-kicker"><i class="ph-bold ph-hammer" aria-hidden="true"></i>{{ __('Pro account') }}</span>
        <h1 class="auth-title">{{ __('Join GetSorted as a pro') }}</h1>
        <p class="auth-sub">{{ __('Already have an account?') }} <a href="{{ route('login', ['as' => 'pro']) }}">{{ __('Sign in') }}</a></p>
        <p class="auth-small">{{ __('A pro account is separate from a client account. Use an email address that is not already registered as a client.') }}</p>

        @include('livewire.auth.partials.register-form')

        <p class="auth-foot"><span>{{ __('Need a tradesperson instead?') }}</span> <a class="auth-link" href="{{ route('register') }}">{{ __('Client sign up') }}</a></p>
    </section>
</main>
