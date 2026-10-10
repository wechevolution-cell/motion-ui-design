<div class="grid split">
  <div class="stack" style="gap:16px">
    <section class="card">
      <div class="card-h"><h2>Προϊόντα</h2><span class="muted"><?= dt($o['created_at']) ?></span></div>
      <div class="table-wrap"><table class="t">
        <thead><tr><th>Προϊόν</th><th>SKU</th><th class="r">Τιμή</th><th class="r">Ποσ.</th><th class="r">Σύνολο</th></tr></thead>
        <tbody><?php foreach ($items as $i): ?><tr><td><?= e($i['name']) ?><?= $i['options'] ? '<br><span class="muted">' . e($i['options']) . '</span>' : '' ?></td><td class="mono"><?= e($i['sku']) ?></td><td class="r num"><?= money($i['price']) ?></td><td class="r num"><?= (int)$i['qty'] ?></td><td class="r num"><?= money($i['total']) ?></td></tr><?php endforeach; ?></tbody>
        <tfoot>
          <tr><td colspan="4" class="r">Υποσύνολο</td><td class="r num"><?= money($o['subtotal']) ?></td></tr>
          <tr><td colspan="4" class="r">Αποστολή (<?= $o['region'] === 'islands' ? 'νησιά' : 'ηπειρωτική' ?>)</td><td class="r num"><?= money($o['shipping']) ?></td></tr>
          <?php if ($o['fee'] > 0): ?><tr><td colspan="4" class="r">Επιβάρυνση πληρωμής</td><td class="r num"><?= money($o['fee']) ?></td></tr><?php endif; ?>
          <tr><td colspan="4" class="r"><b>Σύνολο</b></td><td class="r num"><b><?= money($o['total']) ?></b></td></tr>
        </tfoot>
      </table></div>
    </section>
    <section class="card">
      <h2>Σημειώσεις</h2>
      <?php if ($o['customer_note']): ?><div class="alert alert-info"><b>Σχόλιο πελάτη:</b> <?= e($o['customer_note']) ?></div><?php endif; ?>
      <?php foreach ($notes as $n): ?><div><span class="muted mono"><?= dt($n['created_at']) ?> · <?= e($n['author']) ?></span><br><?= nl2br(e($n['note'])) ?></div><?php endforeach; ?>
      <?php if (!$notes && !$o['customer_note']): ?><p class="muted" style="margin:0">Καμία σημείωση.</p><?php endif; ?>
    </section>
  </div>
  <aside class="stack" style="gap:16px">
    <section class="card">
      <h2>Πελάτης</h2>
      <dl class="kv">
        <dt>Όνομα</dt><dd><?= e($o['name']) ?></dd><dt>Email</dt><dd><?= e($o['email']) ?></dd><dt>Τηλέφωνο</dt><dd><?= e($o['phone']) ?></dd>
        <dt>Διεύθυνση</dt><dd><?= e($o['street']) ?>, <?= e($o['city']) ?> <?= e($o['postcode']) ?></dd>
        <dt>Παραστατικό</dt><dd><?= $o['invoice_type'] === 'invoice' ? 'Τιμολόγιο: ' . e($o['company_name']) . ' · ΑΦΜ ' . e($o['vat_number']) . ' · ' . e($o['tax_office']) : 'Απόδειξη' ?></dd>
        <dt>Πληρωμή</dt><dd><?= e(Payments::METHODS[$o['payment_method']] ?? $o['payment_method']) ?><?= $o['payment_ref'] ? ' · ' . e($o['payment_ref']) : '' ?></dd>
      </dl>
      <?php if ($o['customer_id']): ?><a href="/admin/customers/<?= (int)$o['customer_id'] ?>">Ιστορικό πελάτη →</a><?php endif; ?>
    </section>
    <form class="card f" method="post"><?= Csrf::field() ?>
      <h2>Ενημέρωση</h2>
      <div class="field"><label for="s">Κατάσταση παραγγελίας</label><select class="in" id="s" name="status"><?php foreach (Orders::STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $o['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="ps">Κατάσταση πληρωμής</label><select class="in" id="ps" name="payment_status"><?php foreach (Payments::STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $o['payment_status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="ss">Αποστολή</label><select class="in" id="ss" name="shipping_status"><?php foreach (Orders::SHIPPING as $k => $l): ?><option value="<?= $k ?>"<?= $o['shipping_status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="tn">Αριθμός αποστολής (voucher)</label><input class="in" id="tn" name="tracking_number" value="<?= e($o['tracking_number']) ?>"></div>
      <div class="field"><label for="nt">Εσωτερική σημείωση</label><textarea class="in" id="nt" name="note" rows="2"></textarea></div>
      <label class="chk"><input type="checkbox" name="notify" value="1"> Ειδοποίηση πελάτη με email για αλλαγή κατάστασης</label>
      <?php if ($o['status'] !== 'cancelled'): ?><label class="chk"><input type="checkbox" name="restock" value="1" checked> Επιστροφή αποθέματος αν ακυρωθεί</label><?php endif; ?>
      <button class="btn">Αποθήκευση</button>
    </form>
  </aside>
</div>
