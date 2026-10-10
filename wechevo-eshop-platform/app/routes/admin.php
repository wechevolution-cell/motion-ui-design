<?php
/* Shop Admin (/admin/ on the shop's own domain). Every query goes to this shop's own DB file. */

final class AdminRoutes
{
    private static array $shop;
    private static PDO $db;
    private static ?array $user = null;

    public static function dispatch(array $shop, string $path): void
    {
        self::$shop = $shop;
        Session::start('admin');
        send_security_headers(true);
        $path = rtrim($path, '/') ?: '/admin';
        if ($path === '/admin') {
            $path = '/admin/dashboard';
        }

        // impersonation hand-off from Super Admin (one-time token)
        if ($path === '/admin/impersonate') {
            $r = Impersonation::redeem((string)input('t'), (int)$shop['id']);
            if (!$r) {
                abort(403, 'Ο σύνδεσμος σύνδεσης έληξε ή έχει ήδη χρησιμοποιηθεί.');
            }
            $owner = row(DB::platform(), "SELECT * FROM users WHERE shop_id = ? AND role = 'owner' AND deleted_at IS NULL ORDER BY id LIMIT 1", [$shop['id']]);
            if (!$owner) {
                abort(404, 'Το κατάστημα δεν έχει λογαριασμό ιδιοκτήτη.');
            }
            Auth::login($owner, $r[0]);
            Audit::log('impersonation.started', ['actor' => $r[0], 'label' => 'superadmin #' . $r[0] . ' as ' . $owner['email'], 'entity' => 'user', 'id' => $owner['id']]);
            redirect('/admin/dashboard');
        }

        // locked / suspended: no admin surface at all for the client (Super Admin can still impersonate)
        $impersonating = !empty($_SESSION['impersonator']) && (int)($_SESSION['shop'] ?? 0) === (int)$shop['id'];
        if (!Shops::isServing($shop) && !$impersonating) {
            http_response_code(503);
            header('Retry-After: 86400');
            echo render(APP . '/views/shared/lock.php');
            return;
        }

        if (is_post()) {
            Csrf::check();
        }
        if ($path === '/admin/login') {
            self::login();
            return;
        }
        $u = Auth::user();
        if (!$u) {
            redirect('/admin/login');
        }
        // tenant isolation: a session from another shop (or a super admin session) is refused
        if ($u['shop_id'] === null || (int)$u['shop_id'] !== (int)$shop['id'] || (int)($_SESSION['shop'] ?? 0) !== (int)$shop['id']) {
            Audit::log('tenant.denied', ['outcome' => 'denied', 'meta' => ['session_shop' => $_SESSION['shop'] ?? null, 'path' => $path]]);
            abort(403, 'Δεν έχετε πρόσβαση σε αυτό το κατάστημα.');
        }
        self::$user = $u;
        self::$db = DB::shop((int)$shop['id']);

        if ($u['must_change_password'] && empty($_SESSION['impersonator']) && !in_array($path, ['/admin/account', '/admin/logout'], true)) {
            flash('warning', 'Ορίστε νέο κωδικό πριν συνεχίσετε.');
            redirect('/admin/account');
        }

        $r = new Router();
        $r->add('POST', '/admin/logout', function () {
            Audit::log('logout');
            Session::destroy();
            redirect('/admin/login');
        });
        $r->add('GET', '/admin/dashboard', [self::class, 'dashboard']);
        $r->add('GET|POST', '/admin/products', [self::class, 'products']);
        $r->add('GET|POST', '/admin/products/(?P<id>\d+|new)', [self::class, 'productEdit']);
        $r->add('POST', '/admin/products/(?P<id>\d+)/toggle', [self::class, 'productToggle']);
        $r->add('GET|POST', '/admin/categories', [self::class, 'categories']);
        $r->add('GET|POST', '/admin/orders', [self::class, 'orders']);
        $r->add('GET|POST', '/admin/orders/(?P<id>\d+)', [self::class, 'order']);
        $r->add('GET', '/admin/customers', [self::class, 'customers']);
        $r->add('GET', '/admin/customers/(?P<id>\d+)', [self::class, 'customer']);
        $r->add('GET|POST', '/admin/subscribers', [self::class, 'subscribers']);
        $r->add('GET|POST', '/admin/messages', [self::class, 'messages']);
        $r->add('GET|POST', '/admin/pages', [self::class, 'pages']);
        $r->add('GET|POST', '/admin/pages/(?P<id>\d+|new)', [self::class, 'pageEdit']);
        $r->add('GET|POST', '/admin/menus', [self::class, 'menus']);
        $r->add('GET|POST', '/admin/media', [self::class, 'media']);
        $r->add('GET|POST', '/admin/appearance', [self::class, 'appearance']);
        $r->add('GET|POST', '/admin/homepage', [self::class, 'homepage']);
        $r->add('GET|POST', '/admin/popup', [self::class, 'popup']);
        $r->add('GET|POST', '/admin/feeds', [self::class, 'feeds']);
        $r->add('GET|POST', '/admin/import', [self::class, 'import']);
        $r->add('GET|POST', '/admin/import/(?P<id>\d+)', [self::class, 'importView']);
        $r->add('POST', '/admin/import/(?P<id>\d+)/run', [self::class, 'importRun']);
        $r->add('GET', '/admin/export', [self::class, 'export']);
        $r->add('GET|POST', '/admin/seo', [self::class, 'seo']);
        $r->add('GET|POST', '/admin/settings', [self::class, 'settings']);
        $r->add('GET|POST', '/admin/users', [self::class, 'users']);
        $r->add('GET|POST', '/admin/account', [self::class, 'account']);
        $r->dispatch($path);
    }

    /* ---------- helpers ---------- */
    private static function need(string $module): void
    {
        if (!Perm::has(self::$user, $module)) {
            Audit::log('permission.denied', ['outcome' => 'denied', 'meta' => ['module' => $module]]);
            abort(403, 'Δεν έχετε δικαίωμα πρόσβασης σε αυτή την ενότητα (' . (Perm::MODULES[$module] ?? $module) . ').');
        }
    }

    private static function feature(string $f): void
    {
        if (!Shops::feature(self::$shop, $f)) {
            abort(403, 'Η λειτουργία «' . (Plans::FEATURES[$f] ?? $f) . '» δεν περιλαμβάνεται στο πακέτο σας.');
        }
    }

    private static function limit(): int
    {
        return Shops::activeLimit(self::$shop);
    }

    private static function page(string $title, string $view, array $vars = [], string $active = ''): void
    {
        $u = self::$user;
        $all = [
            ['key' => 'dashboard', 'href' => '/admin/dashboard', 'label' => 'Πίνακας', 'perm' => 'dashboard'],
            ['sep' => 'Κατάλογος'],
            ['key' => 'products', 'href' => '/admin/products', 'label' => 'Προϊόντα', 'perm' => 'products'],
            ['key' => 'categories', 'href' => '/admin/categories', 'label' => 'Κατηγορίες', 'perm' => 'categories'],
            ['key' => 'import', 'href' => '/admin/import', 'label' => 'Εισαγωγή / εξαγωγή', 'perm' => 'imports'],
            ['key' => 'media', 'href' => '/admin/media', 'label' => 'Πολυμέσα', 'perm' => 'media'],
            ['sep' => 'Πωλήσεις'],
            ['key' => 'orders', 'href' => '/admin/orders', 'label' => 'Παραγγελίες', 'perm' => 'orders', 'badge' => (int)val(self::$db, "SELECT COUNT(*) FROM orders WHERE status = 'new'")],
            ['key' => 'customers', 'href' => '/admin/customers', 'label' => 'Πελάτες', 'perm' => 'customers'],
            ['key' => 'subscribers', 'href' => '/admin/subscribers', 'label' => 'Συνδρομητές newsletter', 'perm' => 'popups'],
            ['key' => 'messages', 'href' => '/admin/messages', 'label' => 'Μηνύματα', 'perm' => 'customers', 'badge' => (int)val(self::$db, 'SELECT COUNT(*) FROM contact_messages WHERE read_at IS NULL')],
            ['sep' => 'Κατάστημα'],
            ['key' => 'appearance', 'href' => '/admin/appearance', 'label' => 'Εμφάνιση', 'perm' => 'appearance'],
            ['key' => 'homepage', 'href' => '/admin/homepage', 'label' => 'Αρχική σελίδα', 'perm' => 'appearance'],
            ['key' => 'pages', 'href' => '/admin/pages', 'label' => 'Σελίδες', 'perm' => 'pages'],
            ['key' => 'menus', 'href' => '/admin/menus', 'label' => 'Μενού', 'perm' => 'pages'],
            ['key' => 'popup', 'href' => '/admin/popup', 'label' => 'Pop-up', 'perm' => 'popups'],
            ['key' => 'feeds', 'href' => '/admin/feeds', 'label' => 'XML feeds', 'perm' => 'feeds'],
            ['key' => 'seo', 'href' => '/admin/seo', 'label' => 'SEO', 'perm' => 'seo'],
            ['sep' => 'Διαχείριση'],
            ['key' => 'settings', 'href' => '/admin/settings', 'label' => 'Ρυθμίσεις', 'perm' => 'settings'],
            ['key' => 'users', 'href' => '/admin/users', 'label' => 'Χρήστες', 'perm' => 'users'],
            ['key' => 'account', 'href' => '/admin/account', 'label' => 'Ο λογαριασμός μου', 'perm' => null],
        ];
        $nav = [];
        foreach ($all as $item) {
            if (isset($item['sep']) || $item['perm'] === null || Perm::has($u, $item['perm'])) {
                $nav[] = $item;
            }
        }
        $nav = array_values(array_filter($nav, function ($it, $i) use ($nav) {
            return !isset($it['sep']) || (isset($nav[$i + 1]) && !isset($nav[$i + 1]['sep']));
        }, ARRAY_FILTER_USE_BOTH));
        $shop = self::$shop;
        $banner = null;
        $dl = Shops::daysLeft($shop);
        if (!empty($_SESSION['impersonator'])) {
            $banner = ['level' => 'info', 'html' => 'Λειτουργία υποστήριξης: είστε συνδεδεμένοι ως Super Admin στο κατάστημα «' . e($shop['name']) . '». Όλες οι ενέργειες καταγράφονται.'];
        }
        if ($shop['status'] === 'EXPIRED') {
            $banner = ['level' => 'danger', 'html' => 'Η συνδρομή σας έληξε. Το κατάστημα θα κλειδώσει σύντομα αν δεν γίνει ανανέωση. Επικοινωνήστε μαζί μας.'];
        } elseif ($shop['status'] === 'EXPIRING' && $dl !== null) {
            $banner = ['level' => 'warning', 'html' => 'Η συνδρομή σας λήγει σε ' . max(0, $dl) . ' ' . ($dl === 1 ? 'ημέρα' : 'ημέρες') . ' (' . d($shop['subscription_expires']) . ').'];
        }
        $content = render(APP . '/views/admin/' . $view . '.php', $vars + ['db' => self::$db, 'shop' => $shop, 'me' => $u, 'limit' => self::limit()]);
        echo render(APP . '/views/shared/admin_layout.php', [
            'title' => $title, 'content' => $content, 'nav' => $nav, 'area' => 'admin', 'user' => $u, 'banner' => $banner,
            'brand' => $shop['name'], 'active' => $active,
            'topLinks' => '<a class="btn btn-ghost btn-sm" href="/" target="_blank" rel="noopener">Προβολή καταστήματος ↗</a>',
        ]);
    }

    private static function login(): void
    {
        $error = null;
        if (is_post()) {
            [$u, $error] = Auth::attempt(input('email'), (string)($_POST['password'] ?? ''), (int)self::$shop['id']);
            if ($u) {
                Auth::login($u);
                Audit::log('login', ['actor' => $u['id'], 'label' => $u['email']]);
                redirect('/admin/dashboard');
            }
        }
        echo render(APP . '/views/shared/login.php', ['error' => $error, 'title' => 'Σύνδεση διαχείρισης', 'brand' => self::$shop['name'], 'action' => '/admin/login']);
    }

    private static function settings_(string $k, $d = [])
    {
        return setting_get(self::$db, $k, $d);
    }

    /* ---------- dashboard ---------- */
    public static function dashboard(): void
    {
        self::need('dashboard');
        $db = self::$db;
        $since = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
        $k = [
            'active' => Catalog::activeCount($db),
            'stored' => Catalog::storedCount($db),
            'orders_new' => (int)val($db, "SELECT COUNT(*) FROM orders WHERE status = 'new'"),
            'orders_30' => (int)val($db, "SELECT COUNT(*) FROM orders WHERE created_at >= ? AND status <> 'cancelled'", [$since]),
            'revenue_30' => (float)val($db, "SELECT COALESCE(SUM(total),0) FROM orders WHERE created_at >= ? AND status <> 'cancelled'", [$since]),
            'subscribers' => (int)val($db, 'SELECT COUNT(*) FROM subscribers WHERE unsubscribed_at IS NULL'),
            'out_of_stock' => (int)val($db, "SELECT COUNT(*) FROM products WHERE status = 'active' AND deleted_at IS NULL AND manage_stock = 1 AND stock <= 0"),
            'customers' => (int)val($db, 'SELECT COUNT(*) FROM customers'),
        ];
        $company = self::settings_('company');
        $theme = self::settings_('theme');
        $ship = self::settings_('shipping');
        $pay = self::settings_('payments');
        $pagesTouched = (int)val($db, "SELECT COUNT(*) FROM pages WHERE body NOT LIKE ?", ['%' . ShopSeed::TEMPLATE_TEXT . '%']);
        $checklist = [
            ['Συμπληρώστε τα στοιχεία της επιχείρησης', '/admin/settings', !empty($company['phone']) && !empty($company['address'])],
            ['Ανεβάστε λογότυπο', '/admin/appearance', !empty($theme['logo'])],
            ['Δημιουργήστε κατηγορίες', '/admin/categories', (int)val($db, 'SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL') > 0],
            ['Προσθέστε ή εισάγετε προϊόντα', '/admin/products', $k['stored'] > 0],
            ['Ενεργοποιήστε προϊόντα (έως ' . self::limit() . ')', '/admin/products?status=inactive', $k['active'] > 0],
            ['Ορίστε κόστη αποστολής', '/admin/settings#shipping', !empty($ship['confirmed'])],
            ['Ρυθμίστε τρόπους πληρωμής', '/admin/settings#payments', !empty($pay['confirmed'])],
            ['Προσαρμόστε την αρχική σελίδα', '/admin/homepage', !empty(self::settings_('homepage')['confirmed'])],
            ['Ελέγξτε όρους, απόρρητο, αποστολές, επιστροφές', '/admin/pages', $pagesTouched >= 4],
            ['Ενεργοποιήστε feeds (Skroutz/BestPrice)', '/admin/feeds', (int)val($db, "SELECT COUNT(*) FROM feeds WHERE status = 'active' AND type <> 'generic'") > 0],
        ];
        $recent = rows($db, 'SELECT * FROM orders ORDER BY id DESC LIMIT 8');
        self::page('Πίνακας', 'dashboard', compact('k', 'checklist', 'recent'), 'dashboard');
    }

    /* ---------- products ---------- */
    public static function products(): void
    {
        self::need('products');
        $db = self::$db;
        if (is_post()) {
            self::bulk();
        }
        $where = ['p.deleted_at IS NULL'];
        $p = [];
        $q = input('q');
        if ($q !== '') {
            $where[] = '(fold(p.name) LIKE ? OR p.sku LIKE ? OR p.barcode = ?)';
            array_push($p, '%' . fold($q) . '%', "%$q%", $q);
        }
        $status = input('status');
        if (in_array($status, ['active', 'inactive'], true)) {
            $where[] = 'p.status = ?';
            $p[] = $status;
        }
        if ($cat = (int)input('cat')) {
            $ids = Catalog::descendantIds($db, $cat);
            $where[] = 'p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . implode(',', $ids) . '))';
        }
        $stock = input('stock');
        if ($stock === 'out') $where[] = 'p.manage_stock = 1 AND p.stock <= 0';
        if ($stock === 'low') $where[] = 'p.manage_stock = 1 AND p.stock BETWEEN 1 AND 3';
        if (input('missing') === 'image') $where[] = 'NOT EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id)';
        $sorts = ['new' => 'p.id DESC', 'name' => 'p.name COLLATE NOCASE', 'price' => 'p.price', 'price_desc' => 'p.price DESC', 'stock' => 'p.stock', 'activated' => 'p.activated_at DESC'];
        $sort = $sorts[input('sort')] ?? $sorts['new'];
        $per = in_array((int)input('per'), [25, 50, 100, 200], true) ? (int)input('per') : 50;
        $page = max(1, (int)input('page', 1));
        $w = implode(' AND ', $where);
        $total = (int)val($db, "SELECT COUNT(*) FROM products p WHERE $w", $p);
        $list = rows($db, "SELECT p.*, c.name cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $w ORDER BY $sort LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        $covers = Catalog::coverMap($db, array_column($list, 'id'));
        $qs = http_build_query(array_filter(['q' => $q, 'status' => $status, 'cat' => input('cat'), 'stock' => $stock, 'sort' => input('sort'), 'per' => input('per'), 'missing' => input('missing')]));
        self::page('Προϊόντα', 'products', [
            'list' => $list, 'covers' => $covers, 'total' => $total, 'page' => $page, 'per' => $per, 'qs' => $qs,
            'cats' => Catalog::categoryFlat($db), 'active' => Catalog::activeCount($db), 'stored' => Catalog::storedCount($db),
        ], 'products');
    }

    private static function bulk(): void
    {
        $db = self::$db;
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $action = input('bulk');
        $back = '/admin/products' . (($_SERVER['QUERY_STRING'] ?? '') ? '?' . $_SERVER['QUERY_STRING'] : '');
        if (!$ids) {
            flash('error', 'Επιλέξτε τουλάχιστον ένα προϊόν.');
            redirect($back);
        }
        try {
            switch ($action) {
                case 'activate':
                case 'deactivate':
                    $n = Catalog::setStatus($db, $ids, $action === 'activate' ? 'active' : 'inactive', self::limit());
                    flash('success', $action === 'activate' ? "Ενεργοποιήθηκαν $n προϊόντα." : "Απενεργοποιήθηκαν $n προϊόντα.");
                    break;
                case 'delete':
                    if (input('confirm_delete') !== '1') {
                        throw new DomainException('Τσεκάρετε την επιβεβαίωση διαγραφής.');
                    }
                    $in = implode(',', $ids);
                    $n = q($db, "UPDATE products SET deleted_at = ?, status = 'inactive' WHERE id IN ($in) AND deleted_at IS NULL", [now()])->rowCount();
                    flash('success', "Διαγράφηκαν $n προϊόντα. Οι παλιές παραγγελίες δεν επηρεάζονται.");
                    break;
                case 'category':
                    $cat = (int)input('bulk_category');
                    if (!val($db, 'SELECT 1 FROM categories WHERE id = ? AND deleted_at IS NULL', [$cat])) throw new DomainException('Επιλέξτε κατηγορία.');
                    foreach ($ids as $pid) {
                        q($db, 'INSERT OR IGNORE INTO product_categories(product_id, category_id) VALUES(?, ?)', [$pid, $cat]);
                        if (input('bulk_primary') === '1') q($db, 'UPDATE products SET category_id = ?, updated_at = ? WHERE id = ?', [$cat, now(), $pid]);
                    }
                    flash('success', 'Η κατηγορία ανατέθηκε σε ' . count($ids) . ' προϊόντα.');
                    break;
                case 'price':
                    $mode = input('price_mode');
                    $v = to_float(input('price_value'));
                    if ($v === null) throw new DomainException('Συμπληρώστε τιμή ή ποσοστό.');
                    $in = implode(',', $ids);
                    $expr = match ($mode) {
                        'percent' => 'ROUND(price * (1 + ? / 100.0), 2)',
                        'amount' => 'ROUND(price + ?, 2)',
                        'set' => '?',
                        default => throw new DomainException('Άγνωστος τρόπος.'),
                    };
                    q($db, "UPDATE products SET price = MAX(0, $expr), updated_at = ? WHERE id IN ($in)", [$v, now()]);
                    flash('success', 'Οι τιμές ενημερώθηκαν σε ' . count($ids) . ' προϊόντα.');
                    break;
                case 'export':
                    self::exportCsv('selected', $ids);
                    break;
                default:
                    throw new DomainException('Επιλέξτε ενέργεια.');
            }
            Feeds::invalidate((int)self::$shop['id']);
            Audit::log('product.bulk_' . $action, ['entity' => 'product', 'meta' => ['count' => count($ids)]]);
        } catch (QuotaException $e) {
            Audit::log('quota.blocked', ['entity' => 'product', 'outcome' => 'blocked', 'meta' => ['count' => count($ids)]]);
            flash('error', $e->getMessage());
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirect($back);
    }

    public static function productToggle(string $id): void
    {
        self::need('products');
        $p = row(self::$db, 'SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if (!$p) abort(404);
        $to = $p['status'] === 'active' ? 'inactive' : 'active';
        try {
            Catalog::setStatus(self::$db, [(int)$id], $to, self::limit());
            Audit::log('product.' . ($to === 'active' ? 'activated' : 'deactivated'), ['entity' => 'product', 'id' => $id]);
            Feeds::invalidate((int)self::$shop['id']);
            flash('success', $to === 'active' ? "Το «{$p['name']}» ενεργοποιήθηκε." : "Το «{$p['name']}» απενεργοποιήθηκε.");
        } catch (QuotaException $e) {
            Audit::log('quota.blocked', ['entity' => 'product', 'id' => $id, 'outcome' => 'blocked']);
            flash('error', $e->getMessage());
        }
        $back = input('back');
        redirect(str_starts_with($back, '/admin/') ? $back : '/admin/products');
    }

    public static function productEdit(string $id): void
    {
        self::need('products');
        $db = self::$db;
        $shopId = (int)self::$shop['id'];
        $p = $id === 'new' ? null : row($db, 'SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if ($id !== 'new' && !$p) abort(404);
        $errors = [];
        if (is_post()) {
            $op = input('op', 'save');
            if ($p && $op === 'image_delete') {
                $img = row($db, 'SELECT * FROM product_images WHERE id = ? AND product_id = ?', [(int)input('image_id'), $p['id']]);
                if ($img) {
                    q($db, 'DELETE FROM product_images WHERE id = ?', [$img['id']]);
                    if ($img['media_id'] && !val($db, 'SELECT 1 FROM product_images WHERE media_id = ?', [$img['media_id']])) {
                        Media::delete($db, (int)$img['media_id']);
                    }
                }
                flash('success', 'Η εικόνα αφαιρέθηκε.');
                redirect('/admin/products/' . $p['id'] . '#images');
            }
            if ($p && $op === 'image_first') {
                q($db, 'UPDATE product_images SET sort = sort + 1 WHERE product_id = ?', [$p['id']]);
                q($db, 'UPDATE product_images SET sort = -1 WHERE id = ? AND product_id = ?', [(int)input('image_id'), $p['id']]);
                redirect('/admin/products/' . $p['id'] . '#images');
            }
            if ($p && $op === 'delete') {
                q($db, "UPDATE products SET deleted_at = ?, status = 'inactive' WHERE id = ?", [now(), $p['id']]);
                Audit::log('product.deleted', ['entity' => 'product', 'id' => $p['id']]);
                flash('success', 'Το προϊόν διαγράφηκε. Οι παραγγελίες που το περιέχουν παραμένουν.');
                redirect('/admin/products');
            }
            [$data, $errors] = self::productInput($p);
            if (!$p && Catalog::storedCount($db) >= Shops::storedLimit(self::$shop)) {
                $errors[] = 'Συμπληρώθηκε το όριο αποθήκευσης προϊόντων (' . Shops::storedLimit(self::$shop) . ').';
            }
            if (!$errors) {
                $wantActive = input('status') === 'active';
                $db->beginTransaction();
                try {
                    if ($p) {
                        $data['updated_at'] = now();
                        update($db, 'products', $data, 'id = ?', [$p['id']]);
                        $pid = (int)$p['id'];
                    } else {
                        $data['created_at'] = now();
                        $data['status'] = 'inactive';
                        $pid = insert($db, 'products', $data);
                    }
                    Catalog::syncCategories($db, $pid, (array)($_POST['categories'] ?? []), $data['category_id'] ?? null);
                    self::saveVariants($pid);
                    self::saveAttributes($pid);
                    foreach (array_filter(array_map('trim', preg_split('/\s+/', input('image_urls')))) as $k => $u) {
                        if (preg_match('#^https?://#i', $u)) insert($db, 'product_images', ['product_id' => $pid, 'url' => mb_substr($u, 0, 1000), 'sort' => 200 + $k]);
                    }
                    $db->commit();
                } catch (Throwable $e) {
                    $db->rollBack();
                    throw $e;
                }
                $imgErr = self::saveUploads($pid);
                Feeds::invalidate($shopId);
                $current = row($db, 'SELECT status FROM products WHERE id = ?', [$pid])['status'];
                if ($wantActive !== ($current === 'active')) {
                    try {
                        Catalog::setStatus($db, [$pid], $wantActive ? 'active' : 'inactive', self::limit());
                    } catch (QuotaException $e) {
                        Audit::log('quota.blocked', ['entity' => 'product', 'id' => $pid, 'outcome' => 'blocked']);
                        flash('error', 'Το προϊόν αποθηκεύτηκε ως ανενεργό. ' . $e->getMessage());
                    }
                }
                Audit::log($p ? 'product.updated' : 'product.created', ['entity' => 'product', 'id' => $pid]);
                foreach ($imgErr as $m) flash('error', $m);
                flash('success', 'Το προϊόν αποθηκεύτηκε.');
                redirect('/admin/products/' . $pid);
            }
            $p = array_merge($p ?? [], $data, ['status' => input('status')]);
        }
        $pid = (int)($p['id'] ?? 0);
        self::page($pid ? 'Επεξεργασία προϊόντος' : 'Νέο προϊόν', 'product_edit', [
            'p' => $p, 'errors' => $errors, 'cats' => Catalog::categoryFlat($db),
            'pcats' => $pid ? array_map('intval', array_column(rows($db, 'SELECT category_id FROM product_categories WHERE product_id = ?', [$pid]), 'category_id')) : [],
            'images' => $pid ? rows($db, 'SELECT pi.*, m.path, m.variants, m.width, m.height FROM product_images pi LEFT JOIN media m ON m.id = pi.media_id WHERE pi.product_id = ? ORDER BY pi.sort, pi.id', [$pid]) : [],
            'variants' => $pid ? Catalog::variants($db, $pid) : [],
            'attributes' => $pid ? rows($db, 'SELECT * FROM product_attributes WHERE product_id = ? ORDER BY sort, id', [$pid]) : [],
            'orders' => $pid ? (int)val($db, 'SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$pid]) : 0,
            'activeCount' => Catalog::activeCount($db),
        ], 'products');
    }

    private static function productInput(?array $p): array
    {
        $db = self::$db;
        $e = [];
        $name = Sanitizer::text(input('name'), 300);
        if ($name === '') $e[] = 'Συμπληρώστε όνομα προϊόντος.';
        $price = to_float(input('price'));
        if ($price === null || $price < 0) $e[] = 'Συμπληρώστε έγκυρη τιμή.';
        $sale = to_float(input('sale_price'));
        if ($sale !== null && $price !== null && $sale >= $price) $e[] = 'Η τιμή προσφοράς πρέπει να είναι μικρότερη από την κανονική.';
        $sku = Sanitizer::text(input('sku'), 64);
        if ($sku !== '' && val($db, 'SELECT 1 FROM products WHERE lower(sku) = lower(?) AND deleted_at IS NULL AND id <> ?', [$sku, $p['id'] ?? 0])) $e[] = "Ο κωδικός $sku χρησιμοποιείται ήδη.";
        $slugIn = input('slug');
        $slug = $slugIn !== '' ? slugify($slugIn) : ($p['slug'] ?? '');
        if ($slug === '' || ($slugIn === '' && !$p)) $slug = Catalog::uniqueSlug($db, $name);
        elseif (val($db, 'SELECT 1 FROM products WHERE slug = ? AND deleted_at IS NULL AND id <> ?', [$slug, $p['id'] ?? 0])) $e[] = 'Το slug χρησιμοποιείται ήδη από άλλο προϊόν.';
        $cat = (int)input('category_id') ?: null;
        $num = fn($k) => to_float(input($k));
        $robots = input('robots') === 'noindex' ? 'noindex, follow' : null;
        return [[
            'name' => $name, 'slug' => $slug, 'sku' => $sku ?: null, 'barcode' => Sanitizer::text(input('barcode'), 64) ?: null,
            'mpn' => Sanitizer::text(input('mpn'), 64) ?: null, 'brand' => Sanitizer::text(input('brand'), 120) ?: null,
            'short_description' => Sanitizer::text(input('short_description'), 2000) ?: null, 'description' => Sanitizer::html((string)($_POST['description'] ?? '')),
            'price' => $price ?? 0, 'sale_price' => $sale && $sale > 0 ? $sale : null, 'cost_price' => $num('cost_price'),
            'stock' => (int)input('stock'), 'manage_stock' => input('manage_stock') === '1' ? 1 : 0,
            'weight' => $num('weight'), 'length' => $num('length'), 'width' => $num('width'), 'height' => $num('height'),
            'category_id' => $cat, 'featured' => input('featured') === '1' ? 1 : 0,
            'seo_title' => Sanitizer::text(input('seo_title'), 200) ?: null, 'seo_description' => Sanitizer::text(input('seo_description'), 320) ?: null,
            'canonical_url' => Sanitizer::url(input('canonical_url')) ?: null, 'robots' => $robots,
        ], $e];
    }

    private static function saveVariants(int $pid): void
    {
        $db = self::$db;
        $rows = $_POST['variants'] ?? [];
        $keep = [];
        foreach (is_array($rows) ? $rows : [] as $i => $v) {
            $opts = [];
            foreach ([['o1n', 'o1v'], ['o2n', 'o2v']] as [$kn, $kv]) {
                $n = Sanitizer::text($v[$kn] ?? '', 40);
                $val = Sanitizer::text($v[$kv] ?? '', 60);
                if ($n !== '' && $val !== '') $opts[$n] = $val;
            }
            if (!$opts) continue;
            $data = ['product_id' => $pid, 'sku' => Sanitizer::text($v['sku'] ?? '', 64) ?: null, 'options' => json_encode($opts, JSON_UNESCAPED_UNICODE),
                'price' => to_float($v['price'] ?? ''), 'stock' => (int)($v['stock'] ?? 0), 'sort' => (int)$i];
            $vid = (int)($v['id'] ?? 0);
            if ($vid && val($db, 'SELECT 1 FROM product_variants WHERE id = ? AND product_id = ?', [$vid, $pid])) {
                update($db, 'product_variants', $data, 'id = ?', [$vid]);
            } else {
                $vid = insert($db, 'product_variants', $data);
            }
            $keep[] = $vid;
        }
        q($db, 'DELETE FROM product_variants WHERE product_id = ?' . ($keep ? ' AND id NOT IN (' . implode(',', $keep) . ')' : ''), [$pid]);
        if ($keep) {
            q($db, 'UPDATE products SET stock = (SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = ?) WHERE id = ?', [$pid, $pid]);
        }
    }

    private static function saveAttributes(int $pid): void
    {
        $db = self::$db;
        q($db, 'DELETE FROM product_attributes WHERE product_id = ?', [$pid]);
        foreach ((array)($_POST['attributes'] ?? []) as $i => $a) {
            $n = Sanitizer::text($a['name'] ?? '', 80);
            $v = Sanitizer::text($a['value'] ?? '', 300);
            if ($n !== '' && $v !== '') insert($db, 'product_attributes', ['product_id' => $pid, 'name' => $n, 'value' => $v, 'sort' => (int)$i]);
        }
    }

    private static function saveUploads(int $pid): array
    {
        $errs = [];
        $f = $_FILES['images'] ?? null;
        if (!$f || !is_array($f['name'])) return $errs;
        $max = (int)val(self::$db, 'SELECT COALESCE(MAX(sort),0) FROM product_images WHERE product_id = ?', [$pid]);
        foreach ($f['name'] as $i => $name) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if ($f['error'][$i] !== UPLOAD_ERR_OK) {
                $errs[] = "Η εικόνα «$name» δεν ανέβηκε (σφάλμα μεταφόρτωσης).";
                continue;
            }
            try {
                $mid = Media::store(self::$db, (int)self::$shop['id'], $f['tmp_name'][$i], $name, input('name'));
                insert(self::$db, 'product_images', ['product_id' => $pid, 'media_id' => $mid, 'alt' => Sanitizer::text(input('name'), 200), 'sort' => ++$max]);
            } catch (DomainException $e) {
                $errs[] = "«$name»: " . $e->getMessage();
            }
        }
        return $errs;
    }

    /* ---------- categories ---------- */
    public static function categories(): void
    {
        self::need('categories');
        $db = self::$db;
        if (is_post()) {
            $op = input('op');
            try {
                if ($op === 'save') {
                    $id = (int)input('id');
                    $name = Sanitizer::text(input('name'), 120);
                    if ($name === '') throw new DomainException('Συμπληρώστε όνομα κατηγορίας.');
                    $parent = (int)input('parent_id') ?: null;
                    if ($id && $parent && in_array($parent, Catalog::descendantIds($db, $id), true)) throw new DomainException('Μια κατηγορία δεν μπορεί να μπει μέσα στον εαυτό της ή σε υποκατηγορία της.');
                    $slug = slugify(input('slug') ?: $name);
                    if (val($db, 'SELECT 1 FROM categories WHERE ifnull(parent_id,0) = ? AND slug = ? AND deleted_at IS NULL AND id <> ?', [$parent ?? 0, $slug, $id])) throw new DomainException('Υπάρχει ήδη κατηγορία με αυτό το slug στο ίδιο επίπεδο.');
                    $data = ['parent_id' => $parent, 'name' => $name, 'slug' => $slug, 'description' => Sanitizer::html((string)($_POST['description'] ?? '')),
                        'seo_title' => Sanitizer::text(input('seo_title'), 200) ?: null, 'seo_description' => Sanitizer::text(input('seo_description'), 320) ?: null,
                        'canonical_url' => Sanitizer::url(input('canonical_url')) ?: null, 'sort' => (int)input('sort'), 'updated_at' => now()];
                    if ($id) {
                        update($db, 'categories', $data, 'id = ?', [$id]);
                    } else {
                        $data['created_at'] = now();
                        $id = insert($db, 'categories', $data);
                    }
                    Audit::log('category.saved', ['entity' => 'category', 'id' => $id]);
                    flash('success', 'Η κατηγορία αποθηκεύτηκε.');
                } elseif ($op === 'delete') {
                    $id = (int)input('id');
                    if (val($db, 'SELECT 1 FROM categories WHERE parent_id = ? AND deleted_at IS NULL', [$id])) throw new DomainException('Μετακινήστε ή διαγράψτε πρώτα τις υποκατηγορίες.');
                    q($db, 'UPDATE categories SET deleted_at = ? WHERE id = ?', [now(), $id]);
                    q($db, 'DELETE FROM product_categories WHERE category_id = ?', [$id]);
                    q($db, 'UPDATE products SET category_id = NULL WHERE category_id = ?', [$id]);
                    Audit::log('category.deleted', ['entity' => 'category', 'id' => $id]);
                    flash('success', 'Η κατηγορία διαγράφηκε. Τα προϊόντα της δεν διαγράφηκαν.');
                }
                Feeds::invalidate((int)self::$shop['id']);
            } catch (DomainException $e) {
                flash('error', $e->getMessage());
            }
            redirect('/admin/categories' . (input('id') && $op === 'save' ? '' : ''));
        }
        $edit = input('edit') ? row($db, 'SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL', [(int)input('edit')]) : null;
        $counts = [];
        foreach (rows($db, "SELECT pc.category_id, COUNT(*) n, SUM(p.status = 'active') a FROM product_categories pc JOIN products p ON p.id = pc.product_id AND p.deleted_at IS NULL GROUP BY pc.category_id") as $r) {
            $counts[$r['category_id']] = $r;
        }
        self::page('Κατηγορίες', 'categories', ['tree' => Catalog::categoryTree($db), 'flat' => Catalog::categoryFlat($db), 'edit' => $edit, 'counts' => $counts], 'categories');
    }

    /* ---------- orders ---------- */
    public static function orders(): void
    {
        self::need('orders');
        $db = self::$db;
        $where = ['1=1'];
        $p = [];
        if ($s = input('status')) { $where[] = 'status = ?'; $p[] = $s; }
        if ($ps = input('payment')) { $where[] = 'payment_status = ?'; $p[] = $ps; }
        if ($q = input('q')) { $where[] = '(number LIKE ? OR name LIKE ? OR email LIKE ? OR phone LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
        $page = max(1, (int)input('page', 1));
        $w = implode(' AND ', $where);
        $total = (int)val($db, "SELECT COUNT(*) FROM orders WHERE $w", $p);
        $list = rows($db, "SELECT * FROM orders WHERE $w ORDER BY id DESC LIMIT 50 OFFSET " . (($page - 1) * 50), $p);
        self::page('Παραγγελίες', 'orders', ['list' => $list, 'total' => $total, 'page' => $page], 'orders');
    }

    public static function order(string $id): void
    {
        self::need('orders');
        $db = self::$db;
        $o = row($db, 'SELECT * FROM orders WHERE id = ?', [(int)$id]);
        if (!$o) abort(404);
        if (is_post()) {
            $data = [];
            if (isset(Orders::STATUS[input('status')])) $data['status'] = input('status');
            if (isset(Payments::STATUS[input('payment_status')])) $data['payment_status'] = input('payment_status');
            if (isset(Orders::SHIPPING[input('shipping_status')])) $data['shipping_status'] = input('shipping_status');
            $data['tracking_number'] = Sanitizer::text(input('tracking_number'), 80) ?: null;
            $data['updated_at'] = now();
            // cancelling restores stock once
            if (($data['status'] ?? '') === 'cancelled' && $o['status'] !== 'cancelled' && input('restock') === '1') {
                foreach (rows($db, 'SELECT * FROM order_items WHERE order_id = ?', [$o['id']]) as $it) {
                    if ($it['variant_id']) {
                        q($db, 'UPDATE product_variants SET stock = stock + ? WHERE id = ?', [$it['qty'], $it['variant_id']]);
                        q($db, 'UPDATE products SET stock = (SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = ?) WHERE id = ?', [$it['product_id'], $it['product_id']]);
                    } elseif ($it['product_id']) {
                        q($db, 'UPDATE products SET stock = stock + ? WHERE id = ?', [$it['qty'], $it['product_id']]);
                    }
                }
            }
            update($db, 'orders', $data, 'id = ?', [$o['id']]);
            if ($note = Sanitizer::text(input('note'), 2000)) {
                insert($db, 'order_notes', ['order_id' => $o['id'], 'note' => $note, 'author' => self::$user['email'], 'created_at' => now()]);
            }
            Audit::log('order.updated', ['entity' => 'order', 'id' => $o['number'], 'meta' => $data]);
            if (input('notify') === '1' && ($data['status'] ?? '') !== $o['status']) {
                $c = self::settings_('company');
                Mailer::send($o['email'], 'Ενημέρωση παραγγελίας ' . $o['number'], 'Η παραγγελία σας ' . $o['number'] . ' είναι πλέον: ' . Orders::STATUS[$data['status']] . ($data['tracking_number'] ? "\nΑριθμός αποστολής: {$data['tracking_number']}" : '') . "\n\n" . ($c['name'] ?? ''), $c['email'] ?? null);
            }
            flash('success', 'Η παραγγελία ενημερώθηκε.');
            redirect('/admin/orders/' . $o['id']);
        }
        self::page('Παραγγελία ' . $o['number'], 'order', [
            'o' => $o, 'items' => rows($db, 'SELECT * FROM order_items WHERE order_id = ?', [$o['id']]),
            'notes' => rows($db, 'SELECT * FROM order_notes WHERE order_id = ? ORDER BY id DESC', [$o['id']]),
        ], 'orders');
    }

    public static function customers(): void
    {
        self::need('customers');
        $db = self::$db;
        $q = input('q');
        $p = [];
        $w = '1=1';
        if ($q) { $w = '(c.email LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)'; $p = ["%$q%", "%$q%", "%$q%"]; }
        $page = max(1, (int)input('page', 1));
        $total = (int)val($db, "SELECT COUNT(*) FROM customers c WHERE $w", $p);
        $list = rows($db, "SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) orders, (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id = c.id AND o.status <> 'cancelled') spent FROM customers c WHERE $w ORDER BY c.id DESC LIMIT 50 OFFSET " . (($page - 1) * 50), $p);
        self::page('Πελάτες', 'customers', ['list' => $list, 'total' => $total, 'page' => $page], 'customers');
    }

    public static function customer(string $id): void
    {
        self::need('customers');
        $c = row(self::$db, 'SELECT * FROM customers WHERE id = ?', [(int)$id]);
        if (!$c) abort(404);
        self::page($c['name'] ?: $c['email'], 'customer', [
            'c' => $c, 'orders' => rows(self::$db, 'SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC', [$c['id']]),
            'addresses' => rows(self::$db, 'SELECT * FROM addresses WHERE customer_id = ?', [$c['id']]),
        ], 'customers');
    }

    public static function subscribers(): void
    {
        self::need('popups');
        $db = self::$db;
        if (is_post()) {
            $sid = (int)input('id');
            if (input('op') === 'unsubscribe') {
                update($db, 'subscribers', ['unsubscribed_at' => now()], 'id = ?', [$sid]);
            } elseif (input('op') === 'delete') {
                q($db, 'DELETE FROM subscribers WHERE id = ?', [$sid]);
            }
            Audit::log('subscriber.' . input('op'), ['entity' => 'subscriber', 'id' => $sid]);
            flash('success', 'Ενημερώθηκε.');
            redirect('/admin/subscribers');
        }
        if (input('export') === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="subscribers-' . date('Ymd') . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['email', 'source', 'consent', 'consent_text', 'created_at', 'unsubscribed_at']);
            foreach (rows($db, 'SELECT * FROM subscribers ORDER BY id') as $s) {
                fputcsv($out, [$s['email'], $s['source'], $s['consent'], $s['consent_text'], $s['created_at'], $s['unsubscribed_at']]);
            }
            Audit::log('subscriber.export');
            exit;
        }
        self::page('Συνδρομητές newsletter', 'subscribers', ['list' => rows($db, 'SELECT * FROM subscribers ORDER BY id DESC LIMIT 1000')], 'subscribers');
    }

    public static function messages(): void
    {
        self::need('customers');
        $db = self::$db;
        if (is_post()) {
            if (input('op') === 'delete') q($db, 'DELETE FROM contact_messages WHERE id = ?', [(int)input('id')]);
            else update($db, 'contact_messages', ['read_at' => now()], 'id = ?', [(int)input('id')]);
            redirect('/admin/messages');
        }
        self::page('Μηνύματα επικοινωνίας', 'messages', ['list' => rows($db, 'SELECT * FROM contact_messages ORDER BY id DESC LIMIT 500')], 'messages');
    }

    /* ---------- pages & menus ---------- */
    public static function pages(): void
    {
        self::need('pages');
        self::page('Σελίδες', 'pages', ['list' => rows(self::$db, 'SELECT * FROM pages ORDER BY sort, id')], 'pages');
    }

    public static function pageEdit(string $id): void
    {
        self::need('pages');
        $db = self::$db;
        $pg = $id === 'new' ? null : row($db, 'SELECT * FROM pages WHERE id = ?', [(int)$id]);
        if ($id !== 'new' && !$pg) abort(404);
        $reserved = ['admin', 'products', 'product', 'category', 'search', 'cart', 'checkout', 'order', 'wishlist', 'feed', 'uploads', 'assets', 'newsletter', 'unsubscribe', 'sitemap-xml', 'robots-txt', 'llms-txt'];
        if (is_post()) {
            if ($pg && input('op') === 'delete') {
                if (in_array($pg['slug'], ['terms', 'privacy', 'contact'], true)) {
                    flash('error', 'Οι σελίδες όρων, απορρήτου και επικοινωνίας δεν διαγράφονται· μπορείτε να τις επεξεργαστείτε.');
                } else {
                    q($db, 'DELETE FROM pages WHERE id = ?', [$pg['id']]);
                    Audit::log('page.deleted', ['entity' => 'page', 'id' => $pg['id']]);
                    flash('success', 'Η σελίδα διαγράφηκε.');
                }
                redirect('/admin/pages');
            }
            $title = Sanitizer::text(input('title'), 200);
            $slug = slugify(input('slug') ?: $title);
            $err = null;
            if ($title === '') $err = 'Συμπληρώστε τίτλο.';
            elseif (in_array($slug, $reserved, true)) $err = 'Το slug είναι δεσμευμένο από το σύστημα.';
            elseif (val($db, 'SELECT 1 FROM pages WHERE slug = ? AND id <> ?', [$slug, $pg['id'] ?? 0])) $err = 'Υπάρχει ήδη σελίδα με αυτό το slug.';
            if ($err) {
                flash('error', $err);
            } else {
                $data = ['title' => $title, 'slug' => $pg && in_array($pg['slug'], ['terms', 'privacy', 'contact'], true) ? $pg['slug'] : $slug,
                    'body' => Sanitizer::html((string)($_POST['body'] ?? '')), 'seo_title' => Sanitizer::text(input('seo_title'), 200) ?: null,
                    'seo_description' => Sanitizer::text(input('seo_description'), 320) ?: null, 'status' => input('status') === 'draft' ? 'draft' : 'published',
                    'in_footer' => input('in_footer') === '1' ? 1 : 0, 'sort' => (int)input('sort'), 'updated_at' => now()];
                if ($pg) update($db, 'pages', $data, 'id = ?', [$pg['id']]);
                else $pg = ['id' => insert($db, 'pages', $data)];
                Audit::log('page.saved', ['entity' => 'page', 'id' => $pg['id']]);
                flash('success', 'Η σελίδα αποθηκεύτηκε.');
                redirect('/admin/pages/' . $pg['id']);
            }
        }
        self::page($pg ? 'Σελίδα: ' . $pg['title'] : 'Νέα σελίδα', 'page_edit', ['pg' => $pg], 'pages');
    }

    public static function menus(): void
    {
        self::need('pages');
        $db = self::$db;
        $menus = rows($db, 'SELECT * FROM menus ORDER BY id');
        if (is_post()) {
            $mid = (int)input('menu_id');
            if (!val($db, 'SELECT 1 FROM menus WHERE id = ?', [$mid])) abort(404);
            $op = input('op');
            if ($op === 'add') {
                $type = input('type');
                $label = Sanitizer::text(input('label'), 80);
                $ref = (int)input('ref_id') ?: null;
                $url = Sanitizer::url(input('url'));
                if ($type === 'category' && $ref) $label = $label ?: (string)val($db, 'SELECT name FROM categories WHERE id = ?', [$ref]);
                if ($type === 'page' && $ref) $label = $label ?: (string)val($db, 'SELECT title FROM pages WHERE id = ?', [$ref]);
                if ($type === 'product' && $ref) $label = $label ?: (string)val($db, 'SELECT name FROM products WHERE id = ?', [$ref]);
                if (!in_array($type, ['category', 'page', 'product', 'url'], true) || $label === '' || ($type === 'url' && $url === '') || ($type !== 'url' && !$ref)) {
                    flash('error', 'Συμπληρώστε τα στοιχεία του συνδέσμου.');
                } else {
                    insert($db, 'menu_items', ['menu_id' => $mid, 'parent_id' => (int)input('parent_id') ?: null, 'label' => $label, 'type' => $type, 'ref_id' => $ref, 'url' => $type === 'url' ? $url : null, 'sort' => (int)val($db, 'SELECT COALESCE(MAX(sort),0)+1 FROM menu_items WHERE menu_id = ?', [$mid])]);
                    flash('success', 'Ο σύνδεσμος προστέθηκε.');
                }
            } elseif ($op === 'delete') {
                q($db, 'DELETE FROM menu_items WHERE (id = ? OR parent_id = ?) AND menu_id = ?', [(int)input('item_id'), (int)input('item_id'), $mid]);
            } elseif ($op === 'move') {
                $it = row($db, 'SELECT * FROM menu_items WHERE id = ? AND menu_id = ?', [(int)input('item_id'), $mid]);
                if ($it) {
                    $dir = input('dir') === 'up' ? '<' : '>';
                    $ord = $dir === '<' ? 'DESC' : 'ASC';
                    $sw = row($db, "SELECT * FROM menu_items WHERE menu_id = ? AND ifnull(parent_id,0) = ? AND sort $dir ? ORDER BY sort $ord LIMIT 1", [$mid, $it['parent_id'] ?? 0, $it['sort']]);
                    if ($sw) {
                        update($db, 'menu_items', ['sort' => $sw['sort']], 'id = ?', [$it['id']]);
                        update($db, 'menu_items', ['sort' => $it['sort']], 'id = ?', [$sw['id']]);
                    }
                }
            }
            Audit::log('menu.' . $op, ['entity' => 'menu', 'id' => $mid]);
            redirect('/admin/menus');
        }
        $items = [];
        foreach ($menus as $m) {
            $items[$m['id']] = rows($db, 'SELECT * FROM menu_items WHERE menu_id = ? ORDER BY ifnull(parent_id, id), parent_id IS NOT NULL, sort', [$m['id']]);
        }
        self::page('Μενού', 'menus', ['menus' => $menus, 'items' => $items, 'cats' => Catalog::categoryFlat($db), 'pages' => rows($db, 'SELECT id, title FROM pages ORDER BY sort')], 'menus');
    }

    /* ---------- media ---------- */
    public static function media(): void
    {
        self::need('media');
        $db = self::$db;
        if (is_post()) {
            if (input('op') === 'delete') {
                Media::delete($db, (int)input('id'));
                Audit::log('media.deleted', ['entity' => 'media', 'id' => input('id')]);
                flash('success', 'Η εικόνα διαγράφηκε.');
            } elseif (input('op') === 'alt') {
                update($db, 'media', ['alt' => Sanitizer::text(input('alt'), 200)], 'id = ?', [(int)input('id')]);
                flash('success', 'Το εναλλακτικό κείμενο αποθηκεύτηκε.');
            } else {
                $f = $_FILES['files'] ?? null;
                $n = 0;
                if ($f && is_array($f['name'])) {
                    foreach ($f['name'] as $i => $name) {
                        if ($f['error'][$i] !== UPLOAD_ERR_OK) continue;
                        try {
                            Media::store($db, (int)self::$shop['id'], $f['tmp_name'][$i], $name);
                            $n++;
                        } catch (DomainException $e) {
                            flash('error', "«$name»: " . $e->getMessage());
                        }
                    }
                }
                if ($n) flash('success', "Ανέβηκαν $n εικόνες.");
            }
            redirect('/admin/media');
        }
        $page = max(1, (int)input('page', 1));
        $total = (int)val($db, 'SELECT COUNT(*) FROM media');
        self::page('Πολυμέσα', 'media', ['list' => rows($db, 'SELECT * FROM media ORDER BY id DESC LIMIT 60 OFFSET ' . (($page - 1) * 60)), 'total' => $total, 'page' => $page], 'media');
    }

    /* ---------- appearance ---------- */
    public static function appearance(): void
    {
        self::need('appearance');
        $db = self::$db;
        $manifest = Theme::manifest(self::$shop['template_code']);
        $theme = self::settings_('theme', []);
        if (is_post()) {
            if (input('op') === 'reset') {
                $theme['tokens'] = [];
            } else {
                $tokens = [];
                foreach ($manifest['tokens'] as $k => $def) {
                    $v = Sanitizer::color(input('t_' . $k), $def['default']);
                    if ($v !== strtoupper($def['default'])) $tokens[$k] = $v;
                }
                $theme['tokens'] = $tokens;
                foreach (['logo', 'favicon'] as $kind) {
                    if (input('remove_' . $kind) === '1') $theme[$kind] = null;
                    if (($_FILES[$kind]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        try {
                            $mid = Media::store($db, (int)self::$shop['id'], $_FILES[$kind]['tmp_name'], $_FILES[$kind]['name'], $kind);
                            $m = row($db, 'SELECT * FROM media WHERE id = ?', [$mid]);
                            $theme[$kind] = '/uploads/' . $m['path'];
                        } catch (DomainException $e) {
                            flash('error', $e->getMessage());
                        }
                    }
                }
            }
            setting_set($db, 'theme', $theme);
            Audit::log('appearance.saved');
            flash('success', 'Η εμφάνιση αποθηκεύτηκε.');
            redirect('/admin/appearance');
        }
        $tokens = Theme::tokens($manifest, $theme['tokens'] ?? []);
        $warnings = [];
        foreach ($manifest['contrast_pairs'] ?? [] as [$a, $b]) {
            $r = Theme::contrast($tokens[$a], $tokens[$b]);
            if ($r < 4.5) $warnings[] = sprintf('Χαμηλή αντίθεση (%.1f:1) μεταξύ «%s» και «%s». Το κείμενο μπορεί να μη διαβάζεται εύκολα.', $r, $manifest['tokens'][$a]['label'], $manifest['tokens'][$b]['label']);
        }
        self::page('Εμφάνιση', 'appearance', ['manifest' => $manifest, 'tokens' => $tokens, 'theme' => $theme, 'warnings' => $warnings], 'appearance');
    }

    public static function homepage(): void
    {
        self::need('appearance');
        $db = self::$db;
        $h = self::settings_('homepage', []);
        if (is_post()) {
            $h['hero'] = [
                'eyebrow' => Sanitizer::text(input('hero_eyebrow'), 80), 'title' => Sanitizer::text(input('hero_title'), 120),
                'subtitle' => Sanitizer::text(input('hero_subtitle'), 300), 'cta_label' => Sanitizer::text(input('hero_cta_label'), 40),
                'cta_url' => Sanitizer::url(input('hero_cta_url')) ?: '/products/', 'image' => $h['hero']['image'] ?? null,
            ];
            if (input('hero_image_remove') === '1') $h['hero']['image'] = null;
            if (($_FILES['hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                try {
                    $mid = Media::store($db, (int)self::$shop['id'], $_FILES['hero_image']['tmp_name'], $_FILES['hero_image']['name'], $h['hero']['title']);
                    $h['hero']['image'] = $mid;
                } catch (DomainException $e) {
                    flash('error', $e->getMessage());
                }
            }
            $h['promo'] = ['title' => Sanitizer::text(input('promo_title'), 60), 'text' => Sanitizer::text(input('promo_text'), 160)];
            $h['marquee'] = array_values(array_filter(array_map(fn($x) => Sanitizer::text($x, 60), explode("\n", (string)input('marquee')))));
            $h['show_categories'] = input('show_categories') === '1';
            $h['show_featured'] = input('show_featured') === '1';
            $h['featured_title'] = Sanitizer::text(input('featured_title'), 60);
            $h['values'] = [];
            for ($i = 0; $i < 3; $i++) {
                $h['values'][] = ['title' => Sanitizer::text($_POST['values'][$i]['title'] ?? '', 60), 'text' => Sanitizer::text($_POST['values'][$i]['text'] ?? '', 200)];
            }
            $h['newsletter'] = ['show' => input('nl_show') === '1', 'title' => Sanitizer::text(input('nl_title'), 80), 'text' => Sanitizer::text(input('nl_text'), 200)];
            $h['faq'] = [];
            foreach ((array)($_POST['faq'] ?? []) as $f) {
                $q = Sanitizer::text($f['q'] ?? '', 200);
                $a = Sanitizer::text($f['a'] ?? '', 1000);
                if ($q !== '' && $a !== '') $h['faq'][] = ['q' => $q, 'a' => $a];
            }
            $h['confirmed'] = true;
            setting_set($db, 'homepage', $h);
            Audit::log('homepage.saved');
            flash('success', 'Η αρχική σελίδα αποθηκεύτηκε.');
            redirect('/admin/homepage');
        }
        $heroImg = !empty($h['hero']['image']) ? row($db, 'SELECT * FROM media WHERE id = ?', [(int)$h['hero']['image']]) : null;
        self::page('Αρχική σελίδα', 'homepage', ['h' => $h, 'heroImg' => $heroImg], 'homepage');
    }

    public static function popup(): void
    {
        self::need('popups');
        self::feature('popups');
        $db = self::$db;
        $p = self::settings_('popup', []);
        if (is_post()) {
            $p = [
                'active' => input('active') === '1', 'title' => Sanitizer::text(input('title'), 100), 'body' => Sanitizer::text(input('body'), 400),
                'button_text' => Sanitizer::text(input('button_text'), 40), 'link' => Sanitizer::url(input('link')), 'collect_email' => input('collect_email') === '1',
                'consent_required' => input('consent_required') === '1', 'consent_text' => Sanitizer::text(input('consent_text'), 300),
                'delay' => max(0, min(120, (int)input('delay'))), 'version' => ($p['version'] ?? 0) + 1,
            ];
            setting_set($db, 'popup', $p);
            Audit::log('popup.saved');
            flash('success', 'Το pop-up αποθηκεύτηκε.');
            redirect('/admin/popup');
        }
        self::page('Pop-up αρχικής', 'popup', ['p' => $p, 'subs' => (int)val($db, 'SELECT COUNT(*) FROM subscribers WHERE unsubscribed_at IS NULL')], 'popup');
    }

    /* ---------- feeds ---------- */
    public static function feeds(): void
    {
        self::need('feeds');
        self::feature('feeds');
        $db = self::$db;
        $shopId = (int)self::$shop['id'];
        if (is_post()) {
            $op = input('op');
            $fid = (int)input('id');
            if ($op === 'toggle') {
                q($db, "UPDATE feeds SET status = CASE status WHEN 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?", [$fid]);
            } elseif ($op === 'generate') {
                $f = row($db, 'SELECT * FROM feeds WHERE id = ?', [$fid]);
                if ($f) {
                    $n = Feeds::generate($db, $shopId, $f, Shops::url($shopId, ''));
                    flash('success', "Το feed δημιουργήθηκε με $n προϊόντα.");
                }
            } elseif ($op === 'save') {
                $name = Sanitizer::text(input('name'), 80);
                $slug = slugify(input('slug') ?: $name);
                $type = isset(Feeds::TYPES[input('type')]) ? input('type') : 'generic';
                $fields = array_values(array_intersect((array)($_POST['fields'] ?? []), Feeds::GENERIC_FIELDS)) ?: Feeds::GENERIC_FIELDS;
                $filters = ['categories' => array_map('intval', (array)($_POST['categories'] ?? [])), 'in_stock' => input('in_stock') === '1'];
                if ($name === '' || val($db, 'SELECT 1 FROM feeds WHERE slug = ? AND id <> ?', [$slug, $fid])) {
                    flash('error', 'Συμπληρώστε μοναδικό όνομα/slug.');
                } else {
                    $data = ['name' => $name, 'slug' => $slug, 'type' => $type, 'fields' => json_encode($fields), 'filters' => json_encode($filters), 'frequency_minutes' => max(5, (int)input('frequency') ?: 60)];
                    if ($fid) update($db, 'feeds', $data, 'id = ?', [$fid]);
                    else insert($db, 'feeds', $data + ['status' => 'active', 'created_at' => now()]);
                    flash('success', 'Το feed αποθηκεύτηκε.');
                }
            } elseif ($op === 'delete') {
                q($db, 'DELETE FROM feeds WHERE id = ?', [$fid]);
                @unlink(Feeds::file($shopId, (string)input('slug')));
            }
            Audit::log('feed.' . $op, ['entity' => 'feed', 'id' => $fid]);
            redirect('/admin/feeds');
        }
        $edit = input('edit') ? row($db, 'SELECT * FROM feeds WHERE id = ?', [(int)input('edit')]) : null;
        $feeds = rows($db, 'SELECT f.*, (SELECT items FROM feed_logs l WHERE l.feed_id = f.id ORDER BY id DESC LIMIT 1) last_items FROM feeds f ORDER BY id');
        self::page('XML feeds', 'feeds', ['feeds' => $feeds, 'edit' => $edit, 'cats' => Catalog::categoryFlat($db), 'base' => Shops::url($shopId, '')], 'feeds');
    }

    /* ---------- import / export ---------- */
    public static function import(): void
    {
        self::need('imports');
        $db = self::$db;
        if (is_post()) {
            self::feature('import');
            try {
                Backup::shop((int)self::$shop['id'], 'before-import');
                $id = Importer::upload($db, (int)self::$shop['id'], $_FILES['file'] ?? [], self::$user['email']);
                Audit::log('import.uploaded', ['entity' => 'import', 'id' => $id]);
                redirect('/admin/import/' . $id);
            } catch (DomainException $e) {
                flash('error', $e->getMessage());
                redirect('/admin/import');
            }
        }
        self::page('Εισαγωγή / εξαγωγή', 'import', [
            'history' => rows($db, 'SELECT * FROM imports ORDER BY id DESC LIMIT 30'), 'cats' => Catalog::categoryFlat($db),
            'canImport' => Shops::feature(self::$shop, 'import'), 'canExport' => Shops::feature(self::$shop, 'export'),
        ], 'import');
    }

    public static function importView(string $id): void
    {
        self::need('imports');
        self::feature('import');
        $db = self::$db;
        $imp = row($db, 'SELECT * FROM imports WHERE id = ?', [(int)$id]);
        if (!$imp) abort(404);
        if (is_post() && in_array($imp['status'], ['validated', 'queued'], true)) {
            try {
                $map = [];
                foreach ((array)($_POST['map'] ?? []) as $k => $field) {
                    if ($field !== '') $map[(string)$k] = $field;
                }
                // header names may contain characters PHP rewrites in POST keys, so they are posted by index
                $headers = (json_decode($imp['options'], true) ?: [])['headers'] ?? [];
                $byName = [];
                foreach ($map as $idx => $field) {
                    if (isset($headers[(int)$idx])) $byName[$headers[(int)$idx]] = $field;
                }
                Importer::confirm($db, (int)$id, $byName, $_POST);
                Audit::log('import.confirmed', ['entity' => 'import', 'id' => $id]);
                redirect('/admin/import/' . $id);
            } catch (DomainException $e) {
                flash('error', $e->getMessage());
                redirect('/admin/import/' . $id);
            }
        }
        $opts = json_decode($imp['options'], true) ?: [];
        $check = $imp['status'] === 'validated' ? Importer::check($db, $imp) : null;
        self::page('Εισαγωγή #' . $imp['id'] . ' · ' . $imp['filename'], 'import_view', [
            'imp' => $imp, 'opts' => $opts, 'map' => json_decode($imp['mapping'], true) ?: [], 'check' => $check,
            'errors' => rows($db, 'SELECT * FROM import_rows WHERE import_id = ? ORDER BY row_no LIMIT 500', [$imp['id']]),
            'logs' => rows($db, 'SELECT * FROM import_logs WHERE import_id = ? ORDER BY id DESC', [$imp['id']]),
            'room' => max(0, self::limit() - Catalog::activeCount($db)),
        ], 'import');
    }

    public static function importRun(string $id): void
    {
        self::need('imports');
        self::feature('import');
        try {
            $p = Importer::batch(self::$db, (int)$id, 250, self::limit(), Shops::storedLimit(self::$shop));
            if (($p['status'] ?? '') === 'done') {
                Feeds::invalidate((int)self::$shop['id']);
                Audit::log('import.done', ['entity' => 'import', 'id' => $id, 'meta' => $p]);
                Notify::add('import.done', 'Εισαγωγή ολοκληρώθηκε: ' . self::$shop['name'], "Εισαγωγή #$id: {$p['created']} νέα, {$p['updated']} ενημερώσεις, {$p['errors']} σφάλματα.", (int)self::$shop['id'], 'info', 'import:' . self::$shop['id'] . ':' . $id, 'both');
            }
            json_out($p);
        } catch (Throwable $e) {
            log_error('import: ' . $e->getMessage());
            Notify::add('import.failed', 'Εισαγωγή απέτυχε: ' . self::$shop['name'], "Εισαγωγή #$id: " . $e->getMessage(), (int)self::$shop['id'], 'danger', 'importfail:' . self::$shop['id'] . ':' . $id, 'both');
            json_out(['error' => 'Η εισαγωγή σταμάτησε λόγω σφάλματος. Δείτε το αρχείο καταγραφής της εισαγωγής.'], 500);
        }
    }

    public static function export(): void
    {
        self::need('imports');
        self::feature('export');
        $scope = input('scope');
        self::exportCsv(in_array($scope, ['all', 'active', 'inactive', 'category'], true) ? $scope : 'all', [], (int)input('cat'));
    }

    private static function exportCsv(string $scope, array $ids = [], int $cat = 0): never
    {
        $db = self::$db;
        $where = 'p.deleted_at IS NULL';
        if ($scope === 'active') $where .= " AND p.status = 'active'";
        if ($scope === 'inactive') $where .= " AND p.status = 'inactive'";
        if ($scope === 'category' && $cat) $where .= ' AND p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . implode(',', Catalog::descendantIds($db, $cat)) . '))';
        if ($scope === 'selected') $where .= ' AND p.id IN (' . (implode(',', array_map('intval', $ids)) ?: '0') . ')';
        Audit::log('product.export', ['meta' => ['scope' => $scope]]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products-' . $scope . '-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['sku', 'name', 'price', 'sale_price', 'cost_price', 'stock', 'category', 'brand', 'barcode', 'mpn', 'short_description', 'description', 'weight', 'image', 'images', 'status', 'seo_title', 'seo_description', 'slug']);
        $paths = [];
        foreach (Catalog::categoryFlat($db) as $c) {
            $paths[$c['id']] = implode(' > ', array_column(Catalog::categoryPath($db, (int)$c['id']), 'name'));
        }
        $base = Shops::url((int)self::$shop['id'], '');
        $last = 0;
        while ($batch = rows($db, "SELECT p.* FROM products p WHERE $where AND p.id > ? ORDER BY p.id LIMIT 500", [$last])) {
            $imgs = [];
            foreach (rows($db, 'SELECT pi.*, m.path, m.variants FROM product_images pi LEFT JOIN media m ON m.id = pi.media_id WHERE pi.product_id IN (' . implode(',', array_column($batch, 'id')) . ') ORDER BY pi.sort, pi.id') as $r) {
                $u = Media::urlsFor($r)['full'];
                $imgs[$r['product_id']][] = str_starts_with($u, 'http') ? $u : $base . $u;
            }
            foreach ($batch as $p) {
                $last = (int)$p['id'];
                $im = $imgs[$p['id']] ?? [];
                fputcsv($out, [$p['sku'], $p['name'], $p['price'], $p['sale_price'], $p['cost_price'], $p['stock'], $paths[$p['category_id']] ?? '', $p['brand'], $p['barcode'], $p['mpn'],
                    $p['short_description'], $p['description'], $p['weight'], $im[0] ?? '', implode('|', array_slice($im, 1)), $p['status'] === 'active' ? 1 : 0, $p['seo_title'], $p['seo_description'], $p['slug']]);
            }
        }
        fclose($out);
        exit;
    }

    /* ---------- SEO ---------- */
    public static function seo(): void
    {
        self::need('seo');
        $db = self::$db;
        $s = self::settings_('seo', []);
        if (is_post()) {
            $ga = strtoupper(input('ga4_id'));
            $s = [
                'home_title' => Sanitizer::text(input('home_title'), 70), 'home_description' => Sanitizer::text(input('home_description'), 170),
                'ga4_id' => preg_match('/^G-[A-Z0-9]{4,15}$/', $ga) ? $ga : '', 'gsc_verification' => preg_replace('/[^A-Za-z0-9_\-]/', '', input('gsc_verification')),
                'same_as' => array_values(array_filter(array_map(fn($u) => Sanitizer::url(trim($u)), explode("\n", (string)input('same_as'))))),
                'llms_extra' => Sanitizer::text(input('llms_extra'), 3000),
            ];
            setting_set($db, 'seo', $s);
            Audit::log('seo.saved');
            flash('success', 'Οι ρυθμίσεις SEO αποθηκεύτηκαν.' . (input('ga4_id') && !$s['ga4_id'] ? ' Το GA4 ID δεν είναι έγκυρο (μορφή G-XXXXXXX).' : ''));
            redirect('/admin/seo');
        }
        $report = [
            'no_seo_desc' => (int)val($db, "SELECT COUNT(*) FROM products WHERE status = 'active' AND deleted_at IS NULL AND ifnull(seo_description,'') = '' AND ifnull(short_description,'') = ''"),
            'no_image' => (int)val($db, "SELECT COUNT(*) FROM products p WHERE status = 'active' AND deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id)"),
            'no_category' => (int)val($db, "SELECT COUNT(*) FROM products WHERE status = 'active' AND deleted_at IS NULL AND category_id IS NULL"),
            'no_desc_cats' => (int)val($db, "SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL AND ifnull(description,'') = ''"),
            'no_brand' => (int)val($db, "SELECT COUNT(*) FROM products WHERE status = 'active' AND deleted_at IS NULL AND ifnull(brand,'') = ''"),
            'no_ean' => (int)val($db, "SELECT COUNT(*) FROM products WHERE status = 'active' AND deleted_at IS NULL AND ifnull(barcode,'') = ''"),
            'dup_titles' => rows($db, "SELECT name, COUNT(*) n FROM products WHERE status = 'active' AND deleted_at IS NULL GROUP BY lower(name) HAVING n > 1 LIMIT 20"),
        ];
        self::page('SEO & AI search', 'seo', ['s' => $s, 'report' => $report, 'base' => Shops::url((int)self::$shop['id'], '')], 'seo');
    }

    /* ---------- settings ---------- */
    public static function settings(): void
    {
        self::need('settings');
        $db = self::$db;
        if (is_post()) {
            $section = input('section');
            if ($section === 'company') {
                $c = self::settings_('company', []);
                foreach (['name' => 150, 'legal_name' => 200, 'address' => 250, 'phone' => 40, 'hours' => 200, 'vat_number' => 20] as $k => $max) {
                    $c[$k] = Sanitizer::text(input($k), $max);
                }
                $c['email'] = filter_var(input('email'), FILTER_VALIDATE_EMAIL) ? strtolower(input('email')) : '';
                foreach (['facebook', 'instagram', 'tiktok', 'youtube'] as $sn) {
                    $c['social'][$sn] = Sanitizer::url(input('social_' . $sn));
                }
                setting_set($db, 'company', $c);
            } elseif ($section === 'shipping') {
                setting_set($db, 'shipping', ['flat' => max(0, to_float(input('flat')) ?? 0), 'free_over' => max(0, to_float(input('free_over')) ?? 0), 'islands_extra' => max(0, to_float(input('islands_extra')) ?? 0), 'note' => Sanitizer::text(input('note'), 300), 'confirmed' => true]);
            } elseif ($section === 'payments') {
                $p = self::settings_('payments', []);
                $p['cod'] = ['enabled' => input('cod_enabled') === '1', 'fee' => max(0, to_float(input('cod_fee')) ?? 0)];
                $p['bank'] = ['enabled' => input('bank_enabled') === '1', 'details' => Sanitizer::text(input('bank_details'), 1000)];
                $prov = in_array(input('card_provider'), ['none', 'stripe', 'viva'], true) ? input('card_provider') : 'none';
                $keep = fn($new, $old) => $new !== '' ? $new : ($old ?? '');
                $p['card'] = [
                    'provider' => $prov,
                    'stripe' => ['publishable_key' => Sanitizer::text(input('stripe_pk'), 200), 'secret_key' => $keep(trim((string)($_POST['stripe_sk'] ?? '')), $p['card']['stripe']['secret_key'] ?? ''), 'webhook_secret' => $keep(trim((string)($_POST['stripe_wh'] ?? '')), $p['card']['stripe']['webhook_secret'] ?? '')],
                    'viva' => ['env' => input('viva_env') === 'live' ? 'live' : 'demo', 'client_id' => Sanitizer::text(input('viva_client_id'), 200), 'client_secret' => $keep(trim((string)($_POST['viva_secret'] ?? '')), $p['card']['viva']['client_secret'] ?? ''), 'source_code' => Sanitizer::text(input('viva_source'), 20)],
                ];
                $p['confirmed'] = true;
                setting_set($db, 'payments', $p);
                if (!Payments::available($p)) flash('warning', 'Δεν υπάρχει ενεργός τρόπος πληρωμής· το ταμείο δεν θα δέχεται παραγγελίες.');
            }
            Audit::log('settings.' . $section);
            flash('success', 'Οι ρυθμίσεις αποθηκεύτηκαν.');
            redirect('/admin/settings#' . $section);
        }
        self::page('Ρυθμίσεις καταστήματος', 'settings', ['company' => self::settings_('company'), 'ship' => self::settings_('shipping'), 'pay' => self::settings_('payments')], 'settings');
    }

    /* ---------- users / staff ---------- */
    public static function users(): void
    {
        self::need('users');
        $pdb = DB::platform();
        $shopId = (int)self::$shop['id'];
        $me = self::$user;
        if (is_post()) {
            $op = input('op');
            try {
                $perms = array_values(array_intersect((array)($_POST['perms'] ?? []), array_keys(Perm::MODULES)));
                $role = isset(Perm::ROLES[input('role')]) ? input('role') : 'custom';
                if (isset(Perm::PRESETS[$role]) && $role !== 'custom') $perms = Perm::PRESETS[$role];
                if ($role === 'owner' && $me['role'] !== 'owner') throw new DomainException('Μόνο ο ιδιοκτήτης μπορεί να ορίσει άλλον ιδιοκτήτη.');
                // cannot grant more than you have
                if ($me['role'] !== 'owner') {
                    $extra = array_diff($perms, $me['perms']);
                    if ($extra) throw new DomainException('Δεν μπορείτε να δώσετε δικαιώματα που δεν έχετε: ' . implode(', ', array_map(fn($x) => Perm::MODULES[$x], $extra)) . '.');
                }
                if ($op === 'create') {
                    self::feature('staff');
                    $count = (int)val($pdb, "SELECT COUNT(*) FROM users WHERE shop_id = ? AND deleted_at IS NULL AND status = 'active'", [$shopId]);
                    if ($count >= (int)self::$shop['staff_limit']) throw new DomainException('Το πακέτο σας επιτρέπει έως ' . (int)self::$shop['staff_limit'] . ' χρήστες.');
                    $email = strtolower(input('email'));
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new DomainException('Μη έγκυρο email.');
                    if (val($pdb, 'SELECT 1 FROM users WHERE lower(email) = ? AND shop_id = ? AND deleted_at IS NULL', [$email, $shopId])) throw new DomainException('Υπάρχει ήδη χρήστης με αυτό το email.');
                    $pass = (string)($_POST['password'] ?? '');
                    if ($pw = Auth::passwordProblem($pass)) throw new DomainException($pw);
                    $uid = insert($pdb, 'users', ['shop_id' => $shopId, 'email' => $email, 'name' => Sanitizer::text(input('name'), 120), 'password_hash' => Auth::hashPassword($pass), 'role' => $role, 'permissions' => json_encode($perms), 'must_change_password' => 1, 'created_by' => $me['id'], 'created_at' => now()]);
                    Audit::log('user.created', ['entity' => 'user', 'id' => $uid, 'meta' => ['role' => $role, 'perms' => $perms]]);
                    flash('success', 'Ο χρήστης δημιουργήθηκε. Θα του ζητηθεί αλλαγή κωδικού στην πρώτη σύνδεση.');
                } else {
                    $uid = (int)input('id');
                    $u = row($pdb, 'SELECT * FROM users WHERE id = ? AND shop_id = ? AND deleted_at IS NULL', [$uid, $shopId]);
                    if (!$u) throw new DomainException('Ο χρήστης δεν βρέθηκε.');
                    if ($uid === (int)$me['id']) throw new DomainException('Δεν μπορείτε να αλλάξετε τα δικά σας δικαιώματα.');
                    if ($u['role'] === 'owner' && $me['role'] !== 'owner') throw new DomainException('Μόνο ο ιδιοκτήτης μπορεί να αλλάξει άλλον ιδιοκτήτη.');
                    if ($op === 'update') {
                        update($pdb, 'users', ['role' => $role, 'permissions' => json_encode($perms), 'updated_at' => now()], 'id = ?', [$uid]);
                    } elseif ($op === 'disable' || $op === 'enable') {
                        update($pdb, 'users', ['status' => $op === 'disable' ? 'disabled' : 'active'], 'id = ?', [$uid]);
                    } elseif ($op === 'delete') {
                        update($pdb, 'users', ['deleted_at' => now(), 'status' => 'disabled'], 'id = ?', [$uid]);
                    }
                    Audit::log('user.' . $op, ['entity' => 'user', 'id' => $uid]);
                    flash('success', 'Ο χρήστης ενημερώθηκε.');
                }
            } catch (DomainException $e) {
                flash('error', $e->getMessage());
            }
            redirect('/admin/users');
        }
        $users = rows($pdb, 'SELECT * FROM users WHERE shop_id = ? AND deleted_at IS NULL ORDER BY id', [$shopId]);
        self::page('Χρήστες', 'users', ['users' => $users, 'canStaff' => Shops::feature(self::$shop, 'staff')], 'users');
    }

    public static function account(): void
    {
        if (is_post()) {
            $cur = (string)($_POST['current'] ?? '');
            $new = (string)($_POST['password'] ?? '');
            if (!password_verify($cur, self::$user['password_hash'])) {
                flash('error', 'Ο τρέχων κωδικός είναι λάθος.');
            } elseif ($p = Auth::passwordProblem($new)) {
                flash('error', $p);
            } elseif ($new !== ($_POST['password2'] ?? '')) {
                flash('error', 'Οι νέοι κωδικοί δεν ταιριάζουν.');
            } else {
                update(DB::platform(), 'users', ['password_hash' => Auth::hashPassword($new), 'must_change_password' => 0, 'updated_at' => now()], 'id = ?', [self::$user['id']]);
                Audit::log('user.password_changed');
                flash('success', 'Ο κωδικός άλλαξε.');
                redirect('/admin/dashboard');
            }
            redirect('/admin/account');
        }
        self::page('Ο λογαριασμός μου', 'account', [], 'account');
    }
}
