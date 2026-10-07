{{-- Opens the auth page shell (home v3 look). Pages close it with </section></main>. --}}
<main class="auth">
    <div class="auth-circle" aria-hidden="true"></div>
    <div class="auth-pols" aria-hidden="true">
        <figure class="pol a1"><img src="{{ asset('images/home/trade-plumbing-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Plumbing</figcaption></figure>
        <figure class="pol a2"><img src="{{ asset('images/home/trade-electrical-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Electrical</figcaption></figure>
        <figure class="pol a3"><img src="{{ asset('images/home/trade-painting-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Painting</figcaption></figure>
        <figure class="pol a4"><img src="{{ asset('images/home/trade-tiling-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Tiling</figcaption></figure>
    </div>
    <header class="auth-head">
        <a href="{{ route('home') }}" wire:navigate.hover aria-label="{{ __('Get Sorted home') }}"><img src="{{ asset('home/logo/get-sorted-logo.svg') }}" alt="Get Sorted" width="181" height="32"></a>
        <a class="cta-dark" href="{{ route('home') }}" wire:navigate.hover>{{ __('Back to home') }} <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a>
    </header>
    <section class="auth-card">
