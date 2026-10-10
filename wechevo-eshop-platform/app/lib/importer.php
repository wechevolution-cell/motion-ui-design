<?php
/* Bulk import: Upload → Validate → Preview → Confirm → Queue → Process (batches) → Report.
   Works on files up to 10,000+ rows without long requests: each batch call processes a slice and stores a cursor. */

final class Importer
{
    public const FIELDS = [
        'sku' => 'Κωδικός (SKU)', 'name' => 'Όνομα', 'price' => 'Τιμή', 'sale_price' => 'Τιμή προσφοράς', 'cost_price' => 'Τιμή κόστους',
        'stock' => 'Απόθεμα', 'category' => 'Κατηγορία (π.χ. Κεραμικά > Βάζα)', 'brand' => 'Μάρκα', 'barcode' => 'Barcode / EAN', 'mpn' => 'MPN',
        'short_description' => 'Σύντομη περιγραφή', 'description' => 'Περιγραφή', 'weight' => 'Βάρος (kg)', 'image' => 'Εικόνα (URL)',
        'images' => 'Επιπλέον εικόνες (URLs με |)', 'status' => 'Ενεργό (1/0, ναι/όχι)', 'seo_title' => 'SEO τίτλος', 'seo_description' => 'SEO περιγραφή',
    ];
    private const GUESS = [
        'sku' => ['sku', 'κωδικός', 'κωδικος', 'code', 'product_code', 'id_sku', 'κωδ'],
        'name' => ['name', 'όνομα', 'ονομα', 'title', 'τίτλος', 'τιτλος', 'product_name'],
        'price' => ['price', 'τιμή', 'τιμη', 'price_with_vat', 'regular_price', 'λιανική'],
        'sale_price' => ['sale_price', 'special_price', 'τιμή προσφοράς', 'τιμη προσφορας', 'προσφορά'],
        'cost_price' => ['cost_price', 'cost', 'κόστος', 'τιμή κόστους'],
        'stock' => ['stock', 'quantity', 'qty', 'απόθεμα', 'αποθεμα', 'ποσότητα'],
        'category' => ['category', 'κατηγορία', 'κατηγορια', 'category_path', 'categories'],
        'brand' => ['brand', 'manufacturer', 'μάρκα', 'μαρκα', 'κατασκευαστής'],
        'barcode' => ['barcode', 'ean', 'gtin', 'upc'],
        'mpn' => ['mpn'],
        'short_description' => ['short_description', 'σύντομη περιγραφή', 'excerpt'],
        'description' => ['description', 'περιγραφή', 'περιγραφη', 'long_description'],
        'weight' => ['weight', 'βάρος', 'βαρος'],
        'image' => ['image', 'image_url', 'imageurl', 'εικόνα', 'εικονα', 'main_image'],
        'images' => ['images', 'additional_imageurl', 'gallery', 'additional_images'],
        'status' => ['status', 'active', 'ενεργό', 'ενεργο', 'κατάσταση'],
        'seo_title' => ['seo_title', 'meta_title'],
        'seo_description' => ['seo_description', 'meta_description'],
    ];

    public static function dir(int $shopId): string
    {
        $d = DATA . '/imports/' . $shopId;
        if (!is_dir($d)) {
            mkdir($d, 0775, true);
        }
        return $d;
    }

    /** Step 1: store upload, detect format/headers, count rows, guess mapping. */
    public static function upload(PDO $db, int $shopId, array $file, string $user): int
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0)) {
            throw new DomainException('Το αρχείο ξεπερνά το όριο μεταφόρτωσης του server (upload_max_filesize = ' . ini_get('upload_max_filesize') . ', post_max_size = ' . ini_get('post_max_size') . '). Αυξήστε το από το hPanel → PHP Configuration ή χωρίστε το αρχείο.');
        }
        if ($err === UPLOAD_ERR_NO_FILE) {
            throw new DomainException('Επιλέξτε αρχείο CSV ή XML.');
        }
        if ($err !== UPLOAD_ERR_OK) {
            throw new DomainException('Η μεταφόρτωση απέτυχε (κωδικός ' . (int)$err . '). Δοκιμάστε ξανά.');
        }
        if ($file['size'] > 50 * 1024 * 1024) {
            throw new DomainException('Το αρχείο ξεπερνά τα 50 MB.');
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xml', 'txt'], true)) {
            throw new DomainException('Υποστηρίζονται αρχεία CSV ή XML.');
        }
        $format = $ext === 'xml' ? 'xml' : 'csv';
        $head = file_get_contents($file['tmp_name'], false, null, 0, 4096);
        if ($format === 'csv' && preg_match('/<\?xml|<products|<mywebstore/i', $head)) {
            $format = 'xml';
        }
        $id = insert($db, 'imports', ['format' => $format, 'filename' => Sanitizer::text($file['name'], 200), 'stored_path' => '', 'status' => 'uploaded', 'user_label' => $user, 'created_at' => now()]);
        $path = self::dir($shopId) . '/' . $id . '.' . $format;
        if (!move_uploaded_file($file['tmp_name'], $path) && !copy($file['tmp_name'], $path)) {
            throw new DomainException('Δεν ήταν δυνατή η αποθήκευση του αρχείου.');
        }
        update($db, 'imports', ['stored_path' => $path], 'id = ?', [$id]);
        self::analyse($db, $id);
        return $id;
    }

    /** Step 2: validate whole file quickly, produce summary + preview. */
    public static function analyse(PDO $db, int $id): array
    {
        $imp = row($db, 'SELECT * FROM imports WHERE id = ?', [$id]);
        $headers = [];
        $total = 0;
        $preview = [];
        foreach (self::iterate($imp, 0) as [$i, $rec]) {
            if (!$headers) {
                $headers = array_keys($rec);
            }
            foreach (array_keys($rec) as $k) {
                if (!in_array($k, $headers, true)) {
                    $headers[] = $k;
                }
            }
            if ($total < 8) {
                $preview[] = $rec;
            }
            $total++;
        }
        $mapping = json_decode($imp['mapping'] ?: '{}', true) ?: [];
        if (!$mapping) {
            foreach ($headers as $h) {
                $hf = fold(trim($h));
                foreach (self::GUESS as $field => $names) {
                    if (in_array($hf, array_map('fold', $names), true) && !in_array($field, $mapping, true)) {
                        $mapping[$h] = $field;
                        break;
                    }
                }
            }
        }
        update($db, 'imports', ['total' => $total, 'mapping' => json_encode($mapping, JSON_UNESCAPED_UNICODE), 'status' => $total ? 'validated' : 'failed',
            'options' => json_encode(['headers' => $headers, 'preview' => $preview], JSON_UNESCAPED_UNICODE)], 'id = ?', [$id]);
        return ['total' => $total, 'headers' => $headers, 'mapping' => $mapping, 'preview' => $preview];
    }

    /** Dry-run checks over the mapped file: missing names/prices, duplicate SKUs, existing SKUs. */
    public static function check(PDO $db, array $imp): array
    {
        $map = json_decode($imp['mapping'], true) ?: [];
        $seen = [];
        $out = ['rows' => 0, 'new' => 0, 'existing' => 0, 'dup_in_file' => 0, 'invalid' => 0, 'messages' => []];
        $existing = [];
        foreach (rows($db, "SELECT sku FROM products WHERE deleted_at IS NULL AND sku IS NOT NULL AND sku <> ''") as $r) {
            $existing[mb_strtolower($r['sku'])] = true;
        }
        foreach (self::iterate($imp, 0) as [$i, $rec]) {
            $d = self::mapRow($rec, $map);
            $out['rows']++;
            $err = self::rowError($d);
            if ($err) {
                $out['invalid']++;
                if (count($out['messages']) < 20) $out['messages'][] = 'Γραμμή ' . ($i + 1) . ': ' . $err;
                continue;
            }
            $sku = mb_strtolower($d['sku'] ?? '');
            if ($sku !== '' && isset($seen[$sku])) {
                $out['dup_in_file']++;
                if (count($out['messages']) < 20) $out['messages'][] = 'Γραμμή ' . ($i + 1) . ": διπλός κωδικός {$d['sku']} στο αρχείο (θα αγνοηθεί).";
                continue;
            }
            $seen[$sku] = true;
            $sku !== '' && isset($existing[$sku]) ? $out['existing']++ : $out['new']++;
        }
        return $out;
    }

    public static function confirm(PDO $db, int $id, array $mapping, array $opts): void
    {
        $clean = [];
        foreach ($mapping as $col => $field) {
            if (isset(self::FIELDS[$field])) {
                $clean[(string)$col] = $field;
            }
        }
        if (!in_array('name', $clean, true) && !in_array('sku', $clean, true)) {
            throw new DomainException('Αντιστοιχίστε τουλάχιστον τη στήλη «Όνομα» ή «Κωδικός».');
        }
        $imp = row($db, 'SELECT * FROM imports WHERE id = ?', [$id]);
        $o = json_decode($imp['options'], true) ?: [];
        $o['update_existing'] = !empty($opts['update_existing']);
        $o['create_new'] = !empty($opts['create_new']);
        $o['activation'] = in_array($opts['activation'] ?? '', ['keep', 'up_to_quota', 'from_file'], true) ? $opts['activation'] : 'keep';
        $o['seen'] = [];
        update($db, 'imports', ['mapping' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'options' => json_encode($o, JSON_UNESCAPED_UNICODE), 'status' => 'queued', 'processed' => 0, 'cursor' => null], 'id = ?', [$id]);
    }

    /**
     * Process up to $limit rows from the cursor. Safe to call repeatedly (AJAX or cron).
     * $activeLimit / $storedLimit come from the platform plan.
     */
    public static function batch(PDO $db, int $id, int $limit, int $activeLimit, int $storedLimit): array
    {
        $imp = row($db, 'SELECT * FROM imports WHERE id = ?', [$id]);
        if (!$imp || !in_array($imp['status'], ['queued', 'processing'], true)) {
            return self::progress($imp);
        }
        $map = json_decode($imp['mapping'], true) ?: [];
        $o = json_decode($imp['options'], true) ?: [];
        $seen = array_flip($o['seen'] ?? []);
        $start = (int)$imp['processed'];
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'activated' => 0];
        $db->exec('BEGIN IMMEDIATE');
        try {
            $active = Catalog::activeCount($db);
            $stored = Catalog::storedCount($db);
            $n = 0;
            foreach (self::iterate($imp, $start) as [$i, $rec]) {
                if ($n >= $limit) {
                    break;
                }
                $n++;
                $d = self::mapRow($rec, $map);
                $rowNo = $i + 1;
                if ($err = self::rowError($d)) {
                    $stats['errors']++;
                    insert($db, 'import_rows', ['import_id' => $id, 'row_no' => $rowNo, 'sku' => $d['sku'] ?? null, 'status' => 'error', 'message' => $err]);
                    continue;
                }
                $skuKey = mb_strtolower($d['sku'] ?? '');
                if ($skuKey !== '' && isset($seen[$skuKey])) {
                    $stats['skipped']++;
                    insert($db, 'import_rows', ['import_id' => $id, 'row_no' => $rowNo, 'sku' => $d['sku'], 'status' => 'duplicate', 'message' => 'Διπλός κωδικός στο αρχείο']);
                    continue;
                }
                if ($skuKey !== '') {
                    $seen[$skuKey] = 1;
                }
                $existing = $skuKey !== '' ? row($db, 'SELECT * FROM products WHERE sku = ? COLLATE NOCASE AND deleted_at IS NULL', [$d['sku']]) : null;
                if (!$existing && empty($d['sku']) && !empty($d['name'])) {
                    $existing = row($db, 'SELECT * FROM products WHERE slug = ? AND deleted_at IS NULL', [slugify($d['name'])]);
                }
                $fields = self::productFields($db, $d);
                if ($existing) {
                    if (empty($o['update_existing'])) {
                        $stats['skipped']++;
                        continue;
                    }
                    $fields['updated_at'] = now();
                    update($db, 'products', $fields, 'id = ?', [$existing['id']]);
                    $pid = (int)$existing['id'];
                    $stats['updated']++;
                    $wasActive = $existing['status'] === 'active';
                } else {
                    if (empty($o['create_new'])) {
                        $stats['skipped']++;
                        continue;
                    }
                    if ($stored >= $storedLimit) {
                        $stats['errors']++;
                        insert($db, 'import_rows', ['import_id' => $id, 'row_no' => $rowNo, 'sku' => $d['sku'] ?? null, 'status' => 'error', 'message' => "Συμπληρώθηκε το όριο αποθήκευσης ($storedLimit προϊόντα)."]);
                        continue;
                    }
                    $fields += ['name' => $d['name'] ?? $d['sku'], 'price' => 0];
                    $fields['slug'] = Catalog::uniqueSlug($db, $fields['name']);
                    $fields['status'] = 'inactive';
                    $fields['created_at'] = now();
                    $pid = insert($db, 'products', $fields);
                    $stored++;
                    $stats['created']++;
                    $wasActive = false;
                }
                if (!empty($fields['category_id'])) {
                    Catalog::syncCategories($db, $pid, [], (int)$fields['category_id']);
                }
                if (!empty($d['image']) || !empty($d['images'])) {
                    $urls = array_filter(array_map('trim', array_merge([$d['image'] ?? ''], preg_split('/[|,\s]+/', $d['images'] ?? ''))), fn($u) => preg_match('#^https?://#i', $u));
                    if ($urls) {
                        q($db, 'DELETE FROM product_images WHERE product_id = ? AND media_id IS NULL', [$pid]);
                        foreach (array_values(array_unique($urls)) as $k => $u) {
                            insert($db, 'product_images', ['product_id' => $pid, 'url' => mb_substr($u, 0, 1000), 'sort' => $k + 100]);
                        }
                    }
                }
                // activation policy — always bounded by the plan's active quota
                $wantActive = match ($o['activation']) {
                    'up_to_quota' => true,
                    'from_file' => isset($d['status']) && self::truthy($d['status']),
                    default => false,
                };
                $wantInactive = $o['activation'] === 'from_file' && isset($d['status']) && !self::truthy($d['status']);
                if ($wantActive && !$wasActive) {
                    if ($active < $activeLimit) {
                        q($db, "UPDATE products SET status = 'active', activated_at = ? WHERE id = ?", [now(), $pid]);
                        $active++;
                        $stats['activated']++;
                    } elseif ($o['activation'] === 'from_file') {
                        insert($db, 'import_rows', ['import_id' => $id, 'row_no' => $rowNo, 'sku' => $d['sku'] ?? null, 'status' => 'quota', 'message' => 'Αποθηκεύτηκε ως ανενεργό: συμπληρώθηκε το όριο ενεργών προϊόντων.']);
                    }
                } elseif ($wantInactive && $wasActive) {
                    q($db, "UPDATE products SET status = 'inactive' WHERE id = ?", [$pid]);
                    $active--;
                }
            }
            $processed = $start + $n;
            $done = $n < $limit || $processed >= (int)$imp['total'];
            $o['seen'] = array_keys($seen);
            q($db, 'UPDATE imports SET processed = ?, created = created + ?, updated = updated + ?, skipped = skipped + ?, errors = errors + ?, activated = activated + ?, status = ?, options = ?, finished_at = ? WHERE id = ?', [
                $processed, $stats['created'], $stats['updated'], $stats['skipped'], $stats['errors'], $stats['activated'],
                $done ? 'done' : 'processing', json_encode($o, JSON_UNESCAPED_UNICODE), $done ? now() : null, $id,
            ]);
            if ($done) {
                $f = row($db, 'SELECT * FROM imports WHERE id = ?', [$id]);
                insert($db, 'import_logs', ['import_id' => $id, 'message' => "Ολοκληρώθηκε: {$f['created']} νέα, {$f['updated']} ενημερώσεις, {$f['skipped']} αγνοήθηκαν, {$f['errors']} σφάλματα, {$f['activated']} ενεργοποιήθηκαν.", 'created_at' => now()]);
            }
            $db->exec('COMMIT');
        } catch (Throwable $e) {
            $db->exec('ROLLBACK');
            update($db, 'imports', ['status' => 'failed'], 'id = ?', [$id]);
            insert($db, 'import_logs', ['import_id' => $id, 'message' => 'Σφάλμα: ' . $e->getMessage(), 'created_at' => now()]);
            throw $e;
        }
        return self::progress(row($db, 'SELECT * FROM imports WHERE id = ?', [$id]));
    }

    public static function progress(?array $imp): array
    {
        if (!$imp) {
            return ['status' => 'missing'];
        }
        return array_intersect_key($imp, array_flip(['id', 'status', 'total', 'processed', 'created', 'updated', 'skipped', 'errors', 'activated']))
            + ['percent' => $imp['total'] ? (int)floor($imp['processed'] / $imp['total'] * 100) : 100];
    }

    /* ---------- parsing ---------- */

    /** Yields [index, assoc record] starting at $from (0-based). CSV seeks by row, XML streams with XMLReader. */
    public static function iterate(array $imp, int $from): Generator
    {
        if ($imp['format'] === 'xml') {
            yield from self::iterateXml($imp['stored_path'], $from);
        } else {
            yield from self::iterateCsv($imp['stored_path'], $from);
        }
    }

    private static function iterateCsv(string $path, int $from): Generator
    {
        $fh = fopen($path, 'rb');
        if (!$fh) {
            return;
        }
        $first = fgets($fh);
        if ($first === false) {
            return;
        }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delim = self::delimiter($first);
        $headers = array_map(fn($h) => trim($h), str_getcsv(rtrim($first, "\r\n"), $delim));
        $i = 0;
        while (($r = fgetcsv($fh, 0, $delim)) !== false) {
            if ($r === [null] || (count($r) === 1 && trim((string)$r[0]) === '')) {
                continue;
            }
            if ($i >= $from) {
                $rec = [];
                foreach ($headers as $k => $h) {
                    $rec[$h] = isset($r[$k]) ? self::utf8($r[$k]) : '';
                }
                yield [$i, $rec];
            }
            $i++;
        }
        fclose($fh);
    }

    private static function iterateXml(string $path, int $from): Generator
    {
        $x = new XMLReader();
        if (!$x->open($path, null, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT)) {
            return;
        }
        $i = 0;
        while ($x->read()) {
            if ($x->nodeType === XMLReader::ELEMENT && in_array(strtolower($x->localName), ['product', 'item'], true)) {
                if ($i < $from) {
                    $x->next();
                    $i++;
                    continue;
                }
                $node = @simplexml_load_string($x->readOuterXml(), 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
                $rec = [];
                if ($node) {
                    foreach ($node->attributes() as $k => $v) {
                        $rec[strtolower($k)] = trim((string)$v);
                    }
                    foreach ($node->children() as $k => $v) {
                        $k = strtolower($k);
                        $val = trim($v->count() ? implode('|', array_map('strval', iterator_to_array($v->children(), false))) : (string)$v);
                        $rec[$k] = isset($rec[$k]) && $rec[$k] !== '' ? $rec[$k] . '|' . $val : $val;
                    }
                }
                yield [$i, $rec];
                $i++;
                $x->next();
            }
        }
        $x->close();
    }

    private static function delimiter(string $line): string
    {
        $best = ',';
        $max = -1;
        foreach ([',', ';', "\t", '|'] as $d) {
            $c = substr_count($line, $d);
            if ($c > $max) {
                $max = $c;
                $best = $d;
            }
        }
        return $best;
    }

    private static function utf8(string $s): string
    {
        return mb_check_encoding($s, 'UTF-8') ? $s : (mb_convert_encoding($s, 'UTF-8', 'Windows-1253') ?: $s);
    }

    public static function mapRow(array $rec, array $map): array
    {
        $d = [];
        foreach ($map as $col => $field) {
            if (array_key_exists($col, $rec)) {
                $d[$field] = trim((string)$rec[$col]);
            }
        }
        return $d;
    }

    private static function rowError(array $d): ?string
    {
        if (($d['name'] ?? '') === '' && ($d['sku'] ?? '') === '') {
            return 'Λείπει όνομα και κωδικός.';
        }
        if (isset($d['price']) && $d['price'] !== '' && to_float($d['price']) === null) {
            return 'Μη έγκυρη τιμή «' . mb_substr($d['price'], 0, 20) . '».';
        }
        if (isset($d['price']) && to_float($d['price']) !== null && to_float($d['price']) < 0) {
            return 'Η τιμή δεν μπορεί να είναι αρνητική.';
        }
        if (isset($d['stock']) && $d['stock'] !== '' && !preg_match('/^-?\d+([.,]0+)?$/', $d['stock'])) {
            return 'Μη έγκυρο απόθεμα.';
        }
        return null;
    }

    private static function productFields(PDO $db, array $d): array
    {
        $f = [];
        foreach (['name' => 300, 'sku' => 64, 'brand' => 120, 'barcode' => 64, 'mpn' => 64, 'seo_title' => 200, 'seo_description' => 320, 'short_description' => 2000] as $k => $max) {
            if (isset($d[$k]) && $d[$k] !== '') {
                $f[$k] = Sanitizer::text($d[$k], $max);
            }
        }
        if (isset($d['description']) && $d['description'] !== '') {
            $f['description'] = Sanitizer::html(str_contains($d['description'], '<') ? $d['description'] : '<p>' . nl2br(e($d['description'])) . '</p>');
        }
        foreach (['price', 'cost_price', 'weight'] as $k) {
            if (isset($d[$k]) && to_float($d[$k]) !== null) {
                $f[$k] = to_float($d[$k]);
            }
        }
        if (array_key_exists('sale_price', $d)) {
            $sp = to_float($d['sale_price']);
            $f['sale_price'] = $sp && $sp > 0 ? $sp : null;
        }
        if (isset($d['stock']) && $d['stock'] !== '') {
            $f['stock'] = (int)to_float($d['stock']);
        }
        if (!empty($d['category'])) {
            $f['category_id'] = Catalog::ensureCategoryPath($db, $d['category']);
        }
        return $f;
    }

    private static function truthy(string $v): bool
    {
        return in_array(fold(trim($v)), ['1', 'yes', 'y', 'true', 'ναι', 'ενεργο', 'active', 'on'], true);
    }
}
