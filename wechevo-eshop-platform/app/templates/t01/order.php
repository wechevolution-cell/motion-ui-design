<?php $cancelled = input('cancelled') === '1'; ?>
<div class="wrap sec">
  <div class="tile" style="background:var(--accent);padding:clamp(24px,5vw,56px);gap:18px">
    <span class="bdg" style="align-self:start">Παραγγελία #<?= e($o['number']) ?></span>
    <h1 class="kin" style="font-size:clamp(40px,7vw,104px)"><?= $cancelled && $o['payment_status'] !== 'paid' ? 'Η πληρωμή ακυρώθηκε' : 'Ευχαριστούμε!' ?></h1>
    <?php if ($o['payment_method'] === 'card' && $o['payment_status'] === 'paid'): ?>
      <p style="margin:0;max-width:60ch">Η πληρωμή ολοκληρώθηκε και η παραγγελία σας καταχωρήθηκε. Θα λάβετε email επιβεβαίωσης στο <?= e($o['email']) ?>.</p>
    <?php elseif ($o['payment_method'] === 'card'): ?>
      <p style="margin:0;max-width:60ch">Η παραγγελία καταχωρήθηκε αλλά η πληρωμή με κάρτα δεν έχει επιβεβαιωθεί ακόμη. Αν πληρώσατε, η κατάσταση θα ενημερωθεί σύντομα· διαφορετικά θα επικοινωνήσουμε μαζί σας.</p>
    <?php elseif ($o['payment_method'] === 'bank'): ?>
      <p style="margin:0;max-width:60ch">Η παραγγελία καταχωρήθηκε. Ολοκληρώστε την κατάθεση στον παρακάτω λογαριασμό με αιτιολογία <b><?= e($o['number']) ?></b>.</p>
      <pre style="white-space:pre-wrap;background:var(--surface);border-radius:16px;padding:16px;margin:0;font:inherit;font-weight:700"><?= e($payCfg['bank']['details'] ?? '') ?></pre>
    <?php else: ?>
      <p style="margin:0;max-width:60ch">Η παραγγελία σας καταχωρήθηκε. Θα πληρώσετε με αντικαταβολή κατά την παραλαβή. Θα λάβετε email επιβεβαίωσης στο <?= e($o['email']) ?>.</p>
    <?php endif; ?>
  </div>
  <div class="co" style="margin-top:12px">
    <section class="box"><h2>Προϊόντα</h2>
      <?php foreach ($items as $i): ?><div class="ln"><span><?= (int)$i['qty'] ?> × <?= e($i['name']) ?><?= $i['options'] ? ' <small style="opacity:.7">(' . e($i['options']) . ')</small>' : '' ?></span><span><?= money($i['total']) ?></span></div><?php endforeach; ?>
      <div class="ln"><span>Αποστολή</span><span><?= $o['shipping'] > 0 ? money($o['shipping']) : 'Δωρεάν' ?></span></div>
      <?php if ($o['fee'] > 0): ?><div class="ln"><span>Επιβάρυνση πληρωμής</span><span><?= money($o['fee']) ?></span></div><?php endif; ?>
      <div class="ln tot"><span>Σύνολο</span><span><?= money($o['total']) ?></span></div>
    </section>
    <section class="box"><h2>Αποστολή σε</h2>
      <p style="margin:0"><?= e($o['name']) ?><br><?= e($o['street']) ?><br><?= e($o['city']) ?> <?= e($o['postcode']) ?><br><?= e($o['phone']) ?></p>
      <p style="margin:0">Κατάσταση: <b><?= e(Orders::STATUS[$o['status']] ?? $o['status']) ?></b> · Πληρωμή: <b><?= e(Payments::STATUS[$o['payment_status']] ?? $o['payment_status']) ?></b></p>
      <a class="btn big" href="/products/">Συνέχεια αγορών</a>
    </section>
  </div>
</div>
