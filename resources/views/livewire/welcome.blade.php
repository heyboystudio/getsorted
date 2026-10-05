<div class="sortd-site">
    @include('pages.partials.header')

    <main>
        <section class="hero" aria-labelledby="welcome-title">
            <div class="site-container hero-grid">
                <div class="hero-copy">
                    <div class="eyebrow"><span class="eyebrow-line"></span> GOOD PEOPLE. GOOD WORK. SORTD.</div>
                    <h1 id="welcome-title">Home jobs,<br><em>handled</em> properly<span class="hero-period">.</span></h1>
                    <p class="hero-intro">Find trusted local tradespeople for the jobs that matter. Clear quotes, considered choices, and everything in one place.</p>
                    <div class="hero-actions">
                        <a class="button button-green" href="{{ route('assistant') }}">Get help with a job <span aria-hidden="true">↗</span></a>
                        <a class="button button-outline" href="{{ route('register') }}">Sign up free</a>
                        <a class="button button-outline" href="#how-it-works">See how it works <span aria-hidden="true">↓</span></a>
                    </div>
                    <div class="hero-proof"><p>Made for homes and local pros <strong>across Durban.</strong></p></div>
                </div>
                <div class="hero-art"><img class="hero-photo" src="{{ asset('images/hero-home.webp') }}" alt="Illustrative image of a welcoming Durban home surrounded by greenery" width="1024" height="1536" fetchpriority="high"></div>
            </div>
        </section>

        <div class="trust-strip"><div class="site-container trust-inner"><span>MADE FOR REAL LIFE</span><strong>Local people</strong><i></i><strong>Clear choices</strong><i></i><strong>One easy place</strong></div></div>

        <section class="section section-how" id="how-it-works" aria-labelledby="how-title"><div class="site-container">
            <div class="section-heading"><div><p class="section-kicker">THE SORTD WAY</p><h2 id="how-title">A better way to get<br><em>things done.</em></h2></div><p>From the little fixes to the big projects, finding the right person should feel simple.</p></div>
            <div class="steps"><article class="step"><span class="step-number">01 /</span><div class="step-icon">⌕</div><h3>Tell us what you need</h3><p>Choose the kind of work you're planning and share the details when you're ready.</p></article><article class="step"><span class="step-number">02 /</span><div class="step-icon">✦</div><h3>Meet local pros</h3><p>Get connected with vetted tradespeople who work in your area.</p></article><article class="step"><span class="step-number">03 /</span><div class="step-icon">✓</div><h3>Choose with confidence</h3><p>Compare clear quotes and pick the pro who feels right for your home.</p></article></div>
        </div></section>

        <section class="section section-trades" id="trades" aria-labelledby="trades-title"><div class="site-container"><div class="section-heading trades-heading"><div><p class="section-kicker">WHAT WE COVER</p><h2 id="trades-title">The right hands for<br><em>everyday home jobs.</em></h2></div><p>Starting with four essential trades, right here in Durban.</p></div>
            <div class="trade-grid">
                @forelse ($trades as $trade)
                    <a class="trade-card" href="{{ route('trades.show', $trade) }}"><img class="trade-photo" src="{{ asset('images/trade-'.$trade->key.'-v2.webp') }}" alt="Illustrative image of {{ strtolower($trade->name) }} work" width="800" height="800" loading="lazy"><span class="trade-card-bottom"><strong>{{ $trade->name }}</strong><span aria-hidden="true">↗</span></span></a>
                @empty
                    <div class="trade-card"><img class="trade-photo" src="{{ asset('images/trade-plumbing-v2.webp') }}" alt="Illustrative image of a plumbing professional at work" width="800" height="800" loading="lazy"><span class="trade-card-bottom"><strong>Plumbing</strong></span></div><div class="trade-card"><img class="trade-photo" src="{{ asset('images/trade-electrical-v2.webp') }}" alt="Illustrative image of electrical work" width="800" height="800" loading="lazy"><span class="trade-card-bottom"><strong>Electrical</strong></span></div><div class="trade-card"><img class="trade-photo" src="{{ asset('images/trade-painting-v2.webp') }}" alt="Illustrative image of a painting professional at work" width="800" height="800" loading="lazy"><span class="trade-card-bottom"><strong>Painting</strong></span></div><div class="trade-card"><img class="trade-photo" src="{{ asset('images/trade-tiling-v2.webp') }}" alt="Illustrative image of a tiling professional at work" width="800" height="800" loading="lazy"><span class="trade-card-bottom"><strong>Tiling</strong></span></div>
                @endforelse
            </div>
            @if ($showFallback)<p class="mt-6 text-zinc-700" aria-live="polite">{{ __('Choose the closest service') }}:</p>@endif
        </div></section>

        <section class="pro-section" id="for-pros" aria-labelledby="pros-title"><div class="site-container pro-grid"><div class="pro-visual"><img src="{{ asset('images/pro-electrician.webp') }}" alt="Illustrative image of an electrician working in a home" width="1024" height="1536" loading="lazy"></div><div class="pro-copy"><p class="section-kicker">FOR THE PEOPLE WHO GET IT DONE</p><h2 id="pros-title">Your next great job<br><em>starts here.</em></h2><p>We're building a better way for Durban's tradespeople to meet local customers. You choose the jobs you want and set your own prices.</p><ul><li><span>✓</span> Local work that fits your trade</li><li><span>✓</span> Clear job details upfront</li><li><span>✓</span> Free to join</li></ul><a class="button button-light" href="{{ route('pros.join') }}">Sign up as a pro <span aria-hidden="true">↗</span></a></div></div></section>

        <section class="section faq-section" id="questions" aria-labelledby="faq-title"><div class="site-container faq-grid"><div><p class="section-kicker">GOOD TO KNOW</p><h2 id="faq-title">A few things<br><em>you might ask.</em></h2></div><div class="faq-list"><details><summary>Where is Sortd available?<span>+</span></summary><p>We're starting in selected Durban and eThekwini suburbs, with plans to reach more neighbourhoods over time.</p></details><details><summary>What does it cost to join?<span>+</span></summary><p>Creating a customer account is free. Tradespeople can also join for free.</p></details><details><summary>How do you choose pros?<span>+</span></summary><p>Pros apply to join and go through checks before they can take on work through Sortd.</p></details><details><summary>Can I sign up with Google?<span>+</span></summary><p>Yes. You can create an account with Google or with your email address. We then ask you to verify a South African mobile number.</p></details></div></div></section>

        <section class="final-cta"><div class="site-container final-inner"><div><p class="section-kicker">LET'S GET STARTED</p><h2>Good help is<br><em>closer than you think.</em></h2></div><a class="button button-dark" href="{{ route('register') }}">Create your account <span aria-hidden="true">↗</span></a></div></section>
    </main>
    @include('pages.partials.footer')
</div>
