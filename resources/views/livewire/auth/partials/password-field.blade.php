{{-- Password input with show/hide. Params: autocomplete, (optional) label, hint, forgot (bool) --}}
<div class="auth-field" x-data="{ show: false }">
    <label for="password">
        <span class="auth-label-row">
            <span>{{ $label ?? __('Password') }}</span>
            @if ($forgot ?? false)
                <a wire:navigate.hover href="{{ route('password.request') }}" class="auth-link" style="font-size:12px;text-transform:none">{{ __('Forgot password?') }}</a>
            @endif
        </span>
    </label>
    <div class="auth-pw">
        <input id="password" :type="show ? 'text' : 'password'" autocomplete="{{ $autocomplete }}" wire:model="password"
            @class(['auth-input', 'has-error' => $errors->has('password')])
            aria-describedby="password-hint password-error" @error('password') aria-invalid="true" @enderror>
        <button type="button" x-on:click="show = ! show" x-text="show ? '{{ __('Hide') }}' : '{{ __('Show') }}'">{{ __('Show') }}</button>
    </div>
    @isset($hint) <p id="password-hint" class="auth-hint">{{ $hint }}</p> @endisset
    @error('password') <p id="password-error" class="auth-err" role="alert">{{ $message }}</p> @enderror
</div>
