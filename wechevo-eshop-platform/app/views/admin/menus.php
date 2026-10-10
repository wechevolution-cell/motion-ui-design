<p class="muted" style="margin:0">Αν το κύριο μενού είναι κενό, το κατάστημα δείχνει αυτόματα τις κατηγορίες πρώτου επιπέδου.</p>
<div class="grid g2">
<?php foreach ($menus as $m): $its = $items[$m['id']] ?? []; ?>
  <section class="card">
    <h2><?= e($m['name']) ?></h2>
    <div class="table-wrap"><table class="t"><tbody>
      <?php foreach ($its as $it): ?>
        <tr><td style="padding-left:<?= $it['parent_id'] ? 32 : 12 ?>px"><?= $it['parent_id'] ? '↳ ' : '' ?><b><?= e($it['label']) ?></b> <span class="muted">(<?= e(['category' => 'κατηγορία', 'page' => 'σελίδα', 'product' => 'προϊόν', 'url' => 'URL'][$it['type']] ?? $it['type']) ?>)</span></td>
          <td class="r"><form class="row" method="post" style="justify-content:flex-end"><?= Csrf::field() ?><input type="hidden" name="menu_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
            <button class="btn btn-ghost btn-sm" name="op" value="move" onclick="this.form.dir.value='up'" aria-label="Πάνω">↑</button><button class="btn btn-ghost btn-sm" name="op" value="move" onclick="this.form.dir.value='down'" aria-label="Κάτω">↓</button><input type="hidden" name="dir" value="">
            <button class="btn btn-ghost btn-sm" name="op" value="delete">Αφαίρεση</button></form></td></tr>
      <?php endforeach; if (!$its): ?><tr><td class="muted">Κενό μενού.</td></tr><?php endif; ?>
    </tbody></table></div>
    <form class="f" method="post"><?= Csrf::field() ?><input type="hidden" name="menu_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="op" value="add">
      <div class="grid g2">
        <div class="field"><label for="ty<?= $m['id'] ?>">Τύπος</label><select class="in" id="ty<?= $m['id'] ?>" name="type"><option value="category">Κατηγορία</option><option value="page">Σελίδα</option><option value="url">Εξωτερικό URL</option></select></div>
        <div class="field"><label for="lb<?= $m['id'] ?>">Ετικέτα</label><input class="in" id="lb<?= $m['id'] ?>" name="label" placeholder="αυτόματο"></div>
        <div class="field"><label for="rc<?= $m['id'] ?>">Κατηγορία / σελίδα</label><select class="in" id="rc<?= $m['id'] ?>" name="ref_id"><option value="">—</option>
          <optgroup label="Κατηγορίες"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= str_repeat('— ', $c['depth']) . e($c['name']) ?></option><?php endforeach; ?></optgroup>
          <optgroup label="Σελίδες (επιλέξτε τύπο «Σελίδα»)"><?php foreach ($pages as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach; ?></optgroup></select></div>
        <div class="field"><label for="ur<?= $m['id'] ?>">URL</label><input class="in" id="ur<?= $m['id'] ?>" name="url" placeholder="https://…"></div>
        <div class="field"><label for="pa<?= $m['id'] ?>">Κάτω από</label><select class="in" id="pa<?= $m['id'] ?>" name="parent_id"><option value="">— κορυφή —</option><?php foreach ($its as $it): if ($it['parent_id']) continue; ?><option value="<?= (int)$it['id'] ?>"><?= e($it['label']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div><button class="btn btn-ghost">Προσθήκη</button></div>
    </form>
  </section>
<?php endforeach; ?>
</div>
