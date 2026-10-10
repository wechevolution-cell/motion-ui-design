<?php $c = $company; $soc = $c['social'] ?? []; $card = $pay['card'] ?? []; ?>
<div class="grid g2" style="align-items:start">
<form class="card f" method="post" id="company"><?= Csrf::field() ?><input type="hidden" name="section" value="company">
  <h2>Στοιχεία επιχείρησης</h2>
  <div class="grid g2">
    <div class="field"><label for="n">Όνομα καταστήματος</label><input class="in" id="n" name="name" value="<?= e($c['name'] ?? '') ?>"></div>
    <div class="field"><label for="ln">Επωνυμία</label><input class="in" id="ln" name="legal_name" value="<?= e($c['legal_name'] ?? '') ?>"></div>
    <div class="field"><label for="em">Email</label><input class="in" id="em" name="email" type="email" value="<?= e($c['email'] ?? '') ?>"><small>Εδώ έρχονται οι νέες παραγγελίες και τα μηνύματα.</small></div>
    <div class="field"><label for="ph">Τηλέφωνο</label><input class="in" id="ph" name="phone" value="<?= e($c['phone'] ?? '') ?>"></div>
    <div class="field"><label for="ad">Διεύθυνση</label><input class="in" id="ad" name="address" value="<?= e($c['address'] ?? '') ?>"></div>
    <div class="field"><label for="hr">Ωράριο</label><input class="in" id="hr" name="hours" value="<?= e($c['hours'] ?? '') ?>" placeholder="Δευ–Παρ 9:00–17:00"></div>
    <div class="field"><label for="vat">ΑΦΜ</label><input class="in" id="vat" name="vat_number" value="<?= e($c['vat_number'] ?? '') ?>"></div>
  </div>
  <h3>Social</h3>
  <div class="grid g2"><?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'] as $k => $l): ?><div class="field"><label for="s_<?= $k ?>"><?= $l ?></label><input class="in" id="s_<?= $k ?>" name="social_<?= $k ?>" value="<?= e($soc[$k] ?? '') ?>" placeholder="https://"></div><?php endforeach; ?></div>
  <div><button class="btn">Αποθήκευση</button></div>
</form>
<div class="stack" style="gap:16px">
<form class="card f" method="post" id="shipping"><?= Csrf::field() ?><input type="hidden" name="section" value="shipping">
  <h2>Αποστολές</h2>
  <div class="grid g3">
    <div class="field"><label for="fl">Κόστος αποστολής (€)</label><input class="in" id="fl" name="flat" inputmode="decimal" value="<?= e($ship['flat'] ?? 0) ?>"></div>
    <div class="field"><label for="fo">Δωρεάν άνω των (€)</label><input class="in" id="fo" name="free_over" inputmode="decimal" value="<?= e($ship['free_over'] ?? 0) ?>"><small>0 = ποτέ δωρεάν.</small></div>
    <div class="field"><label for="ie">Επιβάρυνση νησιών (€)</label><input class="in" id="ie" name="islands_extra" inputmode="decimal" value="<?= e($ship['islands_extra'] ?? 0) ?>"></div>
  </div>
  <div class="field"><label for="sn">Σημείωση (π.χ. χρόνος παράδοσης)</label><input class="in" id="sn" name="note" value="<?= e($ship['note'] ?? '') ?>"></div>
  <div><button class="btn">Αποθήκευση</button></div>
</form>
<form class="card f" method="post" id="payments"><?= Csrf::field() ?><input type="hidden" name="section" value="payments">
  <h2>Τρόποι πληρωμής</h2>
  <fieldset><legend>Αντικαταβολή</legend><label class="chk"><input type="checkbox" name="cod_enabled" value="1" <?= !empty($pay['cod']['enabled']) ? 'checked' : '' ?>> Ενεργή</label>
    <div class="field"><label for="cf">Επιβάρυνση (€)</label><input class="in" id="cf" name="cod_fee" inputmode="decimal" value="<?= e($pay['cod']['fee'] ?? 0) ?>"></div></fieldset>
  <fieldset><legend>Τραπεζική κατάθεση</legend><label class="chk"><input type="checkbox" name="bank_enabled" value="1" <?= !empty($pay['bank']['enabled']) ? 'checked' : '' ?>> Ενεργή</label>
    <div class="field"><label for="bd">Στοιχεία λογαριασμού (εμφανίζονται μετά την παραγγελία)</label><textarea class="in" id="bd" name="bank_details" rows="3"><?= e($pay['bank']['details'] ?? '') ?></textarea></div></fieldset>
  <fieldset><legend>Κάρτα</legend>
    <div class="field"><label for="cp">Πάροχος</label><select class="in" id="cp" name="card_provider"><?php foreach (['none' => 'Καμία (απενεργοποιημένη)', 'stripe' => 'Stripe Checkout', 'viva' => 'Viva Wallet Smart Checkout'] as $k => $l): ?><option value="<?= $k ?>"<?= ($card['provider'] ?? 'none') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="grid g2">
      <div class="field"><label for="spk">Stripe publishable key</label><input class="in" id="spk" name="stripe_pk" value="<?= e($card['stripe']['publishable_key'] ?? '') ?>"></div>
      <div class="field"><label for="ssk">Stripe secret key</label><input class="in" id="ssk" name="stripe_sk" type="password" autocomplete="off" placeholder="<?= !empty($card['stripe']['secret_key']) ? '•••• αποθηκευμένο' : 'sk_live_…' ?>"></div>
      <div class="field"><label for="vci">Viva client ID</label><input class="in" id="vci" name="viva_client_id" value="<?= e($card['viva']['client_id'] ?? '') ?>"></div>
      <div class="field"><label for="vcs">Viva client secret</label><input class="in" id="vcs" name="viva_secret" type="password" autocomplete="off" placeholder="<?= !empty($card['viva']['client_secret']) ? '•••• αποθηκευμένο' : '' ?>"></div>
      <div class="field"><label for="vsc">Viva source code</label><input class="in" id="vsc" name="viva_source" value="<?= e($card['viva']['source_code'] ?? '') ?>"></div>
      <div class="field"><label for="ven">Viva περιβάλλον</label><select class="in" id="ven" name="viva_env"><option value="demo">Demo</option><option value="live"<?= ($card['viva']['env'] ?? '') === 'live' ? ' selected' : '' ?>>Live</option></select></div>
    </div>
    <p class="hint" style="margin:0">Η πληρωμή με κάρτα εμφανίζεται στο ταμείο μόνο όταν τα κλειδιά είναι συμπληρωμένα. Ελέγξτε την πρώτα σε demo / test mode.</p>
  </fieldset>
  <div><button class="btn">Αποθήκευση</button></div>
</form>
</div>
</div>
