<form class="card f" method="post" style="max-width:480px"><?= Csrf::field() ?>
  <h2>Αλλαγή κωδικού</h2>
  <div class="field"><label for="c">Τρέχων κωδικός</label><input class="in" id="c" name="current" type="password" required autocomplete="current-password"></div>
  <div class="field"><label for="p">Νέος κωδικός</label><input class="in" id="p" name="password" type="password" required minlength="10" autocomplete="new-password"><small>Τουλάχιστον 10 χαρακτήρες, γράμματα και αριθμοί.</small></div>
  <div><button class="btn">Αλλαγή</button></div>
</form>
