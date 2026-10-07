@include('livewire.auth.partials.shell-start')
        <span class="auth-kicker"><i class="ph-bold ph-key" aria-hidden="true"></i>{{ __('Password help') }}</span>
        <h1 class="auth-title">{{ __('Reset your password') }}</h1>
        @if ($sent)
            <p class="auth-note" role="status"><i class="ph-bold ph-check-circle" aria-hidden="true"></i>{{ __("If that email has an account, we've sent a link to reset your password. It works for 60 minutes.") }}</p>
        @else
            <p class="auth-sub">{{ __("Enter your email and we'll send you a link.") }}</p>
            <form wire:submit="send" class="auth-form" novalidate>
                @include('livewire.auth.partials.field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'autocomplete' => 'email'])
                <button type="submit" wire:loading.attr="disabled" wire:target="send" class="btn-lime auth-submit">
                    <span wire:loading.remove wire:target="send">{{ __('Send reset link') }}</span>
                    <span wire:loading wire:target="send">{{ __('Sending…') }}</span>
                    <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                </button>
            </form>
        @endif
        <p class="auth-foot"><a wire:navigate.hover href="{{ route('login') }}" class="auth-link">← {{ __('Back to sign in') }}</a></p>
    </section>
</main>
