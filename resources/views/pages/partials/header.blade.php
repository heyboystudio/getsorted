<header class="site-header">
    <div class="site-container header-inner">
        <a wire:navigate.hover class="brand" href="{{ route('home') }}" aria-label="Get Sorted home"><img class="brand-logo" src="{{ asset('images/sortd-logo-v2.png') }}" alt="Get Sorted" width="160" height="54"></a>
        <nav class="desktop-nav" aria-label="Main navigation">
            <a wire:navigate.hover href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
            <a wire:navigate.hover href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>About</a>
            <a wire:navigate.hover href="{{ route('customers') }}" @if(request()->routeIs('customers')) aria-current="page" @endif>Customers</a>
            <a wire:navigate.hover href="{{ route('pros.join') }}" @if(request()->routeIs('pros.join')) aria-current="page" @endif>Pros</a>
            <a wire:navigate.hover href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contact</a>
        </nav>
        <div class="header-actions">
            <a wire:navigate.hover class="button button-outline" href="{{ route('login') }}">Sign in</a>
            <a wire:navigate.hover class="button button-dark" href="{{ route('register') }}">Sign up</a>
        </div>
    </div>
    <details class="mobile-menu site-container">
        <summary>Menu <span aria-hidden="true">☰</span></summary>
        <nav aria-label="Mobile navigation">
            <a wire:navigate.hover href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
            <a wire:navigate.hover href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>About</a>
            <a wire:navigate.hover href="{{ route('customers') }}" @if(request()->routeIs('customers')) aria-current="page" @endif>Customers</a>
            <a wire:navigate.hover href="{{ route('pros.join') }}" @if(request()->routeIs('pros.join')) aria-current="page" @endif>Pros</a>
            <a wire:navigate.hover href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contact</a>
        </nav>
    </details>
</header>
