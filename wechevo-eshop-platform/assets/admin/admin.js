/* Admin helpers: sidebar toggle, bulk select, wizard stepper, import progress, colour inputs. No dependencies. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = ($('meta[name=csrf]') || {}).content || '';

  // sidebar (mobile)
  var tg = $('[data-toggle-side]');
  if (tg) tg.addEventListener('click', function () {
    var s = $('#side'), open = !s.classList.contains('open');
    s.classList.toggle('open', open); tg.setAttribute('aria-expanded', open);
  });

  // select all checkboxes
  $$('[data-check-all]').forEach(function (all) {
    all.addEventListener('change', function () {
      $$('input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; });
      count();
    });
  });
  function count() {
    var n = $$('input[name="ids[]"]:checked').length, el = $('[data-selected-count]');
    if (el) el.textContent = n;
  }
  $$('input[name="ids[]"]').forEach(function (c) { c.addEventListener('change', count); });

  // bulk action reveal extra field
  var ba = $('[data-bulk-action]');
  if (ba) {
    var sync = function () {
      $$('[data-bulk-extra]').forEach(function (x) { x.hidden = x.getAttribute('data-bulk-extra') !== ba.value; });
    };
    ba.addEventListener('change', sync); sync();
  }

  // wizard stepper (progressive enhancement: without JS every step is visible)
  var wz = $('form.wizard');
  if (wz) {
    var steps = $$('fieldset[data-step]', wz), nav = $$('.steps li'), i = 0;
    var show = function (n) {
      i = Math.max(0, Math.min(steps.length - 1, n));
      steps.forEach(function (s, k) { s.hidden = k !== i; });
      nav.forEach(function (li, k) { li.classList.toggle('on', k === i); });
      $('[data-prev]', wz).hidden = i === 0;
      $('[data-next]', wz).hidden = i === steps.length - 1;
      $('[data-submit]', wz).hidden = i !== steps.length - 1;
      if (i === steps.length - 1) review();
      var f = steps[i].querySelector('input,select,textarea'); if (f) f.focus();
    };
    var valid = function () {
      var bad = $$('input,select,textarea', steps[i]).filter(function (x) { return !x.checkValidity(); });
      if (bad.length) { bad[0].reportValidity(); return false; }
      return true;
    };
    var review = function () {
      var out = $('[data-review]', wz); if (!out) return;
      var rows = [];
      $$('input,select', wz).forEach(function (x) {
        if (!x.name || x.type === 'hidden' || x.type === 'password' || (x.type === 'radio' && !x.checked)) return;
        var lbl = x.closest('.field, label'); var t = lbl ? (lbl.querySelector('label') || lbl).textContent.trim().split('\n')[0] : x.name;
        var v = x.tagName === 'SELECT' ? x.options[x.selectedIndex].text : (x.type === 'checkbox' ? (x.checked ? 'Ναι' : 'Όχι') : x.value);
        if (x.type === 'radio') { v = (x.closest('label') || {}).textContent; t = x.getAttribute('data-label') || x.name; }
        rows.push('<dt>' + esc(t) + '</dt><dd>' + esc(v || '—') + '</dd>');
      });
      out.innerHTML = '<dl class="kv">' + rows.join('') + '</dl>';
    };
    $('[data-next]', wz).addEventListener('click', function () { if (valid()) show(i + 1); });
    $('[data-prev]', wz).addEventListener('click', function () { show(i - 1); });
    show(0);
  }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  // import progress runner
  var run = $('[data-import-run]');
  if (run) {
    var url = run.getAttribute('data-import-run'), bar = $('.progress i', run), txt = $('[data-import-text]', run);
    var step = function () {
      fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'fetch', 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (p) {
          if (p.error) { txt.textContent = p.error; return; }
          bar.style.width = p.percent + '%';
          txt.textContent = p.processed + ' / ' + p.total + ' γραμμές · ' + p.created + ' νέα · ' + p.updated + ' ενημερώσεις · ' + p.errors + ' σφάλματα · ' + p.activated + ' ενεργοποιήθηκαν';
          if (p.status === 'done' || p.status === 'failed') { setTimeout(function () { location.reload(); }, 600); }
          else setTimeout(step, 150);
        })
        .catch(function () { txt.textContent = 'Η σύνδεση διακόπηκε. Ανανεώστε τη σελίδα για να συνεχίσετε από το σημείο που σταμάτησε.'; });
    };
    step();
  }

  // colour pickers mirror text inputs
  $$('[data-color]').forEach(function (wrap) {
    var c = $('input[type=color]', wrap), t = $('input[type=text]', wrap);
    c.addEventListener('input', function () { t.value = c.value.toUpperCase(); });
    t.addEventListener('input', function () { if (/^#[0-9a-f]{6}$/i.test(t.value)) c.value = t.value; });
  });

  // slug preview
  var nm = $('[data-slug-source]'), sl = $('[data-slug-target]');
  if (nm && sl && !sl.value) nm.addEventListener('input', function () { sl.placeholder = nm.value ? 'αυτόματα από το όνομα' : ''; });
})();
