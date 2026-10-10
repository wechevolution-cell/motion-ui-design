<p class="muted" style="margin:0">Τα πρότυπα αλλάζουν μόνο την εμφάνιση. Όλα διαβάζουν τα ίδια δεδομένα (προϊόντα, κατηγορίες, σελίδες, ρυθμίσεις).</p>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Κωδικός</th><th>Όνομα</th><th>Περιγραφή</th><th>Υλοποίηση</th><th class="r">Καταστήματα</th><th>Κατάσταση</th></tr></thead>
  <tbody>
  <?php foreach ($tpls as $t): ?>
    <tr><td class="mono"><?= e($t['code']) ?></td><td><b><?= e($t['name']) ?></b></td><td class="muted"><?= e($t['description']) ?></td>
      <td><?= Theme::exists($t['code']) ? status_badge('available') : '<span class="badge b-muted">Δεν υπάρχει ακόμη</span>' ?></td>
      <td class="r num"><?= (int)$t['shops'] ?></td>
      <td><form class="row" method="post"><?= Csrf::field() ?><input type="hidden" name="code" value="<?= e($t['code']) ?>">
        <select class="in" name="status" aria-label="Κατάσταση <?= e($t['code']) ?>" style="width:auto"><?php foreach (['available' => 'Διαθέσιμο', 'coming_soon' => 'Σύντομα', 'disabled' => 'Απενεργοποιημένο'] as $k => $l): ?><option value="<?= $k ?>"<?= $t['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
        <button class="btn btn-ghost btn-sm">OK</button></form></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
