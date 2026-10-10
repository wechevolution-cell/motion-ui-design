<?php $csrf = e(Csrf::token()); ?>
<div class="wrap">
  <h1 class="kin page-h">Καλάθι</h1>
  <?php foreach ($notices as $n): ?><div class="alert alert-warning" style="margin-bottom:12px"><?= e($n) ?></div><?php endforeach; ?>
  <?php if (!$lines): ?>
    <div class="empty"><b style="font-size:22px">Το καλάθι σας είναι άδειο</b><a class="btn big" href="/products/">Πάμε για ψώνια</a></div>
  <?php else: ?>
  <div class="co">
    <section class="box" aria-label="Προϊόντα στο καλάθι">
      <?php foreach ($lines as $l): ?>
        <div class="ci" style="grid-template-columns:88px minmax(0,1fr) auto">
          <a class="t" href="/product/<?= e($l['product']['slug']) ?>/" style="width:88px;height:88px;background:<?= t01_tint((int)$l['product']['id']) ?>"><?= t01_img($l['image'], $l['product']['name'], '88px') ?></a>
          <div><b><a href="/product/<?= e($l['product']['slug']) ?>/" style="text-decoration:none"><?= e($l['product']['name']) ?></a></b>
            <?php if ($l['options_label']): ?><small><?= e($l['options_label']) ?></small><br><?php endif; ?>
            <small class="num"><?= money($l['price']) ?> / τεμ.</small>
            <form method="post" action="/cart/" style="display:flex;gap:8px;align-items:center;margin-top:6px;flex-wrap:wrap"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="key" value="<?= e($l['key']) ?>">
              <label class="sr" for="q<?= e(md5($l['key'])) ?>">Ποσότητα</label><input class="num" id="q<?= e(md5($l['key'])) ?>" type="number" name="qty" min="0" max="99" value="<?= (int)$l['qty'] ?>" style="width:70px;border-radius:12px;border:2px solid #ddd;padding:8px;font:inherit">
              <button class="chip" style="border-color:currentColor;color:inherit" name="op" value="qty">Ενημέρωση</button>
              <button class="chip" style="border-color:currentColor;color:inherit" name="op" value="remove">Αφαίρεση</button></form>
          </div>
          <b class="num"><?= money($l['total']) ?></b>
        </div>
      <?php endforeach; ?>
      <a href="/products/" style="font-weight:700">← Συνέχεια αγορών</a>
    </section>
    <aside class="box sum" aria-label="Σύνοψη">
      <h2>Σύνοψη</h2>
      <div class="ln"><span>Υποσύνολο</span><span><?= money($totals['subtotal']) ?></span></div>
      <div class="ln"><span>Αποστολή (ηπειρωτική)</span><span><?= $totals['shipping'] > 0 ? money($totals['shipping']) : 'Δωρεάν' ?></span></div>
      <?php if (!empty($shipping['islands_extra'])): ?><small style="opacity:.75">Νησιά: +<?= money($shipping['islands_extra']) ?> — επιλέγεται στο ταμείο.</small><?php endif; ?>
      <?php if ($totals['free_over'] > 0 && $totals['to_free'] > 0): ?><div class="freebar" role="progressbar" aria-label="Πρόοδος για δωρεάν αποστολή" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)($totals['subtotal'] / $totals['free_over'] * 100) ?>"><i style="width:<?= min(100, $totals['subtotal'] / $totals['free_over'] * 100) ?>%"></i></div><small>Ακόμη <?= money($totals['to_free']) ?> για δωρεάν αποστολή.</small><?php endif; ?>
      <div class="ln tot"><span>Σύνολο</span><span><?= money($totals['total']) ?></span></div>
      <small style="opacity:.75">Οι τιμές περιλαμβάνουν ΦΠΑ.</small>
      <a class="btn big primary" href="/checkout/">Ολοκλήρωση αγοράς →</a>
    </aside>
  </div>
  <?php endif; ?>
</div>
