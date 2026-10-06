<x-layouts.app :title="__('Contact')" :brand="__('Get Sorted')">
<div class="gs-home gs-contact" :class="{ 'gs-collapsed': collapsed }" x-data="{ collapsed: (() => { try { const v = localStorage.getItem('gs-side'); return v === null ? true : v === '1'; } catch (e) { return true; } })(), toggle() { this.collapsed = !this.collapsed; try { localStorage.setItem('gs-side', this.collapsed ? '1' : '0'); } catch (e) {} } }">
    @vite('resources/css/home.css')
    <link rel="stylesheet" href="{{ asset('fonts/phosphor/phosphor.css') }}">
    <a class="gs-skip" href="#main">Skip to content</a>
    <x-get-sorted-sidebar :trade-links="[
        ['name' => 'Plumbing', 'icon' => 'drop', 'url' => route('book.trade', ['trade' => 'plumbing'])],
        ['name' => 'Electrical', 'icon' => 'lightning', 'url' => route('book.trade', ['trade' => 'electrical'])],
        ['name' => 'Painting', 'icon' => 'paint-roller', 'url' => route('book.trade', ['trade' => 'painting'])],
        ['name' => 'Tiling', 'icon' => 'squares-four', 'url' => route('book.trade', ['trade' => 'tiling'])],
    ]" />
    <div class="gs-main">
        <header class="gs-topbar">
            <a class="gs-logo" href="{{ route('home') }}" wire:navigate.hover aria-label="Get Sorted home"><span class="gs-logo-mark"><i class="ph-bold ph-check" aria-hidden="true"></i></span> Get Sorted</a>
            <a class="gs-btn gs-btn-white" href="{{ route('book') }}" wire:navigate.hover>New job</a>
        </header>
        <main id="main" class="gs-wrap">
            <section class="gs-contact-hero" aria-labelledby="contact-title">
                <p class="gs-contact-kicker">GET IN TOUCH</p>
                <h1 class="gs-serif" id="contact-title">Let’s talk about<br>what you need.</h1>
                <p class="gs-contact-intro">Whether you’re caring for your home or growing your trade business, we’ll help you find the right next step.</p>
            </section>
            <section class="gs-contact-grid" aria-label="How can we help?">
                <article class="gs-contact-card">
                    <i class="ph ph-house-line gs-contact-icon" aria-hidden="true"></i>
                    <p class="gs-contact-kicker">FOR HOMEOWNERS</p>
                    <h2 class="gs-serif">A little help at home.</h2>
                    <p>Explore the customer experience, see our launch trades and create an account when you’re ready.</p>
                    <a class="gs-btn gs-btn-white" href="{{ route('customers') }}" wire:navigate.hover>For customers <i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
                </article>
                <article class="gs-contact-card">
                    <i class="ph ph-hard-hat gs-contact-icon" aria-hidden="true"></i>
                    <p class="gs-contact-kicker">FOR TRADESPEOPLE</p>
                    <h2 class="gs-serif">Grow with Get Sorted.</h2>
                    <p>Learn how pro applications work and sign up to tell us about your trade.</p>
                    <a class="gs-btn" href="{{ route('pros.join') }}" wire:navigate.hover>For pros <i class="ph ph-arrow-up-right" aria-hidden="true"></i></a>
                </article>
            </section>
            <section class="gs-contact-direct" aria-labelledby="enquiries-title">
                <div><p class="gs-contact-kicker">GENERAL ENQUIRIES</p><h2 class="gs-serif" id="enquiries-title">Let’s keep in touch.</h2><p>Questions about Get Sorted? Reach us using the details below.</p></div>
                <dl class="gs-contact-details">
                    <div><dt><i class="ph ph-envelope" aria-hidden="true"></i> Email</dt><dd><a href="mailto:hello@sortd.heyboy.co.za">hello@sortd.heyboy.co.za <i class="ph ph-arrow-up-right" aria-hidden="true"></i></a></dd></div>
                    <div><dt><i class="ph ph-phone" aria-hidden="true"></i> Phone</dt><dd>031 000 0000</dd></div>
                </dl>
            </section>
            <footer class="gs-contact-footer"><span>© 2026 Get Sorted · Durban, South Africa</span><nav aria-label="Legal"><a href="{{ route('terms') }}" wire:navigate.hover>Terms</a><a href="{{ route('privacy') }}" wire:navigate.hover>Privacy</a></nav></footer>
        </main>
    </div>
</div>
</x-layouts.app>
