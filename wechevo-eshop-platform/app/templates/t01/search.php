<div class="wrap">
  <h1 class="kin page-h">Αναζήτηση</h1>
  <form class="nl" role="search" action="/search/" method="get" style="background:var(--accent2);grid-template-columns:1fr">
    <div class="r"><label for="q" class="sr">Αναζήτηση προϊόντων</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="π.χ. κούπα" minlength="2" required style="flex:1;min-width:0;border:0;background:none;padding:0 14px;font:inherit;color:#17195A"><button class="btn">Αναζήτηση</button></div>
  </form>
  <div class="sec">
    <?php if ($q !== ''): ?><p class="muted" role="status"><?= (int)$total ?> αποτελέσματα για «<?= e($q) ?>»</p><?php endif; ?>
    <?php if ($list): ?><div class="pgrid"><?php foreach ($list as $p) echo t01_card($p, $covers[$p['id']] ?? null, $wish); ?></div>
    <?php elseif ($q !== ''): ?><div class="empty"><b style="font-size:22px">Δεν βρέθηκαν προϊόντα</b><span class="muted">Δοκιμάστε άλλη λέξη ή δείτε όλες τις κατηγορίες.</span><a class="btn" href="/products/">Όλα τα προϊόντα</a></div><?php endif; ?>
  </div>
</div>
