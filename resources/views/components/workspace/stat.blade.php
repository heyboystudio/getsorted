@props(['icon', 'label', 'value', 'note' => null, 'href' => null, 'action' => null])
{{-- A small summary card: icon chip, label, big value, a note and one link (spec 028). --}}
<div {{ $attributes->class('flex flex-col rounded-xl border border-zinc-200 bg-white p-5') }}>
    <div class="flex items-center gap-3 text-sm text-zinc-600">
        <span class="ws-chip"><flux:icon :name="$icon" class="size-4" /></span>
        {{ $label }}
    </div>
    <p class="mt-4 text-3xl font-semibold tracking-tight text-zinc-900">{{ $value }}</p>
    @if ($note)<p class="mt-1 text-sm text-zinc-500">{{ $note }}</p>@endif
    @if ($href && $action)
        <div class="mt-4 border-t border-zinc-200 pt-3 text-right text-sm"><a wire:navigate href="{{ $href }}" class="ws-link hover:underline">{{ $action }}</a></div>
    @endif
</div>
