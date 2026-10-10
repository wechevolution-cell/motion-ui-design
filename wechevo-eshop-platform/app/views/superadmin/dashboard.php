<?php
$total = array_sum($by);
$k = fn($s) => $by[$s] ?? 0;
?>
<section class="grid g4" aria-label="Δείκτες">
  <div class="card kpi"><a href="/superadmin/shops"><b><?= $total ?></b><span>Σύνολο καταστημάτων</span></a></div>
  <div class="card kpi"><a href="/superadmin/shops?status=ACTIVE"><b><?= $k('ACTIVE') + $k('EXPIRING') ?></b><span>Ενεργά (μαζί με όσα λήγουν σύντομα)</span></a></div>
  <div class="card kpi"><a href="/superadmin/shops?status=LOCKED"><b><?= $k('LOCKED') + $k('SUSPENDED') ?></b><span>Κλειδωμένα / σε αναστολή</span></a></div>
  <div class="card kpi"><a href="/superadmin/shops?status=EXPIRING"><b><?= count($soon) ?></b><span>Λήγουν σε ≤10 ημέρες ή έληξαν</span></a></div>
  <div class="card kpi"><b class="num"><?= number_format($totals['active'], 0, ',', '.') ?></b><span>Ενεργά προϊόντα στο δίκτυο</span></div>
  <div class="card kpi"><b class="num"><?= number_format($totals['stored'], 0, ',', '.') ?></b><span>Αποθηκευμένα προϊόντα στο δίκτυο</span></div>
  <div class="card kpi"><b class="num"><?= money($mrr) ?></b><span>Μηνιαίο έσοδο συνδρομών (ενεργά)</span></div>
  <div class="card kpi"><b><?= $k('PENDING') ?></b><span>Σε αναμονή έναρξης</span></div>
</section>

<?php if ($notes): ?>
<section class="card" aria-labelledby="h-n">
  <div class="card-h"><h2 id="h-n">Νέες ειδοποιήσεις</h2><a href="/superadmin/notifications">Όλες →</a></div>
  <ul class="stack" style="margin:0;padding:0;list-style:none">
  <?php foreach ($notes as $n): ?>
    <li class="spread"><span><?= status_badge($n['level']) ?> <b><?= e($n['title']) ?></b> <span class="muted"><?= e($n['body']) ?></span></span><span class="muted mono"><?= dt($n['created_at']) ?></span></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section aria-labelledby="h-s" class="stack">
  <div class="spread"><h2 id="h-s">Καταστήματα ανά ημερομηνία λήξης</h2><a class="btn" href="/superadmin/shops/new">+ Νέο κατάστημα</a></div>
  <div class="table-wrap"><table class="t">
    <thead><tr><th>Κατάστημα</th><th>Κατάσταση</th><th>Πακέτο</th><th class="r">Ενεργά / όριο</th><th class="r">Αποθηκευμένα</th><th>Πρότυπο</th><th>Έναρξη</th><th>Λήξη</th><th class="r">Ημέρες</th></tr></thead>
    <tbody>
    <?php foreach ($shops as $s): $dl = Shops::daysLeft($s); $lim = (int)($s['active_limit_override'] ?? $s['active_product_limit']); ?>
      <tr>
        <td><a href="/superadmin/shops/<?= (int)$s['id'] ?>"><b><?= e($s['name']) ?></b></a><br><span class="muted mono"><?= e(Shops::primaryDomain((int)$s['id'])) ?></span></td>
        <td><?= status_badge($s['status']) ?></td>
        <td><?= e($s['plan_name']) ?></td>
        <td class="r num"><?= $s['counts']['active'] ?> / <?= $lim ?></td>
        <td class="r num"><?= number_format($s['counts']['stored'], 0, ',', '.') ?></td>
        <td class="mono"><?= e($s['template_code']) ?></td>
        <td><?= d($s['subscription_start']) ?></td>
        <td><?= d($s['subscription_expires']) ?></td>
        <td class="r num"><?= $dl === null ? '—' : ($dl < 0 ? '<span class="err">' . $dl . '</span>' : $dl) ?></td>
      </tr>
    <?php endforeach; if (!$shops): ?>
      <tr><td colspan="9" class="muted">Δεν υπάρχουν καταστήματα ακόμη. <a href="/superadmin/shops/new">Δημιουργήστε το πρώτο.</a></td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>
</section>
