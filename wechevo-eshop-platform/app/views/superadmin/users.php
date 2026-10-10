<div class="grid g2">
<div class="table-wrap"><table class="t">
  <thead><tr><th>Email</th><th>Όνομα</th><th>Τελευταία σύνδεση</th><th>Κατάσταση</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr><td><?= e($u['email']) ?></td><td><?= e($u['name']) ?></td><td><?= dt($u['last_login_at']) ?></td><td><?= status_badge($u['status']) ?></td>
      <td class="r"><?php if ((int)$u['id'] !== (int)$me['id']): ?><form method="post"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <button class="btn btn-ghost btn-sm" name="op" value="<?= $u['status'] === 'active' ? 'disable' : 'enable' ?>"><?= $u['status'] === 'active' ? 'Απενεργοποίηση' : 'Ενεργοποίηση' ?></button></form><?php endif; ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<form class="card f" method="post"><?= Csrf::field() ?><input type="hidden" name="op" value="create">
  <h2>Νέος Super Admin</h2>
  <div class="field"><label for="un">Όνομα</label><input class="in" id="un" name="name"></div>
  <div class="field"><label for="ue">Email</label><input class="in" id="ue" name="email" type="email" required></div>
  <div class="field"><label for="up">Κωδικός</label><input class="in" id="up" name="password" type="password" required minlength="10" autocomplete="new-password"></div>
  <div><button class="btn">Δημιουργία</button></div>
</form>
</div>
