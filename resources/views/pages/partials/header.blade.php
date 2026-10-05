<header class="site-header">
    <div class="site-container header-inner">
        <a class="brand" href="{{ route('home') }}" aria-label="Sortd home"><img class="brand-logo" src="{{ asset('images/sortd-logo-v2.png') }}" alt="Sortd" width="160" height="54"></a>
        <nav class="desktop-nav" aria-label="Main navigation">
            <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
            <a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>About</a>
            <a href="{{ route('customers') }}" @if(request()->routeIs('customers')) aria-current="page" @endif>Customers</a>
            <a href="{{ route('pros.join') }}" @if(request()->routeIs('pros.join')) aria-current="page" @endif>Pros</a>
            <a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contact</a>
        </nav>
        <div class="header-actions">
            <a class="button button-outline" href="{{ route('login') }}">Sign in</a>
            <a class="button button-dark" href="{{ route('register') }}">Sign up</a>
        </div>
    </div>
    <details class="mobile-menu site-container">
        <summary>Menu <span aria-hidden="true">☰</span></summary>
        <nav aria-label="Mobile navigation">
            <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
            <a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>About</a>
            <a href="{{ route('customers') }}" @if(request()->routeIs('customers')) aria-current="page" @endif>Customers</a>
            <a href="{{ route('pros.join') }}" @if(request()->routeIs('pros.join')) aria-current="page" @endif>Pros</a>
            <a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contact</a>
        </nav>
    </details>
</header>
