<?php $headers = $opts['headers'] ?? []; $preview = $opts['preview'] ?? []; $st = $imp['status']; ?>
<ol class="steps"><li class="<?= $st === 'validated' ? 'on' : '' ?>">Έλεγχος &amp; αντιστοίχιση</li><li class="<?= in_array($st, ['queued', 'processing'], true) ? 'on' : '' ?>">Επεξεργασία</li><li class="<?= in_array($st, ['done', 'failed'], true) ? 'on' : '' ?>">Αναφορά</li></ol>

<?php if ($st === 'validated'): ?>
<section class="grid g4">
  <div class="card kpi"><b><?= (int)$check['rows'] ?></b><span>Γραμμές στο αρχείο</span></div>
  <div class="card kpi"><b><?= (int)$check['new'] ?></b><span>Νέα προϊόντα</span></div>
  <div class="card kpi"><b><?= (int)$check['existing'] ?></b><span>Υπάρχουν ήδη (ίδιο SKU)</span></div>
  <div class="card kpi"><b><?= (int)$check['invalid'] + (int)$check['dup_in_file'] ?></b><span>Με πρόβλημα / διπλότυπα</span></div>
</section>
<?php if ($check['messages']): ?><details class="card"><summary><b>Προβλήματα που εντοπίστηκαν</b> (πρώτα 20)</summary><ul><?php foreach ($check['messages'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
<form class="card f" method="post"><?= Csrf::field() ?>
  <h2>Αντιστοίχιση στηλών</h2>
  <p class="hint" style="margin:0">Οι στήλες αναγνωρίστηκαν αυτόματα όπου ήταν δυνατό. Ελέγξτε τες. Ο έλεγχος γίνεται ξανά μετά την αποθήκευση.</p>
  <div class="table-wrap"><table class="t">
    <thead><tr><th>Στήλη αρχείου</th><th>Πεδίο προϊόντος</th><th>Παράδειγμα</th></tr></thead>
    <tbody>
    <?php foreach ($headers as $i => $h): ?>
      <tr><td class="mono"><?= e($h) ?></td>
        <td><select class="in" name="map[<?= $i ?>]" aria-label="Πεδίο για <?= e($h) ?>"><option value="">— αγνόηση —</option><?php foreach (Importer::FIELDS as $k => $l): ?><option value="<?= $k ?>"<?= ($map[$h] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
        <td class="muted" style="max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e(mb_substr((string)($preview[0][$h] ?? ''), 0, 80)) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <fieldset><legend>Επιλογές</legend>
    <label class="chk"><input type="checkbox" name="create_new" value="1" checked> Δημιουργία νέων προϊόντων</label>
    <label class="chk"><input type="checkbox" name="update_existing" value="1" checked> Ενημέρωση υπαρχόντων (ταύτιση με SKU)</label>
  </fieldset>
  <fieldset><legend>Ενεργοποίηση (διαθέσιμες θέσεις: <?= (int)$room ?> από <?= (int)$limit ?>)</legend>
    <label class="chk"><input type="radio" name="activation" value="keep" checked> Τα νέα αποθηκεύονται ως ανενεργά· τα υπάρχοντα κρατούν την κατάστασή τους</label>
    <label class="chk"><input type="radio" name="activation" value="up_to_quota"> Ενεργοποίηση με τη σειρά του αρχείου μέχρι να γεμίσει το όριο (<?= (int)$room ?> θέσεις)</label>
    <label class="chk"><input type="radio" name="activation" value="from_file"> Σύμφωνα με τη στήλη «Ενεργό» του αρχείου (πάντα μέσα στο όριο)</label>
  </fieldset>
  <h3>Προεπισκόπηση (πρώτες γραμμές)</h3>
  <div class="table-wrap"><table class="t"><thead><tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach (array_slice($preview, 0, 5) as $r): ?><tr><?php foreach ($headers as $h): ?><td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e(mb_substr((string)($r[$h] ?? ''), 0, 60)) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="row"><button class="btn">Επιβεβαίωση και έναρξη εισαγωγής</button><a class="btn btn-ghost" href="/admin/import">Άκυρο</a></div>
</form>

<?php elseif (in_array($st, ['queued', 'processing'], true)): ?>
<section class="card" data-import-run="/admin/import/<?= (int)$imp['id'] ?>/run">
  <h2>Η εισαγωγή εκτελείται…</h2>
  <div class="progress" role="progressbar" aria-label="Πρόοδος εισαγωγής"><i style="width:<?= $imp['total'] ? (int)($imp['processed'] / $imp['total'] * 100) : 0 ?>%"></i></div>
  <p data-import-text aria-live="polite" style="margin:0"><?= (int)$imp['processed'] ?> / <?= (int)$imp['total'] ?></p>
  <p class="hint" style="margin:0">Η επεξεργασία γίνεται σε παρτίδες των 250 γραμμών. Αν κλείσετε τη σελίδα, η εισαγωγή συνεχίζει από το ίδιο σημείο όταν την ανοίξετε ξανά.</p>
</section>

<?php else: ?>
<section class="grid g4">
  <div class="card kpi"><b><?= (int)$imp['created'] ?></b><span>Νέα</span></div>
  <div class="card kpi"><b><?= (int)$imp['updated'] ?></b><span>Ενημερώθηκαν</span></div>
  <div class="card kpi"><b><?= (int)$imp['activated'] ?></b><span>Ενεργοποιήθηκαν</span></div>
  <div class="card kpi"><b><?= (int)$imp['errors'] ?> / <?= (int)$imp['skipped'] ?></b><span>Σφάλματα / παραλείψεις</span></div>
</section>
<?php foreach ($logs as $l): ?><div class="alert alert-<?= $st === 'failed' ? 'error' : 'success' ?>"><?= e($l['message']) ?></div><?php endforeach; ?>
<div class="row"><a class="btn" href="/admin/products?sort=new">Δείτε τα προϊόντα</a><a class="btn btn-ghost" href="/admin/import">Νέα εισαγωγή</a></div>
<?php endif; ?>

<?php if ($errors): ?>
<section class="stack"><h2>Γραμμές με σημειώσεις</h2>
<div class="table-wrap"><table class="t"><thead><tr><th class="r">Γραμμή</th><th>SKU</th><th>Τύπος</th><th>Μήνυμα</th></tr></thead><tbody>
<?php foreach ($errors as $r): ?><tr><td class="r num"><?= (int)$r['row_no'] ?></td><td class="mono"><?= e($r['sku']) ?></td><td><?= e(['error' => 'Σφάλμα', 'duplicate' => 'Διπλότυπο', 'quota' => 'Όριο'][$r['status']] ?? $r['status']) ?></td><td><?= e($r['message']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>
