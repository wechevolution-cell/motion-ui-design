<div class="grid g2" style="align-items:start">
<form class="card f" method="post"><?= Csrf::field() ?>
  <h2>Αρχική σελίδα &amp; εργαλεία</h2>
  <div class="field"><label for="ht">SEO τίτλος αρχικής</label><input class="in" id="ht" name="home_title" maxlength="70" value="<?= e($s['home_title'] ?? '') ?>"><small>Έως 60–70 χαρακτήρες. Π.χ. «Χειροποίητα κεραμικά | Όνομα καταστήματος».</small></div>
  <div class="field"><label for="hd">Meta description αρχικής</label><textarea class="in" id="hd" name="home_description" rows="3" maxlength="170"><?= e($s['home_description'] ?? '') ?></textarea><small>Μία σαφής πρόταση: τι πουλάτε, σε ποιον, πού. Χρησιμοποιείται και στο llms.txt.</small></div>
  <div class="field"><label for="ga">Google Analytics 4 (Measurement ID)</label><input class="in" id="ga" name="ga4_id" value="<?= e($s['ga4_id'] ?? '') ?>" placeholder="G-XXXXXXXXXX"><small>Φορτώνεται μόνο αφού ο επισκέπτης αποδεχτεί τα cookies στατιστικών.</small></div>
  <div class="field"><label for="gsc">Google Search Console (κωδικός επαλήθευσης meta)</label><input class="in" id="gsc" name="gsc_verification" value="<?= e($s['gsc_verification'] ?? '') ?>"></div>
  <div class="field"><label for="sa">Προφίλ επιχείρησης (sameAs, ένα URL ανά γραμμή)</label><textarea class="in" id="sa" name="same_as" rows="3" placeholder="https://www.google.com/maps/…"><?= e(implode("\n", $s['same_as'] ?? [])) ?></textarea></div>
  <div class="field"><label for="ll">Επιπλέον κείμενο για llms.txt</label><textarea class="in" id="ll" name="llms_extra" rows="4"><?= e($s['llms_extra'] ?? '') ?></textarea><small>Σύντομα πραγματικά στοιχεία για AI search (π.χ. περιοχές αποστολής, χρόνοι παράδοσης).</small></div>
  <div><button class="btn">Αποθήκευση</button></div>
</form>
<div class="stack" style="gap:16px">
  <section class="card">
    <h2>Αρχεία για μηχανές αναζήτησης &amp; AI</h2>
    <ul class="checklist">
      <?php foreach (['/sitemap.xml' => 'Sitemap (με εικόνες)', '/robots.txt' => 'robots.txt (επιτρέπει GPTBot, ClaudeBot, PerplexityBot, Google-Extended)', '/llms.txt' => 'llms.txt'] as $u => $l): ?>
        <li class="done"><span class="tick" aria-hidden="true">✓</span><a href="<?= e($base . $u) ?>" target="_blank" rel="noopener"><?= e($l) ?></a></li>
      <?php endforeach; ?>
      <li class="<?= !empty($s['gsc_verification']) ? 'done' : '' ?>"><span class="tick" aria-hidden="true"><?= !empty($s['gsc_verification']) ? '✓' : '' ?></span>Search Console <?= empty($s['gsc_verification']) ? '— δεν έχει οριστεί' : '' ?></li>
      <li class="<?= !empty($s['ga4_id']) ? 'done' : '' ?>"><span class="tick" aria-hidden="true"><?= !empty($s['ga4_id']) ? '✓' : '' ?></span>GA4 <?= empty($s['ga4_id']) ? '— δεν έχει οριστεί' : '' ?></li>
      <li class="<?= !empty($s['same_as']) ? 'done' : '' ?>"><span class="tick" aria-hidden="true"><?= !empty($s['same_as']) ? '✓' : '' ?></span>sameAs / προφίλ επιχείρησης</li>
    </ul>
    <p class="hint" style="margin:0">Κάθε προϊόν δημοσιεύει δομημένα δεδομένα Product &amp; Offer (τιμή, διαθεσιμότητα, SKU, μάρκα, GTIN). Δεν δημιουργούνται ποτέ ψεύτικες βαθμολογίες.</p>
  </section>
  <section class="card">
    <h2>Έλεγχος καταλόγου (ενεργά προϊόντα)</h2>
    <div class="table-wrap"><table class="t"><tbody>
      <tr><td>Χωρίς περιγραφή / meta description</td><td class="r num"><?= $report['no_seo_desc'] ?></td></tr>
      <tr><td>Χωρίς εικόνα</td><td class="r num"><a href="/admin/products?status=active&amp;missing=image"><?= $report['no_image'] ?></a></td></tr>
      <tr><td>Χωρίς κατηγορία</td><td class="r num"><?= $report['no_category'] ?></td></tr>
      <tr><td>Χωρίς μάρκα (απαιτείται από Skroutz)</td><td class="r num"><?= $report['no_brand'] ?></td></tr>
      <tr><td>Χωρίς EAN/barcode</td><td class="r num"><?= $report['no_ean'] ?></td></tr>
      <tr><td>Κατηγορίες χωρίς περιγραφή</td><td class="r num"><?= $report['no_desc_cats'] ?></td></tr>
    </tbody></table></div>
    <?php if ($report['dup_titles']): ?><h3>Ίδιοι τίτλοι προϊόντων (ανταγωνίζονται μεταξύ τους)</h3><ul><?php foreach ($report['dup_titles'] as $d): ?><li><?= e($d['name']) ?> × <?= (int)$d['n'] ?></li><?php endforeach; ?></ul><?php endif; ?>
  </section>
</div>
</div>
