{{-- One labelled input with its error. Params: name, label, type, autocomplete, (optional) readonly, hint --}}
<div class="auth-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" type="{{ $type ?? 'text' }}" autocomplete="{{ $autocomplete ?? 'off' }}" wire:model="{{ $name }}" @if ($readonly ?? false) readonly @endif
        @class(['auth-input', 'has-error' => $errors->has($name)])
        aria-describedby="{{ $name }}-error" @error($name) aria-invalid="true" @enderror>
    @isset($hint) <p class="auth-hint">{{ $hint }}</p> @endisset
    @error($name) <p id="{{ $name }}-error" class="auth-err" role="alert">{{ $message }}</p> @enderror
</div>
