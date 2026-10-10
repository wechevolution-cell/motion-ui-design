/* Template 01 · Bento Market — progressive enhancement. Every feature works without JS through normal forms/links. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };
  var eur = function (n) { return new Intl.NumberFormat('el-GR', { style: 'currency', currency: 'EUR' }).format(n); };

  function post(form, extra) {
    var fd = new FormData(form);
    if (extra) Object.keys(extra).forEach(function (k) { fd.set(k, extra[k]); });
    return fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }
  function toast(t) {
    var el = $('#toast'); if (!el) return;
    el.textContent = t; el.classList.add('on');
    clearTimeout(toast.t); toast.t = setTimeout(function () { el.classList.remove('on'); }, 2200);
  }

  /* ---------- cart sheet ---------- */
  var sheet = $('#cart-sheet'), scrim = $('#scrim'), lastFocus = null;
  function setCount(n) {
    $$('[data-cart-count]').forEach(function (el) { el.textContent = n; });
    var pill = $('.cartpill'); if (pill) { pill.classList.remove('pop'); void pill.offsetWidth; pill.classList.add('pop'); pill.setAttribute('aria-label', 'Καλάθι, ' + n + ' τεμάχια'); }
  }
  function fillSheet(html) { $('[data-cart-body]', sheet).innerHTML = html; bindSheet(); }
  function openSheet() {
    lastFocus = document.activeElement;
    sheet.classList.add('on'); sheet.setAttribute('aria-hidden', 'false'); scrim.classList.add('on');
    setTimeout(function () { var b = $('[data-close]', sheet); if (b) b.focus(); }, 50);
  }
  function closeSheet() {
    sheet.classList.remove('on'); sheet.setAttribute('aria-hidden', 'true'); scrim.classList.remove('on');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  function bindSheet() {
    $$('[data-close]', sheet).forEach(function (b) { b.addEventListener('click', closeSheet); });
    $$('form[data-cart-op]', sheet).forEach(function (f) {
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        post(f).then(function (r) { setCount(r.count); fillSheet(r.html); if (r.error) toast(r.error); });
      });
    });
  }
  if (sheet) {
    $$('[data-cart-open]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        if (location.pathname === '/cart/' || location.pathname === '/checkout/') return;
        e.preventDefault();
        fetch('/cart/?partial=1', { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (r) { fillSheet(r.html); openSheet(); })
          .catch(function () { location.href = '/cart/'; });
      });
    });
    scrim.addEventListener('click', closeSheet);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sheet.classList.contains('on')) closeSheet(); });
  }

  /* ---------- add to cart (product page + quick view) ---------- */
  function bindAdd(root) {
    $$('form[data-add-to-cart]', root).forEach(function (f) {
      var variants = []; try { variants = JSON.parse(f.getAttribute('data-variants') || '[]'); } catch (e) {}
      var manage = f.getAttribute('data-manage') === '1';
      var msg = $('[data-form-msg]', f), buy = $('[data-buy]', f) || $('button', f);
      function current() {
        if (!variants.length) return null;
        var sel = {}; var all = true;
        $$('input[data-opt]', f).forEach(function (i) { if (i.checked) sel[i.getAttribute('data-opt')] = i.value; });
        var names = {}; $$('input[data-opt]', f).forEach(function (i) { names[i.getAttribute('data-opt')] = 1; });
        Object.keys(names).forEach(function (n) { if (!(n in sel)) all = false; });
        if (!all) return undefined;
        return variants.filter(function (v) { return Object.keys(sel).every(function (k) { return v.opts[k] === sel[k]; }); })[0] || false;
      }
      function sync() {
        var v = current();
        if (!variants.length) return;
        f.variant_id.value = v ? v.id : '';
        var label = $('[data-stock-label]'), priceEl = $('[data-price]');
        if (v === false) { if (label) { label.className = 'sts out'; label.textContent = 'Μη διαθέσιμος συνδυασμός'; } buy.disabled = true; return; }
        if (!v) { buy.disabled = false; return; }
        var out = manage && v.stock <= 0;
        if (label) { label.className = 'sts ' + (out ? 'out' : (manage && v.stock <= 3 ? 'low' : 'in')); label.textContent = out ? 'Εξαντλημένο' : (manage && v.stock <= 3 ? 'Τελευταία ' + v.stock + ' τεμάχια' : 'Άμεσα διαθέσιμο'); }
        if (priceEl && v.price !== null) priceEl.innerHTML = '<span class="price">' + eur(v.price) + '</span>';
        buy.disabled = out; buy.textContent = out ? 'Εξαντλημένο' : 'Στο καλάθι';
        var bar = $('[data-bar-buy]'); if (bar) { bar.disabled = out; }
      }
      $$('input[data-opt]', f).forEach(function (i) { i.addEventListener('change', sync); });
      sync();
      $$('[data-qty]', f).forEach(function (b) {
        b.addEventListener('click', function () { var q = f.qty; q.value = Math.max(1, Math.min(99, (+q.value || 1) + (+b.getAttribute('data-qty')))); });
      });
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        if (variants.length && !current()) { if (msg) msg.textContent = 'Επιλέξτε ' + $$('legend', f).map(function (l) { return l.textContent.toLowerCase(); }).join(' και ') + '.'; return; }
        if (msg) msg.textContent = '';
        buy.disabled = true;
        post(f).then(function (r) {
          buy.disabled = false;
          if (!r.ok) { if (msg) msg.textContent = r.error || 'Δεν ήταν δυνατή η προσθήκη.'; return; }
          setCount(r.count);
          var d = $('#qview'); if (d && d.open) d.close();
          if (sheet) { fillSheet(r.html); openSheet(); } else toast('Προστέθηκε στο καλάθι');
        }).catch(function () { buy.disabled = false; f.submit(); });
      });
    });
  }
  bindAdd(document);

  // mobile sticky buy bar mirrors the main button
  var bar = $('#buybar'), mainBuy = $('[data-buy]');
  if (bar && mainBuy && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (es) {
      var show = !es[0].isIntersecting && es[0].boundingClientRect.top < 0;
      bar.classList.toggle('on', show); bar.setAttribute('aria-hidden', show ? 'false' : 'true');
    }).observe(mainBuy);
    $('[data-bar-buy]', bar).addEventListener('click', function () { mainBuy.closest('form').requestSubmit(); });
  }

  // gallery thumbnails
  $$('.thumbs button').forEach(function (b) {
    b.addEventListener('click', function () {
      var img = $('#stage img'); if (!img) return;
      img.src = b.getAttribute('data-full'); var ss = b.getAttribute('data-srcset'); if (ss) img.srcset = ss; else img.removeAttribute('srcset');
      $$('.thumbs button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
    });
  });

  /* ---------- wishlist hearts ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target.closest && e.target.closest('form[data-wish]');
    if (!f) return;
    e.preventDefault();
    post(f).then(function (r) {
      var b = $('button', f); b.setAttribute('aria-pressed', r.on ? 'true' : 'false');
      if (b.classList.contains('heart')) b.textContent = r.on ? '♥' : '♡';
      else b.textContent = r.on ? '♥ Στα αγαπημένα' : '♡ Προσθήκη στα αγαπημένα';
      toast(r.on ? 'Προστέθηκε στα αγαπημένα' : 'Αφαιρέθηκε από τα αγαπημένα');
    }).catch(function () { f.submit(); });
  });

  /* ---------- quick view ---------- */
  var qv = $('#qview');
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('[data-quick]');
    if (!a || !qv || typeof qv.showModal !== 'function') return;
    e.preventDefault();
    fetch(a.getAttribute('data-quick'), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw 0; return r.text(); })
      .then(function (html) {
        qv.innerHTML = html; qv.showModal();
        $$('[data-close]', qv).forEach(function (b) { b.addEventListener('click', function () { qv.close(); }); });
        bindAdd(qv);
      })
      .catch(function () { location.href = a.href; });
  });
  if (qv) qv.addEventListener('click', function (e) { if (e.target === qv) qv.close(); });

  /* ---------- listing: load more, autosubmit sort ---------- */
  $$('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });
  var more = $('[data-load-more]');
  if (more) {
    more.addEventListener('click', function (e) {
      e.preventDefault();
      var url = more.getAttribute('data-load-more');
      more.setAttribute('aria-busy', 'true');
      fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
        $('#grid').insertAdjacentHTML('beforeend', html);
        var u = new URL(url, location.href), p = +u.searchParams.get('page'), pages = +more.getAttribute('data-pages');
        u.searchParams.delete('partial');
        history.replaceState(null, '', u.pathname + u.search);
        if (p >= pages) { more.remove(); return; }
        u.searchParams.set('page', p + 1); more.href = u.pathname + u.search; u.searchParams.set('partial', '1');
        more.setAttribute('data-load-more', u.pathname + u.search); more.removeAttribute('aria-busy');
      }).catch(function () { location.href = more.href; });
    });
  }

  /* ---------- search toggle ---------- */
  var st = $('[data-search-toggle]'), sp = $('#search-pop');
  if (st && sp) st.addEventListener('click', function () { sp.hidden = !sp.hidden; st.setAttribute('aria-expanded', !sp.hidden); if (!sp.hidden) $('input', sp).focus(); });

  /* ---------- newsletter (home + popup) ---------- */
  $$('form[data-newsletter]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var out = $('[data-msg]', f);
      fetch(f.action, { method: 'POST', body: new FormData(f), headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) { out.textContent = r.message || ''; if (r.ok) { f.reset(); store.set('nl-done', '1'); } })
        .catch(function () { f.submit(); });
    });
  });

  /* ---------- popup (once per visitor per version) ---------- */
  var pop = $('#popup');
  if (pop && typeof pop.showModal === 'function') {
    var key = 'popup-v' + pop.getAttribute('data-version');
    if (!store.get(key) && !store.get('nl-done')) {
      setTimeout(function () { if (!document.querySelector('dialog[open]')) pop.showModal(); store.set(key, '1'); }, (+pop.getAttribute('data-delay') || 8) * 1000);
    }
    $$('[data-close]', pop).forEach(function (b) { b.addEventListener('click', function () { pop.close(); }); });
    pop.addEventListener('click', function (e) { if (e.target === pop) pop.close(); });
  }

  /* ---------- cookie consent: GA4 loads only after "Αποδοχή" ---------- */
  var ga = document.body.getAttribute('data-ga'), ck = $('#cookie');
  function loadGA() {
    if (!ga || window.__ga) return; window.__ga = 1;
    var s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga);
    document.head.appendChild(s);
    window.dataLayer = window.dataLayer || []; window.gtag = function () { dataLayer.push(arguments); };
    gtag('js', new Date()); gtag('config', ga, { anonymize_ip: true });
  }
  if (ga && ck) {
    var c = store.get('consent');
    if (c === 'yes') loadGA(); else if (!c) ck.hidden = false;
    $$('[data-consent]', ck).forEach(function (b) {
      b.addEventListener('click', function () { var v = b.getAttribute('data-consent'); store.set('consent', v); ck.hidden = true; if (v === 'yes') loadGA(); });
    });
  }
  $$('[data-cookie-settings]').forEach(function (b) { b.addEventListener('click', function () { if (ck) ck.hidden = false; else toast('Χρησιμοποιούνται μόνο απαραίτητα cookies.'); }); });

  /* ---------- checkout live totals ---------- */
  var cfgEl = $('#ship-cfg');
  if (cfgEl) {
    var cfg = JSON.parse(cfgEl.textContent);
    var recalc = function () {
      var isl = ($('input[name=region]:checked') || {}).value === 'islands';
      var pmEl = $('input[name=payment_method]:checked'); var fee = pmEl ? +pmEl.getAttribute('data-fee') : 0;
      var ship = (cfg.free_over > 0 && cfg.subtotal >= cfg.free_over ? 0 : cfg.flat) + (isl ? cfg.islands : 0);
      $('[data-ship]').textContent = ship > 0 ? eur(ship) : 'Δωρεάν';
      $('[data-fee]').textContent = eur(fee); $('[data-fee-row]').hidden = !(fee > 0);
      $$('[data-total]').forEach(function (el) { el.textContent = eur(cfg.subtotal + ship + fee); });
    };
    $$('[data-recalc]').forEach(function (i) { i.addEventListener('change', recalc); });
  }

  /* ---------- kinetic headline (width axis follows scroll) ---------- */
  var kh = $('[data-kinetic]');
  if (kh && !reduced) {
    var tick = false;
    window.addEventListener('scroll', function () {
      if (tick) return; tick = true;
      requestAnimationFrame(function () { var y = Math.min(1, scrollY / 400); kh.style.fontVariationSettings = '"wdth" ' + (151 - y * 60) + ',"opsz" 144'; tick = false; });
    }, { passive: true });
  }
})();
