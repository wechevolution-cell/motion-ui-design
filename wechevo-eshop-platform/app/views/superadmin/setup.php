<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Εγκατάσταση πλατφόρμας</title>
<link rel="stylesheet" href="/assets/admin/admin.css?v=<?= APP_VERSION ?>">
</head>
<body class="adm is-super">
<div class="login">
  <form class="card f" method="post" action="/superadmin/setup" novalidate>
    <?= Csrf::field() ?>
    <div class="brand"><span class="mark" aria-hidden="true"></span><span>Εγκατάσταση<small>Πρώτη ρύθμιση · δεν υπάρχουν προεπιλεγμένοι κωδικοί</small></span></div>
    <p class="muted" style="margin:0">Δημιουργήστε τον λογαριασμό Super Admin. Μετά την εγκατάσταση η σελίδα αυτή κλειδώνει.</p>
    <?php foreach ($errors as $k => $m): ?><div class="alert alert-error" role="alert"><?= e($m) ?></div><?php endforeach; ?>
    <div class="field"><label for="pn">Όνομα πλατφόρμας</label><input class="in" id="pn" name="platform_name" value="<?= e($_POST['platform_name'] ?? 'WeChevo') ?>" required></div>
    <div class="field"><label for="ph">Domain πίνακα ελέγχου</label><input class="in" id="ph" name="platform_host" value="<?= e($_POST['platform_host'] ?? req_host()) ?>" required><small>Το Super Admin λειτουργεί μόνο σε αυτό το domain (π.χ. panel.wechevo.gr). Τοπικά: localhost.</small></div>
    <div class="field"><label for="nm">Ονοματεπώνυμο</label><input class="in" id="nm" name="name" value="<?= e($_POST['name'] ?? '') ?>" autocomplete="name"></div>
    <div class="field"><label for="em">Email</label><input class="in" id="em" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email"></div>
    <div class="field"><label for="ne">Email ειδοποιήσεων (λήξεις, κλειδώματα)</label><input class="in" id="ne" name="notify_email" type="email" value="<?= e($_POST['notify_email'] ?? '') ?>"><small>Αν μείνει κενό, χρησιμοποιείται το email σας.</small></div>
    <div class="field"><label for="pw">Κωδικός</label><input class="in" id="pw" name="password" type="password" required minlength="10" autocomplete="new-password"><small>Τουλάχιστον 10 χαρακτήρες, γράμματα και αριθμοί.</small></div>
    <div class="field"><label for="pw2">Επανάληψη κωδικού</label><input class="in" id="pw2" name="password2" type="password" required autocomplete="new-password"></div>
    <button class="btn">Ολοκλήρωση εγκατάστασης</button>
  </form>
</div>
</body>
</html>
