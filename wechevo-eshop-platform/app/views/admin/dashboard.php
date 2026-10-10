<?php $done = count(array_filter($checklist, fn($c) => $c[2])); ?>
<section class="card"><?= quota_bar($k['active'], $limit) ?>
  <p class="hint" style="margin:0">Αποθηκευμένα προϊόντα: <b class="num"><?= number_format($k['stored'], 0, ',', '.') ?></b> · Στο κατάστημα εμφανίζονται μόνο τα ενεργά. Πακέτο: <b><?= e($shop['plan_name']) ?></b> · Λήξη: <b><?= d($shop['subscription_expires']) ?></b></p>
</section>
<section class="grid g4" aria-label="Δείκτες">
  <div class="card kpi"><a href="/admin/orders?status=new"><b><?= $k['orders_new'] ?></b><span>Νέες παραγγελίες</span></a></div>
  <div class="card kpi"><b class="num"><?= money($k['revenue_30']) ?></b><span>Πωλήσεις 30 ημερών (<?= $k['orders_30'] ?> παραγγελίες)</span></div>
  <div class="card kpi"><a href="/admin/products?stock=out&amp;status=active"><b><?= $k['out_of_stock'] ?></b><span>Ενεργά χωρίς απόθεμα</span></a></div>
  <div class="card kpi"><a href="/admin/subscribers"><b><?= $k['subscribers'] ?></b><span>Συνδρομητές newsletter</span></a></div>
</section>
<div class="grid g2">
  <section class="card" aria-labelledby="h-ob">
    <div class="card-h"><h2 id="h-ob">Ξεκινώντας</h2><span class="muted"><?= $done ?> / <?= count($checklist) ?> βήματα</span></div>
    <ul class="checklist">
      <?php foreach ($checklist as [$label, $href, $ok]): ?>
        <li class="<?= $ok ? 'done' : '' ?>"><span class="tick" aria-hidden="true"><?= $ok ? '✓' : '' ?></span><a href="<?= e($href) ?>"><?= e($label) ?></a><span class="sr"><?= $ok ? '(ολοκληρώθηκε)' : '(εκκρεμεί)' ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="card" aria-labelledby="h-ro">
    <div class="card-h"><h2 id="h-ro">Πρόσφατες παραγγελίες</h2><a href="/admin/orders">Όλες →</a></div>
    <div class="table-wrap"><table class="t"><tbody>
      <?php foreach ($recent as $o): ?><tr><td><a href="/admin/orders/<?= (int)$o['id'] ?>" class="mono"><?= e($o['number']) ?></a></td><td><?= e($o['name']) ?></td><td><?= status_badge($o['status']) ?></td><td class="r num"><?= money($o['total']) ?></td></tr><?php endforeach; ?>
      <?php if (!$recent): ?><tr><td class="muted">Δεν υπάρχουν παραγγελίες ακόμη.</td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="row"><a class="btn btn-ghost" href="/admin/products/new">+ Προϊόν</a><a class="btn btn-ghost" href="/admin/import">Εισαγωγή CSV/XML</a><a class="btn btn-ghost" href="/admin/categories">Κατηγορίες</a><a class="btn btn-ghost" href="/admin/appearance">Εμφάνιση</a></div>
  </section>
</div>
