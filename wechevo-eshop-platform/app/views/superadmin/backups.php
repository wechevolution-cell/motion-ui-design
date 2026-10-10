<div class="card">
  <p style="margin:0">Αντίγραφα SQLite (VACUUM INTO) αποθηκεύονται στο <span class="mono">data/backups/</span>. Αυτόματα αντίγραφα δημιουργούνται πριν από υποβάθμιση πακέτου, διαγραφή καταστήματος και εισαγωγές. Για τα αρχεία εικόνων κάντε επιπλέον λήψη του φακέλου <span class="mono">uploads/</span> από το File Manager του Hostinger.</p>
  <form class="row" method="post"><?= Csrf::field() ?><button class="btn" name="op" value="all">Αντίγραφο όλων τώρα</button><button class="btn btn-ghost" name="op" value="platform">Μόνο πλατφόρμα</button></form>
</div>
<div class="table-wrap"><table class="t">
  <thead><tr><th>Πεδίο</th><th>Αρχείο</th><th class="r">Μέγεθος</th><th>Πότε</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($list as $b): ?>
    <tr><td class="mono"><?= e($b['scope']) ?></td><td class="mono"><?= e($b['file']) ?></td><td class="r num"><?= number_format($b['size'] / 1024, 0, ',', '.') ?> KB</td><td><?= dt(gmdate('Y-m-d H:i:s', $b['time'])) ?></td>
      <td class="r"><a class="btn btn-ghost btn-sm" href="/superadmin/backups/download?scope=<?= urlencode($b['scope']) ?>&amp;file=<?= urlencode($b['file']) ?>">Λήψη</a></td></tr>
  <?php endforeach; if (!$list): ?><tr><td colspan="5" class="muted">Δεν υπάρχουν αντίγραφα ακόμη.</td></tr><?php endif; ?>
  </tbody>
</table></div>
