<div class="spread"><p class="muted" style="margin:0">Στατικές σελίδες (Σχετικά, Όροι, Απόρρητο, Αποστολές, Επιστροφές…). Η σελίδα εμφανίζεται στη διεύθυνση /slug/.</p><a class="btn" href="/admin/pages/new">+ Νέα σελίδα</a></div>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Τίτλος</th><th>Διεύθυνση</th><th>Υποσέλιδο</th><th>Κατάσταση</th><th>Ενημέρωση</th><th>Ελέγχθηκε</th></tr></thead>
  <tbody>
  <?php foreach ($list as $p): $tpl = str_contains((string)$p['body'], ShopSeed::TEMPLATE_TEXT); ?>
    <tr><td><a href="/admin/pages/<?= (int)$p['id'] ?>"><b><?= e($p['title']) ?></b></a></td><td class="mono">/<?= e($p['slug']) ?>/</td><td><?= $p['in_footer'] ? 'Ναι' : '—' ?></td><td><?= status_badge($p['status']) ?></td><td><?= dt($p['updated_at']) ?></td>
      <td><?= $tpl ? '<span class="badge b-warn">Κείμενο προτύπου</span>' : status_badge('ok') ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
