<?php
$id = (int)$shop['id'];
$limit = Shops::activeLimit($shop);
$dl = Shops::daysLeft($shop);
$primary = Shops::primaryDomain($id);
$durs = Plans::durations($plan);
$isDeleted = $shop['status'] === 'DELETED';
?>
<?php if ($created): ?>
<div class="alert alert-success" role="status">
  <b>Στοιχεία σύνδεσης (εμφανίζονται μόνο τώρα):</b><br>
  Διαχείριση: <a href="<?= e($created['url']) ?>" class="mono"><?= e($created['url']) ?></a><br>
  Email: <span class="mono"><?= e($created['email']) ?></span> · Κωδικός: <span class="mono"><?= e($created['password']) ?></span>
</div>
<?php endif; ?>

<section class="grid g4">
  <div class="card kpi"><b><?= status_badge($shop['status']) ?></b><span>Κατάσταση<?= $shop['status_changed_at'] ? ' από ' . dt($shop['status_changed_at']) : '' ?></span></div>
  <div class="card kpi"><b><?= e($shop['plan_name']) ?></b><span><?= money($shop['plan_price']) ?> / μήνα</span></div>
  <div class="card kpi"><b class="num"><?= d($shop['subscription_expires']) ?></b><span>Λήξη · <?= $dl === null ? '—' : ($dl >= 0 ? "σε $dl ημέρες" : abs($dl) . ' ημέρες πριν') ?></span></div>
  <div class="card kpi"><b class="num"><?= number_format($counts['stored'], 0, ',', '.') ?></b><span>Αποθηκευμένα προϊόντα (όριο <?= number_format(Shops::storedLimit($shop), 0, ',', '.') ?>)</span></div>
</section>

<section class="card"><?= quota_bar($counts['active'], $limit) ?></section>

<section class="grid g2">
  <div class="card">
    <div class="card-h"><h2>Ενέργειες</h2>
      <div class="row">
        <?php if ($primary && !$isDeleted): ?><a class="btn btn-ghost btn-sm" href="<?= e(Shops::url($id)) ?>" target="_blank" rel="noopener">Προβολή καταστήματος ↗</a><?php endif; ?>
        <?php if (!$isDeleted): ?><form method="post" action="/superadmin/shops/<?= $id ?>/impersonate"><?= Csrf::field() ?><button class="btn btn-sm">Σύνδεση ως διαχειριστής</button></form><?php endif; ?>
      </div>
    </div>

    <?php if (!$isDeleted): ?>
    <form class="f" method="post" action="/superadmin/shops/<?= $id ?>/renew">
      <?= Csrf::field() ?>
      <h3>Ανανέωση / μηδενισμός χρονομέτρου</h3>
      <p class="hint" style="margin:0">Παρατείνει από την τρέχουσα λήξη (ή από σήμερα αν έχει λήξει) και επαναφέρει την κατάσταση σε «Ενεργό». Δεν αλλάζει προϊόντα, παραγγελίες, ρυθμίσεις ή χρήστες.</p>
      <div class="grid g2">
        <div class="field"><label for="rm">Διάρκεια</label><select class="in" id="rm" name="months"><?php foreach ($durs as $m): ?><option value="<?= $m ?>"><?= $m ?> <?= $m === 1 ? 'μήνας' : 'μήνες' ?> · <?= money($plan['price'] * $m) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="ra">Ποσό που εισπράχθηκε (€)</label><input class="in" id="ra" name="amount" inputmode="decimal" placeholder="προαιρετικό"></div>
        <div class="field"><label for="rmt">Τρόπος</label><select class="in" id="rmt" name="method"><option value="bank_transfer">Τραπεζική κατάθεση</option><option value="cash">Μετρητά</option><option value="card">Κάρτα</option><option value="other">Άλλο</option></select></div>
        <div class="field"><label for="rr">Αναφορά / αιτιολογία</label><input class="in" id="rr" name="reference"></div>
      </div>
      <div><button class="btn">Ανανέωση συνδρομής</button></div>
    </form>
    <hr style="border:0;border-top:1px solid var(--line);width:100%">
    <form class="f" method="post" action="/superadmin/shops/<?= $id ?>/plan">
      <?= Csrf::field() ?>
      <h3>Αλλαγή πακέτου</h3>
      <?php if ($downgrade): ?>
        <div class="alert alert-warning" role="alert">Το νέο πακέτο επιτρέπει <b><?= (int)$downgrade['limit'] ?></b> ενεργά προϊόντα. Θα απενεργοποιηθούν τα <b><?= (int)$downgrade['excess'] ?></b> πιο πρόσφατα ενεργοποιημένα. Κανένα προϊόν δεν διαγράφεται.</div>
        <input type="hidden" name="plan_id" value="<?= (int)$downgrade['plan_id'] ?>">
        <label class="chk"><input type="checkbox" name="confirm_deactivate" value="1" required> Επιβεβαιώνω την αυτόματη απενεργοποίηση <?= (int)$downgrade['excess'] ?> προϊόντων</label>
        <div class="row"><button class="btn btn-danger">Αλλαγή πακέτου και απενεργοποίηση</button><a class="btn btn-ghost" href="/superadmin/shops/<?= $id ?>">Ακύρωση</a></div>
      <?php else: ?>
        <div class="row"><select class="in" name="plan_id" aria-label="Νέο πακέτο" style="max-width:320px"><?php foreach ($plans as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)$p['id'] === (int)$shop['plan_id'] ? ' selected' : '' ?>><?= e($p['name']) ?> · <?= (int)$p['active_product_limit'] ?> ενεργά · <?= money($p['price']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-ghost">Αλλαγή</button></div>
      <?php endif; ?>
    </form>
    <hr style="border:0;border-top:1px solid var(--line);width:100%">
    <div class="stack">
      <h3>Κατάσταση</h3>
      <div class="row">
        <?php if (in_array($shop['status'], ['LOCKED', 'SUSPENDED'], true)): ?>
          <form method="post" action="/superadmin/shops/<?= $id ?>/status"><?= Csrf::field() ?><input type="hidden" name="to" value="activate"><button class="btn">Ενεργοποίηση / ξεκλείδωμα</button></form>
        <?php endif; ?>
        <?php if ($shop['status'] !== 'LOCKED'): ?>
          <form method="post" action="/superadmin/shops/<?= $id ?>/status"><?= Csrf::field() ?><input type="hidden" name="to" value="lock"><button class="btn btn-ghost">Κλείδωμα τώρα</button></form>
        <?php endif; ?>
        <?php if ($shop['status'] !== 'SUSPENDED'): ?>
          <form method="post" action="/superadmin/shops/<?= $id ?>/status"><?= Csrf::field() ?><input type="hidden" name="to" value="suspend"><button class="btn btn-ghost">Αναστολή</button></form>
        <?php endif; ?>
        <form method="post" action="/superadmin/shops/<?= $id ?>/scheduler"><?= Csrf::field() ?><button class="btn btn-ghost">Επανέλεγχος λήξης</button></form>
        <form method="post" action="/superadmin/shops/<?= $id ?>/backup"><?= Csrf::field() ?><button class="btn btn-ghost">Αντίγραφο ασφαλείας</button></form>
      </div>
      <p class="hint" style="margin:0">Κλειδωμένο ή σε αναστολή: όλες οι δημόσιες σελίδες δείχνουν τη σελίδα «προσωρινά μη διαθέσιμο» και η διαχείριση του πελάτη απενεργοποιείται. Τα δεδομένα διατηρούνται.</p>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Στοιχεία πελάτη &amp; ρυθμίσεις</h2>
    <form class="f" method="post" action="/superadmin/shops/<?= $id ?>/edit">
      <?= Csrf::field() ?>
      <div class="grid g2">
        <div class="field"><label for="e-n">Επωνυμία</label><input class="in" id="e-n" name="name" value="<?= e($shop['name']) ?>" required></div>
        <div class="field"><label for="e-o">Υπεύθυνος</label><input class="in" id="e-o" name="owner_name" value="<?= e($shop['owner_name']) ?>"></div>
        <div class="field"><label for="e-e">Email</label><input class="in" id="e-e" name="email" type="email" value="<?= e($shop['email']) ?>"></div>
        <div class="field"><label for="e-p">Τηλέφωνο</label><input class="in" id="e-p" name="phone" value="<?= e($shop['phone']) ?>"></div>
        <div class="field"><label for="e-v">ΑΦΜ</label><input class="in" id="e-v" name="vat_number" value="<?= e($shop['vat_number']) ?>"></div>
        <div class="field"><label for="e-a">Διεύθυνση</label><input class="in" id="e-a" name="address" value="<?= e($shop['address']) ?>"></div>
        <div class="field"><label for="e-t">Πρότυπο</label><select class="in" id="e-t" name="template_code"><?php foreach ($templates as $t): $ok = $t['status'] === 'available' && Theme::exists($t['code']); ?><option value="<?= e($t['code']) ?>"<?= $t['code'] === $shop['template_code'] ? ' selected' : '' ?><?= $ok ? '' : ' disabled' ?>><?= e($t['code'] . ' · ' . $t['name']) ?><?= $ok ? '' : ' (σύντομα)' ?></option><?php endforeach; ?></select><small>Η αλλαγή αφορά μόνο την εμφάνιση· τα δεδομένα δεν επηρεάζονται.</small></div>
        <div class="field"><label for="e-hs">Κατάσταση hosting</label><select class="in" id="e-hs" name="hosting_status"><?php foreach (['ok' => 'OK', 'pending' => 'Σε εκκρεμότητα', 'issue' => 'Πρόβλημα'] as $k => $l): ?><option value="<?= $k ?>"<?= $shop['hosting_status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="e-hp">Πάροχος hosting</label><input class="in" id="e-hp" name="hosting_provider" value="<?= e($shop['hosting_provider']) ?>"></div>
        <div class="field"><label for="e-ao">Όριο ενεργών (παράκαμψη πακέτου)</label><input class="in" id="e-ao" name="active_limit_override" inputmode="numeric" value="<?= e($shop['active_limit_override']) ?>" placeholder="πακέτο: <?= (int)$shop['active_product_limit'] ?>"></div>
        <div class="field"><label for="e-so">Όριο αποθήκευσης (παράκαμψη)</label><input class="in" id="e-so" name="stored_limit_override" inputmode="numeric" value="<?= e($shop['stored_limit_override']) ?>" placeholder="πακέτο: <?= (int)$shop['stored_product_limit'] ?>"></div>
        <div class="field" style="grid-column:1/-1"><label for="e-hn">Σημειώσεις hosting</label><textarea class="in" id="e-hn" name="hosting_notes" rows="2"><?= e($shop['hosting_notes']) ?></textarea></div>
        <div class="field" style="grid-column:1/-1"><label for="e-no">Εσωτερικές σημειώσεις</label><textarea class="in" id="e-no" name="notes" rows="2"><?= e($shop['notes']) ?></textarea></div>
      </div>
      <div><button class="btn">Αποθήκευση</button></div>
    </form>
  </div>
</section>

<section class="grid g2">
  <div class="card">
    <h2>Domains</h2>
    <div class="table-wrap"><table class="t"><thead><tr><th>Domain</th><th>DNS</th><th>SSL</th><th></th></tr></thead><tbody>
    <?php foreach ($domains as $d): ?>
      <tr><td class="mono"><?= e($d['domain']) ?> <?= $d['is_primary'] ? '<span class="badge b-info">κύριο</span>' : '' ?></td>
        <td colspan="2"><form class="row" method="post" action="/superadmin/shops/<?= $id ?>/domains"><?= Csrf::field() ?><input type="hidden" name="op" value="status"><input type="hidden" name="domain_id" value="<?= (int)$d['id'] ?>">
          <select class="in" name="dns" aria-label="DNS" style="width:auto"><?php foreach (['pending' => 'εκκρεμεί', 'ok' => 'OK', 'error' => 'σφάλμα'] as $k => $l): ?><option value="<?= $k ?>"<?= $d['dns_status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
          <select class="in" name="ssl" aria-label="SSL" style="width:auto"><?php foreach (['pending' => 'εκκρεμεί', 'active' => 'ενεργό', 'error' => 'σφάλμα'] as $k => $l): ?><option value="<?= $k ?>"<?= $d['ssl_status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
          <button class="btn btn-ghost btn-sm">OK</button></form></td>
        <td class="r"><?php if (!$d['is_primary']): ?>
          <form class="row" method="post" action="/superadmin/shops/<?= $id ?>/domains" style="justify-content:flex-end"><?= Csrf::field() ?><input type="hidden" name="domain_id" value="<?= (int)$d['id'] ?>">
            <button class="btn btn-ghost btn-sm" name="op" value="primary">Κύριο</button><button class="btn btn-ghost btn-sm" name="op" value="remove">Αφαίρεση</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <form class="row" method="post" action="/superadmin/shops/<?= $id ?>/domains"><?= Csrf::field() ?><input type="hidden" name="op" value="add"><input class="in" name="domain" placeholder="νέο-domain.gr" aria-label="Νέο domain" style="max-width:260px"><button class="btn btn-ghost">Προσθήκη domain</button></form>
  </div>

  <div class="card">
    <h2>Χρήστες καταστήματος</h2>
    <div class="table-wrap"><table class="t"><thead><tr><th>Email</th><th>Ρόλος</th><th>Τελευταία σύνδεση</th><th></th></tr></thead><tbody>
    <?php foreach ($users as $u): ?>
      <tr><td><?= e($u['email']) ?><?= $u['locked_until'] && $u['locked_until'] > now() ? ' <span class="badge b-bad">κλειδωμένος</span>' : '' ?></td><td><?= e(Perm::ROLES[$u['role']] ?? $u['role']) ?></td><td><?= dt($u['last_login_at']) ?></td>
        <td class="r"><form method="post" action="/superadmin/shops/<?= $id ?>/owner-password"><?= Csrf::field() ?><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><button class="btn btn-ghost btn-sm">Νέος κωδικός</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <h3>Καταχώριση πληρωμής</h3>
    <form class="row" method="post" action="/superadmin/shops/<?= $id ?>/payment"><?= Csrf::field() ?>
      <select class="in" name="type" aria-label="Τύπος" style="width:auto"><option value="subscription">Συνδρομή</option><option value="setup">Setup fee</option></select>
      <input class="in" name="amount" placeholder="Ποσό €" aria-label="Ποσό" inputmode="decimal" style="width:110px" required>
      <input class="in" name="method" placeholder="Τρόπος" aria-label="Τρόπος" style="width:130px">
      <input class="in" name="reference" placeholder="Αναφορά" aria-label="Αναφορά" style="width:150px">
      <button class="btn btn-ghost">Καταχώριση</button></form>
  </div>
</section>

<section class="grid g2">
  <div class="card">
    <h2>Ιστορικό συνδρομής</h2>
    <div class="table-wrap"><table class="t"><thead><tr><th>Ημερομηνία</th><th>Ενέργεια</th><th>Πακέτο</th><th>Περίοδος</th><th>Από</th></tr></thead><tbody>
    <?php foreach ($history as $h): ?>
      <tr><td class="mono"><?= dt($h['created_at']) ?></td><td><?= e(History::LABELS[$h['action']] ?? $h['action']) ?><?= $h['note'] ? '<br><span class="muted">' . e($h['note']) . '</span>' : '' ?></td>
        <td><?= e($h['plan_name'] ?? '') ?><?= $h['duration_months'] ? ' · ' . (int)$h['duration_months'] . 'μ' : '' ?></td>
        <td class="mono"><?= $h['start_at'] ? d($h['start_at']) . ' → ' . d($h['end_at']) : '' ?></td><td class="muted"><?= e($h['actor'] ?? 'σύστημα') ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <div class="card">
    <h2>Πληρωμές</h2>
    <div class="table-wrap"><table class="t"><thead><tr><th>Ημερομηνία</th><th>Τύπος</th><th class="r">Ποσό</th><th>Τρόπος</th><th>Αναφορά</th></tr></thead><tbody>
    <?php foreach ($payments as $p): ?>
      <tr><td class="mono"><?= dt($p['created_at']) ?></td><td><?= $p['type'] === 'setup' ? 'Setup' : 'Συνδρομή' ?></td><td class="r num"><?= money($p['amount']) ?></td><td><?= e($p['method']) ?></td><td><?= e($p['reference']) ?></td></tr>
    <?php endforeach; if (!$payments): ?><tr><td colspan="5" class="muted">Καμία πληρωμή.</td></tr><?php endif; ?>
    </tbody></table></div>
    <h2>Πρόσφατες ενέργειες</h2>
    <div class="table-wrap"><table class="t"><tbody>
    <?php foreach ($audit as $a): ?><tr><td class="mono"><?= dt($a['created_at']) ?></td><td><?= e($a['action']) ?></td><td class="muted"><?= e($a['actor_label']) ?></td><td><?= status_badge($a['outcome'] === 'ok' ? 'ok' : 'error') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</section>

<fieldset class="card danger-zone">
  <legend>Επικίνδυνη ζώνη</legend>
  <?php if (!$isDeleted): ?>
  <details class="confirm"><summary>Διαγραφή καταστήματος…</summary>
    <form class="f" method="post" action="/superadmin/shops/<?= $id ?>/delete" style="margin-top:10px"><?= Csrf::field() ?>
      <p style="margin:0">Το κατάστημα βγαίνει εκτός λειτουργίας. Δημιουργείται αντίγραφο ασφαλείας και τα δεδομένα διατηρούνται μέχρι οριστική διαγραφή.</p>
      <div class="field"><label for="cd">Πληκτρολογήστε <span class="mono"><?= e($primary) ?></span> για επιβεβαίωση</label><input class="in" id="cd" name="confirm_domain" autocomplete="off" required></div>
      <div><button class="btn btn-danger">Διαγραφή</button></div>
    </form>
  </details>
  <?php else: ?>
  <div class="row">
    <form method="post" action="/superadmin/shops/<?= $id ?>/restore"><?= Csrf::field() ?><button class="btn btn-ghost">Επαναφορά</button></form>
  </div>
  <details class="confirm"><summary>Οριστική διαγραφή δεδομένων…</summary>
    <form class="f" method="post" action="/superadmin/shops/<?= $id ?>/purge" style="margin-top:10px"><?= Csrf::field() ?>
      <p style="margin:0">Διαγράφει οριστικά βάση δεδομένων, εικόνες, feeds και χρήστες του καταστήματος. Κρατείται ένα τελικό αντίγραφο ασφαλείας.</p>
      <div class="field"><label for="pd">Πληκτρολογήστε <span class="mono">ΟΡΙΣΤΙΚΗ ΔΙΑΓΡΑΦΗ <?= e($primary) ?></span></label><input class="in" id="pd" name="confirm_domain" autocomplete="off" required></div>
      <div><button class="btn btn-danger">Οριστική διαγραφή</button></div>
    </form>
  </details>
  <?php endif; ?>
</fieldset>
