<div class="spread"><p class="muted" style="margin:0">Email που συλλέχθηκαν από το pop-up και τη φόρμα newsletter, με καταγραφή συγκατάθεσης (GDPR).</p><a class="btn btn-ghost" href="/admin/subscribers?export=csv">Εξαγωγή CSV</a></div>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Email</th><th>Πηγή</th><th>Συγκατάθεση</th><th>Ημερομηνία</th><th>Κατάσταση</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($list as $s): ?>
    <tr><td><?= e($s['email']) ?></td><td><?= e($s['source']) ?></td><td><?= $s['consent'] ? 'Ναι' : 'Όχι' ?><?= $s['consent_text'] ? '<br><span class="muted" style="font-size:12px">' . e(mb_substr($s['consent_text'], 0, 90)) . '…</span>' : '' ?></td><td><?= dt($s['created_at']) ?></td>
      <td><?= $s['unsubscribed_at'] ? '<span class="badge b-muted">Διαγράφηκε ' . d($s['unsubscribed_at']) . '</span>' : status_badge('active') ?></td>
      <td class="r"><form class="row" method="post" style="justify-content:flex-end"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><?php if (!$s['unsubscribed_at']): ?><button class="btn btn-ghost btn-sm" name="op" value="unsubscribe">Διαγραφή από λίστα</button><?php endif; ?><button class="btn btn-ghost btn-sm" name="op" value="delete">Οριστική διαγραφή</button></form></td></tr>
  <?php endforeach; if (!$list): ?><tr><td colspan="6" class="muted">Κανένας συνδρομητής ακόμη.</td></tr><?php endif; ?>
  </tbody>
</table></div>
