<?php $o = fn($k) => e(old($k)); $err = fn($k) => isset($errors[$k]) ? '<span class="err" id="e-' . $k . '">' . e($errors[$k]) . '</span>' : ''; ?>
<div class="wrap sec">
  <div class="co" style="padding-top:0">
    <article class="prose" style="max-width:none">
      <h1 class="kin" style="font-size:clamp(34px,5vw,64px);margin-bottom:18px"><?= e($pg['title']) ?></h1>
      <?= $pg['body'] ?>
      <dl style="display:grid;grid-template-columns:max-content 1fr;gap:6px 16px;margin:16px 0 0">
        <?php if (!empty($company['phone'])): ?><dt>Τηλέφωνο</dt><dd style="margin:0"><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $company['phone'])) ?>"><?= e($company['phone']) ?></a></dd><?php endif; ?>
        <?php if (!empty($company['email'])): ?><dt>Email</dt><dd style="margin:0"><a href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a></dd><?php endif; ?>
        <?php if (!empty($company['address'])): ?><dt>Διεύθυνση</dt><dd style="margin:0"><?= e($company['address']) ?></dd><?php endif; ?>
        <?php if (!empty($company['hours'])): ?><dt>Ωράριο</dt><dd style="margin:0"><?= e($company['hours']) ?></dd><?php endif; ?>
      </dl>
    </article>
    <form class="box" method="post" action="/contact/" novalidate>
      <?= t01_form_guard() ?>
      <h2>Στείλτε μας μήνυμα</h2>
      <?php if (!empty($errors['_'])): ?><div class="alert alert-error" role="alert"><?= e($errors['_']) ?></div><?php endif; ?>
      <div class="fi"><label for="c-name">Όνομα</label><input id="c-name" name="name" required autocomplete="name" value="<?= $o('name') ?>"<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="e-name"' : '' ?>><?= $err('name') ?></div>
      <div class="fi"><label for="c-email">Email</label><input id="c-email" name="email" type="email" required autocomplete="email" value="<?= $o('email') ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="e-email"' : '' ?>><?= $err('email') ?></div>
      <div class="fi"><label for="c-phone">Τηλέφωνο (προαιρετικά)</label><input id="c-phone" name="phone" type="tel" autocomplete="tel" value="<?= $o('phone') ?>"></div>
      <div class="fi"><label for="c-msg">Μήνυμα</label><textarea id="c-msg" name="message" required rows="5"<?= isset($errors['message']) ? ' aria-invalid="true" aria-describedby="e-message"' : '' ?>><?= $o('message') ?></textarea><?= $err('message') ?></div>
      <label class="consent"><input type="checkbox" name="consent" value="1" required> <span>Συμφωνώ να χρησιμοποιηθούν τα στοιχεία μου για να απαντήσετε στο μήνυμά μου (<a href="/privacy/">πολιτική απορρήτου</a>).</span></label><?= $err('consent') ?>
      <button class="btn big primary">Αποστολή</button>
    </form>
  </div>
</div>
