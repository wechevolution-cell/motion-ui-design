<?php /** Lock page: served for every public URL of a LOCKED / SUSPENDED shop. Exposes no shop data. */ ?><!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Προσωρινά μη διαθέσιμο</title>
<style>
:root{--bg:#17195A;--card:#FFFFFF;--ink:#17195A;--a:#FF7A59;--b:#FFD95E;--c:#8FE3C8;--d:#C9B8FF}
*{box-sizing:border-box}
html,body{margin:0;height:100%}
body{background:var(--bg);color:var(--ink);font:400 17px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;display:grid;place-items:center;padding:20px}
.card{background:var(--card);border-radius:28px;max-width:560px;width:100%;padding:clamp(28px,6vw,52px);position:relative;overflow:hidden}
.blocks{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:28px;max-width:200px}
.blocks i{aspect-ratio:1;border-radius:12px}
.blocks i:nth-child(1){background:var(--a)}.blocks i:nth-child(2){background:var(--b)}.blocks i:nth-child(3){background:var(--c)}.blocks i:nth-child(4){background:var(--d)}
h1{font-size:clamp(28px,5vw,40px);line-height:1.1;margin:0 0 14px;font-weight:800;letter-spacing:-.01em}
p{margin:0;color:#3E4180}
@media (prefers-reduced-motion:no-preference){.blocks i{animation:b 2.4s ease-in-out infinite}.blocks i:nth-child(2){animation-delay:.2s}.blocks i:nth-child(3){animation-delay:.4s}.blocks i:nth-child(4){animation-delay:.6s}@keyframes b{50%{transform:translateY(-6px)}}}
</style>
</head>
<body>
<main class="card">
  <div class="blocks" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
  <h1>Το ηλεκτρονικό κατάστημα είναι προσωρινά μη διαθέσιμο.</h1>
  <p>Θα είμαστε ξανά κοντά σας σύντομα.</p>
</main>
</body>
</html>
