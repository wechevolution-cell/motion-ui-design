<div class="spread"><p class="muted" style="margin:0">Τα όρια, οι τιμές και οι διάρκειες διαβάζονται από εδώ — δεν υπάρχουν σταθερές στον κώδικα.</p><a class="btn" href="/superadmin/plans/new">+ Νέο πακέτο</a></div>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Πακέτο</th><th class="r">Τιμή/μήνα</th><th class="r">Setup</th><th class="r">Ενεργά</th><th class="r">Αποθηκευμένα</th><th class="r">Χρήστες</th><th>Διάρκειες</th><th>Λειτουργίες</th><th class="r">Καταστήματα</th><th>Κατάσταση</th></tr></thead>
  <tbody>
  <?php foreach ($plans as $p): $f = Plans::features($p); ?>
    <tr><td><a href="/superadmin/plans/<?= (int)$p['id'] ?>"><b><?= e($p['name']) ?></b></a></td>
      <td class="r num"><?= money($p['price']) ?></td><td class="r num"><?= money($p['setup_fee']) ?></td>
      <td class="r num"><?= (int)$p['active_product_limit'] ?></td><td class="r num"><?= number_format($p['stored_product_limit'], 0, ',', '.') ?></td>
      <td class="r num"><?= (int)$p['staff_limit'] ?></td><td class="mono"><?= e(implode(', ', Plans::durations($p))) ?> μ.</td>
      <td class="muted"><?= e(implode(', ', array_map(fn($k) => Plans::FEATURES[$k], array_keys(array_filter($f, fn($v, $k) => $v && isset(Plans::FEATURES[$k]), ARRAY_FILTER_USE_BOTH))))) ?></td>
      <td class="r num"><?= (int)$p['shops'] ?></td><td><?= status_badge($p['status']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
