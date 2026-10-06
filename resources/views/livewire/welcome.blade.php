<div class="gs-home" :class="{ 'gs-collapsed': collapsed }" x-data="{ collapsed: (() => { try { const v = localStorage.getItem('gs-side'); return v === null ? true : v === '1'; } catch (e) { return true; } })(), toggle() { this.collapsed = ! this.collapsed; try { localStorage.setItem('gs-side', this.collapsed ? '1' : '0'); } catch (e) {} } }">
@vite('resources/css/home.css')
<link rel="stylesheet" href="{{ asset('fonts/phosphor/phosphor.css') }}">
<a class="gs-skip" href="#main">Skip to content</a>

<aside class="gs-side" aria-label="Main navigation">
  <div class="gs-side-head"><a class="gs-logo" href="{{ route('home') }}" wire:navigate.hover aria-label="Get Sorted home"><span class="gs-logo-mark"><i class="ph-bold ph-check"></i></span><span class="gs-lbl">Get Sorted</span></a><button class="gs-side-toggle" type="button" @click="toggle()" :aria-expanded="(!collapsed).toString()" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" aria-label="Collapse sidebar"><i class="ph ph-sidebar-simple"></i></button></div>
  <a class="gs-nav-btn gs-primary" href="{{ route('book') }}" wire:navigate.hover title="New job"><i class="ph ph-plus"></i><span class="gs-lbl">New job</span></a>
  @foreach ($tradeLinks as $tradeLink)
  <a class="gs-nav-btn" href="{{ $tradeLink['url'] }}" wire:navigate.hover title="{{ $tradeLink['name'] }}"><i class="ph ph-{{ $tradeLink['icon'] }}"></i><span class="gs-lbl">{{ $tradeLink['name'] }}</span></a>
  @endforeach
  <p class="gs-side-label">Explore</p>
  <a class="gs-nav-btn" href="{{ route('customers') }}" wire:navigate.hover title="For customers"><i class="ph ph-house-line"></i><span class="gs-lbl">For customers</span></a>
  <a class="gs-nav-btn" href="{{ route('pros.join') }}" wire:navigate.hover title="For pros"><i class="ph ph-hard-hat"></i><span class="gs-lbl">For pros</span><span class="gs-badge">Free</span></a>
  <a class="gs-nav-btn" href="{{ route('about') }}" wire:navigate.hover title="About"><i class="ph ph-sparkle"></i><span class="gs-lbl">About</span></a>
  <a class="gs-nav-btn" href="{{ route('contact') }}" wire:navigate.hover title="Contact"><i class="ph ph-chat-circle"></i><span class="gs-lbl">Contact</span></a>
  <div class="gs-side-foot">
    @auth
    <a class="gs-btn gs-btn-white" href="{{ route('account.home') }}" wire:navigate.hover title="My account"><i class="ph ph-user"></i><span class="gs-lbl">My account</span></a>
    @else
    <a class="gs-btn" href="{{ route('login') }}" wire:navigate.hover title="Sign in"><i class="ph ph-sign-in"></i><span class="gs-lbl">Sign in</span></a>
    <a class="gs-btn gs-btn-white" href="{{ route('register') }}" wire:navigate.hover title="Create account"><i class="ph ph-user-plus"></i><span class="gs-lbl">Create account</span></a>
    @endauth
  </div>
</aside>

<div class="gs-main">
  <header class="gs-topbar">
    <a class="gs-logo" href="{{ route('home') }}" wire:navigate.hover aria-label="Get Sorted home"><span class="gs-logo-mark"><i class="ph-bold ph-check"></i></span> Get Sorted</a>
    <div style="display:flex;gap:8px"><a class="gs-btn gs-btn-white" href="{{ route('book') }}" wire:navigate.hover style="min-height:40px">New job</a><a class="gs-btn" href="{{ route('login') }}" wire:navigate.hover style="min-height:40px;padding:0 12px" aria-label="Sign in"><i class="ph ph-user"></i></a></div>
  </header>

  <main id="main">
    <a class="gs-banner" href="{{ route('about') }}" wire:navigate.hover><i class="ph-fill ph-map-pin"></i><span>Now covering Umhlanga and Durban North</span><b>See coverage <i class="ph-bold ph-arrow-right" style="font-size:14px"></i></b></a>
    <section class="gs-hero" x-data="{ tab: 'popular' }" aria-labelledby="h1">
      <h1 class="gs-serif" id="h1">Get your home sorted, properly</h1>
      <p class="gs-sub">Describe the job once. Vetted Durban pros send itemised quotes you can compare in one thread.</p>

      <form class="gs-prompt" wire:submit="start" x-data="{ chip: '' }">
        <div class="gs-prompt-box">
          <label for="job">What needs sorting?</label>
          <input id="job" wire:model="description" type="text" maxlength="500" placeholder="For example, the geyser is leaking in the ceiling" autocomplete="off">
          <div class="gs-chips">
            <button type="button" class="gs-chip" :class="{ 'gs-on': chip === '' }" @click="chip = ''; $wire.tradeKey = ''"><i class="ph ph-sparkle"></i> Any trade</button>
            @foreach ($tradeLinks as $tradeLink)
            <button type="button" class="gs-chip" :class="{ 'gs-on': chip === '{{ $tradeLink['key'] }}' }" @click="chip = '{{ $tradeLink['key'] }}'; $wire.tradeKey = '{{ $tradeLink['key'] }}'"><i class="ph ph-{{ $tradeLink['icon'] }}"></i> {{ $tradeLink['name'] }}</button>
            @endforeach
            <button class="gs-go" type="submit" aria-label="Start job" wire:loading.attr="disabled"><i class="ph-bold ph-arrow-up"></i></button>
          </div>
        </div>
      </form>
      @error('description')<p class="gs-err" role="alert">{{ $message }}</p>@enderror

      <div class="gs-tabs" role="tablist" aria-label="Job ideas">
        <button class="gs-tab" role="tab" :aria-selected="(tab === 'popular').toString()" @click="tab = 'popular'">Popular</button>
        <button class="gs-tab" role="tab" :aria-selected="(tab === 'urgent').toString()" @click="tab = 'urgent'">Urgent</button>
        <button class="gs-tab" role="tab" :aria-selected="(tab === 'planned').toString()" @click="tab = 'planned'">Planned</button>
        <button class="gs-tab" role="tab" :aria-selected="(tab === 'season').toString()" @click="tab = 'season'">This season</button>
      </div>
      <div class="gs-sugs" x-show="tab === 'popular'">
        <a href="{{ route('book.trade', ['trade' => 'plumbing']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Fix a leaking tap or toilet</a>
        <a href="{{ route('book.trade', ['trade' => 'electrical']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Replace a distribution board</a>
        <a href="{{ route('book.trade', ['trade' => 'painting']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Repaint a lounge and passage</a>
        <a href="{{ route('book.trade', ['trade' => 'tiling']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Regrout a bathroom</a>
        <a href="{{ route('book.trade', ['trade' => 'plumbing']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Unblock a kitchen drain</a>
        <a href="{{ route('book.trade', ['trade' => 'electrical']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Add plug points to a bedroom</a>
      </div>
      <div class="gs-sugs" x-show="tab === 'urgent'" x-cloak>
        <a href="{{ route('book.trade', ['trade' => 'plumbing']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Burst pipe, water off now</a>
        <a href="{{ route('book.trade', ['trade' => 'electrical']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Power trips as soon as I reset it</a>
        <a href="{{ route('book.trade', ['trade' => 'plumbing']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Geyser leaking into the ceiling</a>
        <a href="{{ route('book.trade', ['trade' => 'electrical']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Burning smell from a plug</a>
        <a href="{{ route('book.trade', ['trade' => 'plumbing']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Sewer smell in the yard</a>
        <a href="{{ route('book.trade', ['trade' => 'electrical']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Gate motor stopped working</a>
      </div>
      <div class="gs-sugs" x-show="tab === 'planned'" x-cloak>
        <a href="{{ route('book.trade', ['trade' => 'painting']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Paint the outside of the house</a>
        <a href="{{ route('book.trade', ['trade' => 'tiling']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Tile a new kitchen floor</a>
        <a href="{{ route('book.trade', ['trade' => 'plumbing']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Install a new shower</a>
        <a href="{{ route('book.trade', ['trade' => 'electrical']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Wire a new outbuilding</a>
        <a href="{{ route('book.trade', ['trade' => 'painting']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Waterproof and paint a roof</a>
        <a href="{{ route('book.trade', ['trade' => 'tiling']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Tile a braai area</a>
      </div>
      <div class="gs-sugs" x-show="tab === 'season'" x-cloak>
        <a href="{{ route('book.trade', ['trade' => 'plumbing']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Clear gutters before the rains</a>
        <a href="{{ route('book.trade', ['trade' => 'painting']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Treat damp and mould on walls</a>
        <a href="{{ route('book.trade', ['trade' => 'electrical']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Surge protection for storm season</a>
        <a href="{{ route('book.trade', ['trade' => 'tiling']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Seal outdoor tiles against humidity</a>
        <a href="{{ route('book.trade', ['trade' => 'plumbing']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Check the geyser before winter</a>
        <a href="{{ route('book.trade', ['trade' => 'painting']) }}" wire:navigate.hover><i class="ph ph-arrow-elbow-down-right"></i> Refresh a rental between tenants</a>
      </div>
    </section>

    <div class="gs-logos" aria-label="Durban organisations">
      <div class="gs-marquee">
        @foreach ([false, true] as $copy)
        <ul class="gs-track" @if ($copy) aria-hidden="true" @endif>
          @foreach ([['mr-price.png', 'Mr Price Group', 15], ['tongaat-hulett.png', 'Tongaat Hulett', 40], ['illovo.png', 'Illovo Sugar Africa', 44], ['ushaka.png', 'uShaka Marine World', 58], ['gateway.jpg', 'Gateway Theatre of Shopping', 80], ['amazulu.svg', 'AmaZulu FC', 58], ['comrades.svg', 'Comrades Marathon', 52], ['dut.jpg', 'Durban University of Technology', 98]] as [$file, $name, $height])
          <li><img src="{{ asset('images/partners/'.$file) }}" alt="{{ $copy ? '' : $name }}" style="height:{{ $height }}px" loading="lazy"></li>
          @endforeach
        </ul>
        @endforeach
      </div>
    </div>

    <section class="gs-sec" aria-labelledby="trades-h">
      <div class="gs-wrap">
        <div class="gs-sec-head"><h2 class="gs-serif" id="trades-h">Four trades, checked before they start</h2><p>Every pro applies and is vetted before taking a single job through Get Sorted.</p><a class="gs-more" href="{{ route('trades.index') }}" wire:navigate.hover>Browse all pros <i class="ph ph-arrow-up-right" style="font-size:16px"></i></a></div>
        <div class="gs-bento">
          @foreach ($tradeLinks as $tradeLink)
          @if ($loop->index < 4)
          @php($shape = ['gs-big', 'gs-wide', 'gs-sq1', 'gs-sq2'][$loop->index])
          <a class="gs-cell {{ $shape }}" href="{{ $tradeLink['show'] }}" wire:navigate.hover><div class="gs-pic"><img src="{{ asset('images/trade-'.$tradeLink['key'].'-v2.webp') }}" alt="Illustrative image of {{ strtolower($tradeLink['name']) }} work" width="800" height="800" loading="lazy"></div><div class="gs-cap"><div><h3>{{ $tradeLink['name'] }}</h3><p>{{ $loop->index < 2 ? $tradeLink['blurb'] : $tradeLink['short'] }}</p></div><i class="ph ph-arrow-up-right"></i></div></a>
          @endif
          @endforeach
        </div>
      </div>
    </section>


    <section class="gs-sec" aria-labelledby="flow-h" id="how">
      <div class="gs-wrap">
        <div class="gs-how">
          <div>
            <div class="gs-sec-head"><h2 class="gs-serif" id="flow-h">One thread, from leak to paid</h2><p>No phone tag and no five browser tabs. Every step happens in the same place.</p></div>
            <ul class="gs-steps">
              <li><span class="gs-ic"><i class="ph ph-chat-circle-text"></i></span><div><h3>Describe it</h3><p>Chat with Siya or tap through a few questions. Add photos when you are ready.</p></div></li>
              <li><span class="gs-ic"><i class="ph ph-shield-check"></i></span><div><h3>Meet vetted pros</h3><p>We invite vetted pros near you. Your street address stays private until you choose.</p></div></li>
              <li><span class="gs-ic"><i class="ph ph-scales"></i></span><div><h3>Compare quotes</h3><p>Up to three itemised quotes side by side. Ask a pro a question before you decide.</p></div></li>
              <li><span class="gs-ic"><i class="ph ph-credit-card"></i></span><div><h3>Book and pay</h3><p>Pick a slot, pay a deposit, and settle the rest when the work is done. Updates arrive on WhatsApp.</p></div></li>
            </ul>
          </div>
          <div class="gs-thread" role="img" aria-label="Example conversation: a customer reports a dripping tap, the assistant asks a couple of questions, then example quotes arrive.">
            <div class="gs-thread-top"><span>Dripping kitchen tap</span><span class="gs-tag">Example</span></div>
            <div class="gs-b gs-u">The kitchen tap will not stop dripping. It started yesterday.</div>
            <div class="gs-b gs-s"><small>Siya</small>Sounds like a worn cartridge. Does it drip when the tap is fully closed?</div>
            <div class="gs-b gs-u">Morningside</div>
            <div class="gs-b gs-s"><small>Siya</small>We cover Morningside for plumbing. I have sent your job to vetted plumbers nearby.</div>
            <div class="gs-qlist">
              <div class="gs-qrow"><span>Thandi's Plumbing</span><b>R480</b></div>
              <div class="gs-qrow gs-sel"><span>Reddy and Sons</span><b>R550</b></div>
              <div class="gs-qrow"><span>Bluff Fix-It</span><b>R620</b></div>
            </div>
            <p class="gs-note">Example only. Names and prices are invented.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="gs-sec" aria-labelledby="cmp-h">
      <div class="gs-wrap">
        <div class="gs-sec-head"><h2 class="gs-serif" id="cmp-h">Quotes you can compare line by line</h2><p>Labour, materials and call-out are split out, so the difference between quotes is easy to see. These are sample quotes for a leaking tap.</p></div>
        <div class="gs-compare">
          <article class="gs-cq"><div class="gs-cq-top"><div class="gs-who"><div class="gs-mono">TM</div><div><h3>Thandi's Plumbing</h3><span class="gs-rate"><i class="ph-fill ph-star"></i> 4.9 from 61 jobs</span></div></div></div><div class="gs-total">R480</div><div class="gs-lines"><div><span>Labour</span><span>R240</span></div><div><span>Materials</span><span>R140</span></div><div><span>Call-out</span><span>R100</span></div><div><span>Earliest slot</span><span>Thu, 8:00</span></div></div><a class="gs-btn" href="{{ route('book') }}" wire:navigate.hover>View quote</a></article>
          <article class="gs-cq gs-best"><div class="gs-cq-top"><div class="gs-who"><div class="gs-mono">RS</div><div><h3>Reddy and Sons</h3><span class="gs-rate"><i class="ph-fill ph-star"></i> 4.8 from 112 jobs</span></div></div><span class="gs-tag">Best match</span></div><div class="gs-total">R550</div><div class="gs-lines"><div><span>Labour</span><span>R260</span></div><div><span>Materials</span><span>R190</span></div><div><span>Call-out</span><span>R100</span></div><div><span>Earliest slot</span><span>Wed, 14:00</span></div></div><a class="gs-btn gs-btn-white" href="{{ route('book') }}" wire:navigate.hover>Accept quote</a></article>
          <article class="gs-cq"><div class="gs-cq-top"><div class="gs-who"><div class="gs-mono">BF</div><div><h3>Bluff Fix-It</h3><span class="gs-rate"><i class="ph-fill ph-star"></i> 4.7 from 38 jobs</span></div></div></div><div class="gs-total">R620</div><div class="gs-lines"><div><span>Labour</span><span>R300</span></div><div><span>Materials</span><span>R220</span></div><div><span>Call-out</span><span>R100</span></div><div><span>Earliest slot</span><span>Tomorrow, 9:00</span></div></div><a class="gs-btn" href="{{ route('book') }}" wire:navigate.hover>View quote</a></article>
        </div>
      </div>
    </section>

    <section class="gs-sec" aria-labelledby="pro-h">
      <div class="gs-wrap">
        <div class="gs-sec-head"><h2 class="gs-serif" id="pro-h">Pros people rate</h2><p>Sample profiles. Every pro can be reviewed after each finished job.</p></div>
        <div class="gs-dir">
          <a class="gs-pro" href="{{ route('trades.index') }}" wire:navigate.hover><img class="gs-av" src="{{ asset('images/pro-electrician.webp') }}" alt="" loading="lazy" style="object-position:50% 20%"><div><h3>Nomsa Dlamini</h3><p>Electrician in Berea and Musgrave</p><div class="gs-tags"><span class="gs-tag">COC certified</span><span class="gs-tag">4.9 rating</span></div></div><i class="ph ph-arrow-up-right gs-go-i"></i></a>
          <a class="gs-pro" href="{{ route('trades.index') }}" wire:navigate.hover><img class="gs-av" src="{{ asset('images/trade-plumbing-v2.webp') }}" alt="" loading="lazy" style="object-position:30% 20%"><div><h3>Kriben Naidoo</h3><p>Plumber in Westville and Pinetown</p><div class="gs-tags"><span class="gs-tag">Same-day call-outs</span><span class="gs-tag">4.8 rating</span></div></div><i class="ph ph-arrow-up-right gs-go-i"></i></a>
          <a class="gs-pro" href="{{ route('trades.index') }}" wire:navigate.hover><img class="gs-av" src="{{ asset('images/trade-painting-v2.webp') }}" alt="" loading="lazy" style="object-position:40% 40%"><div><h3>Sipho Mkhize</h3><p>Painter in Umhlanga and Durban North</p><div class="gs-tags"><span class="gs-tag">Exteriors</span><span class="gs-tag">4.9 rating</span></div></div><i class="ph ph-arrow-up-right gs-go-i"></i></a>
          <a class="gs-pro" href="{{ route('trades.index') }}" wire:navigate.hover><img class="gs-av" src="{{ asset('images/trade-tiling-v2.webp') }}" alt="" loading="lazy" style="object-position:60% 30%"><div><h3>Ayesha Patel</h3><p>Tiler in Glenwood and Morningside</p><div class="gs-tags"><span class="gs-tag">Waterproofing</span><span class="gs-tag">5.0 rating</span></div></div><i class="ph ph-arrow-up-right gs-go-i"></i></a>
          <a class="gs-pro" href="{{ route('trades.index') }}" wire:navigate.hover><img class="gs-av" src="{{ asset('images/trade-electrical-v2.webp') }}" alt="" loading="lazy" style="object-position:30% 70%"><div><h3>Johan Botha</h3><p>Electrician in Hillcrest and Kloof</p><div class="gs-tags"><span class="gs-tag">Solar ready</span><span class="gs-tag">4.7 rating</span></div></div><i class="ph ph-arrow-up-right gs-go-i"></i></a>
          <a class="gs-pro" href="{{ route('trades.index') }}" wire:navigate.hover><img class="gs-av" src="{{ asset('images/trade-painting-v2.webp') }}" alt="" loading="lazy" style="object-position:90% 60%"><div><h3>Lindiwe Zulu</h3><p>Painter in Bluff and Umbilo</p><div class="gs-tags"><span class="gs-tag">Interiors</span><span class="gs-tag">4.8 rating</span></div></div><i class="ph ph-arrow-up-right gs-go-i"></i></a>
        </div>
      </div>
    </section>

    <section class="gs-sec" aria-labelledby="rev-h">
      <div class="gs-wrap">
        <div class="gs-sec-head"><h2 class="gs-serif" id="rev-h">Sorted this week</h2><p>Sample reviews from finished jobs.</p></div>
        <div class="gs-quotes">
          <figure class="gs-qt gs-big"><p>“Three quotes by lunchtime and the geyser was replaced on Thursday. I never had to chase anyone.”</p><figcaption><b>Lerato Mokoena</b>Homeowner in Morningside</figcaption></figure>
          <figure class="gs-qt"><p>“Seeing labour and materials split out made it easy to choose. The electrician explained everything in the chat before he arrived.”</p><figcaption><b>Dev Pillay</b>Homeowner in Berea</figcaption></figure>
          <figure class="gs-qt"><p>“I sent photos of the damp patch and got useful questions back straight away. Painted and sealed in two days.”</p><figcaption><b>Sarah Kruger</b>Homeowner in Westville</figcaption></figure>
          <figure class="gs-qt"><p>“Updates came on WhatsApp, so I did not have to stay home waiting for a call.”</p><figcaption><b>Thabo Ndlovu</b>Tenant in Umhlanga</figcaption></figure>
          <figure class="gs-qt gs-big"><p>“Fair price, clean work, and my number stayed private until I picked someone.”</p><figcaption><b>Aisha Ferreira</b>Homeowner in Glenwood</figcaption></figure>
          <figure class="gs-qt"><p>“As a landlord I like that every quote and payment sits in one record. Tenant turnarounds are much simpler.”</p><figcaption><b>Greg Rossouw</b>Landlord in Musgrave</figcaption></figure>
        </div>
      </div>
    </section>


    <section class="gs-sec" aria-labelledby="forpros-h" id="for-pros">
      <div class="gs-wrap">
        <div class="gs-pros">
          <div class="gs-pros-copy">
            <h2 class="gs-serif" id="forpros-h">Are you a tradesperson in Durban?</h2>
            <p>Choose the jobs you want, set your own prices, and see the job details before you quote.</p>
            <ul class="gs-checks">
              <li><i class="ph-bold ph-check"></i> Local work that fits your trade and how far you travel</li>
              <li><i class="ph-bold ph-check"></i> An itemised quote builder that works on your phone</li>
              <li><i class="ph-bold ph-check"></i> Deposits held securely and payouts tracked in one place</li>
              <li><i class="ph-bold ph-check"></i> Free to join</li>
            </ul>
            <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="gs-btn gs-btn-accent" href="{{ route('pros.join') }}" wire:navigate.hover>Apply as a pro <i class="ph-bold ph-arrow-up-right"></i></a><a class="gs-btn" href="{{ route('pros.agreement') }}" wire:navigate.hover>Read the pro agreement</a></div>
          </div>
          <div class="gs-pros-img"><img src="{{ asset('images/pro-electrician.webp') }}" alt="Illustrative image of an electrician working in a home" loading="lazy"></div>
        </div>
      </div>
    </section>

    <section class="gs-sec" aria-labelledby="faq-h" id="questions">
      <div class="gs-wrap">
        <div class="gs-sec-head"><h2 class="gs-serif" id="faq-h">Good to know</h2></div>
        <div class="gs-faq">
          <div>
            <details><summary>Where is Get Sorted available? <i class="ph ph-plus"></i></summary><p>We are starting in Durban and eThekwini and adding more over time. If there are no pros near you yet, join the waitlist and we will tell you when we arrive.</p></details>
            <details><summary>What does it cost to join? <i class="ph ph-plus"></i></summary><p>Creating a customer account is free. Tradespeople can also join for free.</p></details>
            <details><summary>How do you choose pros? <i class="ph ph-plus"></i></summary><p>Pros apply to join and go through checks before they can take on work through Get Sorted.</p></details>
            <details><summary>Can I sign up with Google? <i class="ph ph-plus"></i></summary><p>Yes. Use Google or your email address, then verify a South African mobile number.</p></details>
          </div>
          <div>
            <details><summary>Who sees my address? <i class="ph ph-plus"></i></summary><p>Pros see your area and roughly how far away you are, never your street. Your street address and contact details are shared once you accept a quote.</p></details>
            <details><summary>How many quotes will I get? <i class="ph ph-plus"></i></summary><p>Up to five, from vetted pros near you who do the trade you need.</p></details>
            <details><summary>How do payments work? <i class="ph ph-plus"></i></summary><p>You pay a deposit when you accept a quote and settle the final amount when the work is done. Any change to the price needs your approval first.</p></details>
            <details><summary>What if something goes wrong? <i class="ph ph-plus"></i></summary><p>Message your pro in the thread first. If it cannot be resolved, our support team steps in and can review the job record.</p></details>
          </div>
        </div>
      </div>
    </section>

    <div class="gs-wrap">
      <section class="gs-close" aria-labelledby="cta-h">
        <div class="gs-close-main">
          <h2 class="gs-serif" id="cta-h">Good help is closer than you think</h2>
          <p>Tell us what needs fixing and get quotes from vetted Durban pros.</p>
          <div class="gs-acts"><a class="gs-btn gs-btn-white" href="{{ route('book') }}" wire:navigate.hover>Start a job <i class="ph-bold ph-arrow-up-right"></i></a><a class="gs-btn" href="{{ route('register') }}" wire:navigate.hover>Create an account</a></div>
          <ul class="gs-facts">
            <li><i class="ph ph-shield-check"></i> Pros are vetted before they take work</li>
            <li><i class="ph ph-map-pin"></i> Your address stays private until you accept</li>
            <li><i class="ph ph-chat-circle"></i> Updates arrive on WhatsApp</li>
          </ul>
        </div>
        <div class="gs-close-trades" aria-label="Start with a trade">
          @foreach ($tradeLinks as $tradeLink)
          <a href="{{ $tradeLink['url'] }}" wire:navigate.hover><img src="{{ asset('images/trade-'.$tradeLink['key'].'-v2.webp') }}" alt="" loading="lazy"><span>{{ $tradeLink['name'] }} <i class="ph ph-arrow-up-right"></i></span></a>
          @endforeach
        </div>
      </section>
    </div>

    <footer class="gs-foot">
      <div class="gs-wrap">
        <div class="gs-foot-grid">
          <div class="gs-foot-brand"><a class="gs-logo" href="{{ route('home') }}" wire:navigate.hover style="padding:0"><span class="gs-logo-mark"><i class="ph-bold ph-check"></i></span> Get Sorted</a><p>Trusted tradespeople for Durban homes.</p></div>
          <div><h3>Trades</h3><ul><li><a href="{{ route('book.trade', ['trade' => 'plumbing']) }}" wire:navigate.hover>Plumbing</a></li><li><a href="{{ route('book.trade', ['trade' => 'electrical']) }}" wire:navigate.hover>Electrical</a></li><li><a href="{{ route('book.trade', ['trade' => 'painting']) }}" wire:navigate.hover>Painting</a></li><li><a href="{{ route('book.trade', ['trade' => 'tiling']) }}" wire:navigate.hover>Tiling</a></li></ul></div>
          <div><h3>Customers</h3><ul><li><a href="{{ route('book') }}" wire:navigate.hover>Start a job</a></li><li><a href="{{ route('customers') }}" wire:navigate.hover>How it works</a></li><li><a href="{{ route('trades.index') }}" wire:navigate.hover>Browse pros</a></li><li><a href="{{ route('login') }}" wire:navigate.hover>Sign in</a></li></ul></div>
          <div><h3>Pros</h3><ul><li><a href="{{ route('pros.join') }}" wire:navigate.hover>Join as a pro</a></li><li><a href="{{ route('pros.agreement') }}" wire:navigate.hover>Pro agreement</a></li><li><a href="{{ route('login') }}" wire:navigate.hover>Pro sign in</a></li></ul></div>
          <div><h3>Company</h3><ul><li><a href="{{ route('about') }}" wire:navigate.hover>About</a></li><li><a href="{{ route('contact') }}" wire:navigate.hover>Contact</a></li><li><a href="{{ route('terms') }}" wire:navigate.hover>Terms</a></li><li><a href="{{ route('privacy') }}" wire:navigate.hover>Privacy</a></li></ul></div>
        </div>
        <div class="gs-legal"><span>© 2026 Get Sorted, Durban, South Africa</span><span>Photos are illustrative. Sample pros, prices and reviews are examples. Partner logos are placeholders.</span></div>
      </div>
    </footer>
  </main>
</div>
</div>
