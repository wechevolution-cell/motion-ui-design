<?php
$renderTree = function ($nodes) use (&$renderTree, $counts) {
    $h = '<ul>';
    foreach ($nodes as $n) {
        $c = $counts[$n['id']] ?? ['n' => 0, 'a' => 0];
        $h .= '<li><span class="spread"><span><a href="/admin/categories?edit=' . (int)$n['id'] . '"><b>' . e($n['name']) . '</b></a> <span class="muted mono">/' . e($n['slug']) . '</span></span>'
            . '<span class="row"><span class="muted num">' . (int)$c['a'] . ' ενεργά / ' . (int)$c['n'] . '</span><a class="btn btn-ghost btn-sm" href="/admin/products?cat=' . (int)$n['id'] . '">Προϊόντα</a></span></span>';
        if ($n['children']) $h .= $renderTree($n['children']);
        $h .= '</li>';
    }
    return $h . '</ul>';
};
$c = $edit ?? [];
?>
<div class="grid split">
  <section class="card tree">
    <div class="card-h"><h2>Δέντρο κατηγοριών</h2><a class="btn btn-ghost btn-sm" href="/admin/categories">+ Νέα κατηγορία</a></div>
    <?= $tree ? $renderTree($tree) : '<p class="muted">Δεν υπάρχουν κατηγορίες ακόμη. Οι κατηγορίες δεν έχουν όριο και μπορούν να έχουν όσα επίπεδα θέλετε.</p>' ?>
  </section>
  <section class="card">
    <h2><?= $edit ? 'Επεξεργασία: ' . e($edit['name']) : 'Νέα κατηγορία' ?></h2>
    <form class="f" method="post"><?= Csrf::field() ?><input type="hidden" name="op" value="save"><input type="hidden" name="id" value="<?= (int)($c['id'] ?? 0) ?>">
      <div class="field"><label for="cn">Όνομα</label><input class="in" id="cn" name="name" required value="<?= e($c['name'] ?? '') ?>"></div>
      <div class="field"><label for="cp">Γονική κατηγορία</label><select class="in" id="cp" name="parent_id"><option value="">— Κορυφαίο επίπεδο —</option>
        <?php foreach ($flat as $f): if ($edit && (int)$f['id'] === (int)$edit['id']) continue; ?><option value="<?= (int)$f['id'] ?>"<?= (int)($c['parent_id'] ?? 0) === (int)$f['id'] ? ' selected' : '' ?>><?= str_repeat('— ', $f['depth']) . e($f['name']) ?></option><?php endforeach; ?></select></div>
      <div class="grid g2">
        <div class="field"><label for="cs">Slug</label><input class="in" id="cs" name="slug" value="<?= e($c['slug'] ?? '') ?>" placeholder="αυτόματο"></div>
        <div class="field"><label for="co">Σειρά</label><input class="in" id="co" name="sort" type="number" value="<?= (int)($c['sort'] ?? 0) ?>"></div>
      </div>
      <div class="field"><label for="cd">Περιγραφή (εμφανίζεται στη σελίδα κατηγορίας)</label><textarea class="in" id="cd" name="description" rows="4"><?= e($c['description'] ?? '') ?></textarea></div>
      <div class="field"><label for="ct">SEO τίτλος</label><input class="in" id="ct" name="seo_title" value="<?= e($c['seo_title'] ?? '') ?>"></div>
      <div class="field"><label for="cm">Meta description</label><textarea class="in" id="cm" name="seo_description" rows="2"><?= e($c['seo_description'] ?? '') ?></textarea></div>
      <div class="field"><label for="cc">Canonical URL</label><input class="in" id="cc" name="canonical_url" value="<?= e($c['canonical_url'] ?? '') ?>" placeholder="αυτόματο"></div>
      <div class="row"><button class="btn">Αποθήκευση</button><?php if ($edit): ?><a class="btn btn-ghost" href="<?= e(Catalog::categoryUrl($db, (int)$edit['id'])) ?>" target="_blank" rel="noopener">Προβολή ↗</a><?php endif; ?></div>
    </form>
    <?php if ($edit): ?>
    <details class="confirm"><summary>Διαγραφή κατηγορίας…</summary>
      <form method="post" class="stack" style="margin-top:8px"><?= Csrf::field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <p class="hint" style="margin:0">Τα προϊόντα δεν διαγράφονται· απλώς αφαιρούνται από την κατηγορία.</p><div><button class="btn btn-danger">Διαγραφή</button></div></form>
    </details>
    <?php endif; ?>
  </section>
</div>
