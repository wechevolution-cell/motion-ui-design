<?php $s = fn($k, $d = '') => setting_get($db, $k, $d); $smtp = $s('smtp', []); ?>
<form class="f" method="post"><?= Csrf::field() ?>
<div class="grid g2">
  <section class="card">
    <h2>Πλατφόρμα</h2>
    <div class="field"><label for="pn">Όνομα</label><input class="in" id="pn" name="platform_name" value="<?= e($s('platform_name', 'WeChevo')) ?>"></div>
    <div class="field"><label for="ne">Email ειδοποιήσεων</label><input class="in" id="ne" name="notify_email" type="email" value="<?= e($s('notify_email')) ?>"></div>
    <div class="field"><label>Domain πίνακα ελέγχου</label><input class="in" value="<?= e(Config::get('platform_host')) ?>" disabled><small>Αλλάζει στο data/config.php.</small></div>
    <label class="chk"><input type="checkbox" name="force_https" value="1" <?= $s('force_https', true) ? 'checked' : '' ?>> Υποχρεωτικό HTTPS στα καταστήματα (εκτός *.localhost)</label>
  </section>
  <section class="card">
    <h2>Συνδρομές</h2>
    <div class="field"><label for="gd">Ημέρες χάριτος μετά τη λήξη (EXPIRED → LOCKED)</label><input class="in" id="gd" name="grace_days" type="number" min="0" max="60" value="<?= (int)$s('grace_days', 3) ?>"></div>
    <div class="field"><label for="wd">Προειδοποιήσεις πριν τη λήξη (ημέρες)</label><input class="in" id="wd" name="warning_days" value="<?= e(implode(', ', (array)$s('warning_days', [10, 3, 1]))) ?>"></div>
    <div class="field"><label>Cron (Hostinger → Cron Jobs, κάθε 15 λεπτά)</label><input class="in mono" readonly value="/usr/bin/php <?= e(ROOT) ?>/cron.php"><small>ή μέσω URL: <span class="mono">https://<?= e(Config::get('platform_host')) ?>/cron.php?key=<?= e(substr(hash_hmac('sha256', 'cron', (string)Config::get('app_key')), 0, 32)) ?></span></small></div>
    <p class="hint" style="margin:0">Τελευταίος έλεγχος λήξεων: <?= $last ? dt(gmdate('Y-m-d H:i:s', $last)) : 'ποτέ' ?>. Ο έλεγχος τρέχει μέσω cron (cron.php) και αυτόματα κάθε 10 λεπτά με την κίνηση.</p>
  </section>
  <section class="card">
    <h2>Email</h2>
    <div class="field"><label for="mm">Τρόπος αποστολής</label><select class="in" id="mm" name="mail_mode">
      <?php foreach (['log' => 'Καταγραφή σε αρχείο (δοκιμές)', 'mail' => 'PHP mail() (Hostinger)', 'smtp' => 'SMTP'] as $k => $l): ?><option value="<?= $k ?>"<?= $s('mail_mode', 'log') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="mf">Αποστολέας</label><input class="in" id="mf" name="mail_from" type="email" value="<?= e($s('mail_from')) ?>"></div>
    <div class="grid g2">
      <div class="field"><label for="sh">SMTP host</label><input class="in" id="sh" name="smtp_host" value="<?= e($smtp['host'] ?? '') ?>" placeholder="smtp.hostinger.com"></div>
      <div class="field"><label for="sp">Port</label><input class="in" id="sp" name="smtp_port" type="number" value="<?= e($smtp['port'] ?? 465) ?>"></div>
      <div class="field"><label for="su">Χρήστης</label><input class="in" id="su" name="smtp_user" value="<?= e($smtp['user'] ?? '') ?>"></div>
      <div class="field"><label for="spw">Κωδικός</label><input class="in" id="spw" name="smtp_pass" type="password" placeholder="<?= !empty($smtp['pass']) ? '•••••• (αποθηκευμένος)' : '' ?>" autocomplete="new-password"></div>
    </div>
    <div class="field"><label for="te">Αποστολή δοκιμαστικού σε</label><input class="in" id="te" name="test_email" type="email"></div>
  </section>
</div>
<div><button class="btn">Αποθήκευση ρυθμίσεων</button></div>
</form>
