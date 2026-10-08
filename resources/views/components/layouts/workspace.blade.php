{{--
    The neutral signed-in workspace for clients and pros (spec 028): a sidebar on desktop, a top bar and bottom tabs on
    phones, Flux components, no marketing styling. Pages pass `panel` (customer|pro) and a `title`.
--}}
@php
    $panel = $panel ?? \App\Support\PanelNavigation::CUSTOMER;
    $user = auth()->user();
    $items = \App\Support\PanelNavigation::items($panel, $user);
    $showNav = \App\Support\PanelNavigation::showsTabs($user, $panel);
    $switch = \App\Support\PanelNavigation::switchTarget($user, $panel);
    $homeRoute = \App\Support\PanelNavigation::homeRoute($panel);
    $icons = ['home' => 'home', 'briefcase' => 'briefcase', 'user' => 'user', 'chat' => 'chat-bubble-left-right', 'map-pin' => 'map-pin'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title.' · '.__('GetSorted') : __('GetSorted') }}</title>
        <link rel="icon" href="{{ asset('favicon-v2.png') }}" type="image/png">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
        <meta name="theme-color" content="#ffffff">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="GetSorted">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key')))
            <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
        @livewireStyles
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
        @if ($showNav)
            <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-white">
                <flux:sidebar.header>
                    <a wire:navigate href="{{ route($homeRoute) }}" class="flex items-center gap-2" aria-label="{{ __('GetSorted') }}">
                        <img src="{{ asset('home/logo/getsorted-logo.svg') }}" alt="" class="h-7 w-auto">
                    </a>
                    <flux:sidebar.collapse class="lg:hidden" />
                </flux:sidebar.header>

                <flux:sidebar.nav>
                    @foreach ($items as $item)
                        <flux:sidebar.item :icon="$icons[$item['icon']] ?? 'squares-2x2'" :href="route($item['route'])" :current="request()->routeIs(...$item['active'])" :badge="$item['badge'] > 0 ? $item['badge'] : null" wire:navigate>{{ $item['label'] }}</flux:sidebar.item>
                    @endforeach
                </flux:sidebar.nav>

                <flux:spacer />

                <flux:sidebar.nav>
                    @if ($switch)
                        <flux:sidebar.item icon="arrows-right-left" :href="route($switch['route'])" wire:navigate>{{ $switch['label'] }}</flux:sidebar.item>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:sidebar.item icon="arrow-right-start-on-rectangle" as="button" type="submit">{{ __('Log out') }}</flux:sidebar.item>
                    </form>
                </flux:sidebar.nav>
            </flux:sidebar>
        @endif

        <flux:header class="border-b border-zinc-200 bg-white {{ $showNav ? 'lg:hidden' : '' }}">
            @if ($showNav)
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" :label="__('Menu')" />
            @endif
            <a wire:navigate href="{{ route($homeRoute) }}" class="ms-2 flex items-center" aria-label="{{ __('GetSorted') }}">
                <img src="{{ asset('home/logo/getsorted-logo.svg') }}" alt="" class="h-6 w-auto">
            </a>
            <flux:spacer />
            <span x-data="pushControl({ mode: 'silent' })" class="hidden" aria-hidden="true"></span>
            <livewire:notification-bell />
        </flux:header>

        <flux:main class="!p-0">
            {{-- Desktop top bar: the bell sits where people look for it. --}}
            @if ($showNav)
                <div class="hidden items-center justify-end gap-3 border-b border-zinc-200 bg-white px-8 py-3 lg:flex">
                    <span x-data="pushControl({ mode: 'silent' })" class="hidden" aria-hidden="true"></span>
                    <livewire:notification-bell />
                </div>
            @endif

            <div @class(['pb-24 lg:pb-8' => $showNav, 'pb-8' => ! $showNav])>
                {{ $slot }}
            </div>
        </flux:main>

        {{-- Phones: thumb-reach tabs, as before. --}}
        @if ($showNav)
            <nav class="fixed inset-x-0 bottom-0 z-30 flex border-t border-zinc-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="{{ __('Main') }}">
                @foreach ($items as $item)
                    @php($current = request()->routeIs(...$item['active']))
                    <a wire:navigate href="{{ route($item['route']) }}" @class(['relative flex flex-1 flex-col items-center gap-0.5 py-2 text-xs', 'font-semibold text-zinc-900' => $current, 'text-zinc-500' => ! $current]) @if ($current) aria-current="page" @endif>
                        <flux:icon :name="$icons[$item['icon']] ?? 'squares-2x2'" variant="{{ $current ? 'solid' : 'outline' }}" class="size-5" />
                        {{ $item['label'] }}
                        @if ($item['badge'] > 0)<span class="absolute right-1/4 top-1 rounded-full bg-zinc-900 px-1.5 text-[10px] text-white">{{ $item['badge'] }}</span>@endif
                    </a>
                @endforeach
            </nav>
        @endif

        <flux:toast />
        @livewireScripts
        @fluxScripts
    </body>
</html>
