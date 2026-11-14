/* Motion for "One team for software, apps and cloud" (layered stack) and "Your idea, delivered by one team" (path).
   Animations only run while a section is on screen. Respects prefers-reduced-motion. */
(function () {
  'use strict';
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var hasG = typeof gsap !== 'undefined';

  /* ---------- 1. layered stack ---------- */
  var stack = document.getElementById('tsStack');
  if (stack) {
    var layers = [].slice.call(stack.querySelectorAll('.ts-layer'));
    var links = [].slice.call(stack.querySelectorAll('.ts-link span'));
    var bullets = [].slice.call(document.querySelectorAll('.ts-blt'));
    var cur = 0, timer = null, hovering = false, introDone = false;

    var setActive = function (i) {
      cur = i;
      layers.forEach(function (l, k) { l.classList.toggle('on', k === i); });
      bullets.forEach(function (b, k) { b.classList.toggle('on', k === i); });
    };
    var start = function () {
      if (timer || reduce) return;
      timer = setInterval(function () { if (!hovering) setActive((cur + 1) % layers.length); }, 2600);
    };
    var stop = function () { clearInterval(timer); timer = null; };

    bullets.forEach(function (b, k) {
      b.addEventListener('mouseenter', function () { hovering = true; setActive(k); });
      b.addEventListener('mouseleave', function () { hovering = false; });
    });
    layers.forEach(function (l, k) {
      l.addEventListener('mouseenter', function () { hovering = true; setActive(k); });
      l.addEventListener('mouseleave', function () { hovering = false; });
    });
    setActive(0);

    var intro = function () {
      if (introDone || reduce || !hasG) return;
      introDone = true;
      gsap.fromTo(layers, { y: 46, opacity: 0, scale: .94 }, { y: 0, opacity: 1, scale: 1, duration: .8, ease: 'power3.out', stagger: .22, clearProps: 'transform' });
      gsap.fromTo(links, { scaleY: 0 }, { scaleY: 1, duration: .6, ease: 'power2.out', stagger: .3, delay: .35 });
    };
    new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (e.isIntersecting) { stack.classList.add('ts-live'); intro(); start(); }
        else { stack.classList.remove('ts-live'); stop(); }
      });
    }, { threshold: .2 }).observe(stack);
  }

  /* ---------- 2. idea to launch path ---------- */
  var path = document.getElementById('tlPath');
  if (path) {
    var fill = path.querySelector('.tl-fill');
    var steps = [].slice.call(path.querySelectorAll('.tl-step'));
    var cta = document.querySelector('.btm-box .theme-btn');
    var visible = false, ticking = false;

    var update = function () {
      ticking = false;
      if (reduce) return;
      var r = path.getBoundingClientRect(), vh = window.innerHeight;
      var line = vh * 0.62;                           /* reading line */
      var p = (line - r.top) / r.height;
      p = Math.max(0, Math.min(1, p));
      fill.style.transform = 'scaleY(' + p + ')';
      var reached = 0;
      steps.forEach(function (s) {
        var nr = s.querySelector('.tl-node').getBoundingClientRect();
        var on = nr.top + nr.height / 2 < line;
        s.classList.toggle('on', on);
        if (on) reached++;
      });
      if (cta) cta.classList.toggle('tl-pulse', reached === steps.length);
    };
    var req = function () { if (!ticking && visible) { ticking = true; requestAnimationFrame(update); } };
    window.addEventListener('scroll', req, { passive: true });
    window.addEventListener('resize', req);
    new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        visible = e.isIntersecting;
        path.classList.toggle('tl-live', visible);
        if (visible) req();
      });
    }, { rootMargin: '0px 0px 0px 0px', threshold: 0 }).observe(path);
    if (reduce) steps.forEach(function (s) { s.classList.add('on'); });
  }
})();
