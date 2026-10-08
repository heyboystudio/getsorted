@props(['current'])
{{-- The Account area (spec 028): a side menu on desktop, pills on phones, and the page beside it. --}}
@php
    $links = [
        ['account.settings', __('Overview'), 'squares-2x2'],
        ['account.profile', __('Profile'), 'user'],
        ['properties.index', __('Properties'), 'map-pin'],
        ['account.notifications', __('Notifications'), 'bell'],
        ['account.privacy', __('Privacy and data'), 'shield-check'],
    ];
@endphp
<div class="mx-auto w-full max-w-6xl px-4 py-6 lg:px-8">
    <p class="text-xs font-medium uppercase tracking-widest text-zinc-500">{{ __('Account') }}</p>
    <div class="mt-4 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-10">
        <nav aria-label="{{ __('Account') }}" class="-mx-4 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:flex-col lg:gap-1 lg:overflow-visible lg:px-0">
            @foreach ($links as [$route, $label, $icon])
                <a wire:navigate.hover href="{{ route($route) }}" @class(['flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm', 'bg-zinc-900 font-medium text-white' => $current === $route, 'text-zinc-700 hover:bg-zinc-200/60' => $current !== $route]) @if ($current === $route) aria-current="page" @endif>
                    <flux:icon :name="$icon" class="size-4" />{{ $label }}
                </a>
            @endforeach
            <a href="{{ route('contact') }}" class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm text-zinc-700 hover:bg-zinc-200/60"><flux:icon name="lifebuoy" class="size-4" />{{ __('Help') }}</a>
        </nav>
        <div class="min-w-0 max-w-2xl">{{ $slot }}</div>
    </div>
</div>
