<?php
/** @var Seo $seo @var string $content @var array $company @var array $tokens @var array $menu */
$name = $company['name'] ?? $shop['name'];
$path = req_path();
$ga = $seoCfg['ga4_id'] ?? '';
$pop = $popupEnabled && !empty($popup['active']) && $view === 'home' ? $popup : null;
?><!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?= $seo->head() ?>
<meta name="theme-color" content="<?= e($tokens['background']) ?>">
<?php if ($favicon): ?><link rel="icon" href="<?= e($favicon) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e($manifest['fonts']) ?>">
<style><?= Theme::css($tokens) ?></style>
<link rel="stylesheet" href="/assets/templates/t01/style.css?v=<?= APP_VERSION ?>">
<script src="/assets/templates/t01/app.js?v=<?= APP_VERSION ?>" defer></script>
</head>
<body data-view="<?= e($view) ?>"<?= $ga ? ' data-ga="' . e($ga) . '"' : '' ?>>
<a class="skip" href="#main">Μετάβαση στο περιεχόμενο</a>
<header class="site-head">
  <div class="pill">
    <a class="logo" href="/" aria-label="<?= e($name) ?> — αρχική"><?php if (!empty($company['logo'])): ?><img src="<?= e($company['logo']) ?>" alt="<?= e($name) ?>" width="160" height="40"><?php else: ?><?= e($name) ?><?php endif; ?></a>
    <nav aria-label="Κύρια πλοήγηση">
      <a href="/products/"<?= $path === '/products/' ? ' aria-current="page"' : '' ?>>Προϊόντα</a>
      <?php foreach ($menu as $m): ?><a href="<?= e($m['url']) ?>"<?= $path === $m['url'] ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a><?php endforeach; ?>
    </nav>
    <div class="head-actions">
      <button class="icon-btn hide-m" type="button" data-search-toggle aria-expanded="false" aria-controls="search-pop" aria-label="Αναζήτηση">⌕</button>
      <a class="icon-btn hide-m" href="/wishlist/" aria-label="Αγαπημένα (<?= count($wish) ?>)">♡</a>
      <a class="cartpill" href="/cart/" data-cart-open aria-label="Καλάθι, <?= (int)$cartCount ?> τεμάχια"><span class="lbl">Καλάθι</span><span class="n" data-cart-count><?= (int)$cartCount ?></span></a>
    </div>
  </div>
  <form class="search-pop" id="search-pop" role="search" action="/search/" method="get" hidden>
    <label class="sr" for="q-top">Αναζήτηση προϊόντων</label>
    <input id="q-top" name="q" type="search" placeholder="Τι ψάχνετε;" minlength="2" required>
    <button class="btn">Αναζήτηση</button>
  </form>
</header>

<main id="main" tabindex="-1">
<?php foreach (flashes() as [$t, $m]): ?><div class="wrap" style="padding-top:16px"><div class="alert alert-<?= e($t) ?>" role="<?= $t === 'error' ? 'alert' : 'status' ?>"><?= e($m) ?></div></div><?php endforeach; ?>
<?= $content ?>
</main>

<footer class="site-foot wrap">
  <div class="cols">
    <div><b>Ψώνια</b><a href="/products/">Όλα τα προϊόντα</a><?php foreach (array_slice(array_filter($cats, fn($c) => $c['count'] > 0), 0, 5) as $c): ?><a href="/category/<?= e($c['slug']) ?>/"><?= e($c['name']) ?></a><?php endforeach; ?></div>
    <div><b>Πληροφορίες</b><?php foreach ($footerPages as $fp): ?><a href="/<?= e($fp['slug']) ?>/"><?= e($fp['title']) ?></a><?php endforeach; ?></div>
    <div><b>Επικοινωνία</b>
      <?php if (!empty($company['phone'])): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $company['phone'])) ?>"><?= e($company['phone']) ?></a><?php endif; ?>
      <?php if (!empty($company['email'])): ?><a href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a><?php endif; ?>
      <?php if (!empty($company['address'])): ?><span class="muted"><?= e($company['address']) ?></span><?php endif; ?>
      <?php if (!empty($company['hours'])): ?><span class="muted"><?= e($company['hours']) ?></span><?php endif; ?>
    </div>
    <div><b>Social</b><?php foreach (array_filter($company['social'] ?? []) as $k => $u): ?><a href="<?= e($u) ?>" rel="noopener" target="_blank"><?= e(ucfirst($k)) ?></a><?php endforeach; ?>
      <button class="chip" type="button" data-cookie-settings style="margin-top:8px">Ρυθμίσεις cookies</button></div>
  </div>
  <div class="giant kin" aria-hidden="true"><?= e($name) ?></div>
  <div class="legal"><span>© <?= date('Y') ?> <?= e($company['legal_name'] ?? $name) ?><?= !empty($company['vat_number']) ? ' · ΑΦΜ ' . e($company['vat_number']) : '' ?></span><span>Οι τιμές περιλαμβάνουν ΦΠΑ</span></div>
</footer>

<nav class="tabs" aria-label="Γρήγορη πλοήγηση">
  <a href="/"<?= $path === '/' ? ' aria-current="page"' : '' ?>><span class="ic" aria-hidden="true">⌂</span>Αρχική</a>
  <a href="/products/"<?= str_starts_with($path, '/products') || str_starts_with($path, '/category') ? ' aria-current="page"' : '' ?>><span class="ic" aria-hidden="true">▦</span>Προϊόντα</a>
  <a href="/search/"<?= $path === '/search/' ? ' aria-current="page"' : '' ?>><span class="ic" aria-hidden="true">⌕</span>Αναζήτηση</a>
  <a href="/cart/" data-cart-open<?= $path === '/cart/' ? ' aria-current="page"' : '' ?>><span class="ic" aria-hidden="true">◍<span class="cnt" data-cart-count><?= (int)$cartCount ?></span></span>Καλάθι</a>
</nav>

<div class="scrim" id="scrim"></div>
<aside class="sheet" id="cart-sheet" aria-label="Καλάθι" aria-hidden="true" tabindex="-1"><div class="grab" aria-hidden="true"></div><div data-cart-body></div></aside>
<dialog class="qview" id="qview" aria-label="Γρήγορη προβολή"></dialog>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<?php if ($pop): ?>
<dialog class="popup" id="popup" data-version="<?= (int)($pop['version'] ?? 1) ?>" data-delay="<?= (int)($pop['delay'] ?? 8) ?>" aria-labelledby="pop-t">
  <div class="in">
    <div style="display:flex;justify-content:space-between;gap:10px"><h2 id="pop-t" class="kin"><?= e($pop['title']) ?></h2><button class="xb" type="button" data-close aria-label="Κλείσιμο">✕</button></div>
    <?php if ($pop['body']): ?><p style="margin:0"><?= e($pop['body']) ?></p><?php endif; ?>
    <?php if (!empty($pop['collect_email'])): ?>
      <form method="post" action="/newsletter/" data-newsletter><?= t01_form_guard() ?><input type="hidden" name="source" value="popup"><input type="hidden" name="back" value="/">
        <div class="r"><label class="sr" for="pop-e">Email</label><input id="pop-e" type="email" name="email" required placeholder="you@example.gr" autocomplete="email"><button class="btn"><?= e($pop['button_text'] ?: 'Εγγραφή') ?></button></div>
        <?php if (!empty($pop['consent_required'])): ?><label class="consent"><input type="checkbox" name="consent" value="1" required> <?= e($pop['consent_text']) ?></label><?php else: ?><input type="hidden" name="consent" value="1"><?php endif; ?>
        <p data-msg role="status" style="margin:0;font-weight:700"></p>
      </form>
    <?php elseif ($pop['link']): ?><a class="btn" href="<?= e($pop['link']) ?>"><?= e($pop['button_text'] ?: 'Δείτε περισσότερα') ?></a><?php endif; ?>
  </div>
</dialog>
<?php endif; ?>

<?php if ($ga): ?>
<div class="cookie" id="cookie" role="region" aria-label="Cookies" hidden>
  <b>Cookies</b>
  <span>Χρησιμοποιούμε απαραίτητα cookies για το καλάθι. Με τη συγκατάθεσή σας χρησιμοποιούμε και cookies στατιστικών (Google Analytics). <a href="/cookies/">Περισσότερα</a></span>
  <div class="row"><button class="btn primary" type="button" data-consent="yes">Αποδοχή</button><button class="btn light" type="button" data-consent="no">Μόνο απαραίτητα</button></div>
</div>
<?php endif; ?>
</body>
</html>
