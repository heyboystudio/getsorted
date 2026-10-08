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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full ws-dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title.' · '.__('GetSorted') : __('GetSorted') }}</title>
        <link rel="icon" href="{{ asset('favicon-v2.png') }}" type="image/png">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
        <meta name="theme-color" content="#13151a">
        {{-- Dark by default; the toggle in the top bar remembers a light choice (spec 028). Runs before paint so nothing flashes. --}}
        <script>try{var t=localStorage.getItem('gs-theme')||'dark';var c=document.documentElement.classList;c.remove('ws-dark','ws-light');c.add(t==='light'?'ws-light':'ws-dark')}catch(e){}</script>
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="GetSorted">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key')))
            <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
        @if ($showNav)
            <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-white">
                <flux:sidebar.header>
                    <a wire:navigate href="{{ route($homeRoute) }}" class="flex items-center gap-2" aria-label="{{ __('GetSorted') }}">
                        <img src="{{ asset('home/logo/getsorted-logo.svg') }}" alt="" class="h-7 w-auto ws-logo-light">
                        <img src="{{ asset('home/logo/getsorted-logo-inverse.svg') }}" alt="" class="h-7 w-auto ws-logo-dark">
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
                    <flux:sidebar.collapse class="max-lg:hidden" />
                </flux:sidebar.nav>
            </flux:sidebar>
        @endif

        <flux:header class="border-b border-zinc-200 bg-white">
            @if ($showNav)
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" :label="__('Menu')" />
            @endif
            <a wire:navigate href="{{ route($homeRoute) }}" @class(['ms-2 flex items-center', 'lg:hidden' => $showNav]) aria-label="{{ __('GetSorted') }}">
                <img src="{{ asset('home/logo/getsorted-logo.svg') }}" alt="" class="h-6 w-auto ws-logo-light">
                <img src="{{ asset('home/logo/getsorted-logo-inverse.svg') }}" alt="" class="h-6 w-auto ws-logo-dark">
            </a>
            <flux:spacer />
            <span x-data="pushControl({ mode: 'silent' })" class="hidden" aria-hidden="true"></span>

            {{-- Light / dark, remembered on this device. --}}
            <button type="button" x-data="{ dark: document.documentElement.classList.contains('ws-dark') }"
                x-on:click="dark = ! dark; document.documentElement.classList.toggle('ws-dark', dark); document.documentElement.classList.toggle('ws-light', ! dark); try { localStorage.setItem('gs-theme', dark ? 'dark' : 'light') } catch (e) {}"
                class="mr-1 inline-flex size-9 items-center justify-center rounded-lg text-zinc-600 hover:bg-zinc-100" aria-label="{{ __('Switch between light and dark') }}">
                <flux:icon name="sun" class="size-5" x-show="dark" />
                <flux:icon name="moon" class="size-5" x-show="! dark" x-cloak />
            </button>

            <livewire:notification-bell />

            @if ($user)
                <flux:dropdown position="bottom" align="end">
                    <button type="button" class="ml-2 inline-flex size-9 items-center justify-center rounded-full bg-[#c6fd50] text-sm font-bold text-[#131311]" aria-label="{{ __('Your account') }}">{{ mb_strtoupper(mb_substr((string) $user->first_name, 0, 1)) }}</button>
                    <flux:menu>
                        <div class="px-3 py-2 text-sm"><span class="block font-medium">{{ $user->first_name }} {{ $user->last_name }}</span><span class="block text-xs text-zinc-500">{{ $user->email }}</span></div>
                        <flux:menu.separator />
                        <flux:menu.item icon="user" :href="route($panel === 'pro' ? 'pros.profile' : 'account.settings')" wire:navigate>{{ __('Account') }}</flux:menu.item>
                        @if ($switch)<flux:menu.item icon="arrows-right-left" :href="route($switch['route'])" wire:navigate>{{ $switch['label'] }}</flux:menu.item>@endif
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:menu.item icon="arrow-right-start-on-rectangle" as="button" type="submit">{{ __('Log out') }}</flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @endif
        </flux:header>

        <flux:main class="!p-0">
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
                        @if ($item['badge'] > 0)<span class="absolute right-1/4 top-1 rounded-full bg-zinc-900 px-1.5 text-[10px] text-white"><span class="sr-only">{{ __('Unread:') }} </span>{{ $item['badge'] }}</span>@endif
                    </a>
                @endforeach
            </nav>
        @endif

        <flux:toast />
        @livewireScripts
        @fluxScripts
    </body>
</html>
