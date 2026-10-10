<?php $qsBase = fn(array $over) => $path . '?' . http_build_query(array_filter(array_merge(['sort' => $sort !== 'new' ? $sort : null, 'stock' => input('stock') === '1' ? '1' : null], $over), fn($v) => $v !== null && $v !== '')); ?>
<div class="wrap">
  <nav aria-label="Διαδρομή"><ol class="crumbs"><li><a href="/">Αρχική</a></li><?php foreach ($crumbs as $i => $c): ?><li><?= $i === count($crumbs) - 1 ? '<span aria-current="page">' . e($c['name']) . '</span>' : '<a href="' . e($c['url']) . '">' . e($c['name']) . '</a>' ?></li><?php endforeach; ?></ol></nav>
  <header class="shead" style="background:<?= $cat ? 'var(--accent)' : 'var(--accent2)' ?>">
    <span style="font-weight:700"><?= (int)$total ?> προϊόντα</span>
    <h1 class="kin"><?= e($cat['name'] ?? 'Όλα τα προϊόντα') ?></h1>
    <?php if ($cat && $cat['description'] && $page === 1): ?><div class="desc"><?= $cat['description'] ?></div><?php endif; ?>
  </header>
  <div class="toolbar" role="toolbar" aria-label="Φίλτρα και ταξινόμηση">
    <?php if ($cat && $cat['parent_id']): ?><a class="chip" href="<?= e(Catalog::categoryUrl($db, (int)$cat['parent_id'])) ?>">← Πίσω</a><?php endif; ?>
    <?php foreach ($children as $ch): ?><a class="chip" href="<?= e($cat ? Catalog::categoryUrl($db, (int)$ch['id']) : '/category/' . $ch['slug'] . '/') ?>"><?= e($ch['name']) ?></a><?php endforeach; ?>
    <a class="chip" href="<?= e($qsBase(['stock' => input('stock') === '1' ? null : '1', 'page' => null])) ?>" aria-pressed="<?= input('stock') === '1' ? 'true' : 'false' ?>" rel="nofollow">Μόνο διαθέσιμα</a>
    <form method="get" action="<?= e($path) ?>" style="margin-left:auto"><?php if (input('stock') === '1'): ?><input type="hidden" name="stock" value="1"><?php endif; ?>
      <label class="sr" for="sort">Ταξινόμηση</label>
      <select id="sort" name="sort" data-autosubmit><?php foreach (['new' => 'Νεότερα', 'price' => 'Τιμή: χαμηλή → υψηλή', 'price_desc' => 'Τιμή: υψηλή → χαμηλή', 'name' => 'Όνομα'] as $k => $l): ?><option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
      <noscript><button class="chip">OK</button></noscript>
    </form>
  </div>
  <?php if ($list): ?>
    <div class="pgrid" id="grid"><?php foreach ($list as $p) echo t01_card($p, $covers[$p['id']] ?? null, $wish); ?></div>
    <?php if ($pages > 1): ?>
    <div class="more">
      <?php if ($page < $pages): ?><a class="btn big" href="<?= e($qsBase(['page' => $page + 1])) ?>" data-load-more="<?= e($qsBase(['page' => $page + 1, 'partial' => '1'])) ?>" data-pages="<?= $pages ?>">Φόρτωση περισσότερων</a><?php endif; ?>
      <nav class="pager" aria-label="Σελίδες" style="display:flex;gap:6px;flex-wrap:wrap">
        <?php for ($i = 1; $i <= $pages; $i++): ?><?= $i === $page ? '<span aria-current="page">' . $i . '</span>' : '<a href="' . e($qsBase(['page' => $i > 1 ? $i : null])) . '">' . $i . '</a>' ?><?php endfor; ?>
      </nav>
    </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty"><b style="font-size:22px">Δεν υπάρχουν προϊόντα εδώ ακόμη</b><a class="btn" href="/products/">Δείτε όλα τα προϊόντα</a></div>
  <?php endif; ?>
</div>
