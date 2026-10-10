<form class="card row" method="post" enctype="multipart/form-data"><?= Csrf::field() ?>
  <div class="field" style="flex:1"><label for="f">Ανέβασμα εικόνων</label><input class="in" id="f" type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif"><small>JPG, PNG, WebP, GIF έως 10 MB · αυτόματη σμίκρυνση στα 2000px και μετατροπή σε WebP.</small></div>
  <button class="btn">Ανέβασμα</button>
</form>
<div class="grid g4">
<?php foreach ($list as $m): $u = Media::urlsFor($m); ?>
  <div class="card" style="gap:8px">
    <img src="<?= e($u['src']) ?>" alt="<?= e($m['alt']) ?>" width="<?= (int)$m['width'] ?>" height="<?= (int)$m['height'] ?>" loading="lazy" style="width:100%;height:160px;object-fit:cover;border-radius:8px;background:var(--line2)">
    <span class="muted mono" style="font-size:12px;overflow-wrap:anywhere"><?= e(basename($m['path'])) ?> · <?= (int)$m['width'] ?>×<?= (int)$m['height'] ?> · <?= number_format($m['size'] / 1024, 0) ?> KB</span>
    <form class="row" method="post"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input class="in" name="alt" value="<?= e($m['alt']) ?>" placeholder="Εναλλακτικό κείμενο" aria-label="Εναλλακτικό κείμενο" style="flex:1;min-width:0"><button class="btn btn-ghost btn-sm" name="op" value="alt">OK</button></form>
    <details class="confirm"><summary>Διαγραφή…</summary><form method="post" style="margin-top:6px"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-danger btn-sm" name="op" value="delete">Οριστική διαγραφή</button></form></details>
  </div>
<?php endforeach; if (!$list): ?><p class="muted">Δεν υπάρχουν εικόνες ακόμη.</p><?php endif; ?>
</div>
<?= pager($total, $page, 60, '/admin/media') ?>
