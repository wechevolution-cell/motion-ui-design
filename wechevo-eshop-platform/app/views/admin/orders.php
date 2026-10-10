<form class="card toolbar" method="get" role="search">
  <div class="field"><label for="q">Αναζήτηση</label><input class="in" id="q" name="q" value="<?= e(input('q')) ?>" placeholder="αριθμός, όνομα, email, τηλέφωνο"></div>
  <div class="field"><label for="s">Κατάσταση</label><select class="in" id="s" name="status"><option value="">Όλες</option><?php foreach (Orders::STATUS as $k => $l): ?><option value="<?= $k ?>"<?= input('status') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="p">Πληρωμή</label><select class="in" id="p" name="payment"><option value="">Όλες</option><?php foreach (Payments::STATUS as $k => $l): ?><option value="<?= $k ?>"<?= input('payment') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-ghost">Φιλτράρισμα</button>
</form>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Αριθμός</th><th>Ημερομηνία</th><th>Πελάτης</th><th>Πληρωμή</th><th>Κατάσταση πληρωμής</th><th>Αποστολή</th><th>Κατάσταση</th><th class="r">Σύνολο</th></tr></thead>
  <tbody>
  <?php foreach ($list as $o): ?>
    <tr><td><a class="mono" href="/admin/orders/<?= (int)$o['id'] ?>"><b><?= e($o['number']) ?></b></a></td><td><?= dt($o['created_at']) ?></td><td><?= e($o['name']) ?><br><span class="muted"><?= e($o['email']) ?></span></td>
      <td><?= e(Payments::METHODS[$o['payment_method']] ?? $o['payment_method']) ?></td><td><?= status_badge($o['payment_status']) ?></td><td><?= e(Orders::SHIPPING[$o['shipping_status']] ?? '') ?></td><td><?= status_badge($o['status']) ?></td><td class="r num"><?= money($o['total']) ?></td></tr>
  <?php endforeach; if (!$list): ?><tr><td colspan="8" class="muted">Καμία παραγγελία.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?= pager($total, $page, 50, '/admin/orders?' . http_build_query(array_filter(['q' => input('q'), 'status' => input('status'), 'payment' => input('payment')]))) ?>
