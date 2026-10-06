<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $description ?? __('Sortd connects Durban homes with local tradespeople for plumbing, electrical, painting and tiling work.') }}">
        <title>{{ isset($title) ? $title.' · '.($brand ?? __('Sortd')) : ($brand ?? __('Sortd')) }}</title>
        <link rel="icon" href="{{ asset('favicon-v2.png') }}" type="image/png">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-stone-50 font-sans text-zinc-900 antialiased">
        @auth
            @unless (request()->routeIs('notifications', 'messages'))
                @php
                    $unreadNotices = auth()->user()->unreadNotifications()->count();
                    $unreadChats = \App\Domain\ServiceJobs\Support\JobChat::unreadTotal(auth()->user());
                @endphp
                <nav class="fixed right-4 top-4 z-40 flex items-center gap-2" aria-label="{{ __('Inbox') }}">
                    <a href="{{ route('messages') }}" wire:navigate class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-3 py-2 text-sm shadow-sm" aria-label="{{ __('Messages') }}">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.5A8 8 0 1 1 21 12Z"/></svg>
                        @if ($unreadChats > 0)<span class="rounded-full bg-emerald-700 px-2 text-xs font-medium text-white">{{ $unreadChats }}</span>@endif
                    </a>
                    <a href="{{ route('notifications') }}" wire:navigate class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-3 py-2 text-sm shadow-sm" aria-label="{{ __('Notifications') }}">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21a2 2 0 0 0 4 0"/></svg>
                        @if ($unreadNotices > 0)<span class="rounded-full bg-emerald-700 px-2 text-xs font-medium text-white">{{ $unreadNotices }}</span>@endif
                    </a>
                </nav>
            @endunless
        @endauth
        {{ $slot }}
        @livewireScripts
    </body>
</html>
