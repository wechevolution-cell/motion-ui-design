<?php
[$sk, $sl] = t01_stock($p);
$opts = [];
foreach ($variants as $v) foreach ($v['opts'] as $k => $x) $opts[$k][$x] = true;
$vdata = array_map(fn($v) => ['id' => (int)$v['id'], 'opts' => $v['opts'], 'stock' => (int)$v['stock'], 'price' => $v['price'] !== null ? (float)$v['price'] : null], $variants);
$canBuy = $variants ? array_sum(array_column($variants, 'stock')) > 0 || !$p['manage_stock'] : Catalog::inStock($p);
$on = in_array((int)$p['id'], $wish, true);
?>
<div class="wrap">
  <nav aria-label="Διαδρομή"><ol class="crumbs"><li><a href="/">Αρχική</a></li><?php foreach ($crumbs as $c): ?><li><a href="<?= e($c['url']) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?><li><span aria-current="page"><?= e($p['name']) ?></span></li></ol></nav>
  <div class="pdp">
    <div>
      <div class="stage" id="stage"><?= t01_img($images[0] ?? null, $p['name'], '(max-width:860px) 100vw, 58vw', true) ?></div>
      <?php if (count($images) > 1): ?>
      <div class="thumbs" role="group" aria-label="Εικόνες προϊόντος">
        <?php foreach ($images as $i => $im): ?><button type="button" aria-pressed="<?= $i ? 'false' : 'true' ?>" data-full="<?= e($im['src']) ?>" data-srcset="<?= e($im['srcset']) ?>" aria-label="Εικόνα <?= $i + 1 ?>"><img src="<?= e($im['src']) ?>" alt="" width="72" height="72" loading="lazy"></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="panel">
      <span class="sts <?= $sk ?>" data-stock-label><?= e($sl) ?></span>
      <h1 class="kin"><?= e($p['name']) ?></h1>
      <div data-price><?= t01_price($p) ?></div>
      <?php if ($p['short_description']): ?><p style="margin:0"><?= e($p['short_description']) ?></p><?php endif; ?>
      <form method="post" action="/cart/" data-add-to-cart data-variants="<?= e(json_encode($vdata, JSON_UNESCAPED_UNICODE)) ?>" data-manage="<?= (int)$p['manage_stock'] ?>">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="op" value="add"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="variant_id" value="">
        <?php foreach ($opts as $name => $vals): $nid = 'o' . substr(md5($name), 0, 6); ?>
          <fieldset style="border:0;margin:0 0 14px;padding:0"><legend class="lbl"><?= e($name) ?></legend><div class="opts">
            <?php foreach (array_keys($vals) as $i => $val): ?><label><input type="radio" name="<?= $nid ?>" data-opt="<?= e($name) ?>" value="<?= e($val) ?>" required<?= count($vals) === 1 ? ' checked' : '' ?>><span><?= e($val) ?></span></label><?php endforeach; ?>
          </div></fieldset>
        <?php endforeach; ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <div class="qt"><button type="button" data-qty="-1" aria-label="Μείωση ποσότητας">−</button><label class="sr" for="qty">Ποσότητα</label><input id="qty" name="qty" type="number" min="1" max="99" value="1" inputmode="numeric"><button type="button" data-qty="1" aria-label="Αύξηση ποσότητας">+</button></div>
          <button class="btn big primary" style="flex:1" data-buy<?= $canBuy ? '' : ' disabled' ?>><?= $canBuy ? 'Στο καλάθι' : 'Εξαντλημένο' ?></button>
        </div>
        <p data-form-msg role="alert" style="margin:10px 0 0;font-weight:700;color:#B42318"></p>
      </form>
      <form method="post" action="/wishlist/" data-wish><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= e(req_path()) ?>">
        <button class="chip" style="border-color:currentColor" aria-pressed="<?= $on ? 'true' : 'false' ?>"><?= $on ? '♥ Στα αγαπημένα' : '♡ Προσθήκη στα αγαπημένα' ?></button></form>
      <div class="specs">
        <?php if ($p['sku']): ?><div><small>Κωδικός</small><b><?= e($p['sku']) ?></b></div><?php endif; ?>
        <?php if ($p['brand']): ?><div><small>Μάρκα</small><b><?= e($p['brand']) ?></b></div><?php endif; ?>
        <div><small>Αποστολή</small><b><?= !empty($shipping['free_over']) ? 'Δωρεάν άνω των ' . money($shipping['free_over']) : money($shipping['flat'] ?? 0) ?></b></div>
        <div><small>Πληρωμή</small><b>Κάρτα · Αντικαταβολή · Κατάθεση</b></div>
        <?php foreach ($attrs as $a): ?><div><small><?= e($a['name']) ?></small><b><?= e($a['value']) ?></b></div><?php endforeach; ?>
        <?php if ($p['weight']): ?><div><small>Βάρος</small><b><?= e(rtrim(rtrim(number_format((float)$p['weight'], 3, ',', ''), '0'), ',')) ?> kg</b></div><?php endif; ?>
      </div>
      <?php if ($p['description']): ?><details open><summary style="font-weight:800;cursor:pointer;font-size:18px">Περιγραφή</summary><div class="desc-body"><?= $p['description'] ?></div></details><?php endif; ?>
      <details><summary style="font-weight:800;cursor:pointer;font-size:18px">Αποστολή &amp; επιστροφές</summary><p>Κόστος αποστολής <?= money($shipping['flat'] ?? 0) ?><?= !empty($shipping['free_over']) ? ', δωρεάν για παραγγελίες άνω των ' . money($shipping['free_over']) : '' ?><?= !empty($shipping['islands_extra']) ? '. Νησιά: +' . money($shipping['islands_extra']) : '' ?>. <?= e($shipping['note'] ?? '') ?> <a href="/returns/">Πολιτική επιστροφών</a>.</p></details>
    </div>
  </div>
  <?php if ($related): ?>
  <section class="sec" style="padding-top:12px" aria-labelledby="h-r"><div class="sh"><h2 id="h-r" class="kin" style="font-size:clamp(28px,4vw,52px)">Ταιριάζει με</h2></div>
    <div class="pgrid"><?php foreach ($related as $r) echo t01_card($r, $relCovers[$r['id']] ?? null, $wish); ?></div></section>
  <?php endif; ?>
</div>
<div class="buybar" id="buybar" aria-hidden="true"><b data-bar-price><?= money(Catalog::effectivePrice($p)) ?></b><button class="btn" type="button" data-bar-buy<?= $canBuy ? '' : ' disabled' ?>><?= $canBuy ? 'Στο καλάθι' : 'Εξαντλημένο' ?></button></div>
