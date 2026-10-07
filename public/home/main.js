(() => {
  document.documentElement.classList.add('js');
  const reduced = new URLSearchParams(location.search).get('motion') !== 'on' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Header: nav pill hides on scroll down, returns on scroll up */
  const header = document.querySelector('[data-header]');
  let lastY = window.scrollY;
  window.addEventListener('scroll', () => {
    const y = window.scrollY;
    header.classList.toggle('is-hidden', y > 120 && y > lastY);
    lastY = y;
    checkDark();
  }, { passive: true });
  /* Logo turns white while the background behind it is dark */
  const logo = header.querySelector('.logo');
  const bgAt = (x, y) => {
    let el = document.elementsFromPoint(x, y).find((e) => !header.contains(e));
    while (el) {
      const c = getComputedStyle(el).backgroundColor.match(/[\d.]+/g);
      if (c && (c.length < 4 || +c[3] > 0.5)) return c.slice(0, 3).map(Number);
      el = el.parentElement;
    }
    return [245, 245, 245];
  };
  function checkDark() {
    const r = logo.getBoundingClientRect();
    const [red, green, blue] = bgAt(r.left + 6, r.top + r.height / 2);
    header.classList.toggle('on-dark', 0.2126 * red + 0.7152 * green + 0.0722 * blue < 110);
  }
  checkDark();

  /* Mobile menu */
  const sheet = document.querySelector('[data-sheet]');
  const opener = document.querySelector('[data-menu-open]');
  const openSheet = () => { sheet.hidden = false; opener.setAttribute('aria-expanded', 'true'); document.body.style.overflow = 'hidden'; sheet.querySelector('.sheet-links a').focus(); };
  const closeSheet = () => { sheet.hidden = true; opener.setAttribute('aria-expanded', 'false'); document.body.style.overflow = ''; };
  opener.addEventListener('click', openSheet);
  sheet.querySelectorAll('[data-menu-close]').forEach((el) => el.addEventListener('click', closeSheet));
  sheet.addEventListener('click', (e) => { if (e.target === sheet) closeSheet(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !sheet.hidden) { closeSheet(); opener.focus(); } });

  /* To-do list: duplicate so the upward scroll loops seamlessly */
  const todo = document.querySelector('[data-todo]');
  if (todo) todo.innerHTML += todo.innerHTML;

  /* FAQ accordion: one open at a time */
  const qas = [...document.querySelectorAll('.qa')];
  const setQa = (qa, open) => {
    const body = qa.querySelector('.qa-a');
    qa.classList.toggle('is-open', open);
    qa.querySelector('button').setAttribute('aria-expanded', String(open));
    body.style.maxHeight = open ? `${body.scrollHeight}px` : '0px';
  };
  qas.forEach((qa) => qa.querySelector('button').addEventListener('click', () => {
    const open = !qa.classList.contains('is-open');
    qas.forEach((o) => { if (o !== qa) setQa(o, false); });
    setQa(qa, open);
  }));

  /* Reviews rail: duplicate cards for a seamless loop */
  const track = document.querySelector('.rev-track');
  if (track && !reduced) track.innerHTML += track.innerHTML.replace(/<figure /g, '<figure aria-hidden="true" ');

  /* Footer wordmark spans the full content width */
  const word = document.querySelector('.foot-word');
  const fitWord = () => {
    word.style.fontSize = '100px';
    const avail = word.parentElement.clientWidth - parseFloat(getComputedStyle(word.parentElement).paddingLeft) * 2;
    word.style.fontSize = `${Math.floor(100 * avail / word.getBoundingClientRect().width)}px`;
  };
  (document.fonts ? document.fonts.ready : Promise.resolve()).then(fitWord);
  window.addEventListener('resize', fitWord);
})();
