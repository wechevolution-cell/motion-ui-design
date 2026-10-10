<?php
$v = fn($k) => e($data[$k] ?? '');
$err = fn($k) => isset($errors[$k]) ? '<span class="err" id="e-' . $k . '">' . e($errors[$k]) . '</span>' : '';
$inv = fn($k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="e-' . $k . '"' : '';
$region = ($data['region'] ?? 'mainland') === 'islands' ? 'islands' : 'mainland';
$pm = $data['payment_method'] ?? array_key_first($methods);
$first = true;
?>
<div class="wrap">
  <h1 class="kin page-h">Ταμείο</h1>
  <?php if (!empty($errors['_'])): ?><div class="alert alert-error" role="alert" style="margin-bottom:12px"><?= e($errors['_']) ?></div><?php elseif ($errors): ?><div class="alert alert-error" role="alert" style="margin-bottom:12px">Ελέγξτε τα πεδία που σημειώνονται (<?= count($errors) ?>).</div><?php endif; ?>
  <?php foreach ($notices as $n): ?><div class="alert alert-warning" style="margin-bottom:12px"><?= e($n) ?></div><?php endforeach; ?>
  <?php if (!$methods): ?><div class="alert alert-error">Οι παραγγελίες δεν είναι διαθέσιμες αυτή τη στιγμή. Επικοινωνήστε μαζί μας.</div><?php endif; ?>
  <div class="co">
    <form method="post" action="/checkout/" style="display:grid;gap:12px" novalidate data-checkout>
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
      <section class="box">
        <h2><i>1</i>Στοιχεία &amp; διεύθυνση</h2>
        <div class="ff">
          <div class="fi full"><label for="name">Ονοματεπώνυμο</label><input id="name" name="name" required autocomplete="name" value="<?= $v('name') ?>"<?= $inv('name') ?>><?= $err('name') ?></div>
          <div class="fi"><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email" value="<?= $v('email') ?>"<?= $inv('email') ?>><?= $err('email') ?></div>
          <div class="fi"><label for="phone">Κινητό</label><input id="phone" name="phone" type="tel" required autocomplete="tel" value="<?= $v('phone') ?>"<?= $inv('phone') ?>><?= $err('phone') ?></div>
          <div class="fi full"><label for="street">Οδός και αριθμός</label><input id="street" name="street" required autocomplete="street-address" value="<?= $v('street') ?>"<?= $inv('street') ?>><?= $err('street') ?></div>
          <div class="fi"><label for="city">Πόλη</label><input id="city" name="city" required autocomplete="address-level2" value="<?= $v('city') ?>"<?= $inv('city') ?>><?= $err('city') ?></div>
          <div class="fi"><label for="postcode">Τ.Κ.</label><input id="postcode" name="postcode" required inputmode="numeric" autocomplete="postal-code" value="<?= $v('postcode') ?>"<?= $inv('postcode') ?>><?= $err('postcode') ?></div>
        </div>
        <fieldset class="rads" style="border:0;padding:0;margin:0"><legend class="sr">Περιοχή αποστολής</legend>
          <label><span><input type="radio" name="region" value="mainland" <?= $region === 'mainland' ? 'checked' : '' ?> data-recalc>Ηπειρωτική Ελλάδα</span><small><?= $totals['subtotal'] >= ($shipping['free_over'] ?? INF) && !empty($shipping['free_over']) ? 'Δωρεάν' : money($shipping['flat'] ?? 0) ?></small></label>
          <label><span><input type="radio" name="region" value="islands" <?= $region === 'islands' ? 'checked' : '' ?> data-recalc>Νησιά</span><small>+<?= money($shipping['islands_extra'] ?? 0) ?></small></label>
        </fieldset>
        <details<?= ($data['invoice_type'] ?? '') === 'invoice' ? ' open' : '' ?>><summary style="font-weight:700;cursor:pointer">Χρειάζομαι τιμολόγιο</summary>
          <input type="hidden" name="invoice_type" value="receipt">
          <label class="consent" style="margin-top:8px"><input type="checkbox" name="invoice_type" value="invoice" <?= ($data['invoice_type'] ?? '') === 'invoice' ? 'checked' : '' ?>> Έκδοση τιμολογίου</label>
          <div class="ff" style="margin-top:8px">
            <div class="fi full"><label for="company_name">Επωνυμία</label><input id="company_name" name="company_name" value="<?= $v('company_name') ?>"></div>
            <div class="fi"><label for="vat_number">ΑΦΜ</label><input id="vat_number" name="vat_number" inputmode="numeric" value="<?= $v('vat_number') ?>"<?= $inv('vat_number') ?>><?= $err('vat_number') ?></div>
            <div class="fi"><label for="tax_office">ΔΟΥ</label><input id="tax_office" name="tax_office" value="<?= $v('tax_office') ?>"></div>
          </div>
        </details>
        <div class="fi"><label for="note">Σχόλια παραγγελίας (προαιρετικά)</label><textarea id="note" name="customer_note" rows="2"><?= $v('customer_note') ?></textarea></div>
      </section>
      <section class="box">
        <h2><i>2</i>Τρόπος πληρωμής</h2>
        <fieldset class="rads" style="border:0;padding:0;margin:0"><legend class="sr">Τρόπος πληρωμής</legend>
          <?php foreach ($methods as $k => $m): ?><label><span><input type="radio" name="payment_method" value="<?= e($k) ?>" data-fee="<?= e($m['fee']) ?>" <?= $pm === $k ? 'checked' : '' ?> data-recalc><?= e($m['label']) ?></span><small><?= e($m['note']) ?></small></label><?php endforeach; ?>
        </fieldset>
        <?= $err('payment_method') ?>
        <label class="consent"><input type="checkbox" name="terms" value="1" required<?= $inv('terms') ?>> <span>Αποδέχομαι τους <a href="/terms/" target="_blank">όρους χρήσης</a> και την <a href="/privacy/" target="_blank">πολιτική απορρήτου</a>.</span></label><?= $err('terms') ?>
        <button class="btn big primary"<?= $methods ? '' : ' disabled' ?>>Ολοκλήρωση παραγγελίας · <span data-total><?= money($totals['total']) ?></span></button>
      </section>
    </form>
    <aside class="box sum" aria-label="Σύνοψη παραγγελίας">
      <h2>Η παραγγελία σας</h2>
      <?php foreach ($lines as $l): ?>
        <div class="ci" style="grid-template-columns:52px minmax(0,1fr) auto"><div class="t" style="width:52px;height:52px;background:<?= t01_tint((int)$l['product']['id']) ?>"><?= t01_img($l['image'], $l['product']['name'], '52px') ?></div><div><b><?= (int)$l['qty'] ?> × <?= e($l['product']['name']) ?></b><?php if ($l['options_label']): ?><small><?= e($l['options_label']) ?></small><?php endif; ?></div><b class="num"><?= money($l['total']) ?></b></div>
      <?php endforeach; ?>
      <div class="ln"><span>Υποσύνολο</span><span><?= money($totals['subtotal']) ?></span></div>
      <div class="ln"><span>Αποστολή</span><span data-ship><?= $totals['shipping'] > 0 ? money($totals['shipping']) : 'Δωρεάν' ?></span></div>
      <div class="ln" data-fee-row<?= $totals['fee'] > 0 ? '' : ' hidden' ?>><span>Επιβάρυνση πληρωμής</span><span data-fee><?= money($totals['fee']) ?></span></div>
      <div class="ln tot"><span>Σύνολο</span><span data-total><?= money($totals['total']) ?></span></div>
      <small style="opacity:.75">Οι τιμές περιλαμβάνουν ΦΠΑ.</small>
      <script type="application/json" id="ship-cfg"><?= json_encode(['subtotal' => $totals['subtotal'], 'flat' => (float)($shipping['flat'] ?? 0), 'free_over' => (float)($shipping['free_over'] ?? 0), 'islands' => (float)($shipping['islands_extra'] ?? 0)]) ?></script>
    </aside>
  </div>
</div>
