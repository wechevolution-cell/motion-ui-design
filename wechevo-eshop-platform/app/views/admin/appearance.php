<?php foreach ($warnings as $w): ?><div class="alert alert-warning" role="status"><?= e($w) ?></div><?php endforeach; ?>
<form class="f" method="post" enctype="multipart/form-data"><?= Csrf::field() ?>
<div class="grid g2" style="align-items:start">
  <section class="card">
    <div class="card-h"><h2>Χρώματα</h2><span class="muted">Πρότυπο <?= e($manifest['code']) ?> · <?= e($manifest['name']) ?></span></div>
    <p class="hint" style="margin:0">Αλλάζετε μόνο τα χρώματα του προτύπου· η διάταξη μένει σταθερή ώστε το κατάστημα να μη «σπάει».</p>
    <div class="grid g2">
    <?php foreach ($manifest['tokens'] as $k => $def): ?>
      <div class="field"><label for="t_<?= $k ?>"><?= e($def['label']) ?></label>
        <div class="color-row" data-color><input type="color" value="<?= e(strtolower($tokens[$k])) ?>" aria-label="<?= e($def['label']) ?> (επιλογέας)"><input class="in" type="text" id="t_<?= $k ?>" name="t_<?= $k ?>" value="<?= e($tokens[$k]) ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7"></div></div>
    <?php endforeach; ?>
    </div>
    <div class="row"><button class="btn">Αποθήκευση</button><button class="btn btn-ghost" name="op" value="reset" formnovalidate>Επαναφορά προεπιλογών</button><a class="btn btn-ghost" href="/" target="_blank" rel="noopener">Προβολή ↗</a></div>
  </section>
  <section class="card">
    <h2>Λογότυπο &amp; favicon</h2>
    <div class="field"><label for="logo">Λογότυπο</label>
      <?php if (!empty($theme['logo'])): ?><img src="<?= e($theme['logo']) ?>" alt="Τρέχον λογότυπο" style="max-height:60px;max-width:240px;background:#eee;padding:6px;border-radius:8px"><label class="chk"><input type="checkbox" name="remove_logo" value="1"> Αφαίρεση</label><?php endif; ?>
      <input class="in" id="logo" type="file" name="logo" accept="image/png,image/webp,image/jpeg"><small>PNG ή WebP με διάφανο φόντο, ιδανικά 400×120.</small></div>
    <div class="field"><label for="fav">Favicon</label>
      <?php if (!empty($theme['favicon'])): ?><img src="<?= e($theme['favicon']) ?>" alt="Τρέχον favicon" width="32" height="32"><label class="chk"><input type="checkbox" name="remove_favicon" value="1"> Αφαίρεση</label><?php endif; ?>
      <input class="in" id="fav" type="file" name="favicon" accept="image/png"><small>Τετράγωνο PNG, 512×512.</small></div>
    <h3>Προεπισκόπηση</h3>
    <div style="background:<?= e($tokens['background']) ?>;color:<?= e($tokens['text']) ?>;border-radius:16px;padding:16px;display:grid;gap:10px">
      <b>Κείμενο στο φόντο</b>
      <div style="background:<?= e($tokens['surface']) ?>;color:<?= e($tokens['surface_text']) ?>;border-radius:12px;padding:12px">Κάρτα προϊόντος · <b>24,90 €</b></div>
      <span style="display:inline-block;background:<?= e($tokens['primary']) ?>;color:<?= e($tokens['button_text']) ?>;border-radius:99px;padding:8px 16px;font-weight:700;justify-self:start">Στο καλάθι</span>
      <span style="display:flex;gap:6px"><i class="preview-swatch" style="background:<?= e($tokens['secondary']) ?>"></i><i class="preview-swatch" style="background:<?= e($tokens['accent']) ?>"></i><i class="preview-swatch" style="background:<?= e($tokens['accent2']) ?>"></i></span>
    </div>
  </section>
</div>
</form>
