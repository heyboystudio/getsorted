<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $description ?? __('GetSorted connects Durban homes with local tradespeople for plumbing, electrical, painting and tiling work.') }}">
        <title>{{ isset($title) ? $title.' · '.($brand ?? __('GetSorted')) : ($brand ?? __('GetSorted')) }}</title>
        <link rel="icon" href="{{ asset('favicon-v2.png') }}" type="image/png">
        {{-- Installable site and pop-up notifications (spec 022). --}}
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
        <meta name="theme-color" content="{{ ($gs ?? false) ? '#F5F5F5' : '#047857' }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="GetSorted">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key')))
            <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @if ($gs ?? false)
            <link rel="stylesheet" href="{{ asset('home/panel.css') }}?v=3.5">
        @endif
        @livewireStyles
    </head>
    <body @class(['gs-ui antialiased' => $gs ?? false, 'bg-stone-50 font-sans text-zinc-900 antialiased' => ! ($gs ?? false)])>
        @auth
            @unless (($hideInboxNav ?? false) || request()->routeIs('notifications', 'messages'))
                @php
                    $unreadChats = \App\Domain\ServiceJobs\Support\JobChat::unreadTotal(auth()->user());
                @endphp
                <nav class="fixed right-4 top-4 z-40 flex items-center gap-2" aria-label="{{ __('Inbox') }}">
                    <a href="{{ route('messages') }}" wire:navigate class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-3 py-2 text-sm shadow-sm" aria-label="{{ __('Messages') }}">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.5A8 8 0 1 1 21 12Z"/></svg>
                        @if ($unreadChats > 0)<span class="rounded-full bg-emerald-700 px-2 text-xs font-medium text-white">{{ $unreadChats }}</span>@endif
                    </a>
                    <livewire:notification-bell />
                </nav>
            @endunless
        @endauth
        {{ $slot }}
        @livewireScripts
    </body>
</html>
