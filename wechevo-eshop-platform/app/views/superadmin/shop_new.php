<?php $o = fn($k, $d = '') => e(old($k, $d));
$durs = []; foreach ($plans as $pl) { $durs = array_merge($durs, Plans::durations($pl)); } $durs = array_values(array_unique($durs)); sort($durs); ?>
<?php foreach ($errors as $m): ?><div class="alert alert-error" role="alert"><?= e($m) ?></div><?php endforeach; ?>
<form class="card f wizard" method="post" action="/superadmin/shops/new">
  <?= Csrf::field() ?>
  <ol class="steps" aria-label="Βήματα"><li>Πελάτης</li><li>Domain</li><li>Πακέτο</li><li>Διάρκεια</li><li>Πρότυπο</li><li>Διαχειριστής</li><li>Έλεγχος</li></ol>

  <fieldset data-step><legend>Στοιχεία πελάτη</legend><div class="grid g2">
    <div class="field"><label for="n">Επωνυμία καταστήματος / εταιρείας</label><input class="in" id="n" name="name" required value="<?= $o('name') ?>"></div>
    <div class="field"><label for="on">Υπεύθυνος επικοινωνίας</label><input class="in" id="on" name="owner_name" value="<?= $o('owner_name') ?>"></div>
    <div class="field"><label for="em">Email επικοινωνίας</label><input class="in" id="em" name="email" type="email" required value="<?= $o('email') ?>"></div>
    <div class="field"><label for="ph">Τηλέφωνο</label><input class="in" id="ph" name="phone" value="<?= $o('phone') ?>"></div>
    <div class="field"><label for="vat">ΑΦΜ</label><input class="in" id="vat" name="vat_number" value="<?= $o('vat_number') ?>"></div>
    <div class="field"><label for="ad">Διεύθυνση</label><input class="in" id="ad" name="address" value="<?= $o('address') ?>"></div>
  </div></fieldset>

  <fieldset data-step><legend>Domain &amp; hosting</legend><div class="grid g2">
    <div class="field"><label for="dm">Domain καταστήματος</label><input class="in" id="dm" name="domain" required placeholder="example<?= e($suggest) ?>" value="<?= $o('domain') ?>"><small>Χωρίς www· το www ανακατευθύνεται αυτόματα. Το DNS πρέπει να δείχνει σε αυτόν τον server.</small></div>
    <div class="field"><label for="hp">Πάροχος hosting</label><input class="in" id="hp" name="hosting_provider" value="<?= $o('hosting_provider', 'Hostinger') ?>"></div>
    <div class="field" style="grid-column:1/-1"><label for="hn">Σημειώσεις hosting</label><textarea class="in" id="hn" name="hosting_notes" rows="3"><?= $o('hosting_notes') ?></textarea></div>
  </div></fieldset>

  <fieldset data-step><legend>Πακέτο</legend><div class="grid g3">
    <?php foreach ($plans as $i => $p): ?>
      <label class="card" style="cursor:pointer"><span class="row"><input type="radio" name="plan_id" value="<?= (int)$p['id'] ?>" data-label="Πακέτο" required <?= (int)old('plan_id', $plans[1]['id'] ?? $p['id']) === (int)$p['id'] ? 'checked' : '' ?>> <b><?= e($p['name']) ?></b></span>
        <span class="num"><?= money($p['price']) ?> / μήνα · <?= (int)$p['active_product_limit'] ?> ενεργά προϊόντα</span>
        <span class="muted">Setup <?= money($p['setup_fee']) ?> · έως <?= number_format($p['stored_product_limit'], 0, ',', '.') ?> αποθηκευμένα · <?= (int)$p['staff_limit'] ?> χρήστες</span></label>
    <?php endforeach; ?>
  </div>
  <label class="chk"><input type="checkbox" name="setup_fee_paid" value="1" <?= old('setup_fee_paid') ? 'checked' : '' ?>> Το setup fee έχει πληρωθεί (καταχώριση πληρωμής)</label></fieldset>

  <fieldset data-step><legend>Διάρκεια συνδρομής</legend><div class="grid g2">
    <div class="field"><label for="mo">Διάρκεια</label><select class="in" id="mo" name="months" required>
      <?php foreach ($durs as $m): ?><option value="<?= $m ?>"<?= (int)old('months', $durs[0] ?? 1) === $m ? ' selected' : '' ?>><?= $m ?> <?= $m === 1 ? 'μήνας' : 'μήνες' ?></option><?php endforeach; ?></select>
      <small>Οι επιτρεπτές διάρκειες ορίζονται ανά πακέτο.</small></div>
    <div class="field"><label for="sd">Ημερομηνία έναρξης</label><input class="in" id="sd" name="start_date" type="date" value="<?= $o('start_date', date('Y-m-d')) ?>"><small>Μελλοντική ημερομηνία = κατάσταση «Σε αναμονή» μέχρι την έναρξη.</small></div>
  </div></fieldset>

  <fieldset data-step><legend>Πρότυπο</legend><div class="grid g3">
    <?php foreach ($templates as $t): $ok = $t['status'] === 'available' && Theme::exists($t['code']); ?>
      <label class="card" style="cursor:<?= $ok ? 'pointer' : 'not-allowed' ?>;<?= $ok ? '' : 'opacity:.55' ?>"><span class="row"><input type="radio" name="template" value="<?= e($t['code']) ?>" data-label="Πρότυπο" required <?= $ok ? '' : 'disabled' ?> <?= old('template', 't01') === $t['code'] && $ok ? 'checked' : '' ?>> <b><?= e($t['code']) ?> · <?= e($t['name']) ?></b></span>
        <span class="muted"><?= e($t['description']) ?></span><?= $ok ? '' : status_badge('coming_soon') ?></label>
    <?php endforeach; ?>
  </div></fieldset>

  <fieldset data-step><legend>Λογαριασμός διαχειριστή καταστήματος</legend><div class="grid g2">
    <div class="field"><label for="an">Όνομα</label><input class="in" id="an" name="admin_name" value="<?= $o('admin_name') ?>"></div>
    <div class="field"><label for="ae">Email σύνδεσης</label><input class="in" id="ae" name="admin_email" type="email" required value="<?= $o('admin_email') ?>"></div>
    <div class="field"><label for="ap">Κωδικός</label><input class="in" id="ap" name="admin_password" type="password" autocomplete="new-password" minlength="10"><small>Αφήστε κενό για αυτόματο προσωρινό κωδικό (ζητείται αλλαγή στην πρώτη σύνδεση).</small></div>
    <div class="stack">
      <label class="chk"><input type="checkbox" name="send_credentials" value="1" checked> Αποστολή στοιχείων σύνδεσης με email</label>
      <label class="chk"><input type="checkbox" name="demo_catalog" value="1" <?= old('demo_catalog') ? 'checked' : '' ?>> Δημιουργία δοκιμαστικών προϊόντων (για έλεγχο, σημειώνονται ως «δεδομένα επίδειξης»)</label>
    </div>
  </div></fieldset>

  <fieldset data-step><legend>Έλεγχος &amp; δημιουργία</legend>
    <div data-review><p class="muted">Ελέγξτε τα στοιχεία πριν τη δημιουργία.</p></div>
  </fieldset>

  <div class="row">
    <button type="button" class="btn btn-ghost" data-prev hidden>← Πίσω</button>
    <button type="button" class="btn" data-next hidden>Επόμενο →</button>
    <button class="btn" data-submit>Δημιουργία καταστήματος</button>
  </div>
</form>
