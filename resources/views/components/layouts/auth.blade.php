{{-- Sign-in, sign-up and verification pages (home v3 look). Everything it loads is self-hosted: the security headers only allow 'self'. --}}
@php($v = '3.3')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @isset($description)
        <meta name="description" content="{{ $description }}">
        @endisset
        <title>{{ isset($title) ? $title.' · Get Sorted' : 'Get Sorted' }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('home/logo/get-sorted-mark.svg') }}">
        <link rel="icon" href="{{ asset('favicon-v2.png') }}" type="image/png">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
        <meta name="theme-color" content="#F5F5F5">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key')))
            <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
        @endif
        <link rel="preload" href="{{ asset('home/fonts/Satoshi-Black.woff2') }}" as="font" type="font/woff2" crossorigin>
        <link rel="stylesheet" href="{{ asset('home/lib/phosphor/bold.css') }}?v={{ $v }}">
        <link rel="stylesheet" href="{{ asset('home/home.css') }}?v={{ $v }}">
        <link rel="stylesheet" href="{{ asset('home/auth.css') }}?v={{ $v }}">
        @isset($extraCss)
            <link rel="stylesheet" href="{{ asset('home/'.$extraCss) }}?v={{ $v }}">
        @endisset
        @livewireStyles
    </head>
    <body class="auth-body">
        {{ $slot }}
        @livewireScripts
    </body>
</html>
