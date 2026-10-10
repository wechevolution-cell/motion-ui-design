<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= (int)$code ?> · <?= e($title) ?></title>
<style>
:root{--bg:#F5F6F8;--fg:#16181D;--muted:#5B6170;--accent:#3346D3}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--fg);font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;padding:24px}
main{max-width:520px}
.code{font:600 14px ui-monospace,monospace;color:var(--muted)}
h1{font-size:30px;margin:6px 0 10px;line-height:1.2}
p{color:var(--muted);margin:0 0 20px}
a{color:var(--accent);font-weight:600}
</style>
</head>
<body>
<main>
  <div class="code">Σφάλμα <?= (int)$code ?></div>
  <h1><?= e($title) ?></h1>
  <?php if ($msg): ?><p><?= e($msg) ?></p><?php endif; ?>
  <a href="/">← Αρχική σελίδα</a>
</main>
</body>
</html>
