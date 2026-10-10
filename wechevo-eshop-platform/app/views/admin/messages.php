<div class="table-wrap"><table class="t">
  <thead><tr><th>Πότε</th><th>Από</th><th>Μήνυμα</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($list as $m): ?>
    <tr<?= $m['read_at'] ? ' class="muted"' : '' ?>><td class="mono"><?= dt($m['created_at']) ?></td><td><b><?= e($m['name']) ?></b><br><?= e($m['email']) ?><br><?= e($m['phone']) ?></td><td style="max-width:520px"><?= nl2br(e($m['message'])) ?></td>
      <td class="r"><form class="row" method="post" style="justify-content:flex-end"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><?php if (!$m['read_at']): ?><button class="btn btn-ghost btn-sm" name="op" value="read">Αναγνώστηκε</button><?php endif; ?><button class="btn btn-ghost btn-sm" name="op" value="delete">Διαγραφή</button></form></td></tr>
  <?php endforeach; if (!$list): ?><tr><td colspan="4" class="muted">Κανένα μήνυμα.</td></tr><?php endif; ?>
  </tbody>
</table></div>
