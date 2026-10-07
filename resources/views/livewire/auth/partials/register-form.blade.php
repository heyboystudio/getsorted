@unless ($withGoogle)
    @include('livewire.auth.partials.google-button', ['intent' => 'register'])
@else
    <p class="auth-note"><i class="ph-bold ph-google-logo" aria-hidden="true"></i>{{ __('Signing up with Google. Check your name and accept the terms to finish.') }}</p>
@endunless

<form wire:submit="register" class="auth-form" novalidate>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        @include('livewire.auth.partials.field', ['name' => 'firstName', 'label' => __('First name'), 'autocomplete' => 'given-name'])
        @include('livewire.auth.partials.field', ['name' => 'lastName', 'label' => __('Surname'), 'autocomplete' => 'family-name'])
    </div>
    @include('livewire.auth.partials.field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'autocomplete' => 'email', 'readonly' => $withGoogle])

    @unless ($withGoogle)
        @include('livewire.auth.partials.password-field', ['autocomplete' => 'new-password', 'hint' => __('At least 10 characters.')])
    @endunless

    <div class="auth-checks">
        <label class="auth-check">
            <input type="checkbox" wire:model="acceptTerms">
            <span>{{ __('I accept the') }} <a href="{{ route('terms') }}" target="_blank">{{ __('terms of service') }}</a></span>
        </label>
        @error('acceptTerms') <p class="auth-err" role="alert">{{ $message }}</p> @enderror

        <label class="auth-check">
            <input type="checkbox" wire:model="acceptPrivacy">
            <span>{{ __('I accept the') }} <a href="{{ route('privacy') }}" target="_blank">{{ __('privacy notice') }}</a></span>
        </label>
        @error('acceptPrivacy') <p class="auth-err" role="alert">{{ $message }}</p> @enderror

        @if ($asPro)
            <label class="auth-check">
                <input type="checkbox" wire:model="acceptProAgreement">
                <span>{{ __('I accept the') }} <a href="{{ route('pros.agreement') }}" target="_blank">{{ __('pro agreement') }}</a></span>
            </label>
            @error('acceptProAgreement') <p class="auth-err" role="alert">{{ $message }}</p> @enderror
        @endif

        <label class="auth-check muted">
            <input type="checkbox" wire:model="marketing">
            <span>{{ __('Send me tips and offers (optional)') }}</span>
        </label>
    </div>

    <button type="submit" wire:loading.attr="disabled" wire:target="register" class="btn-lime auth-submit">
        <span wire:loading.remove wire:target="register">{{ $asPro ? __('Create pro account') : __('Create client account') }}</span>
        <span wire:loading wire:target="register">{{ __('Creating…') }}</span>
        <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
    </button>
    <p class="auth-small" style="text-align:center">{{ __("Next, we'll confirm your email and mobile number.") }}</p>
</form>
