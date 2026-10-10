<form class="card toolbar" method="get">
  <div class="field"><label for="sh">Κατάστημα</label><select class="in" id="sh" name="shop"><option value="">Όλα</option><?php foreach ($shops as $s): ?><option value="<?= (int)$s['id'] ?>"<?= (int)input('shop') === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="ac">Ενέργεια (αρχίζει με)</label><input class="in" id="ac" name="action" value="<?= e(input('action')) ?>" placeholder="π.χ. login, product, shop"></div>
  <button class="btn btn-ghost">Φιλτράρισμα</button><span class="muted"><?= number_format($total, 0, ',', '.') ?> εγγραφές</span>
</form>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Πότε</th><th>Ποιος</th><th>Ενέργεια</th><th>Κατάστημα</th><th>Οντότητα</th><th>IP</th><th>Αποτέλεσμα</th><th>Λεπτομέρειες</th></tr></thead>
  <tbody>
  <?php foreach ($logs as $l): ?>
    <tr><td class="mono"><?= dt($l['created_at'], 'd/m/Y H:i:s') ?></td><td><?= e($l['actor_label'] ?? 'σύστημα') ?></td><td class="mono"><?= e($l['action']) ?></td>
      <td><?= e($l['shop_name'] ?? '—') ?></td><td class="mono"><?= e(trim(($l['entity'] ?? '') . ' ' . ($l['entity_id'] ?? ''))) ?></td><td class="mono"><?= e($l['ip']) ?></td>
      <td><?= $l['outcome'] === 'ok' ? status_badge('ok') : '<span class="badge b-bad">' . e($l['outcome']) . '</span>' ?></td><td class="mono muted" style="max-width:320px;overflow-wrap:anywhere"><?= e($l['meta']) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?= pager($total, $page, 100, '/superadmin/audit?shop=' . (int)input('shop') . '&action=' . urlencode(input('action'))) ?>
