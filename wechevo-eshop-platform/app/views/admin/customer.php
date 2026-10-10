<div class="grid g2">
  <section class="card"><h2>Στοιχεία</h2><dl class="kv"><dt>Email</dt><dd><?= e($c['email']) ?></dd><dt>Τηλέφωνο</dt><dd><?= e($c['phone']) ?></dd><dt>Πελάτης από</dt><dd><?= d($c['created_at']) ?></dd></dl>
    <h3>Διευθύνσεις</h3><?php foreach ($addresses as $a): ?><p style="margin:0"><?= e($a['street']) ?>, <?= e($a['city']) ?> <?= e($a['postcode']) ?> <span class="muted">(<?= $a['region'] === 'islands' ? 'νησιά' : 'ηπειρωτική' ?>)</span></p><?php endforeach; ?></section>
  <section class="card"><h2>Παραγγελίες</h2><div class="table-wrap"><table class="t"><tbody>
    <?php foreach ($orders as $o): ?><tr><td><a class="mono" href="/admin/orders/<?= (int)$o['id'] ?>"><?= e($o['number']) ?></a></td><td><?= dt($o['created_at']) ?></td><td><?= status_badge($o['status']) ?></td><td class="r num"><?= money($o['total']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
</div>
