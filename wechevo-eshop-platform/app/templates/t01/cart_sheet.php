<?php $t = Cart::totals($lines, $shipping); $csrf = e(Csrf::token()); ?>
<div class="shd"><h2 class="kin">Καλάθι</h2><button class="xb" type="button" data-close aria-label="Κλείσιμο καλαθιού">✕</button></div>
<div class="cl">
  <?php foreach ($notices as $n): ?><div class="alert alert-warning"><?= e($n) ?></div><?php endforeach; ?>
  <?php foreach ($lines as $l): ?>
    <div class="ci">
      <div class="t" style="background:<?= t01_tint((int)$l['product']['id']) ?>"><?= t01_img($l['image'], $l['product']['name'], '72px') ?></div>
      <div><b><?= e($l['product']['name']) ?></b><?php if ($l['options_label']): ?><small><?= e($l['options_label']) ?></small><?php endif; ?>
        <div class="qt" style="margin-top:6px;transform:scale(.85);transform-origin:left">
          <form method="post" action="/cart/" data-cart-op><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="op" value="qty"><input type="hidden" name="key" value="<?= e($l['key']) ?>"><input type="hidden" name="qty" value="<?= $l['qty'] - 1 ?>"><button aria-label="Μείωση">−</button></form>
          <span style="min-width:36px;text-align:center;font-weight:800" class="num"><?= (int)$l['qty'] ?></span>
          <form method="post" action="/cart/" data-cart-op><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="op" value="qty"><input type="hidden" name="key" value="<?= e($l['key']) ?>"><input type="hidden" name="qty" value="<?= $l['qty'] + 1 ?>"><button aria-label="Αύξηση">+</button></form>
        </div>
      </div>
      <b class="num"><?= money($l['total']) ?></b>
    </div>
  <?php endforeach; ?>
  <?php if (!$lines): ?><div style="text-align:center;padding:30px 0;display:grid;gap:14px;justify-items:center"><b style="font-size:20px">Το καλάθι είναι άδειο</b><a class="btn" href="/products/">Πάμε για ψώνια</a></div><?php endif; ?>
</div>
<?php if ($lines): ?>
<div class="cf">
  <?php if ($t['free_over'] > 0): ?>
  <div><b style="font-size:14px"><?= $t['to_free'] > 0 ? 'Ακόμη ' . money($t['to_free']) . ' για δωρεάν αποστολή' : 'Έχετε δωρεάν αποστολή!' ?></b>
    <div class="freebar" style="margin-top:8px" role="progressbar" aria-label="Πρόοδος για δωρεάν αποστολή" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)min(100, $t['subtotal'] / $t['free_over'] * 100) ?>"><i style="width:<?= min(100, $t['subtotal'] / $t['free_over'] * 100) ?>%"></i></div></div>
  <?php endif; ?>
  <div class="tl"><span>Υποσύνολο</span><span class="num"><?= money($t['subtotal']) ?></span></div>
  <a class="btn big primary" href="/checkout/">Ταμείο →</a>
  <a href="/cart/" style="text-align:center;font-weight:700">Προβολή καλαθιού</a>
</div>
<?php endif; ?>
