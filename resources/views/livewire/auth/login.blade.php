@include('livewire.auth.partials.shell-start')
        <span class="auth-kicker"><i class="ph-bold ph-sign-in" aria-hidden="true"></i>{{ $asPro ? __('Pro sign in') : __('Welcome back') }}</span>
        <h1 class="auth-title">{{ $asPro ? __('Sign in to join as a pro.') : __('Sign in.') }}</h1>
        <p class="auth-sub">{{ __('New to GetSorted?') }} <a href="{{ route($asPro ? 'pros.register' : 'register') }}">{{ $asPro ? __('Create a pro account') : __('Create an account') }}</a></p>

        @if (session('status'))
            <p class="auth-note" role="status"><i class="ph-bold ph-info" aria-hidden="true"></i>{{ session('status') }}</p>
        @endif

        @include('livewire.auth.partials.google-button')

        <form wire:submit="login" class="auth-form" novalidate>
            @include('livewire.auth.partials.field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'autocomplete' => 'email', 'readonly' => $linkingGoogle])
            @include('livewire.auth.partials.password-field', ['autocomplete' => 'current-password', 'forgot' => true])

            <div class="auth-checks">
                <label class="auth-check"><input type="checkbox" wire:model="remember"><span>{{ __('Keep me signed in for 30 days') }}</span></label>
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="login" class="btn-lime auth-submit">
                <span wire:loading.remove wire:target="login">{{ $linkingGoogle ? __('Sign in and connect Google') : __('Sign in') }}</span>
                <span wire:loading wire:target="login">{{ __('Signing in…') }}</span>
                <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            </button>
        </form>

        <p class="auth-foot"><span>{{ __('Looking for work as a tradesperson?') }}</span> <a class="auth-link" href="{{ route('pros.register') }}">{{ __('Join as a pro') }}</a></p>
    </section>
</main>
