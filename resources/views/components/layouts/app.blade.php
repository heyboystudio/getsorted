<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ __('Sortd — local home services in Durban. Coming soon.') }}">
        <title>{{ isset($title) ? $title.' · '.__('Sortd') : __('Sortd') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-stone-50 font-sans text-zinc-900 antialiased">
        @if (\App\Support\AppMode::isPreview())
            <p class="bg-amber-300 px-4 py-2 text-center text-sm font-medium text-amber-950" role="note">{{ __('Test site: fake data only. Please don\'t enter real personal details.') }}</p>
        @endif
        {{ $slot }}
        @livewireScripts
    </body>
</html>
