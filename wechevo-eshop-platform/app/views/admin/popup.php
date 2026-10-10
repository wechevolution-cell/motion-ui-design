<form class="card f" method="post" style="max-width:760px"><?= Csrf::field() ?>
  <label class="chk"><input type="checkbox" name="active" value="1" <?= !empty($p['active']) ? 'checked' : '' ?>> <b>Ενεργό pop-up στην αρχική σελίδα</b></label>
  <div class="field"><label for="t">Τίτλος</label><input class="in" id="t" name="title" value="<?= e($p['title'] ?? '') ?>"></div>
  <div class="field"><label for="b">Κείμενο</label><textarea class="in" id="b" name="body" rows="3"><?= e($p['body'] ?? '') ?></textarea></div>
  <div class="grid g2">
    <div class="field"><label for="bt">Κείμενο κουμπιού</label><input class="in" id="bt" name="button_text" value="<?= e($p['button_text'] ?? '') ?>"></div>
    <div class="field"><label for="l">Σύνδεσμος (αν δεν συλλέγει email)</label><input class="in" id="l" name="link" value="<?= e($p['link'] ?? '') ?>" placeholder="/products/"></div>
    <div class="field"><label for="d">Εμφάνιση μετά από (δευτ.)</label><input class="in" id="d" name="delay" type="number" min="0" max="120" value="<?= (int)($p['delay'] ?? 8) ?>"></div>
  </div>
  <label class="chk"><input type="checkbox" name="collect_email" value="1" <?= !empty($p['collect_email']) ? 'checked' : '' ?>> Πεδίο email (εγγραφή στο newsletter)</label>
  <label class="chk"><input type="checkbox" name="consent_required" value="1" <?= !empty($p['consent_required']) ? 'checked' : '' ?>> Υποχρεωτικό checkbox συγκατάθεσης (GDPR)</label>
  <div class="field"><label for="ct">Κείμενο συγκατάθεσης</label><textarea class="in" id="ct" name="consent_text" rows="2"><?= e($p['consent_text'] ?? '') ?></textarea></div>
  <p class="hint" style="margin:0">Εμφανίζεται μία φορά ανά επισκέπτη (μέχρι να αλλάξετε το pop-up). Συνδρομητές: <b><?= (int)$subs ?></b> · <a href="/admin/subscribers">Λίστα</a></p>
  <div><button class="btn">Αποθήκευση</button></div>
</form>
