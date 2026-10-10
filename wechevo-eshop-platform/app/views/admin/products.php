<section class="card"><?= quota_bar($active, $limit) ?>
  <?php if ($active >= $limit): ?><div class="alert alert-warning" role="status">Συμπληρώσατε το όριο ενεργών προϊόντων του πακέτου σας. Για να ενεργοποιήσετε νέο προϊόν, απενεργοποιήστε πρώτα κάποιο άλλο ή αναβαθμίστε το πακέτο.</div><?php endif; ?>
  <p class="hint" style="margin:0">Αποθηκευμένα: <b class="num"><?= number_format($stored, 0, ',', '.') ?></b> / <?= number_format(Shops::storedLimit($shop), 0, ',', '.') ?> · Τα ανενεργά προϊόντα παραμένουν αποθηκευμένα και δεν εμφανίζονται στο κατάστημα.</p>
</section>
<form class="card toolbar" method="get" action="/admin/products" role="search">
  <div class="field"><label for="q">Αναζήτηση</label><input class="in" id="q" name="q" value="<?= e(input('q')) ?>" placeholder="όνομα, SKU, barcode"></div>
  <div class="field"><label for="st">Κατάσταση</label><select class="in" id="st" name="status"><option value="">Όλα</option><option value="active"<?= input('status') === 'active' ? ' selected' : '' ?>>Ενεργά</option><option value="inactive"<?= input('status') === 'inactive' ? ' selected' : '' ?>>Ανενεργά</option></select></div>
  <div class="field"><label for="ct">Κατηγορία</label><select class="in" id="ct" name="cat"><option value="">Όλες</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)input('cat') === (int)$c['id'] ? ' selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="sk">Απόθεμα</label><select class="in" id="sk" name="stock"><option value="">Όλα</option><option value="out"<?= input('stock') === 'out' ? ' selected' : '' ?>>Εξαντλημένα</option><option value="low"<?= input('stock') === 'low' ? ' selected' : '' ?>>Χαμηλό (1–3)</option></select></div>
  <div class="field"><label for="so">Ταξινόμηση</label><select class="in" id="so" name="sort"><?php foreach (['new' => 'Νεότερα', 'name' => 'Όνομα', 'price' => 'Τιμή ↑', 'price_desc' => 'Τιμή ↓', 'stock' => 'Απόθεμα', 'activated' => 'Πρόσφατα ενεργοποιημένα'] as $k => $l): ?><option value="<?= $k ?>"<?= input('sort') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="field" style="min-width:90px"><label for="pp">Ανά σελίδα</label><select class="in" id="pp" name="per"><?php foreach ([25, 50, 100, 200] as $n): ?><option<?= $per === $n ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-ghost">Εφαρμογή</button>
  <a class="btn" href="/admin/products/new" style="margin-left:auto">+ Νέο προϊόν</a>
</form>
<form method="post" action="/admin/products?<?= e($qs) ?>">
  <?= Csrf::field() ?>
  <div class="table-wrap">
    <div class="bulkbar">
      <label class="chk"><input type="checkbox" data-check-all> Όλα</label><span class="muted">Επιλεγμένα: <b data-selected-count>0</b></span>
      <select class="in" name="bulk" data-bulk-action aria-label="Μαζική ενέργεια" style="width:auto">
        <option value="">Μαζική ενέργεια…</option><option value="activate">Ενεργοποίηση</option><option value="deactivate">Απενεργοποίηση</option>
        <option value="category">Ανάθεση κατηγορίας</option><option value="price">Αλλαγή τιμής</option><option value="export">Εξαγωγή CSV</option><option value="delete">Διαγραφή</option>
      </select>
      <span data-bulk-extra="category" hidden><select class="in" name="bulk_category" aria-label="Κατηγορία" style="width:auto"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= str_repeat('— ', $c['depth']) . e($c['name']) ?></option><?php endforeach; ?></select> <label class="chk" style="display:inline-flex"><input type="checkbox" name="bulk_primary" value="1"> ως κύρια</label></span>
      <span data-bulk-extra="price" hidden><select class="in" name="price_mode" aria-label="Τρόπος" style="width:auto"><option value="percent">± ποσοστό %</option><option value="amount">± ποσό €</option><option value="set">ορισμός τιμής €</option></select> <input class="in" name="price_value" aria-label="Τιμή" style="width:100px" inputmode="decimal" placeholder="π.χ. -10"></span>
      <span data-bulk-extra="delete" hidden><label class="chk" style="display:inline-flex"><input type="checkbox" name="confirm_delete" value="1"> Επιβεβαιώνω τη διαγραφή</label></span>
      <button class="btn btn-sm">Εφαρμογή</button>
    </div>
    <table class="t">
      <thead><tr><th class="sr">Επιλογή</th><th></th><th>Προϊόν</th><th>SKU</th><th>Κατηγορία</th><th class="r">Τιμή</th><th class="r">Απόθεμα</th><th>Κατάσταση</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $p): $img = $covers[$p['id']] ?? null; ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" aria-label="Επιλογή <?= e($p['name']) ?>"></td>
          <td><?php if ($img): ?><img class="thumb" src="<?= e($img['src']) ?>" alt="" loading="lazy" width="44" height="44"><?php else: ?><span class="thumb" aria-hidden="true"></span><?php endif; ?></td>
          <td><a href="/admin/products/<?= (int)$p['id'] ?>"><b><?= e($p['name']) ?></b></a><?= $p['featured'] ? ' <span class="badge b-info">προβεβλημένο</span>' : '' ?></td>
          <td class="mono"><?= e($p['sku']) ?></td>
          <td><?= e($p['cat_name'] ?? '—') ?></td>
          <td class="r num"><?php if ($p['sale_price']): ?><s class="muted"><?= money($p['price']) ?></s> <?= money($p['sale_price']) ?><?php else: ?><?= money($p['price']) ?><?php endif; ?></td>
          <td class="r num<?= $p['manage_stock'] && $p['stock'] <= 0 ? ' err' : '' ?>"><?= $p['manage_stock'] ? (int)$p['stock'] : '∞' ?></td>
          <td><?= status_badge($p['status']) ?></td>
          <td class="r"><button class="btn btn-ghost btn-sm" formaction="/admin/products/<?= (int)$p['id'] ?>/toggle?back=<?= e(urlencode('/admin/products?' . $qs)) ?>" name="noop" value="1"><?= $p['status'] === 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?></button></td>
        </tr>
      <?php endforeach; if (!$list): ?><tr><td colspan="9" class="muted">Δεν βρέθηκαν προϊόντα. <a href="/admin/products/new">Προσθέστε προϊόν</a> ή <a href="/admin/import">κάντε εισαγωγή</a>.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</form>
<div class="spread"><span class="muted">Προϊόντα <?= $total ? (($page - 1) * $per + 1) : 0 ?>–<?= min($total, $page * $per) ?> από <?= number_format($total, 0, ',', '.') ?></span><?= pager($total, $page, $per, '/admin/products?' . $qs) ?></div>
