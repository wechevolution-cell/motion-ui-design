<?php
$hero = $home['hero'] ?? [];
$catsWith = array_values(array_filter($cats, fn($c) => $c['count'] > 0));
$tileCats = array_slice($catsWith, 0, 2);
$catCovers = [];
foreach ($tileCats as $c) {
    $pid = val($db, "SELECT p.id FROM products p JOIN product_categories pc ON pc.product_id = p.id WHERE pc.category_id IN (" . implode(',', Catalog::descendantIds($db, (int)$c['id'])) . ") AND p.status = 'active' AND p.deleted_at IS NULL ORDER BY p.featured DESC, p.id DESC LIMIT 1");
    $catCovers[$c['id']] = $pid ? (Catalog::coverMap($db, [(int)$pid])[(int)$pid] ?? null) : null;
}
$free = (float)($shipping['free_over'] ?? 0);
?>
<div class="wrap">
  <section class="bento" aria-label="Βιτρίνα">
    <div class="tile t-head<?= $heroImg ? ' has-img' : '' ?>">
      <?php if ($heroImg): ?><?= t01_img($heroImg, $hero['title'] ?? '', '(max-width:860px) 100vw, 66vw', true, 'hero-img') ?><?php endif; ?>
      <?php if (!empty($hero['eyebrow'])): ?><span class="eyebrow"><?= e($hero['eyebrow']) ?></span><?php endif; ?>
      <h1 class="kin" data-kinetic><?= e($hero['title'] ?? $company['name'] ?? '') ?></h1>
      <div style="display:flex;gap:16px;align-items:end;justify-content:space-between;flex-wrap:wrap">
        <?php if (!empty($hero['subtitle'])): ?><p><?= e($hero['subtitle']) ?></p><?php endif; ?>
        <a class="btn big" href="<?= e($hero['cta_url'] ?? '/products/') ?>"><?= e($hero['cta_label'] ?: 'Δείτε τα προϊόντα') ?></a>
      </div>
    </div>
    <?php if ($star): ?>
    <a class="tile t-prod" href="/product/<?= e($star['slug']) ?>/">
      <div style="display:flex;justify-content:space-between;gap:10px"><span class="bdg">Προτεινόμενο</span><span class="arrow" aria-hidden="true">→</span></div>
      <div class="flo"><?= t01_img($covers[$star['id']] ?? null, $star['name'], '(max-width:860px) 90vw, 30vw', !$heroImg) ?></div>
      <div><b style="font-size:22px;display:block"><?= e($star['name']) ?></b><?= t01_price($star) ?></div>
    </a>
    <?php endif; ?>
    <?php if (!empty($home['promo']['title'])): ?>
    <div class="tile t-ship"><b><?= e($home['promo']['title']) ?></b><span><?= e($home['promo']['text'] ?? '') ?></span></div>
    <?php elseif ($free > 0): ?>
    <div class="tile t-ship"><b>Δωρεάν αποστολή</b><span>για παραγγελίες άνω των <?= money($free) ?></span></div>
    <?php endif; ?>
    <?php foreach ($tileCats as $c): ?>
      <a class="tile t-cat" href="/category/<?= e($c['slug']) ?>/"><b><?= e($c['name']) ?><br><small style="font-weight:600;opacity:.7"><?= (int)$c['count'] ?> προϊόντα</small></b><?= t01_img($catCovers[$c['id']] ?? null, $c['name'], '200px') ?></a>
    <?php endforeach; ?>
  </section>
</div>

<?php if (!empty($home['marquee'])): $mq = $home['marquee']; ?>
<div class="marq" aria-hidden="true"><div><?php for ($k = 0; $k < 2; $k++) foreach ($mq as $m) echo '<span>' . e($m) . '</span>'; ?></div></div>
<?php endif; ?>

<?php if (!empty($home['show_categories']) && $catsWith): ?>
<section class="wrap sec" aria-labelledby="h-c">
  <div class="sh"><h2 id="h-c" class="kin">Κατηγορίες</h2></div>
  <div class="chip-row"><?php foreach ($catsWith as $c): ?><a class="chip" href="/category/<?= e($c['slug']) ?>/"><?= e($c['name']) ?> <span style="opacity:.6"><?= (int)$c['count'] ?></span></a><?php endforeach; ?></div>
</section>
<?php endif; ?>

<?php if (!empty($home['show_featured']) && $featured): ?>
<section class="wrap sec" style="padding-top:0" aria-labelledby="h-p">
  <div class="sh"><h2 id="h-p" class="kin"><?= e($home['featured_title'] ?: 'Προϊόντα') ?></h2><a class="chip" href="/products/">Όλα →</a></div>
  <div class="pgrid feature"><?php foreach ($featured as $p) echo t01_card($p, $covers[$p['id']] ?? null, $wish); ?></div>
</section>
<?php elseif (!$featured): ?>
<section class="wrap sec"><div class="empty"><b style="font-size:22px">Σύντομα κοντά σας</b><span class="muted">Ετοιμάζουμε τα προϊόντα μας.</span></div></section>
<?php endif; ?>

<?php $vals = array_filter($home['values'] ?? [], fn($v) => $v['title'] !== ''); if ($vals): ?>
<section class="wrap sec" style="padding-top:0" aria-label="Γιατί από εμάς"><div class="vals"><?php foreach ($vals as $v): ?><div><b><?= e($v['title']) ?></b><span><?= e($v['text']) ?></span></div><?php endforeach; ?></div></section>
<?php endif; ?>

<?php if (!empty($home['faq'])): ?>
<section class="wrap sec" style="padding-top:0" aria-labelledby="h-faq">
  <div class="sh"><h2 id="h-faq" class="kin">Συχνές ερωτήσεις</h2></div>
  <div class="faq"><?php foreach ($home['faq'] as $f): ?><details><summary><?= e($f['q']) ?></summary><p><?= e($f['a']) ?></p></details><?php endforeach; ?></div>
</section>
<?php endif; ?>

<?php if (!empty($home['newsletter']['show']) && $popupEnabled): ?>
<section class="wrap sec" style="padding-top:0" aria-labelledby="h-nl">
  <div class="nl"><h2 id="h-nl" class="kin"><?= e($home['newsletter']['title'] ?: 'Newsletter') ?></h2>
    <form method="post" action="/newsletter/" data-newsletter><?= t01_form_guard() ?><input type="hidden" name="source" value="home"><input type="hidden" name="back" value="/">
      <?php if (!empty($home['newsletter']['text'])): ?><p style="margin:0"><?= e($home['newsletter']['text']) ?></p><?php endif; ?>
      <div class="r"><label for="nle" class="sr">Email</label><input id="nle" type="email" name="email" placeholder="you@example.gr" required autocomplete="email"><button class="btn">Εγγραφή</button></div>
      <label class="consent"><input type="checkbox" name="consent" value="1" required> <?= e($popup['consent_text'] ?? 'Αποδέχομαι την πολιτική απορρήτου.') ?></label>
      <p data-msg role="status" style="margin:0;font-weight:700"></p>
    </form>
  </div>
</section>
<?php endif; ?>
