<form class="card toolbar" method="get" role="search"><div class="field"><label for="q">Αναζήτηση</label><input class="in" id="q" name="q" value="<?= e(input('q')) ?>" placeholder="email, όνομα, τηλέφωνο"></div><button class="btn btn-ghost">Αναζήτηση</button><span class="muted"><?= $total ?> πελάτες</span></form>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Πελάτης</th><th>Email</th><th>Τηλέφωνο</th><th class="r">Παραγγελίες</th><th class="r">Αγορές</th><th>Από</th></tr></thead>
  <tbody>
  <?php foreach ($list as $c): ?><tr><td><a href="/admin/customers/<?= (int)$c['id'] ?>"><?= e($c['name'] ?: '—') ?></a></td><td><?= e($c['email']) ?></td><td><?= e($c['phone']) ?></td><td class="r num"><?= (int)$c['orders'] ?></td><td class="r num"><?= money($c['spent']) ?></td><td><?= d($c['created_at']) ?></td></tr><?php endforeach; ?>
  <?php if (!$list): ?><tr><td colspan="6" class="muted">Κανένας πελάτης ακόμη.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?= pager($total, $page, 50, '/admin/customers?q=' . urlencode(input('q'))) ?>
