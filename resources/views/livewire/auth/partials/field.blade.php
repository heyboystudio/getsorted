{{-- One labelled input with its error. Params: name, label, type, autocomplete, (optional) readonly, hint --}}
<div>
    <label for="{{ $name }}" class="block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" type="{{ $type ?? 'text' }}" autocomplete="{{ $autocomplete ?? 'off' }}" wire:model="{{ $name }}" @if ($readonly ?? false) readonly @endif
        @class(['mt-1 block w-full rounded-lg border bg-white px-3 py-3 outline-none focus:ring-2 focus:ring-emerald-600', 'bg-zinc-100 text-zinc-600' => $readonly ?? false, 'border-red-500' => $errors->has($name), 'border-zinc-300' => ! $errors->has($name)])
        aria-describedby="{{ $name }}-error" @error($name) aria-invalid="true" @enderror>
    @isset($hint) <p class="mt-1 text-xs text-zinc-500">{{ $hint }}</p> @endisset
    @error($name) <p id="{{ $name }}-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
</div>
