<div class="spread"><p class="muted" style="margin:0">Προειδοποιήσεις λήξης (10/3/1 ημέρες), λήξεις, κλειδώματα, αλλαγές πακέτου και εισαγωγές.</p>
<form method="post"><?= Csrf::field() ?><button class="btn btn-ghost" name="op" value="read_all">Σήμανση όλων ως αναγνωσμένων</button></form></div>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Πότε</th><th>Επίπεδο</th><th>Ειδοποίηση</th><th>Κατάστημα</th><th>Email</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($list as $n): ?>
    <tr<?= $n['read_at'] ? ' class="muted"' : '' ?>><td class="mono"><?= dt($n['created_at']) ?></td><td><?= status_badge($n['level']) ?></td>
      <td><b><?= e($n['title']) ?></b><br><span class="muted"><?= e($n['body']) ?></span></td>
      <td><?php if ($n['shop_id']): ?><a href="/superadmin/shops/<?= (int)$n['shop_id'] ?>"><?= e($n['shop_name']) ?></a><?php endif; ?></td>
      <td><?= $n['emailed_at'] ? 'στάλθηκε' : '—' ?></td>
      <td class="r"><?php if (!$n['read_at']): ?><form method="post"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-ghost btn-sm">Αναγνώστηκε</button></form><?php endif; ?></td></tr>
  <?php endforeach; if (!$list): ?><tr><td colspan="6" class="muted">Καμία ειδοποίηση.</td></tr><?php endif; ?>
  </tbody>
</table></div>
