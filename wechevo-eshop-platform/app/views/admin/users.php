<p class="muted" style="margin:0">Όριο πακέτου: <b><?= (int)$shop['staff_limit'] ?></b> χρήστες. Οι ρόλοι δίνουν έτοιμα δικαιώματα· με «Προσαρμοσμένος» επιλέγετε ενότητες. Δεν μπορείτε να δώσετε δικαιώματα που δεν έχετε.</p>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Χρήστης</th><th>Ρόλος &amp; δικαιώματα</th><th>Τελευταία σύνδεση</th><th>Κατάσταση</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): $perms = json_decode($u['permissions'] ?: '[]', true) ?: []; $self = (int)$u['id'] === (int)$me['id']; ?>
    <tr><td><b><?= e($u['name'] ?: '—') ?></b><br><?= e($u['email']) ?><?= $self ? ' <span class="badge b-info">εσείς</span>' : '' ?></td>
      <td><?php if ($self || ($u['role'] === 'owner' && $me['role'] !== 'owner')): ?><?= e(Perm::ROLES[$u['role']] ?? $u['role']) ?><?php else: ?>
        <form class="stack" method="post"><?= Csrf::field() ?><input type="hidden" name="op" value="update"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <select class="in" name="role" aria-label="Ρόλος"><?php foreach (Perm::ROLES as $k => $l): if ($k === 'owner' && $me['role'] !== 'owner') continue; ?><option value="<?= $k ?>"<?= $u['role'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
          <details><summary class="hint">Ενότητες (για «Προσαρμοσμένος»)</summary><div class="grid g2"><?php foreach (Perm::MODULES as $k => $l): ?><label class="chk"><input type="checkbox" name="perms[]" value="<?= $k ?>" <?= in_array($k, $perms, true) ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?></div></details>
          <div><button class="btn btn-ghost btn-sm">Αποθήκευση</button></div></form><?php endif; ?></td>
      <td><?= dt($u['last_login_at']) ?></td><td><?= status_badge($u['status']) ?></td>
      <td class="r"><?php if (!$self && !($u['role'] === 'owner' && $me['role'] !== 'owner')): ?><form class="row" method="post" style="justify-content:flex-end"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <button class="btn btn-ghost btn-sm" name="op" value="<?= $u['status'] === 'active' ? 'disable' : 'enable' ?>"><?= $u['status'] === 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?></button>
        <button class="btn btn-ghost btn-sm" name="op" value="delete">Διαγραφή</button></form><?php endif; ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($canStaff): ?>
<form class="card f" method="post" style="max-width:760px"><?= Csrf::field() ?><input type="hidden" name="op" value="create">
  <h2>Νέος χρήστης</h2>
  <div class="grid g2">
    <div class="field"><label for="un">Όνομα</label><input class="in" id="un" name="name"></div>
    <div class="field"><label for="ue">Email</label><input class="in" id="ue" name="email" type="email" required></div>
    <div class="field"><label for="up">Προσωρινός κωδικός</label><input class="in" id="up" name="password" type="password" required minlength="10" autocomplete="new-password"></div>
    <div class="field"><label for="ur">Ρόλος</label><select class="in" id="ur" name="role"><?php foreach (Perm::ROLES as $k => $l): if ($k === 'owner' && $me['role'] !== 'owner') continue; ?><option value="<?= $k ?>"<?= $k === 'editor' ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  </div>
  <details><summary>Ενότητες (για «Προσαρμοσμένος»)</summary><div class="grid g3"><?php foreach (Perm::MODULES as $k => $l): ?><label class="chk"><input type="checkbox" name="perms[]" value="<?= $k ?>"> <?= e($l) ?></label><?php endforeach; ?></div></details>
  <div><button class="btn">Δημιουργία</button></div>
</form>
<?php else: ?><div class="alert alert-info">Οι πολλαπλοί χρήστες δεν περιλαμβάνονται στο πακέτο σας.</div><?php endif; ?>
