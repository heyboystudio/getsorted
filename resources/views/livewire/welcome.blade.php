@php($bookTrade = fn (string $trade): string => auth()->check() ? route('book.trade', $trade) : route('start'))
<div class="gs-home-v3">
<a class="skip" href="#main">Skip to content</a>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="logo" viewBox="0 0 552.50 97.00"><path fill="var(--logo-get, #131311)" d="M41.0 25.0C48.7 25.0 55.5 28.4 57.400000000000006 36.199999999999996H74.4C72.7 20.200000000000003 59.1 9.799999999999997 41.400000000000006 9.799999999999997C18.5 9.799999999999997 3.6 25.9 3.6 48.199999999999996C3.6 70.9 18.1 86.1 38.800000000000004 86.1C47.6 86.1 55.6 83.0 59.6 78.3L60.5 85.0H74.4V42.5H39.6V56.7H59.2C58.5 64.4 53.1 71.0 40.900000000000006 71.0C29.1 71.0 20.700000000000003 63.599999999999994 20.700000000000003 48.699999999999996C20.700000000000003 34.4 27.6 25.0 41.0 25.0Z M109.10000000000001 86.3C122.80000000000001 86.3 132.70000000000002 79.2 134.5 68.1H120.4C119.30000000000001 71.7 115.20000000000002 73.8 109.4 73.8C102.60000000000001 73.8 98.9 70.7 98.00000000000001 64.1L134.3 63.9V60.0C134.3 43.699999999999996 124.50000000000001 33.4 108.80000000000001 33.4C93.70000000000002 33.4 83.2 44.3 83.2 59.9C83.2 75.3 94.00000000000001 86.3 109.10000000000001 86.3ZM108.9 45.9C115.10000000000001 45.9 119.00000000000001 49.3 119.00000000000001 54.599999999999994H98.20000000000002C99.4 48.6 102.80000000000001 45.9 108.9 45.9Z M163.60000000000002 85.0V47.8H173.10000000000002V35.0H163.60000000000002V19.5H148.20000000000002V35.0H138.70000000000002V47.8H148.20000000000002V85.0Z"/><rect x="181.20" y="0.00" width="371.30" height="97.00" rx="4.00" fill="var(--logo-box, #131311)"/><path fill="var(--logo-sorted, #fff)" d="M193.8 32.5C193.8 44.599999999999994 200.60000000000002 52.1 213.8 54.8L225.10000000000002 57.099999999999994C230.00000000000003 58.099999999999994 231.90000000000003 60.2 231.90000000000003 64.3C231.90000000000003 69.0 227.3 71.9 220.00000000000003 71.9C212.8 71.9 208.8 68.9 208.8 63.5H192.70000000000002C192.70000000000002 77.4 203.4 86.3 219.90000000000003 86.3C236.90000000000003 86.3 248.10000000000002 77.0 248.10000000000002 62.7C248.10000000000002 51.0 241.8 44.699999999999996 228.60000000000002 42.0L217.50000000000003 39.699999999999996C212.3 38.599999999999994 210.00000000000003 36.4 210.00000000000003 31.9C210.00000000000003 27.0 214.20000000000002 24.0 220.8 24.0C226.90000000000003 24.0 230.8 27.199999999999996 230.8 32.4H246.90000000000003C246.90000000000003 18.39999999999999 236.90000000000003 9.599999999999994 221.00000000000003 9.599999999999994C205.10000000000002 9.599999999999994 193.8 19.099999999999994 193.8 32.5Z M254.20000000000002 59.9C254.20000000000002 75.7 265.8 86.2 281.8 86.2C297.70000000000005 86.2 309.3 75.7 309.3 59.9C309.3 44.099999999999994 297.70000000000005 33.5 281.8 33.5C265.8 33.5 254.20000000000002 44.099999999999994 254.20000000000002 59.9ZM269.70000000000005 59.8C269.70000000000005 52.3 274.6 47.3 281.8 47.3C288.90000000000003 47.3 293.8 52.3 293.8 59.8C293.8 67.4 288.90000000000003 72.4 281.8 72.4C274.6 72.4 269.70000000000005 67.4 269.70000000000005 59.8Z M352.6 35.0C350.50000000000006 34.5 348.50000000000006 34.3 346.70000000000005 34.3C340.00000000000006 34.3 335.6 37.599999999999994 333.50000000000006 42.5L332.70000000000005 35.099999999999994H318.20000000000005V85.0H333.6V63.3C333.6 53.4 338.90000000000003 49.5 347.70000000000005 49.5H352.6Z M380.6 85.0V47.8H390.1V35.0H380.6V19.5H365.20000000000005V35.0H355.70000000000005V47.8H365.20000000000005V85.0Z M420.20000000000005 86.3C433.90000000000003 86.3 443.80000000000007 79.2 445.6 68.1H431.50000000000006C430.40000000000003 71.7 426.30000000000007 73.8 420.50000000000006 73.8C413.70000000000005 73.8 410.00000000000006 70.7 409.1 64.1L445.40000000000003 63.9V60.0C445.40000000000003 43.699999999999996 435.6 33.4 419.90000000000003 33.4C404.80000000000007 33.4 394.30000000000007 44.3 394.30000000000007 59.9C394.30000000000007 75.3 405.1 86.3 420.20000000000005 86.3ZM420.00000000000006 45.9C426.20000000000005 45.9 430.1 49.3 430.1 54.599999999999994H409.30000000000007C410.50000000000006 48.6 413.90000000000003 45.9 420.00000000000006 45.9Z M475.50000000000006 86.3C482.70000000000005 86.3 488.90000000000003 83.2 491.6 78.6L492.40000000000003 85.0H506.90000000000003V9.599999999999994H491.50000000000006V39.599999999999994C488.6 35.9 482.70000000000005 33.4 476.50000000000006 33.4C461.30000000000007 33.4 451.80000000000007 44.4 451.80000000000007 60.3C451.80000000000007 76.1 461.1 86.3 475.50000000000006 86.3ZM479.20000000000005 72.2C471.90000000000003 72.2 467.30000000000007 67.1 467.30000000000007 59.7C467.30000000000007 52.3 471.90000000000003 47.199999999999996 479.20000000000005 47.199999999999996C486.40000000000003 47.199999999999996 491.40000000000003 52.199999999999996 491.40000000000003 59.7C491.40000000000003 67.2 486.40000000000003 72.2 479.20000000000005 72.2Z"/><path class="logo-dot" fill="var(--logo-dot, #C6FD50)" d="M528.1 86.3C533.0 86.3 537.3000000000001 82.2 537.3000000000001 77.3C537.3000000000001 72.3 533.0 68.2 528.1 68.2C523.0 68.2 518.9000000000001 72.3 518.9000000000001 77.3C518.9000000000001 82.2 523.0 86.3 528.1 86.3Z"/></symbol>
  <symbol id="arrow-r" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="arrow-ur" viewBox="0 0 16 16"><path d="M4.5 11.5 11.5 4.5M5.5 4.5h6v6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="check" viewBox="0 0 16 16"><circle cx="8" cy="8" r="8" fill="currentColor"/><path d="m4.8 8.2 2.1 2.1 4.3-4.5" fill="none" stroke="var(--tick, #fff)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="scribble-arrow" viewBox="0 0 60 80"><path d="M48 2C46 30 34 52 6 72M6 72l4-16M6 72l16-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
</svg>

<header class="header" data-header>
  <a class="logo" href="{{ route('home') }}" aria-label="GetSorted home"><svg class="logo-svg" viewBox="0 0 552.5 97" aria-hidden="true"><path fill="var(--logo-get, #131311)" d="M41.0 25.0C48.7 25.0 55.5 28.4 57.400000000000006 36.199999999999996H74.4C72.7 20.200000000000003 59.1 9.799999999999997 41.400000000000006 9.799999999999997C18.5 9.799999999999997 3.6 25.9 3.6 48.199999999999996C3.6 70.9 18.1 86.1 38.800000000000004 86.1C47.6 86.1 55.6 83.0 59.6 78.3L60.5 85.0H74.4V42.5H39.6V56.7H59.2C58.5 64.4 53.1 71.0 40.900000000000006 71.0C29.1 71.0 20.700000000000003 63.599999999999994 20.700000000000003 48.699999999999996C20.700000000000003 34.4 27.6 25.0 41.0 25.0Z M109.10000000000001 86.3C122.80000000000001 86.3 132.70000000000002 79.2 134.5 68.1H120.4C119.30000000000001 71.7 115.20000000000002 73.8 109.4 73.8C102.60000000000001 73.8 98.9 70.7 98.00000000000001 64.1L134.3 63.9V60.0C134.3 43.699999999999996 124.50000000000001 33.4 108.80000000000001 33.4C93.70000000000002 33.4 83.2 44.3 83.2 59.9C83.2 75.3 94.00000000000001 86.3 109.10000000000001 86.3ZM108.9 45.9C115.10000000000001 45.9 119.00000000000001 49.3 119.00000000000001 54.599999999999994H98.20000000000002C99.4 48.6 102.80000000000001 45.9 108.9 45.9Z M163.60000000000002 85.0V47.8H173.10000000000002V35.0H163.60000000000002V19.5H148.20000000000002V35.0H138.70000000000002V47.8H148.20000000000002V85.0Z"/><rect x="181.20" y="0.00" width="371.30" height="97.00" rx="4.00" fill="var(--logo-box, #131311)"/><path fill="var(--logo-sorted, #fff)" d="M193.8 32.5C193.8 44.599999999999994 200.60000000000002 52.1 213.8 54.8L225.10000000000002 57.099999999999994C230.00000000000003 58.099999999999994 231.90000000000003 60.2 231.90000000000003 64.3C231.90000000000003 69.0 227.3 71.9 220.00000000000003 71.9C212.8 71.9 208.8 68.9 208.8 63.5H192.70000000000002C192.70000000000002 77.4 203.4 86.3 219.90000000000003 86.3C236.90000000000003 86.3 248.10000000000002 77.0 248.10000000000002 62.7C248.10000000000002 51.0 241.8 44.699999999999996 228.60000000000002 42.0L217.50000000000003 39.699999999999996C212.3 38.599999999999994 210.00000000000003 36.4 210.00000000000003 31.9C210.00000000000003 27.0 214.20000000000002 24.0 220.8 24.0C226.90000000000003 24.0 230.8 27.199999999999996 230.8 32.4H246.90000000000003C246.90000000000003 18.39999999999999 236.90000000000003 9.599999999999994 221.00000000000003 9.599999999999994C205.10000000000002 9.599999999999994 193.8 19.099999999999994 193.8 32.5Z M254.20000000000002 59.9C254.20000000000002 75.7 265.8 86.2 281.8 86.2C297.70000000000005 86.2 309.3 75.7 309.3 59.9C309.3 44.099999999999994 297.70000000000005 33.5 281.8 33.5C265.8 33.5 254.20000000000002 44.099999999999994 254.20000000000002 59.9ZM269.70000000000005 59.8C269.70000000000005 52.3 274.6 47.3 281.8 47.3C288.90000000000003 47.3 293.8 52.3 293.8 59.8C293.8 67.4 288.90000000000003 72.4 281.8 72.4C274.6 72.4 269.70000000000005 67.4 269.70000000000005 59.8Z M352.6 35.0C350.50000000000006 34.5 348.50000000000006 34.3 346.70000000000005 34.3C340.00000000000006 34.3 335.6 37.599999999999994 333.50000000000006 42.5L332.70000000000005 35.099999999999994H318.20000000000005V85.0H333.6V63.3C333.6 53.4 338.90000000000003 49.5 347.70000000000005 49.5H352.6Z M380.6 85.0V47.8H390.1V35.0H380.6V19.5H365.20000000000005V35.0H355.70000000000005V47.8H365.20000000000005V85.0Z M420.20000000000005 86.3C433.90000000000003 86.3 443.80000000000007 79.2 445.6 68.1H431.50000000000006C430.40000000000003 71.7 426.30000000000007 73.8 420.50000000000006 73.8C413.70000000000005 73.8 410.00000000000006 70.7 409.1 64.1L445.40000000000003 63.9V60.0C445.40000000000003 43.699999999999996 435.6 33.4 419.90000000000003 33.4C404.80000000000007 33.4 394.30000000000007 44.3 394.30000000000007 59.9C394.30000000000007 75.3 405.1 86.3 420.20000000000005 86.3ZM420.00000000000006 45.9C426.20000000000005 45.9 430.1 49.3 430.1 54.599999999999994H409.30000000000007C410.50000000000006 48.6 413.90000000000003 45.9 420.00000000000006 45.9Z M475.50000000000006 86.3C482.70000000000005 86.3 488.90000000000003 83.2 491.6 78.6L492.40000000000003 85.0H506.90000000000003V9.599999999999994H491.50000000000006V39.599999999999994C488.6 35.9 482.70000000000005 33.4 476.50000000000006 33.4C461.30000000000007 33.4 451.80000000000007 44.4 451.80000000000007 60.3C451.80000000000007 76.1 461.1 86.3 475.50000000000006 86.3ZM479.20000000000005 72.2C471.90000000000003 72.2 467.30000000000007 67.1 467.30000000000007 59.7C467.30000000000007 52.3 471.90000000000003 47.199999999999996 479.20000000000005 47.199999999999996C486.40000000000003 47.199999999999996 491.40000000000003 52.199999999999996 491.40000000000003 59.7C491.40000000000003 67.2 486.40000000000003 72.2 479.20000000000005 72.2Z"/><path class="logo-dot" fill="var(--logo-dot, #C6FD50)" d="M528.1 86.3C533.0 86.3 537.3000000000001 82.2 537.3000000000001 77.3C537.3000000000001 72.3 533.0 68.2 528.1 68.2C523.0 68.2 518.9000000000001 72.3 518.9000000000001 77.3C518.9000000000001 82.2 523.0 86.3 528.1 86.3Z"/></svg></a>
  <div class="header-right">
    <nav class="nav" aria-label="Main">
      <a href="#how">How it works</a>
      <a href="#trades">Trades</a>
      <a href="#quotes">Quotes</a>
      <a href="#faqs">FAQs</a>
    </nav>
    @auth
      <a class="cta-dark" href="{{ route('account.home') }}">My account <span class="sq"><svg aria-hidden="true"><use href="#arrow-r"/></svg></span></a>
    @else
      <a class="cta-dark" href="{{ route('register') }}">Sign up <span class="sq"><svg aria-hidden="true"><use href="#arrow-r"/></svg></span></a>
    @endauth
    <button class="menu-btn" type="button" aria-expanded="false" aria-controls="sheet" data-menu-open><i class="ph-bold ph-list" aria-hidden="true"></i><span class="sr">Open menu</span></button>
  </div>
</header>

<div class="sheet" id="sheet" hidden data-sheet>
  <div class="sheet-panel" role="dialog" aria-modal="true" aria-label="Menu">
    <div class="sheet-head">
      <span class="logo" aria-label="GetSorted"><svg class="logo-svg" viewBox="0 0 552.5 97" aria-hidden="true"><use href="#logo"/></svg></span>
      <button class="menu-btn" type="button" data-menu-close><i class="ph-bold ph-x" aria-hidden="true"></i><span class="sr">Close menu</span></button>
    </div>
    <nav class="sheet-links" aria-label="Mobile">
      <a href="#how" data-menu-close>How it works</a>
      <a href="#trades" data-menu-close>Trades</a>
      <a href="#quotes" data-menu-close>Quotes</a>
      <a href="#faqs" data-menu-close>FAQs</a>
      @guest<a href="{{ route('login') }}">Sign in</a>@endguest
    </nav>
    @auth
      <a class="btn-lime" href="{{ route('account.home') }}">My account <span class="sq"><svg aria-hidden="true"><use href="#arrow-r"/></svg></span></a>
    @else
      <a class="btn-lime" href="{{ route('register') }}">Sign up <span class="sq"><svg aria-hidden="true"><use href="#arrow-r"/></svg></span></a>
    @endauth
  </div>
</div>

<main id="main">

  <!-- ============ HERO ============ -->
  <section class="hero" aria-labelledby="hero-h">
    <div class="hero-circle" aria-hidden="true"></div>
    <div class="polaroids" aria-hidden="true">
      <div class="par p1"><figure class="pol"><img src="{{ asset('images/home/trade-plumbing-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Plumbing</figcaption></figure></div>
      <div class="par p2"><figure class="pol"><img src="{{ asset('images/home/trade-electrical-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Electrical</figcaption></figure></div>
      <div class="par p3"><figure class="pol"><img src="{{ asset('images/home/trade-painting-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Painting</figcaption></figure></div>
      <div class="par p4"><figure class="pol"><img src="{{ asset('images/home/trade-tiling-v3.webp') }}" alt="" width="1280" height="960"><figcaption>Tiling</figcaption></figure></div>
      <div class="par p5"><figure class="pol"><img src="{{ asset('images/home/step-meet-v3.webp') }}" alt="" width="1024" height="1024"><figcaption>Umhlanga</figcaption></figure></div>
      <div class="par p6"><figure class="pol"><img src="{{ asset('images/home/step-compare-v3.webp') }}" alt="" width="1024" height="1024"><figcaption>Durban North</figcaption></figure></div>
    </div>
    <div class="hero-inner">
      <a class="badge-dark appear" href="{{ route('about') }}"><i class="ph-fill ph-map-pin" aria-hidden="true"></i> Starting in Durban</a>
      <p class="kicker appear">Vetted pros. Itemised quotes. One thread.</p>
      <h1 class="h-hero appear" id="hero-h">Get Your Home Sorted, Properly.</h1>
      <p class="hero-sub appear">Describe the job once. Vetted Durban pros send itemised quotes you can compare in one thread.</p>
      <a class="btn-lime appear" href="#start">Start a job <span class="sq"><svg aria-hidden="true"><use href="#arrow-r"/></svg></span></a>
      <p class="guarantee appear"><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Free to create an account</p>
    </div>
  </section>

  <!-- ============ SOUND FAMILIAR ============ -->
  <section class="wrap fam-sec" aria-labelledby="fam-h">
    <div class="familiar">
      <div class="todo" aria-hidden="true">
        <div class="todo-track" data-todo>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Fix a leaking tap or toilet</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Replace a distribution board</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Repaint a lounge and passage</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Regrout a bathroom</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Unblock a kitchen drain</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Add plug points to a bedroom</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Geyser leaking into the ceiling</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Gate motor stopped working</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Paint the outside of the house</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Tile a new kitchen floor</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Install a new shower</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Clear gutters before the rains</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Treat damp and mould on walls</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Surge protection for storm season</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Check the geyser before winter</span><span class="dots3">•••</span></div>
          <div class="todo-item"><span class="box"></span><span class="todo-label">Refresh a rental between tenants</span><span class="dots3">•••</span></div>
        </div>
        <span class="bubble b1">Who do I even call?</span>
        <span class="bubble b2">Is this quote fair?</span>
      </div>
      <div class="familiar-copy">
        <span class="bubble b3">No more phone tag</span>
        <p class="familiar-k appear">Sound familiar?</p>
        <h2 class="h-md appear" id="fam-h">Your Home Shouldn’t Feel Like a Never-Ending To-Do List</h2>
        <span class="bubble b4">No five browser tabs</span>
      </div>
    </div>
  </section>

  <hr class="rule">

  <!-- ============ ONE THREAD GRID ============ -->
  <section class="wrap grid-sec" aria-labelledby="thread-h">
    <div class="grid-copy">
      <p class="kicker appear">No phone tag and no five browser tabs</p>
      <h2 class="h-md dark appear" id="thread-h">One Thread, From Leak To Paid. Every Step in the Same Place.</h2>
      <a class="btn-lime appear" href="#start">Start a job <span class="sq"><svg aria-hidden="true"><use href="#arrow-r"/></svg></span></a>
    </div>
    <ul class="tiles">
      <li class="tile lime appear"><svg class="tick" aria-hidden="true"><use href="#check"/></svg><span>Vetted pros near you</span></li>
      <li class="tile cream appear"><svg class="tick" aria-hidden="true"><use href="#check"/></svg><span>Your street address stays private</span></li>
      <li class="tile lime appear"><svg class="tick" aria-hidden="true"><use href="#check"/></svg><span>Up to five itemised quotes</span></li>
      <li class="tile cream appear"><svg class="tick" aria-hidden="true"><use href="#check"/></svg><span>Ask a pro before you decide</span></li>
      <li class="tile lime appear"><svg class="tick" aria-hidden="true"><use href="#check"/></svg><span>Contact details only after you choose</span></li>
      <li class="tile ink appear"><span class="logo logo-inv" aria-label="GetSorted"><svg class="logo-svg" viewBox="0 0 552.5 97" aria-hidden="true"><use href="#logo"/></svg></span></li>
    </ul>
  </section>

  <!-- ============ MEET / TIMELINE ============ -->
  <section class="meet" id="how" aria-labelledby="meet-h">
    <p class="kicker appear">Up to five quotes from vetted Durban pros</p>
    <h2 class="h-hero appear" id="meet-h">Meet Get <mark>Sorted<span class="wdot">.</span></mark></h2>
    <p class="meet-sub appear">Describe the job once. We invite vetted pros near you, you compare itemised quotes side by side, then book and pay in the same thread.</p>
    <ul class="chips appear">
      <li>Vetted before they start</li>
      <li>Up to five itemised quotes</li>
      <li>Address private until you accept</li>
      <li>Updates arrive on WhatsApp</li>
    </ul>
    <p class="scribble meet-note" aria-hidden="true">All of this in<br>one thread!<svg viewBox="0 0 60 80"><path class="draw" d="M48 2C46 30 34 52 6 72M6 72l4-16M6 72l16-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></p>

    <div class="timeline">
      <div class="tl-cols" aria-hidden="true">
        <span>Describe it</span><span>Meet vetted pros</span><span>Compare quotes</span><span>Book your pro</span><span>Sorted!</span>
      </div>
      <ol class="tl-cards">
        <li class="tl c1 appear"><h3>Describe it</h3><b class="num">1</b><p>Chat with Siya or tap through a few questions. Add photos when you are ready.</p></li>
        <li class="tl c2 appear"><h3>Meet vetted pros</h3><b class="num">2</b><p>We invite vetted pros near you. Your street address stays private until you choose.</p></li>
        <li class="tl c3 appear"><h3>Compare quotes</h3><b class="num">3</b><p>Up to five itemised quotes side by side. Ask a pro a question before you decide.</p></li>
        <li class="tl c4 appear"><h3>Book your pro</h3><b class="num">4</b><p>Accept the quote you like. You get the pro’s details and arrange the work and payment with them.</p></li>
        <li class="tl c5 appear"><h3>Stay in the loop</h3><b class="num">5</b><p>Updates arrive on WhatsApp, so you don’t have to stay home waiting for a call.</p></li>
        <li class="tl c6 appear"><h3>Review your pro</h3><b class="num">6</b><p>Every pro can be reviewed after each finished job.</p></li>
      </ol>
    </div>
  </section>

  <!-- ============ MARQUEE ============ -->
  <div class="band" aria-hidden="true">
    <div class="band-track">
      <span>Vetted pros</span><i></i><span>Itemised quotes</span><i></i><span>One thread</span><i></i><span>Private address</span><i></i><span>WhatsApp updates</span><i></i><span>Free to join</span><i></i>
      <span>Vetted pros</span><i></i><span>Itemised quotes</span><i></i><span>One thread</span><i></i><span>Private address</span><i></i><span>WhatsApp updates</span><i></i><span>Free to join</span><i></i>
    </div>
  </div>

  <!-- ============ TRADES (dark) ============ -->
  <section class="trades" id="trades" aria-labelledby="trades-h">
    <figure class="tilt t-left" aria-hidden="true"><img src="{{ asset('images/home/trade-plumbing-v3.webp') }}" alt="" width="1280" height="960" loading="lazy"></figure>
    <figure class="tilt t-right" aria-hidden="true"><img src="{{ asset('images/home/trade-electrical-v3.webp') }}" alt="" width="1280" height="960" loading="lazy"></figure>
    <p class="kicker light appear">Our trades</p>
    <h2 class="h-xl light appear" id="trades-h">Four Trades, Checked Before They Start</h2>
    <p class="trades-sub appear">Every pro applies and is vetted before taking a single job through GetSorted.</p>
    <ul class="trade-links appear">
      <li><a href="{{ $bookTrade('plumbing') }}"><b>Plumbing</b><span>Leaks, geysers, drains and new fittings</span><svg aria-hidden="true"><use href="#arrow-ur"/></svg></a></li>
      <li><a href="{{ $bookTrade('electrical') }}"><b>Electrical</b><span>Fault finding, DB boards, certificates</span><svg aria-hidden="true"><use href="#arrow-ur"/></svg></a></li>
      <li><a href="{{ $bookTrade('painting') }}"><b>Painting</b><span>Interior and exterior</span><svg aria-hidden="true"><use href="#arrow-ur"/></svg></a></li>
      <li><a href="{{ $bookTrade('tiling') }}"><b>Tiling</b><span>Floors and walls</span><svg aria-hidden="true"><use href="#arrow-ur"/></svg></a></li>
    </ul>
    <div class="arc" aria-hidden="true"></div>
  </section>

  <!-- ============ FOR PROS (pricing layout) ============ -->
  <section class="pros" aria-labelledby="pros-h">
    <span class="badge-dark appear"><i class="ph-fill ph-hard-hat" aria-hidden="true"></i> For tradespeople</span>
    <h2 class="h-xl appear" id="pros-h">Are You a Tradesperson in Durban?</h2>
    <p class="pros-sub appear">Choose the jobs you want, set your own prices, and see the job details before you quote.</p>
    <span class="pill-out appear"><i class="dot"></i> Free to join</span>

    <div class="price-grid">
      <article class="pc pc-dark appear">
        <span class="tag-lime">Join as a pro</span>
        <p class="pc-price">Free<small>to join</small></p>
        <p class="pc-per">No sign-up fee to start quoting</p>
        <a class="btn-flat" href="{{ route('pros.join') }}">Apply as a pro</a>
        <p class="pc-q">Got questions? <a href="{{ route('pros.agreement') }}">Read the pro agreement</a></p>
        <hr>
        <p class="pc-k">What’s included:</p>
        <ul class="pc-list">
          <li><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Local work that fits your trade</li>
          <li><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Jobs within how far you travel</li>
          <li><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Itemised quote builder on your phone</li>
          <li><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Messages and photos in one thread</li>
          <li><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Customers see your registrations</li>
          <li class="hl"><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Free to join</li>
        </ul>
      </article>
      <div class="pc-mid">
        <article class="pc pc-lime appear">
          <span class="pc-float">You choose</span>
          <span class="tag-dark">How quoting works</span>
          <p class="pc-lead">See the job details before you quote, then price it your way.</p>
          <ul class="pc-list dark">
            <li><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Choose the jobs you want</li>
            <li><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Set your own prices</li>
            <li><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Split out labour, materials and call-out</li>
            <li><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Answer questions in the thread</li>
          </ul>
        </article>
        <article class="pc pc-note appear">
          <span class="bang">!</span>
          <p>For now, customers pay you directly, including any deposit in your quote. GetSorted does not handle payments yet.</p>
        </article>
      </div>
      <article class="pc pc-work appear">
        <h3>Start Quoting Sooner</h3>
        <p>Apply once, get vetted, then see local jobs that fit your trade.</p>
        <ul class="work-pills">
          <li><a href="{{ route('pros.join') }}">Plumbers <svg aria-hidden="true"><use href="#arrow-ur"/></svg></a></li>
          <li><a href="{{ route('pros.join') }}">Electricians <svg aria-hidden="true"><use href="#arrow-ur"/></svg></a></li>
          <li><a href="{{ route('pros.join') }}">Painters <svg aria-hidden="true"><use href="#arrow-ur"/></svg></a></li>
          <li><a href="{{ route('pros.join') }}">Tilers <svg aria-hidden="true"><use href="#arrow-ur"/></svg></a></li>
          <li><a href="{{ route('pros.join') }}">Apply as a pro <svg aria-hidden="true"><use href="#arrow-ur"/></svg></a></li>
          <li><a href="{{ route('pros.agreement') }}">Pro agreement <svg aria-hidden="true"><use href="#arrow-ur"/></svg></a></li>
        </ul>
      </article>
    </div>
  </section>

  <!-- ============ COMPARE ============ -->
  <section class="compare" id="quotes" aria-labelledby="cmp-h">
    <p class="kicker appear">Sample quotes for a leaking tap</p>
    <h2 class="h-xl appear" id="cmp-h">Quotes You Can Compare Line by Line.</h2>
    <p class="compare-sub appear">Labour, materials and call-out are split out, so the difference between quotes is easy to see.</p>
    <p class="scribble cmp-note" aria-hidden="true">Every line,<br>split out!<svg viewBox="0 0 60 80"><path class="draw" d="M48 2C46 30 34 52 6 72M6 72l4-16M6 72l16-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></p>
    <div class="table-wrap appear">
      <table class="cmp">
        <caption class="sr">Three sample quotes for a leaking tap</caption>
        <thead><tr><th scope="col"><span class="sr">Line item</span></th><th scope="col"><span class="tag-lime">Reddy and Sons</span></th><th scope="col"><span class="tag-dark">Thandi's Plumbing</span></th><th scope="col"><span class="tag-dark">Bluff Fix-It</span></th></tr></thead>
        <tbody>
          <tr><th scope="row">Labour</th><td><svg class="ok" aria-hidden="true"><use href="#check"/></svg>R260</td><td><i class="na"></i>R240</td><td><i class="na"></i>R300</td></tr>
          <tr><th scope="row">Materials</th><td><svg class="ok" aria-hidden="true"><use href="#check"/></svg>R190</td><td><i class="na"></i>R140</td><td><i class="na"></i>R220</td></tr>
          <tr><th scope="row">Call-out</th><td><svg class="ok" aria-hidden="true"><use href="#check"/></svg>R100</td><td><i class="na"></i>R100</td><td><i class="na"></i>R100</td></tr>
          <tr><th scope="row">Earliest slot</th><td><svg class="ok" aria-hidden="true"><use href="#check"/></svg>Wed, 14:00</td><td><i class="na"></i>Thu, 8:00</td><td><i class="na"></i>Tomorrow, 9:00</td></tr>
          <tr><th scope="row">Rating</th><td><svg class="ok" aria-hidden="true"><use href="#check"/></svg>4.8 from 112 jobs</td><td><i class="na"></i>4.9 from 61 jobs</td><td><i class="na"></i>4.7 from 38 jobs</td></tr>
          <tr class="tot"><th scope="row">Total</th><td><svg class="ok" aria-hidden="true"><use href="#check"/></svg>R550 <small>Best match</small></td><td><i class="na"></i>R480</td><td><i class="na"></i>R620</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <!-- ============ FAQ (lime panel) ============ -->
  <section class="faq-sec" id="faqs" aria-labelledby="faq-h">
    <div class="faq-panel">
      <h2 class="h-faq appear" id="faq-h">FAQs</h2>
      <div class="faq-list">
        <div class="qa"><button type="button" aria-expanded="false">Where is GetSorted available?<i class="chev" aria-hidden="true"></i></button><div class="qa-a"><p>We are starting in Durban (eThekwini) and adding more areas over time. Outside Durban you can join the waitlist and we will tell you when we arrive.</p></div></div>
        <div class="qa"><button type="button" aria-expanded="false">What does it cost to join?<i class="chev" aria-hidden="true"></i></button><div class="qa-a"><p>Creating a customer account is free. Tradespeople can also join for free.</p></div></div>
        <div class="qa"><button type="button" aria-expanded="false">How do you choose pros?<i class="chev" aria-hidden="true"></i></button><div class="qa-a"><p>Pros apply to join and go through checks before they can take on work through GetSorted.</p></div></div>
        <div class="qa"><button type="button" aria-expanded="false">Can I sign up with Google?<i class="chev" aria-hidden="true"></i></button><div class="qa-a"><p>Yes. Use Google or your email address, then verify a South African mobile number.</p></div></div>
        <div class="qa"><button type="button" aria-expanded="false">Who sees my address?<i class="chev" aria-hidden="true"></i></button><div class="qa-a"><p>Pros see your area and roughly how far away you are, never your street. Your street address and contact details are shared once you accept a quote.</p></div></div>
        <div class="qa"><button type="button" aria-expanded="false">How many quotes will I get?<i class="chev" aria-hidden="true"></i></button><div class="qa-a"><p>Up to five, from vetted pros near you who do the trade you need.</p></div></div>
        <div class="qa"><button type="button" aria-expanded="false">How do payments work?<i class="chev" aria-hidden="true"></i></button><div class="qa-a"><p>You pay your pro directly, as agreed in their quote, including any deposit. GetSorted does not take payments yet. Ask your pro for a receipt.</p></div></div>
        <div class="qa"><button type="button" aria-expanded="false">What if something goes wrong?<i class="chev" aria-hidden="true"></i></button><div class="qa-a"><p>Message your pro in the thread first. If it cannot be resolved, our support team steps in and can review the job record.</p></div></div>
      </div>
    </div>
  </section>

  <!-- ============ START A JOB (contact) ============ -->
  <section class="contact" id="start" aria-labelledby="start-h">
    <p class="kicker appear"><i class="live"></i> Something else on your mind? <a href="{{ route('contact') }}">Contact us</a></p>
    <h2 class="h-xl appear" id="start-h">Good Help Is Closer Than You Think.</h2>
    <p class="contact-sub appear">Tell us what needs fixing and get quotes from vetted Durban pros.</p>
    <form class="form-card appear" data-ask wire:submit="start" novalidate>
      <h3><strong>Start a Job</strong></h3>
      <label class="sr" for="job">What needs sorting?</label>
      <textarea id="job" wire:model="description" @error('description') aria-invalid="true" @enderror rows="3" maxlength="500" placeholder="What needs sorting?* For example, the geyser is leaking in the ceiling" aria-describedby="ask-err"></textarea>
      @error('description')<p class="ask-err" id="ask-err" role="alert">{{ $message }}</p>@enderror
      <div class="form-foot">
        <button class="btn-submit" type="submit" wire:loading.attr="disabled"><span wire:loading.remove wire:target="start">Start a job</span><span wire:loading wire:target="start">Starting…</span><span class="circ"><svg aria-hidden="true"><use href="#arrow-r"/></svg></span></button>
        <p class="form-safe"><svg class="tick" aria-hidden="true"><use href="#check"/></svg> Your address stays private<br>until you accept a quote</p>
      </div>
    </form>
    <p class="form-alt appear">Prefer an account first? <a href="{{ route('register') }}">Create an account</a> or <a href="{{ route('login') }}">sign in</a>.</p>
  </section>
</main>

<footer class="footer">
  <div class="foot-top">
    <div class="foot-brand">
      <a class="logo logo-inv" href="{{ route('home') }}" aria-label="GetSorted home"><svg class="logo-svg" viewBox="0 0 552.5 97" aria-hidden="true"><use href="#logo"/></svg></a>
      <p>Trusted tradespeople for Durban homes. Describe the job, compare quotes from vetted pros, and keep everything in one place.</p>
      <a class="btn-lime" href="#start">Start a job <span class="sq"><svg aria-hidden="true"><use href="#arrow-r"/></svg></span></a>
    </div>
    <div class="foot-cols">
      <div><p class="foot-k">Navigation</p><ul><li><a href="#how">How it works</a></li><li><a href="#trades">Trades</a></li><li><a href="#quotes">Quotes</a></li><li><a href="#faqs">FAQs</a></li></ul></div>
      <div><p class="foot-k">Trades</p><ul><li><a href="{{ $bookTrade('plumbing') }}">Plumbing</a></li><li><a href="{{ $bookTrade('electrical') }}">Electrical</a></li><li><a href="{{ $bookTrade('painting') }}">Painting</a></li><li><a href="{{ $bookTrade('tiling') }}">Tiling</a></li></ul></div>
      <div><p class="foot-k">Pros</p><ul><li><a href="{{ route('pros.join') }}">Join as a pro</a></li><li><a href="{{ route('pros.agreement') }}">Pro agreement</a></li><li><a href="{{ route('login') }}">Sign in</a></li></ul></div>
    </div>
  </div>
  <div class="foot-bottom">
    <span>© 2026 GetSorted, Durban, South Africa. All rights reserved.</span>
    <nav aria-label="Legal"><a href="{{ route('about') }}">About</a><a href="{{ route('contact') }}">Contact</a><a href="{{ route('terms') }}">Terms</a><a href="{{ route('privacy') }}">Privacy</a></nav>
    <span class="foot-fine">Photos are illustrative. Sample pros, prices and reviews are examples.</span>
  </div>
  <p class="foot-word" aria-hidden="true">GetSorted</p>
</footer>
</div>
