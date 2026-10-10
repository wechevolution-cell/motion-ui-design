<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e($brand) ?></title>
<link rel="stylesheet" href="/assets/admin/admin.css?v=<?= APP_VERSION ?>">
</head>
<body class="adm <?= !empty($super) ? 'is-super' : 'is-shop' ?>">
<main class="login">
  <form class="card f" method="post" action="<?= e($action) ?>">
    <?= Csrf::field() ?>
    <div class="brand"><span class="mark" aria-hidden="true"></span><span><?= e($brand) ?><small><?= e($title) ?></small></span></div>
    <?php foreach (flashes() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <div class="field"><label for="email">Email</label><input class="in" id="email" name="email" type="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>" autofocus></div>
    <div class="field"><label for="password">Κωδικός</label><input class="in" id="password" name="password" type="password" required autocomplete="current-password"></div>
    <button class="btn">Σύνδεση</button>
    <p class="hint" style="margin:0">Μετά από 5 αποτυχημένες προσπάθειες ο λογαριασμός κλειδώνει για 15 λεπτά.</p>
  </form>
</main>
</body>
</html>
