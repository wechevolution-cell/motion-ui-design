<?php
/** @var string $title @var string $content @var array $nav @var string $area @var ?array $user @var string $brand @var string $active */
$isSuper = $area === 'super';
?><!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= e(Csrf::token()) ?>">
<title><?= e($title) ?> · <?= e($brand) ?></title>
<link rel="stylesheet" href="/assets/admin/admin.css?v=<?= APP_VERSION ?>">
</head>
<body class="adm <?= $isSuper ? 'is-super' : 'is-shop' ?>">
<a class="skip" href="#main">Μετάβαση στο περιεχόμενο</a>
<?php if (!empty($banner)): ?><div class="banner banner-<?= e($banner['level']) ?>" role="status"><?= $banner['html'] ?></div><?php endif; ?>
<div class="shell">
  <aside class="side" id="side">
    <div class="brand"><span class="mark" aria-hidden="true"></span><span><?= e($brand) ?><small><?= $isSuper ? 'Super Admin' : 'Διαχείριση καταστήματος' ?></small></span></div>
    <?php if ($user): ?>
    <nav aria-label="Κύρια πλοήγηση">
      <?php foreach ($nav as $item): if (!empty($item['sep'])): ?><div class="nav-sep"><?= e($item['sep']) ?></div><?php continue; endif; ?>
        <a href="<?= e($item['href']) ?>"<?= ($active ?? '') === $item['key'] ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?><?php if (!empty($item['badge'])): ?><span class="pill"><?= (int)$item['badge'] ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
  </aside>
  <div class="main-col">
    <header class="top">
      <button class="menu-btn" type="button" aria-controls="side" aria-expanded="false" data-toggle-side>☰ <span class="sr">Μενού</span></button>
      <h1 class="page-title"><?= e($title) ?></h1>
      <?php if ($user): ?>
      <div class="who">
        <?php if (!empty($topLinks)) echo $topLinks; ?>
        <span class="muted"><?= e($user['name'] ?: $user['email']) ?></span>
        <form method="post" action="<?= $isSuper ? '/superadmin/logout' : '/admin/logout' ?>"><?= Csrf::field() ?><button class="btn btn-ghost btn-sm">Αποσύνδεση</button></form>
      </div>
      <?php endif; ?>
    </header>
    <main id="main" tabindex="-1">
      <?php foreach (flashes() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($msg) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script src="/assets/admin/admin.js?v=<?= APP_VERSION ?>" defer></script>
</body>
</html>
