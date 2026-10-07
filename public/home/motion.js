/* GetSorted — motion layer (GSAP + ScrollTrigger + SplitText + Lenis).
   Everything here is decoration: if the libraries fail to load or the visitor
   prefers reduced motion, the page shows its final state and stays usable. */
(() => {
  /* ?motion=on forces animation for previewing on a machine with Reduce Motion switched on */
  const forced = new URLSearchParams(location.search).get('motion') === 'on';
  const reduced = !forced && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (forced) document.documentElement.classList.add('force-motion');
  const finePointer = window.matchMedia('(pointer: fine)').matches;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const showAll = () => $$('.appear').forEach((el) => el.classList.add('in'));
  if (reduced || !window.gsap || !window.ScrollTrigger) { showAll(); return; }

  const init = () => {
  const { gsap, ScrollTrigger } = window;
  const SplitText = window.SplitText;
  gsap.registerPlugin(ScrollTrigger);
  if (SplitText) gsap.registerPlugin(SplitText);
  const root = document.documentElement;
  root.classList.add('motion', 'motion-measure');
  /* CSS transitions must not run while GSAP measures, or it records half-finished values */
  ScrollTrigger.addEventListener('refreshInit', () => root.classList.add('motion-measure'));
  ScrollTrigger.addEventListener('refresh', () => requestAnimationFrame(() => root.classList.remove('motion-measure')));
  gsap.defaults({ ease: 'power3.out', duration: 0.9 });

  /* ---------- smooth scroll ---------- */
  let lenis = null;
  if (window.Lenis) {
    lenis = new window.Lenis({ duration: 1.1, smoothWheel: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((t) => lenis.raf(t * 1000));
    gsap.ticker.lagSmoothing(0);
    $$('a[href^="#"]').forEach((a) => a.addEventListener('click', (e) => {
      const id = a.getAttribute('href');
      if (id.length < 2) return;
      const target = $(id);
      if (!target) return;
      e.preventDefault();
      lenis.scrollTo(target, { offset: -90, duration: 1.4 });
    }));
  }

  /* ---------- helpers ---------- */
  const splitWords = (el) => {
    if (!SplitText) return [el];
    const split = new SplitText(el, { type: 'lines,words', linesClass: 'split-line', mask: 'lines' });
    return split.words;
  };
  const onEnter = (trigger, tl, start = 'top 82%') => ScrollTrigger.create({ trigger, start, once: true, onEnter: () => tl.play() });

  /* ---------- intro: logo, nav, hero ---------- */
  const heroTitle = $('.hero .h-hero');
  const heroWords = splitWords(heroTitle);
  const pars = $$('.par');
  const intro = gsap.timeline({ defaults: { ease: 'power4.out' } });
  intro
    .from('.header > .logo', { y: -30, autoAlpha: 0, duration: 0.8, clearProps: 'all' })
    .from('.header .logo-dot', { scale: 0, transformOrigin: '50% 100%', duration: 0.7, ease: 'back.out(5)' }, '-=0.2')
    .from('.nav a, .header .cta-dark, .header .menu-btn', { y: -18, autoAlpha: 0, stagger: 0.05, duration: 0.6, clearProps: 'all' }, 0.1)
    .from('.hero-circle', { scale: 0.55, autoAlpha: 0, duration: 1.6, ease: 'expo.out' }, 0)
    .from('.hero .badge-dark', { y: -24, autoAlpha: 0, duration: 0.7 }, 0.25)
    .from('.hero .kicker', { autoAlpha: 0, y: 10, duration: 0.6 }, 0.35)
    .from(heroWords, { yPercent: 115, rotate: 5, duration: 1, stagger: 0.07 }, 0.4)
    .from('.hero-sub', { y: 24, autoAlpha: 0, duration: 0.8 }, 0.75)
    .from('.hero .btn-lime', { scale: 0.7, autoAlpha: 0, duration: 0.9, ease: 'back.out(2.2)', clearProps: 'transform' }, 0.9)
    .from('.hero .guarantee', { autoAlpha: 0, y: 8, duration: 0.6 }, 1.05);

  /* polaroids fly in from the edges, tumbling into their tilt */
  pars.forEach((par, i) => {
    const pol = $('.pol', par);
    const rot = parseFloat(getComputedStyle(par).getPropertyValue('--rot')) || 0;
    gsap.set(pol, { rotation: rot });
    const fromTop = par.getBoundingClientRect().top < window.innerHeight / 2;
    intro.from(par, {
      y: fromTop ? -window.innerHeight * 0.9 : window.innerHeight * 0.9,
      x: (par.getBoundingClientRect().left < window.innerWidth / 2 ? -1 : 1) * 160,
      rotation: (i % 2 ? 1 : -1) * 35,
      autoAlpha: 0,
      duration: 1.4,
      ease: 'expo.out',
    }, 0.2 + i * 0.09);

    /* idle drift */
    gsap.to(pol, { y: i % 2 ? 9 : -9, duration: 2.6 + i * 0.35, ease: 'sine.inOut', yoyo: true, repeat: -1, delay: 1.8 });

    /* hover: straighten and lift */
    pol.addEventListener('mouseenter', () => gsap.to(pol, { rotation: 0, scale: 1.06, duration: 0.5, ease: 'back.out(2)', overwrite: 'auto' }));
    pol.addEventListener('mouseleave', () => gsap.to(pol, { rotation: rot, scale: 1, duration: 0.7, ease: 'elastic.out(1, .5)', overwrite: 'auto' }));

    /* scroll parallax: each card leaves at its own speed */
    gsap.to(par, { yPercent: -(30 + (i % 3) * 25), ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
  });

  /* pointer parallax on the polaroids */
  if (finePointer) {
    const movers = pars.map((par, i) => ({ x: gsap.quickTo(par, 'x', { duration: 1, ease: 'power3' }), y: gsap.quickTo(par, 'y', { duration: 1, ease: 'power3' }), d: 14 + (i % 3) * 12 }));
    $('.hero').addEventListener('mousemove', (e) => {
      const nx = e.clientX / window.innerWidth - 0.5;
      const ny = e.clientY / window.innerHeight - 0.5;
      movers.forEach((m) => { m.x(-nx * m.d); m.y(-ny * m.d); });
    });
  }
  gsap.to('.hero-circle', { scale: 1.08, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
  gsap.to('.hero-inner', { y: 120, autoAlpha: 0.2, ease: 'none', scrollTrigger: { trigger: '.hero', start: '30% top', end: 'bottom top', scrub: true } });

  /* ---------- headings: words rise out of a mask ---------- */
  $$('.h-xl, .h-md, .h-faq, .meet .h-hero').forEach((h) => {
    const words = splitWords(h);
    gsap.set(words, { yPercent: 110 });
    onEnter(h, gsap.timeline({ paused: true }).to(words, { yPercent: 0, duration: 1, stagger: 0.05, ease: 'power4.out' }));
  });

  /* ---------- everything else marked .appear fades up in batches ---------- */
  const rest = $$('.appear').filter((el) => !el.closest('.hero') && !el.matches('.h-xl, .h-md, .h-faq, .h-hero, .tile, .tl, .pc'));
  gsap.set(rest, { y: 34, autoAlpha: 0 });
  ScrollTrigger.batch(rest, { start: 'top 88%', once: true, onEnter: (b) => gsap.to(b, { y: 0, autoAlpha: 1, stagger: 0.09, duration: 0.9 }) });

  /* ---------- sound familiar ---------- */
  const fam = $('.familiar');
  gsap.fromTo(fam, { clipPath: 'inset(9% 7% 9% 7% round 56px)' }, { clipPath: 'inset(0% 0% 0% 0% round 24px)', ease: 'none', scrollTrigger: { trigger: fam, start: 'top 95%', end: 'top 35%', scrub: true } });
  const bubbles = $$('.familiar .bubble');
  gsap.set(bubbles, { scale: 0, autoAlpha: 0 });
  onEnter(fam, gsap.timeline({ paused: true }).to(bubbles, { scale: 1, autoAlpha: 1, duration: 0.7, stagger: 0.2, ease: 'back.out(3)' }, 0.2), 'top 75%');

  /* pin the panel; to-do items tick off and fall away one by one, bubbles fade between them */
  const todoItems = $$('.todo-item').slice(0, 9);
  const step = 1;
  const famTl = gsap.timeline({ defaults: { ease: 'power2.inOut' } });
  todoItems.forEach((it, i) => {
    famTl.to(it, { x: -40, autoAlpha: 0, duration: step * 0.6 }, i * step + 0.4)
      .to(it, { height: 0, paddingTop: 0, paddingBottom: 0, marginBottom: -6, borderWidth: 0, duration: step * 0.5 }, i * step + 0.75);
  });
  const order = [0, 3, 1, 2];
  order.forEach((b, k) => famTl.to(bubbles[b], { scale: 0.4, autoAlpha: 0, duration: 0.5, ease: 'back.in(2)', immediateRender: false }, 1.2 + k * 2));
  const total = famTl.duration();
  const mm = gsap.matchMedia();
  const pinFor = (start) => ScrollTrigger.create({
    trigger: '.fam-sec', start, end: `+=${Math.round(total * 160)}`, pin: true, scrub: 0.5, animation: famTl,
    onUpdate: (self) => {
      const t = self.progress * total;
      todoItems.forEach((it, i) => it.classList.toggle('done', t > i * step + 0.1));
    },
  });
  mm.add('(min-width: 1101px)', () => { pinFor('center center'); });
  mm.add('(max-width: 1100px)', () => { pinFor('top top+=70'); });

  gsap.from('.rule', { scaleX: 0, transformOrigin: 'left', ease: 'none', scrollTrigger: { trigger: '.rule', start: 'top 95%', end: 'top 60%', scrub: true } });

  /* ---------- one-thread tiles ---------- */
  const tiles = $$('.tile');
  gsap.set(tiles, { y: 70, rotation: () => gsap.utils.random(-9, 9), autoAlpha: 0 });
  onEnter('.tiles', gsap.timeline({ paused: true }).to(tiles, { y: 0, rotation: 0, autoAlpha: 1, duration: 0.9, stagger: { each: 0.08, from: 'random' }, ease: 'back.out(1.7)', clearProps: 'transform' }));

  /* ---------- meet / timeline ---------- */
  gsap.fromTo('.meet mark', { backgroundSize: '0% 100%' }, { backgroundSize: '100% 100%', duration: 1, ease: 'power3.inOut', scrollTrigger: { trigger: '.meet mark', start: 'top 80%', once: true } });
  gsap.from('.chips li', { x: -24, autoAlpha: 0, stagger: 0.08, duration: 0.7, scrollTrigger: { trigger: '.chips', start: 'top 85%', once: true } });
  $$('.scribble').forEach((sc) => {
    const path = $('.draw', sc);
    const len = path.getTotalLength();
    gsap.set(path, { strokeDasharray: len, strokeDashoffset: len });
    const tl = gsap.timeline({ paused: true })
      .from(sc, { autoAlpha: 0, scale: 0.8, duration: 0.6, ease: 'back.out(2)' })
      .to(path, { strokeDashoffset: 0, duration: 0.9, ease: 'power2.inOut' }, 0.3);
    onEnter(sc.parentElement, tl, 'top 60%');
  });
  gsap.from('.tl-cols span', { y: -16, autoAlpha: 0, stagger: 0.08, duration: 0.6, scrollTrigger: { trigger: '.timeline', start: 'top 80%', once: true } });
  const cards = $$('.tl');
  gsap.set(cards, { x: -80, rotation: -4, autoAlpha: 0 });
  onEnter('.tl-cards', gsap.timeline({ paused: true }).to(cards, { x: 0, rotation: 0, autoAlpha: 1, stagger: 0.18, duration: 0.9, ease: 'back.out(1.4)', clearProps: 'transform' }), 'top 72%');
  gsap.from('.tl .num', { scale: 0, stagger: 0.12, duration: 0.6, ease: 'back.out(4)', scrollTrigger: { trigger: '.timeline', start: 'top 55%', once: true } });

  /* ---------- marquees react to scroll speed ---------- */
  const loops = $$('.band-track, .rev-track').map((track, i) => gsap.to(track, { xPercent: -50, ease: 'none', duration: i ? 48 : 26, repeat: -1 }));
  let skewTo = gsap.quickTo('.band-track', 'skewX', { duration: 0.5, ease: 'power3' });
  ScrollTrigger.create({
    start: 0, end: 'max',
    onUpdate: (self) => {
      const v = self.getVelocity();
      const boost = gsap.utils.clamp(1, 5, 1 + Math.abs(v) / 400);
      loops.forEach((l) => { gsap.to(l, { timeScale: boost * (v < 0 ? -1 : 1), duration: 0.2, overwrite: true }); });
      skewTo(gsap.utils.clamp(-10, 10, v / -250));
    },
  });
  ScrollTrigger.addEventListener('scrollEnd', () => { loops.forEach((l) => gsap.to(l, { timeScale: Math.sign(l.timeScale()) || 1, duration: 1.2 })); skewTo(0); });
  const rail = $('.rev-rail');
  if (rail && loops[1]) {
    rail.addEventListener('mouseenter', () => gsap.to(loops[1], { timeScale: 0, duration: 0.6 }));
    rail.addEventListener('mouseleave', () => gsap.to(loops[1], { timeScale: 1, duration: 0.6 }));
  }

  /* ---------- trades ---------- */
  gsap.fromTo('.t-left', { y: 160, rotation: -26 }, { y: -120, rotation: -8, ease: 'none', scrollTrigger: { trigger: '.trades', start: 'top bottom', end: 'bottom top', scrub: true } });
  gsap.fromTo('.t-right', { y: 220, rotation: 22 }, { y: -160, rotation: 4, ease: 'none', scrollTrigger: { trigger: '.trades', start: 'top bottom', end: 'bottom top', scrub: true } });
  gsap.from('.trade-links li', { y: 30, autoAlpha: 0, stagger: 0.08, duration: 0.8, scrollTrigger: { trigger: '.trade-links', start: 'top 88%', once: true } });
  gsap.from('.arc', { yPercent: 12, scaleX: 0.7, ease: 'none', scrollTrigger: { trigger: '.arc', start: 'top bottom', end: 'top 55%', scrub: true } });

  /* ---------- for pros ---------- */
  const pcs = $$('.pc');
  gsap.set(pcs, { y: 90, autoAlpha: 0, rotation: (i) => [-3, 2, -2, 3][i % 4] });
  onEnter('.price-grid', gsap.timeline({ paused: true })
    .to(pcs, { y: 0, autoAlpha: 1, rotation: 0, stagger: 0.12, duration: 1, ease: 'power4.out', clearProps: 'transform' })
    .from('.pc-float', { y: -30, scale: 0, duration: 0.7, ease: 'back.out(3)' }, 0.5)
    .from('.work-pills li', { scale: 0.6, autoAlpha: 0, stagger: 0.05, duration: 0.5, ease: 'back.out(2)' }, 0.6)
    .from('.pc-list li', { x: -14, autoAlpha: 0, stagger: 0.04, duration: 0.5 }, 0.4), 'top 80%');

  /* ---------- compare table ---------- */
  const rows = $$('.cmp tbody tr');
  onEnter('.cmp', gsap.timeline({ paused: true })
    .from('.cmp thead span', { y: -14, autoAlpha: 0, stagger: 0.08, duration: 0.6 })
    .from(rows, { x: -30, autoAlpha: 0, stagger: 0.09, duration: 0.7 }, 0.15)
    .from('.cmp .ok', { scale: 0, rotation: -90, stagger: 0.09, duration: 0.5, ease: 'back.out(3)' }, 0.4)
    .fromTo('.cmp .tot', { backgroundColor: 'rgba(198,253,80,0)' }, { backgroundColor: 'rgba(198,253,80,.55)', duration: 0.5, yoyo: true, repeat: 1 }, '>-0.1'), 'top 78%');

  /* ---------- reviews ---------- */
  if ($('.reviews')) {
    gsap.from('.faces span', { scale: 0, autoAlpha: 0, stagger: 0.07, duration: 0.6, ease: 'back.out(3)', clearProps: 'transform', scrollTrigger: { trigger: '.faces', start: 'top 90%', once: true } });
    gsap.from('.rule-dark', { scaleX: 0, transformOrigin: 'left', ease: 'none', scrollTrigger: { trigger: '.rule-dark', start: 'top 95%', end: 'top 65%', scrub: true } });
  }

  /* ---------- FAQ ---------- */
  gsap.from('.faq-panel', { y: 80, autoAlpha: 0, duration: 1.1, ease: 'power4.out', clearProps: 'transform', scrollTrigger: { trigger: '.faq-sec', start: 'top 85%', once: true } });
  gsap.from('.qa', { y: 20, autoAlpha: 0, stagger: 0.05, duration: 0.6, scrollTrigger: { trigger: '.faq-list', start: 'top 85%', once: true } });

  /* ---------- start a job ---------- */
  gsap.set('.form-card', { y: 140, rotation: -5, autoAlpha: 0 });
  onEnter('.contact', gsap.timeline({ paused: true }).to('.form-card', { y: 0, rotation: 0, autoAlpha: 1, duration: 1.2, ease: 'back.out(1.3)', clearProps: 'transform' }), 'top 40%');

  /* ---------- footer ---------- */
  const word = $('.foot-word');
  if (SplitText) {
    const chars = new SplitText(word, { type: 'chars' }).chars;
    gsap.from(chars, { yPercent: 100, ease: 'none', stagger: 0.04, scrollTrigger: { trigger: '.footer', start: 'top 70%', end: 'bottom bottom', scrub: 0.6 } });
  }
  gsap.from('.foot-brand > *, .foot-cols > div', { y: 30, autoAlpha: 0, stagger: 0.08, duration: 0.8, scrollTrigger: { trigger: '.footer', start: 'top 80%', once: true } });

  /* ---------- magnetic buttons ---------- */
  if (finePointer) {
    $$('.cta-dark, .btn-submit, .header .menu-btn').forEach((btn) => {
      const mx = gsap.quickTo(btn, 'x', { duration: 0.4, ease: 'power3' });
      const my = gsap.quickTo(btn, 'y', { duration: 0.4, ease: 'power3' });
      btn.addEventListener('mousemove', (e) => {
        const r = btn.getBoundingClientRect();
        mx((e.clientX - r.left - r.width / 2) * 0.3);
        my((e.clientY - r.top - r.height / 2) * 0.4);
      });
      btn.addEventListener('mouseleave', () => { gsap.to(btn, { x: 0, y: 0, duration: 0.8, ease: 'elastic.out(1, .4)' }); });
    });
  }

  ScrollTrigger.refresh();
  };

  /* split headings only once the web fonts have landed, so line breaks are final */
  let started = false;
  const go = () => { if (!started) { started = true; init(); } };
  if (document.fonts) { document.fonts.ready.then(go); setTimeout(go, 3500); } else go();
})();
