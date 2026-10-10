<?php $v = fn($k, $d = '') => e($pg[$k] ?? $d); $locked = $pg && in_array($pg['slug'], ['terms', 'privacy', 'contact'], true); ?>
<form class="f" method="post"><?= Csrf::field() ?>
<div class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
  <section class="card">
    <div class="field"><label for="t">Τίτλος</label><input class="in" id="t" name="title" required value="<?= $v('title') ?>"></div>
    <div class="field"><label for="b">Περιεχόμενο (HTML)</label><textarea class="in" id="b" name="body" rows="18"><?= $v('body') ?></textarea>
      <small>Χρησιμοποιήστε επικεφαλίδες &lt;h2&gt;, λίστες και σύντομες παραγράφους — βοηθούν μηχανές αναζήτησης και AI. Αφαιρέστε το «Κείμενο προτύπου» όταν συμπληρώσετε το πραγματικό κείμενο.</small></div>
  </section>
  <aside class="card">
    <div class="field"><label for="s">Slug</label><input class="in" id="s" name="slug" value="<?= $v('slug') ?>" <?= $locked ? 'readonly' : '' ?>><small><?= $locked ? 'Σταθερή διεύθυνση συστήματος.' : 'Κενό = από τον τίτλο.' ?></small></div>
    <div class="field"><label for="st">Κατάσταση</label><select class="in" id="st" name="status"><option value="published">Δημοσιευμένη</option><option value="draft"<?= ($pg['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Πρόχειρο</option></select></div>
    <label class="chk"><input type="checkbox" name="in_footer" value="1" <?= ($pg['in_footer'] ?? 1) ? 'checked' : '' ?>> Σύνδεσμος στο υποσέλιδο</label>
    <div class="field"><label for="so">Σειρά</label><input class="in" id="so" name="sort" type="number" value="<?= (int)($pg['sort'] ?? 0) ?>"></div>
    <div class="field"><label for="sti">SEO τίτλος</label><input class="in" id="sti" name="seo_title" value="<?= $v('seo_title') ?>"></div>
    <div class="field"><label for="sd">Meta description</label><textarea class="in" id="sd" name="seo_description" rows="3"><?= $v('seo_description') ?></textarea></div>
    <button class="btn">Αποθήκευση</button>
    <?php if ($pg): ?><a class="btn btn-ghost" href="/<?= $v('slug') ?>/" target="_blank" rel="noopener">Προβολή ↗</a><?php endif; ?>
    <?php if ($pg && !$locked): ?><details class="confirm"><summary>Διαγραφή…</summary><button class="btn btn-danger" style="margin-top:8px" name="op" value="delete" formnovalidate>Ναι, διαγραφή σελίδας</button></details><?php endif; ?>
  </aside>
</div>
</form>
