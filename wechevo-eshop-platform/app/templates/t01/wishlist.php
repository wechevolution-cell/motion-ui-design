<div class="wrap">
  <h1 class="kin page-h">Αγαπημένα</h1>
  <?php if ($list): ?><div class="pgrid"><?php foreach ($list as $p) echo t01_card($p, $covers[$p['id']] ?? null, $wish); ?></div>
  <?php else: ?><div class="empty"><b style="font-size:22px">Δεν έχετε αγαπημένα ακόμη</b><span class="muted">Πατήστε την καρδιά σε ένα προϊόν για να το κρατήσετε εδώ.</span><a class="btn" href="/products/">Δείτε τα προϊόντα</a></div><?php endif; ?>
</div>
