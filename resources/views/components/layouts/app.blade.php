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
        {{ $slot }}
        @livewireScripts
    </body>
</html>
