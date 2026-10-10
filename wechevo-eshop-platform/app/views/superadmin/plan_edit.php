<?php $v = fn($k, $d = '') => e($_POST[$k] ?? ($plan[$k] ?? $d)); $f = $plan ? Plans::features($plan) : array_fill_keys(array_keys(Plans::FEATURES), true); ?>
<?php foreach ($errors as $m): ?><div class="alert alert-error" role="alert"><?= e($m) ?></div><?php endforeach; ?>
<form class="card f" method="post">
  <?= Csrf::field() ?>
  <div class="grid g3">
    <div class="field"><label for="n">Όνομα</label><input class="in" id="n" name="name" required value="<?= $v('name') ?>"></div>
    <div class="field"><label for="p">Τιμή ανά μήνα (€)</label><input class="in" id="p" name="price" inputmode="decimal" required value="<?= $v('price', '0') ?>"></div>
    <div class="field"><label for="s">Setup fee (€)</label><input class="in" id="s" name="setup_fee" inputmode="decimal" value="<?= $v('setup_fee', '0') ?>"></div>
    <div class="field"><label for="a">Όριο ενεργών προϊόντων</label><input class="in" id="a" name="active_product_limit" type="number" min="0" required value="<?= $v('active_product_limit', '20') ?>"></div>
    <div class="field"><label for="st">Όριο αποθηκευμένων προϊόντων</label><input class="in" id="st" name="stored_product_limit" type="number" min="1" required value="<?= $v('stored_product_limit', '10000') ?>"></div>
    <div class="field"><label for="sf">Όριο χρηστών</label><input class="in" id="sf" name="staff_limit" type="number" min="1" required value="<?= $v('staff_limit', '3') ?>"></div>
    <div class="field"><label for="sm">Χώρος αρχείων (MB)</label><input class="in" id="sm" name="storage_mb" type="number" min="50" value="<?= $v('storage_mb', '2048') ?>"></div>
    <div class="field"><label for="d">Διάρκειες (μήνες, με κόμμα)</label><input class="in" id="d" name="duration_options" required value="<?= e($_POST['duration_options'] ?? ($plan ? implode(', ', Plans::durations($plan)) : '1, 3, 6, 12')) ?>"></div>
    <div class="field"><label for="so">Σειρά</label><input class="in" id="so" name="sort" type="number" value="<?= $v('sort', '0') ?>"></div>
  </div>
  <fieldset><legend>Λειτουργίες πακέτου</legend><div class="grid g3">
    <?php foreach (Plans::FEATURES as $k => $l): ?><label class="chk"><input type="checkbox" name="features[<?= $k ?>]" value="1" <?= !empty($f[$k]) ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?>
  </div></fieldset>
  <div class="field" style="max-width:260px"><label for="ss">Κατάσταση</label><select class="in" id="ss" name="status"><option value="active">Ενεργό (διαθέσιμο σε νέα)</option><option value="archived"<?= ($plan['status'] ?? '') === 'archived' ? ' selected' : '' ?>>Αρχειοθετημένο</option></select></div>
  <div class="row"><button class="btn">Αποθήκευση</button><a class="btn btn-ghost" href="/superadmin/plans">Άκυρο</a></div>
  <p class="hint" style="margin:0">Μείωση ορίου σε υπάρχον πακέτο δεν απενεργοποιεί προϊόντα αυτόματα· απλώς εμποδίζει νέες ενεργοποιήσεις μέχρι να πέσουν κάτω από το όριο. Για άμεση απενεργοποίηση χρησιμοποιήστε «Αλλαγή πακέτου» στο κατάστημα.</p>
</form>
