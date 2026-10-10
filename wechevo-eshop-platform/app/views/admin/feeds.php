<?php $f = $edit ?? []; $ff = json_decode($f['fields'] ?? '[]', true) ?: Feeds::GENERIC_FIELDS; $fl = json_decode($f['filters'] ?? '{}', true) ?: []; ?>
<p class="muted" style="margin:0">Τα feeds περιέχουν μόνο <b>ενεργά</b> προϊόντα και ανανεώνονται αυτόματα. Δώστε τη διεύθυνση στο Skroutz / BestPrice.</p>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Feed</th><th>Τύπος</th><th>Διεύθυνση</th><th class="r">Προϊόντα</th><th>Τελευταία δημιουργία</th><th>Κατάσταση</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($feeds as $x): $url = $base . '/feed/' . $x['slug'] . '.xml'; ?>
    <tr><td><b><?= e($x['name']) ?></b></td><td><?= e(Feeds::TYPES[$x['type']] ?? $x['type']) ?></td><td class="mono" style="overflow-wrap:anywhere"><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($url) ?></a></td>
      <td class="r num"><?= $x['last_items'] === null ? '—' : (int)$x['last_items'] ?></td><td><?= dt($x['last_generated_at']) ?></td><td><?= status_badge($x['status']) ?></td>
      <td class="r"><form class="row" method="post" style="justify-content:flex-end"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><input type="hidden" name="slug" value="<?= e($x['slug']) ?>">
        <button class="btn btn-ghost btn-sm" name="op" value="toggle"><?= $x['status'] === 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?></button>
        <button class="btn btn-ghost btn-sm" name="op" value="generate">Δημιουργία τώρα</button>
        <a class="btn btn-ghost btn-sm" href="/admin/feeds?edit=<?= (int)$x['id'] ?>">Ρυθμίσεις</a></form></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<form class="card f" method="post"><?= Csrf::field() ?><input type="hidden" name="op" value="save"><input type="hidden" name="id" value="<?= (int)($f['id'] ?? 0) ?>">
  <h2><?= $edit ? 'Ρυθμίσεις: ' . e($f['name']) : 'Νέο προσαρμοσμένο feed' ?></h2>
  <div class="grid g4">
    <div class="field"><label for="fn">Όνομα</label><input class="in" id="fn" name="name" required value="<?= e($f['name'] ?? '') ?>"></div>
    <div class="field"><label for="fs">Slug (URL)</label><input class="in" id="fs" name="slug" value="<?= e($f['slug'] ?? '') ?>" placeholder="αυτόματο"></div>
    <div class="field"><label for="fty">Μορφή</label><select class="in" id="fty" name="type"><?php foreach (Feeds::TYPES as $k => $l): ?><option value="<?= $k ?>"<?= ($f['type'] ?? 'generic') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="ffr">Ανανέωση κάθε (λεπτά)</label><input class="in" id="ffr" name="frequency" type="number" min="5" value="<?= (int)($f['frequency_minutes'] ?? 60) ?>"></div>
  </div>
  <fieldset><legend>Φίλτρα</legend>
    <label class="chk"><input type="checkbox" name="in_stock" value="1" <?= !empty($fl['in_stock']) ? 'checked' : '' ?>> Μόνο διαθέσιμα (απόθεμα &gt; 0)</label>
    <div class="grid g3" style="max-height:200px;overflow:auto"><?php foreach ($cats as $c): ?><label class="chk"><input type="checkbox" name="categories[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], array_map('intval', $fl['categories'] ?? []), true) ? 'checked' : '' ?>> <?= str_repeat('— ', $c['depth']) . e($c['name']) ?></label><?php endforeach; ?></div>
    <p class="hint" style="margin:0">Χωρίς επιλεγμένες κατηγορίες = όλες.</p>
  </fieldset>
  <fieldset><legend>Πεδία (μόνο για «Γενικό XML»)</legend><div class="grid g4">
    <?php foreach (Feeds::GENERIC_FIELDS as $k): ?><label class="chk"><input type="checkbox" name="fields[]" value="<?= $k ?>" <?= in_array($k, $ff, true) ? 'checked' : '' ?>> <span class="mono"><?= $k ?></span></label><?php endforeach; ?>
  </div></fieldset>
  <div class="row"><button class="btn">Αποθήκευση feed</button><?php if ($edit): ?><a class="btn btn-ghost" href="/admin/feeds">Νέο</a><button class="btn btn-ghost" name="op" value="delete" formnovalidate onclick="return this.form.querySelector('[name=name]').value!==''">Διαγραφή</button><?php endif; ?></div>
</form>
