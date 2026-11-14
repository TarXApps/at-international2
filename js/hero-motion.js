/* Hero motion graphics. Needs GSAP + SplitText (js/vendor). Safe to fail: without GSAP the finished design is shown. */
(function () {
  var root = document.querySelector('.hero-motion');
  var html = document.documentElement;
  function done() { html.classList.remove('hm-pre'); }
  if (!root || !window.gsap) { done(); return; }
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { done(); return; }

  var hasSplit = !!window.SplitText;
  if (hasSplit) gsap.registerPlugin(window.SplitText);
  var q = function (s) { return root.querySelector(s); };
  var qa = function (s) { return Array.prototype.slice.call(root.querySelectorAll(s)); };

  var GX = 422.5, GY = 416.2;                       // gear centre in the 1100 x 889 artwork
  var gxp = (GX / 1100 * 100) + '%', gyp = (GY / 889 * 100) + '%';
  var title = q('.hm-title'), tags = qa('.hm-tag span'), sub = q('.hm-sub'), btns = qa('.hero-actions .theme-btn');
  var art = q('.hm-art'), plate = q('.hm-plate'), hand = q('.hm-hand'), gearSvg = q('.hm-gear'), gearRot = q('.gear-rot');
  var glow = q('.hm-glow'), pulse = q('.hm-pulse'), sparksBox = q('.hm-sparks');
  var chips = qa('.hm-chip'), r1 = q('.hm-ring.r1'), r2 = q('.hm-ring.r2'), orb1 = q('.hm-orbiter'), orb2 = q('.hm-orbiter2');
  var tilt = q('.hm-tilt'), floatEl = q('.hm-float');
  var loops = gsap.timeline({ paused: true });      // everything that repeats forever lives here, so it can be paused off-screen
  var started = false;

  gsap.set(hand, { transformOrigin: gxp + ' ' + gyp });
  gsap.set(gearRot, { svgOrigin: GX + ' ' + GY, scale: 1.04, rotation: 30 });   // rest pose: a dip (not a tooth) sits under each fingertip
  gsap.set(plate, { transformOrigin: gxp + ' ' + gyp });
  gsap.set([orb1, orb2], { svgOrigin: '422.5 422.5' });
  gsap.set(r1, { svgOrigin: '422.5 422.5' });

  // ---- sparks created once, reused on every "click" of the gear
  var sparks = [];
  for (var i = 0; i < 9; i++) { var s = document.createElement('span'); sparksBox.appendChild(s); sparks.push(s); }

  function click(strength) {
    var k = strength || 1;
    gsap.fromTo(pulse, { scale: .5, opacity: .9 }, { scale: 1.55, opacity: 0, duration: .85, ease: 'power2.out', overwrite: true });
    gsap.fromTo(glow, { opacity: .5 }, { opacity: 0, duration: .8, ease: 'power2.out', overwrite: true });
    sparks.forEach(function (sp, n) {
      var a = (n / sparks.length) * Math.PI * 2 + Math.random() * .6, d = (110 + Math.random() * 90) * k;
      gsap.fromTo(sp, { x: 0, y: 0, scale: .5 + Math.random() * .8, opacity: 1 },
        { x: Math.cos(a) * d, y: Math.sin(a) * d, scale: 0, opacity: 0, duration: .55 + Math.random() * .35, ease: 'power3.out', overwrite: true });
    });
    gsap.fromTo(plate, { scale: 1 }, { scale: 1.012, duration: .12, yoyo: true, repeat: 1, ease: 'power1.inOut', overwrite: 'auto' });
  }

  // ---- the turning loop: grip, twist (hand rolls, bolt turns), click, release, re-grip
  function buildTurning() {
    // One grip = one move of the hand and one move of the bolt, together (the bolt advances one tooth, so the
    // fingertips land in the next pair of dips every time).
    var t = gsap.timeline({ repeat: -1 });
    t.to(hand, { scale: .92, duration: .3, ease: 'power2.inOut' })                                       // fingertips press into the dips
     .to(hand, { rotation: 12, duration: .9, ease: 'power2.inOut' }, '>.05')                              // wrist rolls...
     .to(gearRot, { rotation: '+=60', duration: .9, ease: 'power2.inOut' }, '<')                           // ...and carries the bolt one tooth round
     .add(function () { click(1); }, '>-.02')
     .to(hand, { scale: 1.03, duration: .38, ease: 'power2.out' }, '>.12')                                // fingertips lift out of the dips
     .to(hand, { rotation: 0, duration: .6, ease: 'power3.inOut' }, '<.04')                               // wrist rolls back
     .to(hand, { scale: 1, duration: .25, ease: 'sine.inOut' }, '>-.1')
     .to({}, { duration: .15 });
    return t;
  }

  // ---- tagline words light up in turn, chips follow
  function buildTagLoop() {
    var t = gsap.timeline({ repeat: -1, repeatDelay: .5 });
    tags.forEach(function (el, n) {
      t.add(function () {
        tags.forEach(function (x, m) { x.classList.toggle('on', m === n); });
        chips.forEach(function (c, m) { c.classList.toggle('on', m === n); });
        gsap.fromTo(chips[n], { scale: 1 }, { scale: 1.12, duration: .3, yoyo: true, repeat: 1, ease: 'power2.out', overwrite: 'auto' });
      }, n * 1.35);
    });
    t.add(function () { tags.forEach(function (x) { x.classList.remove('on'); }); chips.forEach(function (c) { c.classList.remove('on'); }); }, tags.length * 1.35);
    return t;
  }

  function buildIdle() {
    loops.add(gsap.to(floatEl, { y: -10, rotation: .5, duration: 3.4, ease: 'sine.inOut', yoyo: true, repeat: -1 }), 0);
    loops.add(gsap.to(r1, { rotation: 360, duration: 70, ease: 'none', repeat: -1 }), 0);
    loops.add(gsap.to(orb1, { rotation: 360, duration: 14, ease: 'none', repeat: -1 }), 0);
    loops.add(gsap.to(orb2, { rotation: -360, duration: 26, ease: 'none', repeat: -1 }), 0);
    loops.add(gsap.to(r2, { opacity: .35, duration: 2.6, ease: 'sine.inOut', yoyo: true, repeat: -1 }), 0);
    chips.forEach(function (c, n) {
      loops.add(gsap.to(c, { y: n % 2 ? 9 : -9, duration: 2.6 + n * .45, ease: 'sine.inOut', yoyo: true, repeat: -1 }), n * .3);
    });
    loops.add(buildTagLoop(), 0);
    loops.add(buildTurning(), 0);
  }

  // ---- intro
  function intro(split) {
    var words = split ? split.words : [title];
    if (split) {
      split.words.forEach(function (w) { if (w.closest('.hm-grad')) w.classList.add('hm-gw'); });
    }
    var subSplit = hasSplit ? SplitText.create(sub, { type: 'lines', mask: 'lines', linesClass: 'hm-sline' }) : null;
    var tl = gsap.timeline({ defaults: { ease: 'power4.out' }, onComplete: function () { started = true; } });

    // artwork
    tl.fromTo(plate, { scale: .72, rotation: -14, autoAlpha: 0 }, { scale: 1, rotation: 0, autoAlpha: 1, duration: 1.5, ease: 'expo.out' }, 0)
      .fromTo(art, { autoAlpha: 0 }, { autoAlpha: 1, duration: .01 }, 0)
      .fromTo(r1, { opacity: 0, scale: .8 }, { opacity: 1, scale: 1, duration: 1.6, ease: 'expo.out' }, .2)
      .fromTo(gearSvg, { scale: 0, rotation: -140, transformOrigin: gxp + ' ' + gyp }, { scale: 1, rotation: 0, duration: 1.3, ease: 'back.out(1.5)' }, .55)
      .fromTo(hand, { x: 260, y: 170, rotation: 11, autoAlpha: 0 }, { x: 0, y: 0, rotation: 0, autoAlpha: 1, duration: 1.5, ease: 'power4.out' }, .85)
      .fromTo(chips, { scale: 0, autoAlpha: 0 }, { scale: 1, autoAlpha: 1, duration: .8, stagger: .16, ease: 'back.out(2.2)' }, 1.5)
      .add(function () { click(1.15); }, 2.2);

    // copy
    tl.fromTo(words, { yPercent: 125, rotation: 6, autoAlpha: 0 }, { yPercent: 0, rotation: 0, autoAlpha: 1, duration: 1.1, stagger: .075, ease: 'expo.out' }, .15)
      .fromTo(title, { autoAlpha: 0 }, { autoAlpha: 1, duration: .01 }, .1)
      .fromTo(tags, { y: 34, scale: .7, autoAlpha: 0, filter: 'blur(8px)' }, { y: 0, scale: 1, autoAlpha: 1, filter: 'blur(0px)', duration: .85, stagger: .17, ease: 'back.out(2)' }, .75);
    if (subSplit) tl.fromTo(subSplit.lines, { yPercent: 105, autoAlpha: 0 }, { yPercent: 0, autoAlpha: 1, duration: .9, stagger: .1 }, 1.1)
                    .fromTo(sub, { autoAlpha: 0 }, { autoAlpha: 1, duration: .01 }, 1.1);
    else tl.fromTo(sub, { y: 24, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: .9 }, 1.1);
    tl.fromTo(btns, { y: 26, scale: .88, autoAlpha: 0 }, { y: 0, scale: 1, autoAlpha: 1, duration: .75, stagger: .12, ease: 'back.out(1.8)' }, 1.4);

    done();                                                // from-states are applied, safe to drop the "hidden" class
    tl.add(function () { loops.paused(!visible); if (visible) loops.play(); }, 2.45);
  }

  var visible = true;
  function start() {
    buildIdle();
    if (!hasSplit) { intro(null); return; }
    var opts = { type: 'lines,words', mask: 'lines', linesClass: 'hm-line', wordsClass: 'hm-w' };
    var split = SplitText.create(title, opts);
    intro(split);
    // re-wrap the headline if the width changes (after the intro has finished)
    var rt;
    window.addEventListener('resize', function () {
      clearTimeout(rt);
      rt = setTimeout(function () {
        if (!started) return;
        split.revert();
        split = SplitText.create(title, opts);
        split.words.forEach(function (w) { if (w.closest('.hm-grad')) w.classList.add('hm-gw'); });
      }, 250);
    });
  }

  // ---- pointer parallax (mouse devices only)
  if (window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
    var rx = gsap.quickTo(tilt, 'rotationX', { duration: .9, ease: 'power3.out' });
    var ry = gsap.quickTo(tilt, 'rotationY', { duration: .9, ease: 'power3.out' });
    var hx = gsap.quickTo(hand, 'xPercent', { duration: 1.1, ease: 'power3.out' });
    var cx = chips.map(function (c) { return gsap.quickTo(c, 'x', { duration: 1.2, ease: 'power3.out' }); });
    root.addEventListener('mousemove', function (e) {
      var b = root.getBoundingClientRect(), nx = (e.clientX - b.left) / b.width - .5, ny = (e.clientY - b.top) / b.height - .5;
      ry(nx * 9); rx(-ny * 7); hx(nx * -1.2);
      cx.forEach(function (f, n) { f(nx * (n % 2 ? 22 : -22)); });
    });
    root.addEventListener('mouseleave', function () { ry(0); rx(0); hx(0); cx.forEach(function (f) { f(0); }); });
  }

  // ---- gentle scroll drift
  var ticking = false;
  window.addEventListener('scroll', function () {
    if (ticking) return; ticking = true;
    requestAnimationFrame(function () {
      var y = Math.min(window.scrollY, 900);
      gsap.set(q('.hm-stage'), { y: y * .07 });
      gsap.set(q('.hm-copy'), { y: y * -.035 });
      ticking = false;
    });
  }, { passive: true });

  // ---- background particles (dots + faint links), paused when off-screen
  (function particles() {
    var cv = q('.hm-particles'); if (!cv || !cv.getContext) return;
    var ctx = cv.getContext('2d'), w = 0, h = 0, dpr = Math.min(window.devicePixelRatio || 1, 2), pts = [], on = true;
    function size() {
      var b = root.getBoundingClientRect(); w = b.width; h = b.height;
      cv.width = w * dpr; cv.height = h * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var n = Math.round(Math.min(56, Math.max(18, w * h / 26000)));
      pts = []; for (var i = 0; i < n; i++) pts.push({ x: Math.random() * w, y: Math.random() * h, vx: (Math.random() - .5) * .22, vy: (Math.random() - .5) * .22, r: .8 + Math.random() * 1.7, p: Math.random() * 6.28 });
    }
    size(); window.addEventListener('resize', size);
    gsap.ticker.add(function (t) {
      if (!on) return;
      ctx.clearRect(0, 0, w, h);
      for (var i = 0; i < pts.length; i++) {
        var p = pts[i]; p.x += p.vx; p.y += p.vy;
        if (p.x < -10) p.x = w + 10; if (p.x > w + 10) p.x = -10; if (p.y < -10) p.y = h + 10; if (p.y > h + 10) p.y = -10;
        var a = .35 + .3 * Math.sin(t * 1.4 + p.p);
        ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.283); ctx.fillStyle = 'rgba(190,200,255,' + a.toFixed(2) + ')'; ctx.fill();
        for (var j = i + 1; j < pts.length; j++) {
          var dx = p.x - pts[j].x, dy = p.y - pts[j].y, d = dx * dx + dy * dy;
          if (d < 12100) { ctx.strokeStyle = 'rgba(160,170,255,' + (.16 * (1 - d / 12100)).toFixed(3) + ')'; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(pts[j].x, pts[j].y); ctx.stroke(); }
        }
      }
    });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) {
        on = visible = en[0].isIntersecting;
        if (started) { loops.paused(!visible); }
      }, { threshold: 0 }).observe(root);
    }
  })();

  // go once the page (and the site preloader) is done and fonts are ready
  function go() { (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(function () { setTimeout(start, 350); }); }
  if (document.readyState === 'complete') go(); else window.addEventListener('load', go);
})();
