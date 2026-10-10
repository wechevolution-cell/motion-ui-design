<div class="grid g2" style="align-items:start">
  <section class="card">
    <h2>Εισαγωγή προϊόντων (CSV ή XML)</h2>
    <?php if (!$canImport): ?><div class="alert alert-warning">Η εισαγωγή δεν περιλαμβάνεται στο πακέτο σας.</div><?php else: ?>
    <ol class="steps"><li class="on">Ανέβασμα</li><li>Έλεγχος</li><li>Προεπισκόπηση</li><li>Επιβεβαίωση</li><li>Επεξεργασία</li><li>Αναφορά</li></ol>
    <form class="f" method="post" enctype="multipart/form-data"><?= Csrf::field() ?>
      <div class="field"><label for="file">Αρχείο</label><input class="in" id="file" type="file" name="file" accept=".csv,.xml,.txt,text/csv,text/xml,application/xml" required>
        <small>CSV (κόμμα, ερωτηματικό ή tab, UTF-8 ή Windows-1253) ή XML (στοιχεία &lt;product&gt; ή &lt;item&gt;, π.χ. μορφή Skroutz). Έως 10.000+ γραμμές, έως 50 MB.</small></div>
      <div><button class="btn">Ανέβασμα και έλεγχος</button></div>
    </form>
    <details><summary>Πρότυπο CSV</summary><pre class="mono" style="white-space:pre-wrap;background:var(--line2);padding:10px;border-radius:8px">sku,name,price,sale_price,stock,category,brand,barcode,short_description,image,status
ABC-1,Κούπα κεραμική,14.00,,25,Κεραμικά > Κούπες,Brand,5200000000001,Χειροποίητη κούπα,https://…/kupa.jpg,1</pre></details>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2>Εξαγωγή προϊόντων (CSV)</h2>
    <?php if (!$canExport): ?><div class="alert alert-warning">Η εξαγωγή δεν περιλαμβάνεται στο πακέτο σας.</div><?php else: ?>
    <form class="f" method="get" action="/admin/export">
      <fieldset><legend>Ποια προϊόντα</legend>
        <label class="chk"><input type="radio" name="scope" value="all" checked> Όλα</label>
        <label class="chk"><input type="radio" name="scope" value="active"> Μόνο ενεργά</label>
        <label class="chk"><input type="radio" name="scope" value="inactive"> Μόνο ανενεργά</label>
        <label class="chk"><input type="radio" name="scope" value="category"> Ανά κατηγορία:</label>
        <select class="in" name="cat" aria-label="Κατηγορία εξαγωγής"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= str_repeat('— ', $c['depth']) . e($c['name']) ?></option><?php endforeach; ?></select>
      </fieldset>
      <p class="hint" style="margin:0">Για επιλεγμένα προϊόντα: λίστα προϊόντων → επιλογή → «Εξαγωγή CSV».</p>
      <div><button class="btn btn-ghost">Λήψη CSV</button></div>
    </form>
    <?php endif; ?>
  </section>
</div>
<section class="stack">
  <h2>Ιστορικό εισαγωγών</h2>
  <div class="table-wrap"><table class="t">
    <thead><tr><th>#</th><th>Αρχείο</th><th>Ημερομηνία</th><th>Κατάσταση</th><th class="r">Γραμμές</th><th class="r">Νέα</th><th class="r">Ενημ.</th><th class="r">Σφάλματα</th><th class="r">Ενεργοπ.</th><th>Χρήστης</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?><tr><td><a href="/admin/import/<?= (int)$h['id'] ?>">#<?= (int)$h['id'] ?></a></td><td><?= e($h['filename']) ?> <span class="muted">(<?= e(strtoupper($h['format'])) ?>)</span></td><td><?= dt($h['created_at']) ?></td><td><?= status_badge($h['status']) ?></td>
      <td class="r num"><?= (int)$h['total'] ?></td><td class="r num"><?= (int)$h['created'] ?></td><td class="r num"><?= (int)$h['updated'] ?></td><td class="r num"><?= (int)$h['errors'] ?></td><td class="r num"><?= (int)$h['activated'] ?></td><td class="muted"><?= e($h['user_label']) ?></td></tr><?php endforeach; ?>
    <?php if (!$history): ?><tr><td colspan="10" class="muted">Καμία εισαγωγή ακόμη.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
