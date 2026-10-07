<x-layouts.auth :title="__('Join as a pro')" extra-css="pages.css" :description="__('Get matched with Durban homes that need your trade. Choose the jobs you want, set your own prices and keep the details together.')">
<header class="pg-head">
    <a href="{{ route('home') }}" aria-label="{{ __('Get Sorted home') }}"><img src="{{ asset('home/logo/get-sorted-logo.svg') }}" alt="Get Sorted" width="181" height="32"></a>
    <div class="pg-right">
        <nav class="nav" aria-label="{{ __('Main') }}">
            <a href="{{ route('home') }}">{{ __('Home') }}</a>
            <a href="{{ route('about') }}">{{ __('About') }}</a>
            <a href="{{ route('customers') }}">{{ __('Customers') }}</a>
            <a href="{{ route('pros.join') }}" aria-current="page">{{ __('Pros') }}</a>
            <a href="{{ route('contact') }}">{{ __('Contact') }}</a>
        </nav>
        @auth
            <a class="cta-dark" href="{{ route('account.home') }}">{{ __('My account') }} <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a>
        @else
            <a class="cta-dark" href="{{ route('login', ['as' => 'pro']) }}">{{ __('Sign in') }} <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a>
        @endauth
    </div>
</header>

<main class="pg">
    <section class="pg-hero">
        <div class="auth-circle" aria-hidden="true"></div>
        <div class="auth-pols" aria-hidden="true">
            <figure class="pol a1"><img src="{{ asset('images/home/trade-electrical-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Electrical</figcaption></figure>
            <figure class="pol a2"><img src="{{ asset('images/home/trade-plumbing-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Plumbing</figcaption></figure>
            <figure class="pol a3"><img src="{{ asset('images/home/trade-tiling-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Tiling</figcaption></figure>
            <figure class="pol a4"><img src="{{ asset('images/home/trade-painting-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Painting</figcaption></figure>
        </div>
        <div class="pg-hero-in">
            <span class="auth-kicker"><i class="ph-bold ph-hammer" aria-hidden="true"></i>{{ __('For Durban tradespeople') }}</span>
            <h1 class="pg-h1">{{ __('Do good work.') }}<br>{{ __('Grow your way.') }}</h1>
            <p class="pg-sub">{{ __('Meet local clients looking for the skills you bring. Choose the jobs you want, set your own prices and keep the details together.') }}</p>
            @auth
                <p class="auth-note warn"><i class="ph-bold ph-info" aria-hidden="true"></i><span>{{ __("You're signed in with a client account. Pro accounts are separate: sign out, then create a pro account with a different email.") }}</span></p>
                <form method="post" action="{{ route('logout') }}" class="pg-actions">
                    @csrf
                    <button class="btn-lime" type="submit">{{ __('Sign out') }} <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></button>
                </form>
            @else
                <div class="pg-actions">
                    <a class="btn-lime" href="{{ route('pros.register') }}">{{ __('Sign up as a pro') }} <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a>
                    <a class="pg-outline" href="{{ route('login', ['as' => 'pro']) }}">{{ __('Pro sign in') }}</a>
                </div>
            @endauth
            <p class="guarantee"><svg class="tick" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="8" fill="currentColor"/><path d="m4.8 8.2 2.1 2.1 4.3-4.5" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('Free to join') }}</p>
        </div>
    </section>

    <section class="pg-sec">
        <p class="kicker">{{ __('Why join Get Sorted') }}</p>
        <h2 class="pg-h2">{{ __('Built around the way you work.') }}</h2>
        <div class="pg-cards">
            <article><span>01</span><h3>{{ __('Relevant local jobs') }}</h3><p>{{ __('See opportunities that fit the services you offer and the areas you cover.') }}</p></article>
            <article><span>02</span><h3>{{ __('Your quote, your price') }}</h3><p>{{ __('Decide which jobs to quote and present clear labour, material and call-out costs.') }}</p></article>
            <article><span>03</span><h3>{{ __('One place to follow up') }}</h3><p>{{ __('Keep track of invitations, quotes and job progress from your pro account.') }}</p></article>
        </div>
    </section>

    <section class="pg-sec">
        <div class="pg-dark">
            <div>
                <p class="kicker light">{{ __('Joining is simple') }}</p>
                <h2 class="pg-h2 light">{{ __('How joining works.') }}</h2>
            </div>
            <ol class="pg-steps">
                <li><span>01</span><div><h3>{{ __('Create your account') }}</h3><p>{{ __('Sign up with email or Google, then verify your email and South African mobile.') }}</p></div></li>
                <li><span>02</span><div><h3>{{ __('Tell us about your work') }}</h3><p>{{ __('Complete your pro application with your business, services and coverage areas.') }}</p></div></li>
                <li><span>03</span><div><h3>{{ __('Go through review') }}</h3><p>{{ __('Get Sorted checks identity, relevant registrations and references before approving pros.') }}</p></div></li>
            </ol>
        </div>
    </section>

    <section class="pg-sec pg-final">
        <p class="kicker">{{ __('Ready to join?') }}</p>
        <h2 class="pg-h2">{{ __("Let's get to work.") }}</h2>
        @auth
            <form method="post" action="{{ route('logout') }}">@csrf<button class="btn-lime" type="submit">{{ __('Sign out to create a pro account') }} <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></button></form>
        @else
            <a class="btn-lime" href="{{ route('pros.register') }}">{{ __('Sign up as a pro') }} <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a>
        @endauth
    </section>
</main>

<footer class="pg-foot">
    <img src="{{ asset('home/logo/get-sorted-logo-inverse.svg') }}" alt="Get Sorted" width="150" height="26">
    <nav aria-label="{{ __('Footer') }}">
        <a href="{{ route('terms') }}">{{ __('Terms') }}</a>
        <a href="{{ route('privacy') }}">{{ __('Privacy') }}</a>
        <a href="{{ route('pros.agreement') }}">{{ __('Pro agreement') }}</a>
        <a href="{{ route('contact') }}">{{ __('Contact') }}</a>
    </nav>
</footer>
</x-layouts.auth>
