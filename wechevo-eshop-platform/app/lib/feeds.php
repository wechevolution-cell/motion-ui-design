<?php
/* XML feed engine: generic, Skroutz, BestPrice and custom feeds. Only ACTIVE products of a SERVING shop.
   Output is cached in data/feeds/{shop}/ and regenerated after the feed's frequency. */

final class Feeds
{
    public const TYPES = ['generic' => 'Γενικό XML', 'skroutz' => 'Skroutz', 'bestprice' => 'BestPrice'];
    public const GENERIC_FIELDS = ['id', 'sku', 'name', 'link', 'image', 'images', 'category', 'price', 'sale_price', 'final_price', 'availability', 'stock', 'brand', 'barcode', 'mpn', 'weight', 'description'];

    public static function file(int $shopId, string $slug): string
    {
        return DATA . '/feeds/' . $shopId . '/' . $slug . '.xml';
    }

    /** Returns path to an up-to-date feed file. */
    public static function ensure(PDO $db, int $shopId, array $feed, string $baseUrl): string
    {
        $f = self::file($shopId, $feed['slug']);
        $fresh = is_file($f) && (time() - filemtime($f)) < max(5, (int)$feed['frequency_minutes']) * 60;
        if (!$fresh) {
            self::generate($db, $shopId, $feed, $baseUrl);
        }
        return $f;
    }

    public static function generate(PDO $db, int $shopId, array $feed, string $baseUrl): int
    {
        $t0 = microtime(true);
        $dir = dirname(self::file($shopId, $feed['slug']));
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = self::file($shopId, $feed['slug']) . '.tmp';
        $filters = json_decode($feed['filters'] ?: '{}', true) ?: [];
        $fields = json_decode($feed['fields'] ?: '[]', true) ?: self::GENERIC_FIELDS;
        $where = "p.status = 'active' AND p.deleted_at IS NULL";
        $params = [];
        if (!empty($filters['categories'])) {
            $cats = [];
            foreach ((array)$filters['categories'] as $c) {
                $cats = array_merge($cats, Catalog::descendantIds($db, (int)$c));
            }
            $where .= ' AND p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . implode(',', array_map('intval', $cats)) . '))';
        }
        if (!empty($filters['in_stock'])) {
            $where .= ' AND (p.manage_stock = 0 OR p.stock > 0)';
        }
        $catPaths = [];
        foreach (Catalog::categoryFlat($db) as $c) {
            $catPaths[$c['id']] = implode(' > ', array_column(Catalog::categoryPath($db, (int)$c['id']), 'name'));
        }

        $w = new XMLWriter();
        $w->openUri($tmp);
        $w->setIndent(true);
        $w->startDocument('1.0', 'UTF-8');
        $type = $feed['type'];
        if ($type === 'skroutz') {
            $w->startElement('mywebstore');
            $w->writeElement('created_at', date('Y-m-d H:i'));
            $w->startElement('products');
        } elseif ($type === 'bestprice') {
            $w->startElement('store');
            $w->writeElement('date', date('Y-m-d H:i'));
            $w->startElement('products');
        } else {
            $w->startElement('products');
            $w->writeAttribute('generated', date('c'));
        }
        $count = 0;
        $last = 0;
        // keyset pagination keeps memory flat for 10,000 products
        while (true) {
            $batch = rows($db, "SELECT p.* FROM products p WHERE $where AND p.id > ? ORDER BY p.id LIMIT 500", array_merge($params, [$last]));
            if (!$batch) {
                break;
            }
            $ids = array_column($batch, 'id');
            $imgs = [];
            $in = implode(',', array_map('intval', $ids));
            foreach (rows($db, "SELECT pi.*, m.path, m.variants, m.width, m.height FROM product_images pi LEFT JOIN media m ON m.id = pi.media_id WHERE pi.product_id IN ($in) ORDER BY pi.product_id, pi.sort, pi.id") as $r) {
                $u = Media::urlsFor($r)['full'];
                $imgs[$r['product_id']][] = str_starts_with($u, 'http') ? $u : $baseUrl . $u;
            }
            $vars = [];
            foreach (rows($db, "SELECT * FROM product_variants WHERE product_id IN ($in) ORDER BY sort") as $v) {
                $vars[$v['product_id']][] = json_decode($v['options'], true) ?: [];
            }
            foreach ($batch as $p) {
                $last = (int)$p['id'];
                $link = $baseUrl . '/product/' . $p['slug'] . '/';
                $images = $imgs[$p['id']] ?? [];
                $inStock = Catalog::inStock($p);
                $price = Catalog::effectivePrice($p);
                $cat = $catPaths[$p['category_id']] ?? '';
                $sizes = $colors = [];
                foreach ($vars[$p['id']] ?? [] as $o) {
                    foreach ($o as $k => $v) {
                        if (preg_match('/μέγεθος|size|νούμερο/iu', $k)) $sizes[] = $v;
                        if (preg_match('/χρώμα|color/iu', $k)) $colors[] = $v;
                    }
                }
                if ($type === 'skroutz') {
                    $w->startElement('product');
                    $w->writeElement('id', (string)$p['id']);
                    $w->startElement('name'); $w->writeCdata($p['name']); $w->endElement();
                    $w->writeElement('link', $link);
                    $w->writeElement('image', $images[0] ?? '');
                    foreach (array_slice($images, 1, 10) as $im) $w->writeElement('additional_imageurl', $im);
                    $w->startElement('category'); $w->writeAttribute('id', (string)$p['category_id']); $w->writeCdata($cat); $w->endElement();
                    $w->writeElement('price_with_vat', number_format($price, 2, '.', ''));
                    $w->writeElement('vat', '24');
                    $w->startElement('manufacturer'); $w->writeCdata((string)$p['brand']); $w->endElement();
                    $w->writeElement('mpn', (string)($p['mpn'] ?: $p['sku']));
                    $w->writeElement('ean', (string)$p['barcode']);
                    $w->writeElement('availability', $inStock ? 'Άμεσα διαθέσιμο' : 'Κατόπιν παραγγελίας');
                    if ($sizes) $w->writeElement('size', implode(',', array_unique($sizes)));
                    if ($colors) $w->writeElement('color', implode(',', array_unique($colors)));
                    if ($p['weight']) $w->writeElement('weight', (string)round($p['weight'] * 1000));
                    $w->writeElement('quantity', (string)max(0, (int)$p['stock']));
                    $w->startElement('description'); $w->writeCdata(Sanitizer::text($p['short_description'] ?: $p['description'], 5000)); $w->endElement();
                    $w->endElement();
                } elseif ($type === 'bestprice') {
                    $w->startElement('product');
                    $w->writeElement('productId', (string)$p['id']);
                    $w->startElement('title'); $w->writeCdata($p['name']); $w->endElement();
                    $w->writeElement('productURL', $link);
                    $w->writeElement('imageURL', $images[0] ?? '');
                    $w->writeElement('price', number_format($price, 2, '.', ''));
                    $w->startElement('category_path'); $w->writeCdata($cat); $w->endElement();
                    $w->writeElement('availability', $inStock ? 'Άμεσα διαθέσιμο' : 'Κατόπιν παραγγελίας');
                    $w->writeElement('stock', $inStock ? 'Y' : 'N');
                    $w->startElement('brand'); $w->writeCdata((string)$p['brand']); $w->endElement();
                    $w->writeElement('ean', (string)$p['barcode']);
                    $w->writeElement('mpn', (string)($p['mpn'] ?: $p['sku']));
                    if ($sizes) $w->writeElement('size', implode(',', array_unique($sizes)));
                    $w->endElement();
                } else {
                    $all = [
                        'id' => $p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'link' => $link, 'image' => $images[0] ?? '',
                        'images' => $images, 'category' => $cat, 'price' => number_format((float)$p['price'], 2, '.', ''),
                        'sale_price' => $p['sale_price'] !== null ? number_format((float)$p['sale_price'], 2, '.', '') : '',
                        'final_price' => number_format($price, 2, '.', ''), 'availability' => $inStock ? 'in_stock' : 'out_of_stock',
                        'stock' => (int)$p['stock'], 'brand' => $p['brand'], 'barcode' => $p['barcode'], 'mpn' => $p['mpn'],
                        'weight' => $p['weight'], 'description' => Sanitizer::text($p['short_description'] ?: $p['description'], 5000),
                    ];
                    $w->startElement('product');
                    foreach ($fields as $f) {
                        if (!array_key_exists($f, $all)) continue;
                        if ($f === 'images') {
                            $w->startElement('images');
                            foreach ($all['images'] as $im) $w->writeElement('image', $im);
                            $w->endElement();
                        } elseif (in_array($f, ['name', 'description', 'category', 'brand'], true)) {
                            $w->startElement($f); $w->writeCdata((string)$all[$f]); $w->endElement();
                        } else {
                            $w->writeElement($f, (string)$all[$f]);
                        }
                    }
                    $w->endElement();
                }
                $count++;
            }
            $w->flush();
        }
        $w->endElement();
        if ($type !== 'generic') {
            $w->endElement();
        }
        $w->endDocument();
        $w->flush();
        rename($tmp, self::file($shopId, $feed['slug']));
        update($db, 'feeds', ['last_generated_at' => now()], 'id = ?', [$feed['id']]);
        insert($db, 'feed_logs', ['feed_id' => $feed['id'], 'items' => $count, 'duration_ms' => (int)((microtime(true) - $t0) * 1000), 'status' => 'ok', 'created_at' => now()]);
        q($db, 'DELETE FROM feed_logs WHERE feed_id = ? AND id NOT IN (SELECT id FROM feed_logs WHERE feed_id = ? ORDER BY id DESC LIMIT 50)', [$feed['id'], $feed['id']]);
        return $count;
    }

    public static function invalidate(int $shopId): void
    {
        foreach (glob(DATA . '/feeds/' . $shopId . '/*.xml') ?: [] as $f) {
            @touch($f, time() - 86400 * 2);
        }
    }
}
