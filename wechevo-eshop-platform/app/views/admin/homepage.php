<?php $hero = $h['hero'] ?? []; $vals = $h['values'] ?? []; $faq = $h['faq'] ?? []; for ($i = count($faq); $i < count($faq) + 3; $i++) $faq[] = ['q' => '', 'a' => '']; ?>
<form class="f" method="post" enctype="multipart/form-data"><?= Csrf::field() ?>
<div class="grid g2" style="align-items:start">
  <section class="card">
    <h2>Hero</h2>
    <div class="field"><label for="he">Μικρή ετικέτα</label><input class="in" id="he" name="hero_eyebrow" value="<?= e($hero['eyebrow'] ?? '') ?>"></div>
    <div class="field"><label for="ht">Τίτλος</label><input class="in" id="ht" name="hero_title" value="<?= e($hero['title'] ?? '') ?>" maxlength="120"></div>
    <div class="field"><label for="hs">Υπότιτλος</label><textarea class="in" id="hs" name="hero_subtitle" rows="2"><?= e($hero['subtitle'] ?? '') ?></textarea></div>
    <div class="grid g2">
      <div class="field"><label for="hcl">Κουμπί</label><input class="in" id="hcl" name="hero_cta_label" value="<?= e($hero['cta_label'] ?? '') ?>"></div>
      <div class="field"><label for="hcu">Σύνδεσμος κουμπιού</label><input class="in" id="hcu" name="hero_cta_url" value="<?= e($hero['cta_url'] ?? '/products/') ?>"></div>
    </div>
    <div class="field"><label for="hi">Εικόνα hero</label><?php if ($heroImg): ?><img src="/uploads/<?= e($heroImg['path']) ?>" alt="" style="max-height:120px;border-radius:8px"><label class="chk"><input type="checkbox" name="hero_image_remove" value="1"> Αφαίρεση (εμφανίζεται το προβεβλημένο προϊόν)</label><?php endif; ?><input class="in" id="hi" type="file" name="hero_image" accept="image/*"></div>
  </section>
  <section class="card">
    <h2>Ζώνες</h2>
    <div class="grid g2">
      <div class="field"><label for="pt">Προσφορά: τίτλος</label><input class="in" id="pt" name="promo_title" value="<?= e($h['promo']['title'] ?? '') ?>"></div>
      <div class="field"><label for="px">Προσφορά: κείμενο</label><input class="in" id="px" name="promo_text" value="<?= e($h['promo']['text'] ?? '') ?>"></div>
    </div>
    <div class="field"><label for="mq">Κυλιόμενη ταινία (μία φράση ανά γραμμή)</label><textarea class="in" id="mq" name="marquee" rows="4"><?= e(implode("\n", $h['marquee'] ?? [])) ?></textarea></div>
    <label class="chk"><input type="checkbox" name="show_categories" value="1" <?= !empty($h['show_categories']) ? 'checked' : '' ?>> Εμφάνιση κατηγοριών</label>
    <label class="chk"><input type="checkbox" name="show_featured" value="1" <?= !empty($h['show_featured']) ? 'checked' : '' ?>> Εμφάνιση προϊόντων</label>
    <div class="field"><label for="ft">Τίτλος ενότητας προϊόντων</label><input class="in" id="ft" name="featured_title" value="<?= e($h['featured_title'] ?? '') ?>"></div>
    <label class="chk"><input type="checkbox" name="nl_show" value="1" <?= !empty($h['newsletter']['show']) ? 'checked' : '' ?>> Ενότητα newsletter</label>
    <div class="grid g2">
      <div class="field"><label for="nt">Newsletter: τίτλος</label><input class="in" id="nt" name="nl_title" value="<?= e($h['newsletter']['title'] ?? '') ?>"></div>
      <div class="field"><label for="nx">Newsletter: κείμενο</label><input class="in" id="nx" name="nl_text" value="<?= e($h['newsletter']['text'] ?? '') ?>"></div>
    </div>
  </section>
  <section class="card">
    <h2>Πλεονεκτήματα (3 κάρτες)</h2>
    <?php for ($i = 0; $i < 3; $i++): ?>
      <div class="grid g2"><input class="in" name="values[<?= $i ?>][title]" value="<?= e($vals[$i]['title'] ?? '') ?>" aria-label="Τίτλος <?= $i + 1 ?>" placeholder="Τίτλος"><input class="in" name="values[<?= $i ?>][text]" value="<?= e($vals[$i]['text'] ?? '') ?>" aria-label="Κείμενο <?= $i + 1 ?>" placeholder="Κείμενο"></div>
    <?php endfor; ?>
  </section>
  <section class="card">
    <h2>Συχνές ερωτήσεις (αρχική)</h2>
    <p class="hint" style="margin:0">Σύντομες, άμεσες απαντήσεις. Δημοσιεύονται και ως FAQPage structured data για Google και AI search.</p>
    <?php foreach ($faq as $i => $f): ?>
      <input class="in" name="faq[<?= $i ?>][q]" value="<?= e($f['q']) ?>" aria-label="Ερώτηση <?= $i + 1 ?>" placeholder="Ερώτηση">
      <textarea class="in" name="faq[<?= $i ?>][a]" rows="2" aria-label="Απάντηση <?= $i + 1 ?>" placeholder="Απάντηση"><?= e($f['a']) ?></textarea>
    <?php endforeach; ?>
  </section>
</div>
<div class="row"><button class="btn">Αποθήκευση αρχικής</button><a class="btn btn-ghost" href="/" target="_blank" rel="noopener">Προβολή ↗</a></div>
</form>
