<form class="card toolbar" method="get" action="/superadmin/shops" role="search">
  <div class="field"><label for="q">Αναζήτηση</label><input class="in" id="q" name="q" value="<?= e(input('q')) ?>" placeholder="όνομα, email, domain"></div>
  <div class="field"><label for="st">Κατάσταση</label><select class="in" id="st" name="status"><option value="">Όλες</option>
    <?php foreach (Shops::LABELS as $k => $l): if ($k === 'DELETED') continue; ?><option value="<?= $k ?>"<?= input('status') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    <option value="deleted"<?= input('status') === 'deleted' ? ' selected' : '' ?>>Διαγραμμένα</option></select></div>
  <div class="field"><label for="pl">Πακέτο</label><select class="in" id="pl" name="plan"><option value="">Όλα</option>
    <?php foreach ($plans as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)input('plan') === (int)$p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-ghost">Φιλτράρισμα</button>
  <a class="btn" href="/superadmin/shops/new" style="margin-left:auto">+ Νέο κατάστημα</a>
</form>
<div class="table-wrap"><table class="t">
  <thead><tr><th>#</th><th>Κατάστημα</th><th>Domain</th><th>Κατάσταση</th><th>Πακέτο</th><th class="r">Ενεργά</th><th class="r">Αποθηκευμένα</th><th>Πρότυπο</th><th>Λήξη</th></tr></thead>
  <tbody>
  <?php foreach ($shops as $s): $lim = (int)($s['active_limit_override'] ?? $s['active_product_limit']); ?>
    <tr>
      <td class="mono"><?= (int)$s['id'] ?></td>
      <td><a href="/superadmin/shops/<?= (int)$s['id'] ?>"><b><?= e($s['name']) ?></b></a><br><span class="muted"><?= e($s['email']) ?></span></td>
      <td class="mono"><?= e($s['domain'] ?? '—') ?></td>
      <td><?= status_badge($s['status']) ?></td>
      <td><?= e($s['plan_name']) ?></td>
      <td class="r num"><?= $s['counts']['active'] ?> / <?= $lim ?></td>
      <td class="r num"><?= number_format($s['counts']['stored'], 0, ',', '.') ?></td>
      <td class="mono"><?= e($s['template_code']) ?></td>
      <td><?= d($s['subscription_expires']) ?></td>
    </tr>
  <?php endforeach; if (!$shops): ?><tr><td colspan="9" class="muted">Κανένα κατάστημα δεν ταιριάζει στα φίλτρα.</td></tr><?php endif; ?>
  </tbody>
</table></div>
