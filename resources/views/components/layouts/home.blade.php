{{-- Public home page layout (home v3). Everything it loads is self-hosted: the security headers only allow 'self'. --}}
@php($v = '3.1')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $description ?? __('Describe the job, compare quotes from vetted Durban pros, and keep everything in one place.') }}">
        <title>{{ $brand ?? __('Get Sorted') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('home/logo/get-sorted-mark.svg') }}">
        <link rel="icon" href="{{ asset('favicon-v2.png') }}" type="image/png">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
        <meta name="theme-color" content="#F5F5F5">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="preload" href="{{ asset('home/fonts/Satoshi-Black.woff2') }}" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="{{ asset('home/fonts/Satoshi-Bold.woff2') }}" as="font" type="font/woff2" crossorigin>
        <link rel="stylesheet" href="{{ asset('home/vendor/phosphor/bold.css') }}?v={{ $v }}">
        <link rel="stylesheet" href="{{ asset('home/vendor/phosphor/fill.css') }}?v={{ $v }}">
        <link rel="stylesheet" href="{{ asset('home/home.css') }}?v={{ $v }}">
        @livewireStyles
    </head>
    <body>
        {{ $slot }}
        @livewireScripts
        <script src="{{ asset('home/vendor/gsap.min.js') }}" defer></script>
        <script src="{{ asset('home/vendor/ScrollTrigger.min.js') }}" defer></script>
        <script src="{{ asset('home/vendor/SplitText.min.js') }}" defer></script>
        <script src="{{ asset('home/vendor/lenis.min.js') }}" defer></script>
        <script src="{{ asset('home/main.js') }}?v={{ $v }}" defer></script>
        <script src="{{ asset('home/motion.js') }}?v={{ $v }}" defer></script>
    </body>
</html>
