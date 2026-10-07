@include('livewire.auth.partials.shell-start')
        <p class="auth-step"><b>1 / 2</b>{{ __('Step 1 of 2') }}</p>
        <h1 class="auth-title">{{ __('Check your email') }}</h1>
        <p class="auth-sub">{{ __("We sent a link to :email. Open it to confirm your address, then we'll verify your mobile.", ['email' => $email]) }}</p>

        <div wire:poll.5s="check" class="auth-form">
            @if ($resent)
                <p class="auth-note" role="status"><i class="ph-bold ph-check-circle" aria-hidden="true"></i>{{ __('We sent a new link.') }}</p>
            @endif
            @error('resend') <p class="auth-err" role="alert">{{ $message }}</p> @enderror
            <button type="button" wire:click="resend" wire:loading.attr="disabled" class="auth-ghost">{{ __('Send the link again') }}</button>
            <p class="auth-small">{{ __("Can't find it? Check your spam folder.") }}</p>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="auth-foot">
            @csrf
            <button type="submit" class="auth-text-btn">{{ __('Use a different account') }}</button>
        </form>
    </section>
</main>
