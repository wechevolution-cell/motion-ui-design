<form class="card f" method="post" style="max-width:480px"><?= Csrf::field() ?>
  <h2>Αλλαγή κωδικού</h2>
  <div class="field"><label for="c">Τρέχων κωδικός</label><input class="in" id="c" name="current" type="password" required autocomplete="current-password"></div>
  <div class="field"><label for="p">Νέος κωδικός</label><input class="in" id="p" name="password" type="password" required minlength="10" autocomplete="new-password"><small>Τουλάχιστον 10 χαρακτήρες, γράμματα και αριθμοί.</small></div>
  <div class="field"><label for="p2">Επανάληψη νέου κωδικού</label><input class="in" id="p2" name="password2" type="password" required autocomplete="new-password"></div>
  <div><button class="btn">Αλλαγή κωδικού</button></div>
</form>
