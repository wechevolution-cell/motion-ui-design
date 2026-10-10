<?php
$v = fn($k, $d = '') => e($p[$k] ?? $d);
$pid = (int)($p['id'] ?? 0);
$isActive = ($p['status'] ?? 'inactive') === 'active';
$room = max(0, $limit - $activeCount);
$vrows = $variants ?: [];
$vTarget = max(3, count($vrows) + 2);
while (count($vrows) < $vTarget) $vrows[] = ['id' => 0, 'sku' => '', 'opts' => [], 'price' => null, 'stock' => 0];
$arows = $attributes ?: [];
$aTarget = max(3, count($arows) + 2);
while (count($arows) < $aTarget) $arows[] = ['name' => '', 'value' => ''];
?>
<?php foreach ($errors as $m): ?><div class="alert alert-error" role="alert"><?= e($m) ?></div><?php endforeach; ?>
<form class="f" method="post" enctype="multipart/form-data" action="/admin/products/<?= $pid ?: 'new' ?>">
  <?= Csrf::field() ?>
  <div class="grid split">
    <div class="stack" style="gap:16px">
      <section class="card">
        <h2>Βασικά στοιχεία</h2>
        <div class="field"><label for="name">Όνομα προϊόντος</label><input class="in" id="name" name="name" required value="<?= $v('name') ?>" data-slug-source></div>
        <div class="grid g3">
          <div class="field"><label for="sku">Κωδικός (SKU)</label><input class="in" id="sku" name="sku" value="<?= $v('sku') ?>"></div>
          <div class="field"><label for="barcode">Barcode / EAN</label><input class="in" id="barcode" name="barcode" value="<?= $v('barcode') ?>"></div>
          <div class="field"><label for="mpn">MPN</label><input class="in" id="mpn" name="mpn" value="<?= $v('mpn') ?>"></div>
          <div class="field"><label for="brand">Μάρκα</label><input class="in" id="brand" name="brand" value="<?= $v('brand') ?>"></div>
        </div>
        <div class="field"><label for="sd">Σύντομη περιγραφή</label><textarea class="in" id="sd" name="short_description" rows="3"><?= $v('short_description') ?></textarea><small>Εμφανίζεται δίπλα στην τιμή και στα feeds. 1–2 προτάσεις.</small></div>
        <div class="field"><label for="desc">Περιγραφή (HTML)</label><textarea class="in" id="desc" name="description" rows="8"><?= $v('description') ?></textarea><small>Επιτρέπονται: παράγραφοι, έντονα, λίστες, πίνακες, σύνδεσμοι, εικόνες, βίντεο YouTube/Vimeo. Τα scripts αφαιρούνται.</small></div>
      </section>

      <section class="card">
        <h2>Τιμή &amp; απόθεμα</h2>
        <div class="grid g3">
          <div class="field"><label for="price">Τιμή (€, με ΦΠΑ)</label><input class="in" id="price" name="price" inputmode="decimal" required value="<?= $v('price', '0') ?>"></div>
          <div class="field"><label for="sale">Τιμή προσφοράς (€)</label><input class="in" id="sale" name="sale_price" inputmode="decimal" value="<?= $v('sale_price') ?>"></div>
          <div class="field"><label for="cost">Τιμή κόστους (€)</label><input class="in" id="cost" name="cost_price" inputmode="decimal" value="<?= $v('cost_price') ?>"><small>Δεν εμφανίζεται στο κατάστημα.</small></div>
          <div class="field"><label for="stock">Απόθεμα</label><input class="in" id="stock" name="stock" type="number" value="<?= $v('stock', '0') ?>"><small>Με παραλλαγές, υπολογίζεται από το άθροισμά τους.</small></div>
          <label class="chk" style="align-self:center"><input type="checkbox" name="manage_stock" value="1" <?= ($p['manage_stock'] ?? 1) ? 'checked' : '' ?>> Παρακολούθηση αποθέματος</label>
        </div>
        <div class="grid g4">
          <div class="field"><label for="w">Βάρος (kg)</label><input class="in" id="w" name="weight" inputmode="decimal" value="<?= $v('weight') ?>"></div>
          <div class="field"><label for="l">Μήκος (cm)</label><input class="in" id="l" name="length" inputmode="decimal" value="<?= $v('length') ?>"></div>
          <div class="field"><label for="wi">Πλάτος (cm)</label><input class="in" id="wi" name="width" inputmode="decimal" value="<?= $v('width') ?>"></div>
          <div class="field"><label for="h">Ύψος (cm)</label><input class="in" id="h" name="height" inputmode="decimal" value="<?= $v('height') ?>"></div>
        </div>
      </section>

      <section class="card" id="images">
        <h2>Εικόνες</h2>
        <?php if ($images): ?>
        <div class="row" style="align-items:flex-start">
          <?php foreach ($images as $i => $im): $u = Media::urlsFor($im); ?>
            <div class="stack" style="width:120px">
              <img src="<?= e($u['src']) ?>" alt="" width="120" height="120" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--line)" loading="lazy">
              <span class="hint"><?= $i === 0 ? 'Κύρια' : ($im['media_id'] ? 'Τοπική' : 'URL') ?></span>
              <div class="row">
                <?php if ($i > 0): ?><button class="linkbtn" name="op" value="image_first" formnovalidate onclick="this.form.image_id.value=<?= (int)$im['id'] ?>">Κύρια</button><?php endif; ?>
                <button class="linkbtn" name="op" value="image_delete" formnovalidate onclick="this.form.image_id.value=<?= (int)$im['id'] ?>">Αφαίρεση</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <input type="hidden" name="image_id" value="">
        <div class="field"><label for="imgs">Ανέβασμα εικόνων</label><input class="in" id="imgs" type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple><small>JPG, PNG, WebP, GIF έως 10 MB. Μετατρέπονται αυτόματα σε WebP διαφόρων μεγεθών.</small></div>
        <div class="field"><label for="urls">ή URL εικόνων (ένα ανά γραμμή)</label><textarea class="in" id="urls" name="image_urls" rows="2" placeholder="https://…"></textarea></div>
      </section>

      <section class="card">
        <h2>Παραλλαγές</h2>
        <p class="hint" style="margin:0">Π.χ. Χρώμα: Λευκό, Μέγεθος: M. Κάθε παραλλαγή έχει δικό της απόθεμα. Οι παραλλαγές δεν μετράνε στο όριο ενεργών — μετράει μόνο το προϊόν.</p>
        <div class="table-wrap"><table class="t">
          <thead><tr><th>Ιδιότητα 1</th><th>Τιμή 1</th><th>Ιδιότητα 2</th><th>Τιμή 2</th><th>SKU</th><th>Τιμή €</th><th>Απόθεμα</th></tr></thead>
          <tbody>
          <?php foreach ($vrows as $i => $vr): $keys = array_keys($vr['opts']); $vals = array_values($vr['opts']); ?>
            <tr>
              <td><input type="hidden" name="variants[<?= $i ?>][id]" value="<?= (int)$vr['id'] ?>"><input class="in" name="variants[<?= $i ?>][o1n]" value="<?= e($keys[0] ?? '') ?>" placeholder="Χρώμα" aria-label="Ιδιότητα 1"></td>
              <td><input class="in" name="variants[<?= $i ?>][o1v]" value="<?= e($vals[0] ?? '') ?>" placeholder="Λευκό" aria-label="Τιμή 1"></td>
              <td><input class="in" name="variants[<?= $i ?>][o2n]" value="<?= e($keys[1] ?? '') ?>" placeholder="Μέγεθος" aria-label="Ιδιότητα 2"></td>
              <td><input class="in" name="variants[<?= $i ?>][o2v]" value="<?= e($vals[1] ?? '') ?>" placeholder="M" aria-label="Τιμή 2"></td>
              <td><input class="in" name="variants[<?= $i ?>][sku]" value="<?= e($vr['sku']) ?>" aria-label="SKU παραλλαγής"></td>
              <td><input class="in" name="variants[<?= $i ?>][price]" value="<?= e($vr['price']) ?>" inputmode="decimal" placeholder="ίδια" aria-label="Τιμή παραλλαγής" style="width:80px"></td>
              <td><input class="in" name="variants[<?= $i ?>][stock]" value="<?= (int)$vr['stock'] ?>" type="number" aria-label="Απόθεμα παραλλαγής" style="width:80px"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <p class="hint" style="margin:0">Αποθηκεύστε για περισσότερες κενές γραμμές. Αδειάστε τις τιμές μιας γραμμής για να τη διαγράψετε.</p>
      </section>

      <section class="card">
        <h2>Χαρακτηριστικά</h2>
        <div class="grid g2">
          <?php foreach ($arows as $i => $a): ?>
            <input class="in" name="attributes[<?= $i ?>][name]" value="<?= e($a['name']) ?>" placeholder="π.χ. Υλικό" aria-label="Χαρακτηριστικό <?= $i + 1 ?>">
            <input class="in" name="attributes[<?= $i ?>][value]" value="<?= e($a['value']) ?>" placeholder="π.χ. Κεραμικό" aria-label="Τιμή χαρακτηριστικού <?= $i + 1 ?>">
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card">
        <h2>SEO</h2>
        <div class="field"><label for="slug">Slug (διεύθυνση)</label><input class="in" id="slug" name="slug" value="<?= $v('slug') ?>" data-slug-target><small>/product/<b><?= $v('slug', 'onoma-proiontos') ?></b>/ — αν αλλάξει, οι παλιοί σύνδεσμοι σταματούν να λειτουργούν.</small></div>
        <div class="field"><label for="st">SEO τίτλος</label><input class="in" id="st" name="seo_title" maxlength="200" value="<?= $v('seo_title') ?>"><small>Ιδανικά έως 60 χαρακτήρες. Κενό = όνομα προϊόντος.</small></div>
        <div class="field"><label for="sdsc">Meta description</label><textarea class="in" id="sdsc" name="seo_description" rows="2" maxlength="320"><?= $v('seo_description') ?></textarea><small>Ιδανικά 120–160 χαρακτήρες.</small></div>
        <div class="grid g2">
          <div class="field"><label for="can">Canonical URL</label><input class="in" id="can" name="canonical_url" value="<?= $v('canonical_url') ?>" placeholder="αυτόματο"></div>
          <div class="field"><label for="rb">Ευρετηρίαση</label><select class="in" id="rb" name="robots"><option value="">index, follow</option><option value="noindex"<?= str_contains((string)($p['robots'] ?? ''), 'noindex') ? ' selected' : '' ?>>noindex</option></select></div>
        </div>
      </section>
    </div>

    <aside class="stack" style="gap:16px;position:sticky;top:80px">
      <section class="card">
        <h2>Δημοσίευση</h2>
        <fieldset><legend>Κατάσταση</legend>
          <label class="chk"><input type="radio" name="status" value="active" <?= $isActive ? 'checked' : '' ?> <?= !$isActive && $room === 0 ? 'disabled' : '' ?>> Ενεργό (εμφανίζεται στο κατάστημα)</label>
          <label class="chk"><input type="radio" name="status" value="inactive" <?= !$isActive ? 'checked' : '' ?>> Ανενεργό (μόνο αποθηκευμένο)</label>
        </fieldset>
        <?php if (!$isActive && $room === 0): ?><div class="alert alert-warning">Συμπληρώσατε το όριο ενεργών προϊόντων (<?= $activeCount ?> / <?= $limit ?>). Απενεργοποιήστε κάποιο άλλο προϊόν για να ενεργοποιήσετε αυτό.</div>
        <?php else: ?><p class="hint" style="margin:0">Ενεργά: <?= $activeCount ?> / <?= $limit ?></p><?php endif; ?>
        <label class="chk"><input type="checkbox" name="featured" value="1" <?= !empty($p['featured']) ? 'checked' : '' ?>> Προβολή στην αρχική</label>
        <button class="btn" name="op" value="save">Αποθήκευση</button>
        <?php if ($pid && $isActive): ?><a class="btn btn-ghost" href="/product/<?= $v('slug') ?>/" target="_blank" rel="noopener">Προβολή ↗</a><?php endif; ?>
      </section>
      <section class="card">
        <h2>Κατηγορίες</h2>
        <div class="field"><label for="cat">Κύρια κατηγορία</label><select class="in" id="cat" name="category_id"><option value="">—</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)($p['category_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="stack" style="max-height:220px;overflow:auto">
          <span class="lbl">Επιπλέον κατηγορίες</span>
          <?php foreach ($cats as $c): ?><label class="chk" style="padding-left:<?= $c['depth'] * 14 ?>px"><input type="checkbox" name="categories[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $pcats, true) ? 'checked' : '' ?>> <?= e($c['name']) ?></label><?php endforeach; ?>
          <?php if (!$cats): ?><span class="muted">Δεν υπάρχουν κατηγορίες. <a href="/admin/categories">Δημιουργία</a></span><?php endif; ?>
        </div>
      </section>
      <?php if ($pid): ?>
      <section class="card danger-zone">
        <h2>Διαγραφή</h2>
        <p class="hint" style="margin:0"><?= $orders ? "Το προϊόν υπάρχει σε $orders γραμμές παραγγελιών· αυτές δεν επηρεάζονται." : 'Το προϊόν δεν υπάρχει σε παραγγελίες.' ?> Συνήθως αρκεί η απενεργοποίηση.</p>
        <details class="confirm"><summary>Διαγραφή προϊόντος…</summary><button class="btn btn-danger" style="margin-top:8px" name="op" value="delete" formnovalidate>Ναι, διαγραφή</button></details>
      </section>
      <?php endif; ?>
    </aside>
  </div>
</form>
