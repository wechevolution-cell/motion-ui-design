<?php
/* Shop data engines: permissions, quota, products, categories, media. All operate on ONE tenant DB handle. */

final class Perm
{
    public const MODULES = [
        'dashboard' => 'Πίνακας', 'products' => 'Προϊόντα', 'categories' => 'Κατηγορίες', 'orders' => 'Παραγγελίες',
        'customers' => 'Πελάτες', 'pages' => 'Σελίδες & μενού', 'media' => 'Πολυμέσα', 'appearance' => 'Εμφάνιση',
        'popups' => 'Pop-up & newsletter', 'feeds' => 'Feeds', 'imports' => 'Εισαγωγή / εξαγωγή', 'seo' => 'SEO',
        'settings' => 'Ρυθμίσεις', 'users' => 'Χρήστες',
    ];
    public const ROLES = [
        'owner' => 'Ιδιοκτήτης',
        'administrator' => 'Διαχειριστής',
        'editor' => 'Συντάκτης',
        'product_manager' => 'Υπεύθυνος προϊόντων',
        'custom' => 'Προσαρμοσμένος',
    ];
    public const PRESETS = [
        'owner' => ['dashboard', 'products', 'categories', 'orders', 'customers', 'pages', 'media', 'appearance', 'popups', 'feeds', 'imports', 'seo', 'settings', 'users'],
        'administrator' => ['dashboard', 'products', 'categories', 'orders', 'customers', 'pages', 'media', 'appearance', 'popups', 'feeds', 'imports', 'seo', 'settings'],
        'editor' => ['dashboard', 'pages', 'media', 'popups', 'seo', 'categories'],
        'product_manager' => ['dashboard', 'products', 'categories', 'media', 'imports', 'feeds'],
    ];

    public static function has(array $user, string $module): bool
    {
        if ($user['role'] === 'owner') {
            return true;
        }
        return in_array($module, $user['perms'] ?? [], true);
    }
}

final class QuotaException extends DomainException {}

final class Catalog
{
    public static function activeCount(PDO $db): int
    {
        return (int)val($db, "SELECT COUNT(*) FROM products WHERE status = 'active' AND deleted_at IS NULL");
    }

    public static function storedCount(PDO $db): int
    {
        return (int)val($db, 'SELECT COUNT(*) FROM products WHERE deleted_at IS NULL');
    }

    public static function quotaMessage(int $limit, int $active, int $wanted = 1): string
    {
        $room = max(0, $limit - $active);
        if ($room === 0) {
            return "Δεν είναι δυνατή η ενεργοποίηση. Το πακέτο σας επιτρέπει έως $limit ενεργά προϊόντα και έχετε ήδη $active ενεργά. Απενεργοποιήστε κάποιο προϊόν ή αναβαθμίστε το πακέτο σας.";
        }
        return "Δεν είναι δυνατή η ενεργοποίηση $wanted προϊόντων. Το πακέτο σας επιτρέπει έως $limit ενεργά προϊόντα · έχετε $active ενεργά, άρα μπορείτε να ενεργοποιήσετε ακόμη $room.";
    }

    /**
     * Set status for products. Activation is all-or-nothing and checked inside an IMMEDIATE transaction,
     * so two concurrent requests cannot both pass the check. The limit comes from the platform DB.
     * Returns number of products whose status changed.
     */
    public static function setStatus(PDO $db, array $ids, string $status, int $limit): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !in_array($status, ['active', 'inactive'], true)) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $db->exec('BEGIN IMMEDIATE');
        try {
            if ($status === 'active') {
                $toActivate = (int)val($db, "SELECT COUNT(*) FROM products WHERE id IN ($in) AND deleted_at IS NULL AND status <> 'active'", $ids);
                $active = self::activeCount($db);
                if ($toActivate > 0 && $active + $toActivate > $limit) {
                    throw new QuotaException(self::quotaMessage($limit, $active, $toActivate));
                }
                $n = q($db, "UPDATE products SET status = 'active', activated_at = ?, updated_at = ? WHERE id IN ($in) AND deleted_at IS NULL AND status <> 'active'", array_merge([now(), now()], $ids))->rowCount();
            } else {
                $n = q($db, "UPDATE products SET status = 'inactive', updated_at = ? WHERE id IN ($in) AND deleted_at IS NULL AND status <> 'inactive'", array_merge([now()], $ids))->rowCount();
            }
            $db->exec('COMMIT');
            return $n;
        } catch (Throwable $e) {
            $db->exec('ROLLBACK');
            throw $e;
        }
    }

    /** Used by downgrades: deactivate the N most recently activated products. Returns their ids. */
    public static function deactivateNewest(PDO $db, int $n): array
    {
        $ids = array_column(rows($db, "SELECT id FROM products WHERE status = 'active' AND deleted_at IS NULL ORDER BY activated_at DESC, id DESC LIMIT ?", [$n]), 'id');
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            q($db, "UPDATE products SET status = 'inactive', updated_at = ? WHERE id IN ($in)", array_merge([now()], $ids));
        }
        return array_map('intval', $ids);
    }

    public static function uniqueSlug(PDO $db, string $base, ?int $exceptId = null): string
    {
        $base = slugify($base);
        $slug = $base;
        for ($i = 2; val($db, 'SELECT 1 FROM products WHERE slug = ? AND deleted_at IS NULL' . ($exceptId ? ' AND id <> ' . (int)$exceptId : ''), [$slug]); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    public static function effectivePrice(array $p): float
    {
        return ($p['sale_price'] !== null && $p['sale_price'] !== '' && (float)$p['sale_price'] > 0 && (float)$p['sale_price'] < (float)$p['price']) ? (float)$p['sale_price'] : (float)$p['price'];
    }

    public static function inStock(array $p): bool
    {
        return !$p['manage_stock'] || (int)$p['stock'] > 0;
    }

    public static function images(PDO $db, int $pid): array
    {
        return array_map(fn($r) => Media::urlsFor($r), rows($db, 'SELECT pi.*, m.path, m.variants, m.width, m.height FROM product_images pi LEFT JOIN media m ON m.id = pi.media_id WHERE pi.product_id = ? ORDER BY pi.sort, pi.id', [$pid]));
    }

    /** First image per product for listing pages (one query). */
    public static function coverMap(PDO $db, array $pids): array
    {
        if (!$pids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($pids), '?'));
        $out = [];
        foreach (rows($db, "SELECT pi.*, m.path, m.variants, m.width, m.height FROM product_images pi LEFT JOIN media m ON m.id = pi.media_id WHERE pi.product_id IN ($in) ORDER BY pi.product_id, pi.sort, pi.id", $pids) as $r) {
            if (!isset($out[$r['product_id']])) {
                $out[$r['product_id']] = Media::urlsFor($r);
            }
        }
        return $out;
    }

    public static function variants(PDO $db, int $pid): array
    {
        return array_map(function ($v) {
            $v['opts'] = json_decode($v['options'] ?: '{}', true) ?: [];
            return $v;
        }, rows($db, 'SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort, id', [$pid]));
    }

    /* ---------- categories ---------- */
    public static function categoryTree(PDO $db): array
    {
        $all = rows($db, 'SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort, name');
        $by = [];
        foreach ($all as $c) {
            $c['children'] = [];
            $by[$c['id']] = $c;
        }
        $tree = [];
        foreach ($by as $id => &$c) {
            if ($c['parent_id'] && isset($by[$c['parent_id']])) {
                $by[$c['parent_id']]['children'][] = &$c;
            } else {
                $tree[] = &$c;
            }
        }
        unset($c);
        return $tree;
    }

    /** Flattened tree with depth, for selects and admin lists. */
    public static function categoryFlat(PDO $db): array
    {
        $out = [];
        $walk = function ($nodes, $depth) use (&$walk, &$out) {
            foreach ($nodes as $n) {
                $kids = $n['children'];
                unset($n['children']);
                $n['depth'] = $depth;
                $out[] = $n;
                $walk($kids, $depth + 1);
            }
        };
        $walk(self::categoryTree($db), 0);
        return $out;
    }

    public static function categoryPath(PDO $db, int $catId): array
    {
        $path = [];
        $guard = 0;
        while ($catId && $guard++ < 20) {
            $c = row($db, 'SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL', [$catId]);
            if (!$c) {
                break;
            }
            array_unshift($path, $c);
            $catId = (int)$c['parent_id'];
        }
        return $path;
    }

    public static function categoryUrl(PDO $db, int $catId): string
    {
        return '/category/' . implode('/', array_column(self::categoryPath($db, $catId), 'slug')) . '/';
    }

    public static function descendantIds(PDO $db, int $catId): array
    {
        $ids = [$catId];
        $frontier = [$catId];
        for ($guard = 0; $frontier && $guard < 20; $guard++) {
            $in = implode(',', array_fill(0, count($frontier), '?'));
            $frontier = array_map('intval', array_column(rows($db, "SELECT id FROM categories WHERE parent_id IN ($in) AND deleted_at IS NULL", $frontier), 'id'));
            $ids = array_merge($ids, $frontier);
        }
        return $ids;
    }

    /** Resolve "Κεραμικά > Βάζα" into a category id, creating missing nodes (imports). */
    public static function ensureCategoryPath(PDO $db, string $path): ?int
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s*[>|\/]\s*/u', $path))));
        $parent = null;
        foreach ($parts as $i => $name) {
            $name = mb_substr($name, 0, 120);
            $id = val($db, 'SELECT id FROM categories WHERE deleted_at IS NULL AND ifnull(parent_id,0) = ? AND (name = ? OR slug = ?)', [$parent ?? 0, $name, slugify($name)]);
            if (!$id) {
                $id = insert($db, 'categories', ['parent_id' => $parent, 'name' => $name, 'slug' => slugify($name), 'sort' => 0, 'created_at' => now()]);
            }
            $parent = (int)$id;
        }
        return $parent;
    }

    /** Products in a category subtree + counts used by storefront menus. */
    public static function activeCountsByCategory(PDO $db): array
    {
        $out = [];
        foreach (rows($db, "SELECT pc.category_id, COUNT(*) n FROM product_categories pc JOIN products p ON p.id = pc.product_id WHERE p.status = 'active' AND p.deleted_at IS NULL GROUP BY pc.category_id") as $r) {
            $out[(int)$r['category_id']] = (int)$r['n'];
        }
        return $out;
    }

    public static function syncCategories(PDO $db, int $pid, array $catIds, ?int $primary): void
    {
        $catIds = array_values(array_unique(array_filter(array_map('intval', array_merge($catIds, [$primary])))));
        q($db, 'DELETE FROM product_categories WHERE product_id = ?', [$pid]);
        foreach ($catIds as $c) {
            q($db, 'INSERT OR IGNORE INTO product_categories(product_id, category_id) VALUES(?, ?)', [$pid, $c]);
        }
    }
}

final class Media
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_DIM = 2000;
    public const SIZES = [480, 960, 1600];
    private const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];

    /** Validate (getimagesize + finfo), resize to ≤2000px, write WebP sizes. Returns media row id. */
    public static function store(PDO $db, int $shopId, string $tmp, string $originalName, ?string $alt = null): int
    {
        if (!is_file($tmp) || filesize($tmp) > self::MAX_BYTES) {
            throw new DomainException('Το αρχείο ξεπερνά τα 10 MB.');
        }
        $info = @getimagesize($tmp);
        if (!$info || !isset(self::TYPES[$info[2]])) {
            throw new DomainException('Επιτρέπονται μόνο εικόνες JPG, PNG, WebP ή GIF.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            throw new DomainException('Ο τύπος αρχείου δεν είναι έγκυρη εικόνα.');
        }
        $ext = self::TYPES[$info[2]];
        $rel = $shopId . '/' . gmdate('Y/m');
        $dir = UPLOADS . '/' . $rel;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = slugify(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'image';
        $file = $name . '.' . $ext;
        for ($i = 2; is_file($dir . '/' . $file); $i++) {
            $file = $name . '-' . $i . '.' . $ext;
        }
        [$w, $h] = $info;
        $img = self::load($tmp, $info[2]);
        if ($img && ($w > self::MAX_DIM || $h > self::MAX_DIM)) {
            $img = self::resize($img, self::MAX_DIM);
            $w = imagesx($img);
            $h = imagesy($img);
            self::save($img, $dir . '/' . $file, $ext);
        } else {
            // re-encode when possible to strip metadata / payloads; fall back to a validated copy (animated GIF)
            if ($img && $ext !== 'gif') {
                self::save($img, $dir . '/' . $file, $ext);
            } elseif (!move_uploaded_file($tmp, $dir . '/' . $file)) {
                copy($tmp, $dir . '/' . $file);
            }
        }
        $variants = [];
        if ($img && function_exists('imagewebp')) {
            foreach (self::SIZES as $size) {
                if ($size >= $w && $size !== self::SIZES[0]) {
                    continue;
                }
                $v = $size < $w ? self::resize($img, $size, true) : $img;
                $vf = pathinfo($file, PATHINFO_FILENAME) . '-' . $size . '.webp';
                imagewebp($v, $dir . '/' . $vf, 82);
                $variants[$size] = $rel . '/' . $vf;
            }
        }
        $id = insert($db, 'media', [
            'path' => $rel . '/' . $file, 'mime' => $mime, 'width' => $w, 'height' => $h, 'size' => filesize($dir . '/' . $file),
            'alt' => $alt ? Sanitizer::text($alt, 200) : null, 'variants' => json_encode($variants), 'created_at' => now(),
        ]);
        return $id;
    }

    public static function delete(PDO $db, int $id): void
    {
        $m = row($db, 'SELECT * FROM media WHERE id = ?', [$id]);
        if (!$m) {
            return;
        }
        $files = array_merge([$m['path']], array_values(json_decode($m['variants'] ?: '{}', true) ?: []));
        foreach ($files as $f) {
            $p = realpath(UPLOADS . '/' . $f);
            if ($p && str_starts_with($p, realpath(UPLOADS))) {
                @unlink($p);
            }
        }
        q($db, 'UPDATE product_images SET media_id = NULL WHERE media_id = ?', [$id]);
        q($db, 'DELETE FROM product_images WHERE media_id IS NULL AND ifnull(url, ?) = ?', ['', '']);
        q($db, 'DELETE FROM media WHERE id = ?', [$id]);
    }

    /** Normalised image info for templates: src, srcset, width, height, alt. */
    public static function urlsFor(array $r): array
    {
        if (!empty($r['path'])) {
            $vars = json_decode($r['variants'] ?? '{}', true) ?: [];
            $srcset = [];
            foreach ($vars as $size => $p) {
                $srcset[] = '/uploads/' . $p . ' ' . $size . 'w';
            }
            $best = $vars['960'] ?? $vars[960] ?? null;
            return [
                'src' => '/uploads/' . ($best ?: $r['path']),
                'full' => '/uploads/' . $r['path'],
                'srcset' => implode(', ', $srcset),
                'width' => (int)($r['width'] ?? 0) ?: 800,
                'height' => (int)($r['height'] ?? 0) ?: 800,
                'alt' => $r['alt'] ?? '',
            ];
        }
        return ['src' => $r['url'] ?? '', 'full' => $r['url'] ?? '', 'srcset' => '', 'width' => 800, 'height' => 800, 'alt' => $r['alt'] ?? ''];
    }

    private static function load(string $f, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($f),
            IMAGETYPE_PNG => @imagecreatefrompng($f),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f) : false,
            IMAGETYPE_GIF => @imagecreatefromgif($f),
            default => false,
        };
    }

    private static function resize($img, int $max, bool $byWidth = false)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = $byWidth ? $max / $w : min($max / $w, $max / $h);
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    private static function save($img, string $path, string $ext): void
    {
        match ($ext) {
            'jpg' => imagejpeg($img, $path, 85),
            'png' => imagepng($img, $path, 6),
            'webp' => imagewebp($img, $path, 85),
            'gif' => imagegif($img, $path),
        };
    }
}
