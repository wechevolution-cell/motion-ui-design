<?php [$sk, $sl] = t01_stock($p); $hasVariants = (bool)$variants; ?>
<div class="qgrid">
  <div class="st"><?= t01_img($images[0] ?? null, $p['name'], '400px', true) ?></div>
  <div class="bd">
    <div style="display:flex;justify-content:space-between;gap:10px"><span class="sts <?= $sk ?>"><?= e($sl) ?></span><button class="xb" type="button" data-close aria-label="Κλείσιμο">✕</button></div>
    <h2 class="kin" style="font-size:30px"><?= e($p['name']) ?></h2>
    <?= t01_price($p) ?>
    <?php if ($p['short_description']): ?><p style="margin:0"><?= e($p['short_description']) ?></p><?php endif; ?>
    <?php if ($hasVariants || !Catalog::inStock($p)): ?>
      <a class="btn big primary" href="/product/<?= e($p['slug']) ?>/"><?= $hasVariants ? 'Επιλογή παραλλαγής' : 'Λεπτομέρειες' ?></a>
    <?php else: ?>
      <form method="post" action="/cart/" data-add-to-cart data-variants="[]" data-manage="<?= (int)$p['manage_stock'] ?>"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="op" value="add"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="variant_id" value=""><input type="hidden" name="qty" value="1">
        <button class="btn big primary" style="width:100%">Στο καλάθι</button><p data-form-msg role="alert" style="margin:8px 0 0;font-weight:700;color:#B42318"></p></form>
    <?php endif; ?>
    <a href="/product/<?= e($p['slug']) ?>/" style="font-weight:700">Όλες οι λεπτομέρειες →</a>
  </div>
</div>
