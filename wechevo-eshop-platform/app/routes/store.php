<?php
/* Public storefront. Order of checks (spec §51): shop status → product status. */

final class StoreRoutes
{
    public static array $shop;
    public static PDO $db;
    public static array $ctx = [];

    public static function dispatch(array $shop, string $path): void
    {
        self::$shop = $shop;
        send_security_headers(false);

        // 1. Shop must be serving. Locked / suspended → lock page for EVERY public URL (feeds, sitemap, API included).
        if (!Shops::isServing($shop)) {
            http_response_code(503);
            header('Retry-After: 86400');
            header('X-Robots-Tag: noindex');
            echo render(APP . '/views/shared/lock.php');
            return;
        }
        self::$db = DB::shop((int)$shop['id']);

        // machine files (no session)
        if ($path === '/robots.txt') {
            header('Content-Type: text/plain; charset=utf-8');
            echo Seo::robots(self::base());
            return;
        }
        if ($path === '/sitemap.xml') {
            header('Content-Type: application/xml; charset=utf-8');
            echo Seo::sitemap(self::$db, self::base());
            return;
        }
        if ($path === '/llms.txt') {
            header('Content-Type: text/plain; charset=utf-8');
            echo Seo::llms(self::$db, self::base(), setting_get(self::$db, 'company', []), setting_get(self::$db, 'seo', []));
            return;
        }
        if (preg_match('#^/feed/(?P<slug>[a-z0-9-]+)\.xml$#', $path, $m)) {
            self::feed($m['slug']);
            return;
        }

        // canonical trailing slash for HTML pages
        if ($path !== '/' && !str_ends_with($path, '/') && !preg_match('/\.[a-z0-9]{2,4}$/i', $path)) {
            $qs = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
            redirect($path . '/' . $qs, 301);
        }

        Session::start('store');
        if (is_post()) {
            Csrf::check();
        }
        self::context();

        $r = new Router();
        $r->add('GET', '/', [self::class, 'home']);
        $r->add('GET', '/products/', [self::class, 'products']);
        $r->add('GET', '/category/(?P<path>[a-z0-9-]+(?:/[a-z0-9-]+)*)/', [self::class, 'category']);
        $r->add('GET', '/product/(?P<slug>[a-z0-9-]+)/', [self::class, 'product']);
        $r->add('GET', '/search/', [self::class, 'search']);
        $r->add('GET|POST', '/cart/', [self::class, 'cart']);
        $r->add('GET|POST', '/checkout/', [self::class, 'checkout']);
        $r->add('GET', '/order/(?P<number>[0-9]{5,12})/', [self::class, 'order']);
        $r->add('GET|POST', '/wishlist/', [self::class, 'wishlist']);
        $r->add('POST', '/newsletter/', [self::class, 'newsletter']);
        $r->add('GET', '/unsubscribe/', [self::class, 'unsubscribe']);
        $r->add('GET|POST', '/contact/', [self::class, 'contact']);
        $r->add('GET', '/(?P<slug>[a-z0-9-]+)/', [self::class, 'page']);
        $r->dispatch($path);
    }

    public static function base(): string
    {
        return Shops::url((int)self::$shop['id'], '');
    }

    /** Everything a template needs, read-only. */
    private static function context(): void
    {
        $db = self::$db;
        $shop = self::$shop;
        $manifest = Theme::manifest($shop['template_code']);
        $theme = setting_get($db, 'theme', []);
        $company = setting_get($db, 'company', []);
        $company['logo'] = $theme['logo'] ?? null;
        $cats = Catalog::categoryTree($db);
        $counts = Catalog::activeCountsByCategory($db);
        // roll subtree counts up so menus can hide empty branches
        $roll = function (&$nodes) use (&$roll, $counts) {
            $total = 0;
            foreach ($nodes as &$n) {
                $n['count'] = ($counts[$n['id']] ?? 0) + $roll($n['children']);
                $total += $n['count'];
            }
            return $total;
        };
        $roll($cats);
        $menu = [];
        $mid = val($db, "SELECT id FROM menus WHERE code = 'main'");
        foreach (rows($db, 'SELECT * FROM menu_items WHERE menu_id = ? AND parent_id IS NULL ORDER BY sort', [$mid]) as $it) {
            $menu[] = ['label' => $it['label'], 'url' => self::menuUrl($it)];
        }
        if (!$menu) {
            foreach ($cats as $c) {
                if ($c['count'] > 0) $menu[] = ['label' => $c['name'], 'url' => '/category/' . $c['slug'] . '/'];
            }
        }
        self::$ctx = [
            'shop' => $shop, 'db' => $db, 'company' => $company, 'manifest' => $manifest,
            'tokens' => Theme::tokens($manifest, $theme['tokens'] ?? []), 'favicon' => $theme['favicon'] ?? null,
            'cats' => $cats, 'menu' => array_slice($menu, 0, 7),
            'footerPages' => rows($db, "SELECT slug, title FROM pages WHERE status = 'published' AND in_footer = 1 ORDER BY sort"),
            'cartCount' => Cart::count(), 'home' => setting_get($db, 'homepage', []), 'popup' => setting_get($db, 'popup', []),
            'shipping' => setting_get($db, 'shipping', []), 'seoCfg' => setting_get($db, 'seo', []),
            'wish' => array_map('intval', $_SESSION['wish'] ?? []),
            'popupEnabled' => Shops::feature($shop, 'popups'),
        ];
    }

    private static function menuUrl(array $it): string
    {
        $db = self::$db;
        return match ($it['type']) {
            'category' => $it['ref_id'] ? Catalog::categoryUrl($db, (int)$it['ref_id']) : '/',
            'page' => '/' . (val($db, 'SELECT slug FROM pages WHERE id = ?', [$it['ref_id']]) ?? '') . '/',
            'product' => '/product/' . (val($db, 'SELECT slug FROM products WHERE id = ?', [$it['ref_id']]) ?? '') . '/',
            default => $it['url'] ?: '/',
        };
    }

    private static function seo(string $title, string $desc, string $path): Seo
    {
        $s = new Seo(self::base(), self::$ctx['company'], self::$ctx['seoCfg']);
        $name = self::$ctx['company']['name'] ?? self::$shop['name'];
        $s->title = $title === '' ? $name : $title . ' | ' . $name;
        $s->description = $desc;
        $s->canonical = self::base() . $path;
        return $s;
    }

    private static function view(string $name, array $vars, Seo $seo, int $status = 200): void
    {
        http_response_code($status);
        $code = self::$shop['template_code'];
        $vars = $vars + self::$ctx + ['seo' => $seo, 'view' => $name];
        require_once Theme::view($code, 'partials');
        $content = render(Theme::view($code, $name), $vars);
        echo render(Theme::view($code, 'layout'), $vars + ['content' => $content]);
    }

    public static function errorPage(int $code, string $title, string $msg): void
    {
        if (!self::$ctx) {
            Session::start('store');
            self::context();
        }
        $seo = self::seo($title, '', req_path());
        $seo->robots = 'noindex, follow';
        self::view('error', ['code' => $code, 'title' => $title, 'msg' => $msg], $seo, $code);
    }

    /* ---------- product queries (ACTIVE only) ---------- */
    private const LIVE = "p.status = 'active' AND p.deleted_at IS NULL";

    private static function listing(array $where, array $params, string $sort, int $page, int $per): array
    {
        $db = self::$db;
        $w = implode(' AND ', array_merge([self::LIVE], $where));
        $total = (int)val($db, "SELECT COUNT(*) FROM products p WHERE $w", $params);
        $order = match ($sort) {
            'price' => 'CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END ASC',
            'price_desc' => 'CASE WHEN p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END DESC',
            'name' => 'p.name COLLATE NOCASE',
            default => 'p.featured DESC, p.activated_at DESC, p.id DESC',
        };
        $list = rows($db, "SELECT p.* FROM products p WHERE $w ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per), $params);
        return [$list, $total, Catalog::coverMap($db, array_column($list, 'id'))];
    }

    /* ---------- pages ---------- */
    public static function home(): void
    {
        $db = self::$db;
        $h = self::$ctx['home'];
        [$featured, , $covers] = self::listing([], [], 'new', 1, 12);
        $heroImg = null;
        if (!empty($h['hero']['image'])) {
            $m = row($db, 'SELECT * FROM media WHERE id = ?', [(int)$h['hero']['image']]);
            if ($m) $heroImg = Media::urlsFor($m);
        }
        $star = $featured[0] ?? null;
        $cfg = self::$ctx['seoCfg'];
        $seo = self::seo($cfg['home_title'] ?? '', $cfg['home_description'] ?? ($h['hero']['subtitle'] ?? ''), '/');
        if (!empty($cfg['home_title'])) $seo->title = $cfg['home_title'];
        $seo->itemList($featured, fn($p) => '/product/' . $p['slug'] . '/');
        $seo->faq($h['faq'] ?? []);
        if ($heroImg) $seo->image = $heroImg['full'];
        elseif ($star && isset($covers[$star['id']])) $seo->image = $covers[$star['id']]['full'];
        self::view('home', compact('featured', 'covers', 'heroImg', 'star'), $seo);
    }

    public static function products(): void
    {
        self::listPage(null);
    }

    public static function category(string $path): void
    {
        $db = self::$db;
        $parent = 0;
        $cat = null;
        foreach (explode('/', $path) as $slug) {
            $cat = row($db, 'SELECT * FROM categories WHERE slug = ? AND ifnull(parent_id,0) = ? AND deleted_at IS NULL', [$slug, $parent]);
            if (!$cat) abort(404, 'Η κατηγορία δεν βρέθηκε.');
            $parent = (int)$cat['id'];
        }
        self::listPage($cat);
    }

    private static function listPage(?array $cat): void
    {
        $db = self::$db;
        $page = max(1, (int)input('page', 1));
        $sort = input('sort', 'new');
        $where = [];
        $params = [];
        if ($cat) {
            $ids = Catalog::descendantIds($db, (int)$cat['id']);
            $where[] = 'p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . implode(',', $ids) . '))';
        }
        if (input('stock') === '1') $where[] = '(p.manage_stock = 0 OR p.stock > 0)';
        [$list, $total, $covers] = self::listing($where, $params, $sort, $page, 24);
        $pages = max(1, (int)ceil($total / 24));
        if ($page > $pages && $total) abort(404);
        $path = $cat ? Catalog::categoryUrl($db, (int)$cat['id']) : '/products/';
        $title = $cat ? ($cat['seo_title'] ?: $cat['name']) : 'Όλα τα προϊόντα';
        $desc = $cat ? ($cat['seo_description'] ?: Sanitizer::text($cat['description'], 160)) : 'Όλα τα διαθέσιμα προϊόντα του καταστήματος.';
        $seo = self::seo($title, $desc, $path . ($page > 1 ? '?page=' . $page : ''));
        if ($cat && $cat['canonical_url']) $seo->canonical = $cat['canonical_url'];
        if ($sort !== 'new' || input('stock') === '1') {
            $seo->robots = 'noindex, follow';
            $seo->canonical = self::base() . $path;
        }
        $crumbs = [];
        if ($cat) {
            foreach (Catalog::categoryPath($db, (int)$cat['id']) as $c) {
                $crumbs[] = ['name' => $c['name'], 'url' => Catalog::categoryUrl($db, (int)$c['id'])];
                $seo->crumb($c['name'], Catalog::categoryUrl($db, (int)$c['id']));
            }
        } else {
            $crumbs[] = ['name' => 'Προϊόντα', 'url' => '/products/'];
            $seo->crumb('Προϊόντα', '/products/');
        }
        $seo->itemList($list, fn($p) => '/product/' . $p['slug'] . '/');
        $children = $cat ? rows($db, 'SELECT * FROM categories WHERE parent_id = ? AND deleted_at IS NULL ORDER BY sort, name', [$cat['id']]) : array_map(fn($c) => $c, array_filter(self::$ctx['cats'], fn($c) => $c['count'] > 0));
        $partial = input('partial') === '1';
        if ($partial) {
            require_once Theme::view(self::$shop['template_code'], 'partials');
            header('Content-Type: text/html; charset=utf-8');
            foreach ($list as $p) echo t01_card($p, $covers[$p['id']] ?? null, self::$ctx['wish']);
            return;
        }
        self::view('listing', compact('cat', 'list', 'total', 'covers', 'page', 'pages', 'sort', 'path', 'crumbs', 'children'), $seo);
    }

    public static function search(): void
    {
        $q = Sanitizer::text(input('q'), 80);
        $list = $covers = [];
        $total = 0;
        if (mb_strlen($q) >= 2) {
            $f = '%' . fold($q) . '%';
            [$list, $total, $covers] = self::listing(['(fold(p.name) LIKE ? OR fold(ifnull(p.brand,\'\')) LIKE ? OR p.sku LIKE ? OR fold(ifnull(p.short_description,\'\')) LIKE ?)'], [$f, $f, '%' . $q . '%', $f], input('sort', 'new'), max(1, (int)input('page', 1)), 48);
        }
        $seo = self::seo('Αναζήτηση' . ($q ? ': ' . $q : ''), '', '/search/');
        $seo->robots = 'noindex, follow';
        self::view('search', compact('q', 'list', 'total', 'covers'), $seo);
    }

    public static function product(string $slug): void
    {
        $db = self::$db;
        $p = row($db, 'SELECT * FROM products p WHERE p.slug = ? AND ' . self::LIVE, [$slug]);
        // 2. Product must be ACTIVE — inactive, deleted or unknown products are a plain 404
        if (!$p) abort(404, 'Το προϊόν δεν είναι διαθέσιμο.');
        $images = Catalog::images($db, (int)$p['id']);
        $variants = Catalog::variants($db, (int)$p['id']);
        $attrs = rows($db, 'SELECT * FROM product_attributes WHERE product_id = ? ORDER BY sort, id', [$p['id']]);
        $url = '/product/' . $p['slug'] . '/';
        if (input('partial') === 'quick') {
            require_once Theme::view(self::$shop['template_code'], 'partials');
            header('Content-Type: text/html; charset=utf-8');
            header('X-Robots-Tag: noindex');
            echo render(Theme::view(self::$shop['template_code'], 'quickview'), self::$ctx + compact('p', 'images', 'variants'));
            return;
        }
        $seo = self::seo($p['seo_title'] ?: $p['name'], $p['seo_description'] ?: Sanitizer::text($p['short_description'] ?: $p['description'], 160), $url);
        if ($p['canonical_url']) $seo->canonical = $p['canonical_url'];
        if ($p['robots']) $seo->robots = $p['robots'];
        if ($images) $seo->image = $images[0]['full'];
        $crumbs = [];
        if ($p['category_id']) {
            foreach (Catalog::categoryPath($db, (int)$p['category_id']) as $c) {
                $crumbs[] = ['name' => $c['name'], 'url' => Catalog::categoryUrl($db, (int)$c['id'])];
                $seo->crumb($c['name'], Catalog::categoryUrl($db, (int)$c['id']));
            }
        }
        $seo->crumb($p['name'], $url);
        $seo->product($p, $images, self::base() . $url, $variants);
        // related: same category first, then newest
        $related = rows($db, 'SELECT p.* FROM products p WHERE ' . self::LIVE . ' AND p.id <> ? ORDER BY (p.category_id = ?) DESC, p.activated_at DESC LIMIT 4', [$p['id'], (int)$p['category_id']]);
        $relCovers = Catalog::coverMap($db, array_column($related, 'id'));
        self::view('product', compact('p', 'images', 'variants', 'attrs', 'crumbs', 'related', 'relCovers'), $seo);
    }

    /* ---------- cart ---------- */
    public static function cart(): void
    {
        $db = self::$db;
        $ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']);
        if (is_post()) {
            $op = input('op');
            $err = null;
            try {
                if ($op === 'add') {
                    Cart::add($db, (int)input('product_id'), (int)input('variant_id') ?: null, (int)input('qty', 1));
                } elseif ($op === 'qty') {
                    $key = (string)input('key');
                    $line = Cart::raw()[$key] ?? null;
                    $q = (int)input('qty');
                    if ($line && $q > (int)$line['q']) {
                        Cart::add($db, (int)$line['p'], $line['v'] ? (int)$line['v'] : null, $q - (int)$line['q']);
                    } else {
                        Cart::setQty($key, $q);
                    }
                } elseif ($op === 'remove') {
                    Cart::setQty((string)input('key'), 0);
                }
            } catch (DomainException $e) {
                $err = $e->getMessage();
            }
            if ($ajax) {
                self::$ctx['cartCount'] = Cart::count();
                $notices = [];
                $lines = Cart::lines($db, $notices);
                require_once Theme::view(self::$shop['template_code'], 'partials');
                json_out(['ok' => !$err, 'error' => $err, 'count' => Cart::count(), 'html' => render(Theme::view(self::$shop['template_code'], 'cart_sheet'), self::$ctx + ['lines' => $lines, 'notices' => $notices])]);
            }
            if ($err) flash('error', $err);
            redirect('/cart/');
        }
        $notices = [];
        $lines = Cart::lines($db, $notices);
        if (input('partial') === '1') {
            require_once Theme::view(self::$shop['template_code'], 'partials');
            json_out(['count' => Cart::count(), 'html' => render(Theme::view(self::$shop['template_code'], 'cart_sheet'), self::$ctx + ['lines' => $lines, 'notices' => $notices])]);
        }
        $totals = Cart::totals($lines, self::$ctx['shipping']);
        $seo = self::seo('Καλάθι', '', '/cart/');
        $seo->robots = 'noindex, nofollow';
        self::view('cart', compact('lines', 'notices', 'totals'), $seo);
    }

    public static function checkout(): void
    {
        $db = self::$db;
        $payCfg = setting_get($db, 'payments', []);
        $methods = Payments::available($payCfg);
        $errors = [];
        $data = $_SESSION['checkout'] ?? [];
        if (is_post()) {
            if (!RateLimit::hit('checkout:' . self::$shop['id'] . ':' . client_ip(), 10, 600)) {
                abort(429, 'Πολλές προσπάθειες παραγγελίας. Δοκιμάστε ξανά σε λίγα λεπτά.');
            }
            [$data, $errors] = Orders::validate($_POST, $methods);
            $_SESSION['checkout'] = array_diff_key($data, ['terms' => 1]);
            if (!$errors) {
                try {
                    $order = Orders::place($db, $data, $methods, self::$ctx['shipping']);
                    unset($_SESSION['checkout']);
                    $orderUrl = self::base() . '/order/' . $order['number'] . '/?k=' . $order['access_token'];
                    Orders::emails($db, $order, self::$ctx['company'], $orderUrl, $payCfg);
                    Audit::log('order.placed', ['actor' => null, 'label' => 'customer', 'entity' => 'order', 'id' => $order['number']]);
                    if ($order['payment_method'] === 'card') {
                        try {
                            $gw = Payments::gateway($payCfg);
                            redirect($gw->start($order, $orderUrl, $orderUrl . '&cancelled=1'));
                        } catch (RuntimeException $e) {
                            log_error('payment start: ' . $e->getMessage());
                            flash('error', 'Η σύνδεση με την τράπεζα δεν ήταν δυνατή. Η παραγγελία καταχωρήθηκε ως «εκκρεμής πληρωμή» — θα επικοινωνήσουμε μαζί σας.');
                        }
                    }
                    redirect('/order/' . $order['number'] . '/?k=' . $order['access_token']);
                } catch (DomainException $e) {
                    $errors['_'] = $e->getMessage();
                }
            }
        }
        $notices = [];
        $lines = Cart::lines($db, $notices);
        if (!$lines) {
            flash('error', 'Το καλάθι σας είναι άδειο.');
            redirect('/cart/');
        }
        $region = ($data['region'] ?? 'mainland') === 'islands' ? 'islands' : 'mainland';
        $pm = $data['payment_method'] ?? array_key_first($methods);
        $totals = Cart::totals($lines, self::$ctx['shipping'], $region, (float)($methods[$pm]['fee'] ?? 0));
        $seo = self::seo('Ολοκλήρωση αγοράς', '', '/checkout/');
        $seo->robots = 'noindex, nofollow';
        self::view('checkout', compact('lines', 'notices', 'totals', 'methods', 'errors', 'data', 'payCfg'), $seo);
    }

    public static function order(string $number): void
    {
        $db = self::$db;
        $o = row($db, 'SELECT * FROM orders WHERE number = ?', [$number]);
        if (!$o || !hash_equals($o['access_token'], (string)input('k'))) abort(404);
        $payCfg = setting_get($db, 'payments', []);
        if ($o['payment_method'] === 'card' && $o['payment_status'] === 'pending' && (input('session_id') || input('t'))) {
            try {
                $gw = Payments::gateway($payCfg);
                if ($gw && $gw->verify($o, $_GET) === 'paid') {
                    update($db, 'orders', ['payment_status' => 'paid', 'payment_ref' => Sanitizer::text(input('session_id') ?: input('t'), 120), 'updated_at' => now()], 'id = ?', [$o['id']]);
                    $o = row($db, 'SELECT * FROM orders WHERE id = ?', [$o['id']]);
                }
            } catch (Throwable $e) {
                log_error('payment verify: ' . $e->getMessage());
            }
        }
        $items = rows($db, 'SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
        $seo = self::seo('Παραγγελία ' . $o['number'], '', '/order/' . $o['number'] . '/');
        $seo->robots = 'noindex, nofollow';
        header('Referrer-Policy: no-referrer');
        self::view('order', compact('o', 'items', 'payCfg'), $seo);
    }

    public static function wishlist(): void
    {
        $wish = array_map('intval', $_SESSION['wish'] ?? []);
        if (is_post()) {
            $id = (int)input('product_id');
            $wish = in_array($id, $wish, true) ? array_values(array_diff($wish, [$id])) : array_slice(array_merge($wish, [$id]), -100);
            $_SESSION['wish'] = $wish;
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                json_out(['on' => in_array($id, $wish, true), 'count' => count($wish)]);
            }
            redirect(str_starts_with((string)input('back'), '/') ? input('back') : '/wishlist/');
        }
        $list = $covers = [];
        if ($wish) {
            $in = implode(',', $wish);
            [$list, , $covers] = self::listing(["p.id IN ($in)"], [], 'new', 1, 100);
        }
        $seo = self::seo('Αγαπημένα', '', '/wishlist/');
        $seo->robots = 'noindex, follow';
        self::view('wishlist', compact('list', 'covers'), $seo);
    }

    /* ---------- forms: newsletter / contact (CSRF, honeypot, timing, rate limit, consent, PRG) ---------- */
    public static function formStamp(): string
    {
        $t = (string)time();
        return $t . '.' . substr(hash_hmac('sha256', $t . '|' . self::$shop['id'], (string)Config::get('app_key')), 0, 16);
    }

    private static function spamCheck(string $key): ?string
    {
        if (trim((string)($_POST['website'] ?? '')) !== '') {
            return 'spam';
        }
        [$t, $sig] = array_pad(explode('.', (string)input('stamp')), 2, '');
        $ok = hash_equals(substr(hash_hmac('sha256', $t . '|' . self::$shop['id'], (string)Config::get('app_key')), 0, 16), $sig);
        if (!$ok || time() - (int)$t < 3 || time() - (int)$t > 86400) {
            return 'Η φόρμα στάλθηκε πολύ γρήγορα ή έληξε. Δοκιμάστε ξανά.';
        }
        if (!RateLimit::hit($key . ':' . self::$shop['id'] . ':' . client_ip(), 5, 3600)) {
            return 'Πολλές υποβολές από αυτή τη σύνδεση. Δοκιμάστε ξανά αργότερα.';
        }
        return null;
    }

    public static function newsletter(): void
    {
        $ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $popup = self::$ctx['popup'];
        $email = strtolower(trim((string)input('email')));
        $msg = null;
        $ok = false;
        $spam = self::spamCheck('nl');
        if ($spam === 'spam') {
            $ok = true; // silently accept bots
            $msg = 'Ευχαριστούμε για την εγγραφή!';
        } elseif ($spam) {
            $msg = $spam;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Συμπληρώστε έγκυρο email.';
        } elseif (input('consent') !== '1') {
            $msg = 'Για την εγγραφή χρειάζεται η συγκατάθεσή σας.';
        } else {
            $db = self::$db;
            $ex = row($db, 'SELECT * FROM subscribers WHERE email = ?', [$email]);
            if ($ex) {
                update($db, 'subscribers', ['unsubscribed_at' => null, 'consent' => 1, 'consent_text' => Sanitizer::text($popup['consent_text'] ?? 'newsletter', 500), 'created_at' => now()], 'id = ?', [$ex['id']]);
            } else {
                insert($db, 'subscribers', ['email' => $email, 'source' => in_array(input('source'), ['popup', 'home', 'footer'], true) ? input('source') : 'form', 'consent' => 1,
                    'consent_text' => Sanitizer::text($popup['consent_text'] ?? 'Συγκατάθεση για newsletter', 500), 'ip' => client_ip(), 'token' => random_token(18), 'created_at' => now()]);
            }
            $ok = true;
            $msg = 'Ευχαριστούμε για την εγγραφή!';
        }
        if ($ajax) {
            json_out(['ok' => $ok, 'message' => $msg], $ok ? 200 : 422);
        }
        flash($ok ? 'success' : 'error', $msg);
        $back = (string)input('back');
        redirect(str_starts_with($back, '/') && !str_starts_with($back, '//') ? $back : '/');
    }

    public static function unsubscribe(): void
    {
        $t = (string)input('t');
        $done = false;
        if ($t !== '') {
            $done = q(self::$db, 'UPDATE subscribers SET unsubscribed_at = ? WHERE token = ? AND unsubscribed_at IS NULL', [now(), $t])->rowCount() > 0;
        }
        $seo = self::seo('Διαγραφή από τη λίστα', '', '/unsubscribe/');
        $seo->robots = 'noindex';
        self::view('page', ['pg' => ['title' => 'Διαγραφή από τη λίστα', 'body' => $done ? '<p>Η διεύθυνσή σας διαγράφηκε από τη λίστα. Δεν θα λαμβάνετε άλλα ενημερωτικά email.</p>' : '<p>Ο σύνδεσμος δεν είναι έγκυρος ή έχει ήδη χρησιμοποιηθεί.</p>', 'slug' => 'unsubscribe']], $seo);
    }

    public static function contact(): void
    {
        $db = self::$db;
        $errors = [];
        if (is_post()) {
            $d = ['name' => Sanitizer::text(input('name'), 120), 'email' => strtolower(Sanitizer::text(input('email'), 160)), 'phone' => Sanitizer::text(input('phone'), 30), 'message' => Sanitizer::text(input('message'), 5000)];
            $spam = self::spamCheck('contact');
            if ($spam === 'spam') {
                flash('success', 'Ευχαριστούμε! Λάβαμε το μήνυμά σας.');
                redirect('/contact/');
            }
            if ($spam) $errors['_'] = $spam;
            if (mb_strlen($d['name']) < 2) $errors['name'] = 'Συμπληρώστε το όνομά σας.';
            if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Συμπληρώστε έγκυρο email.';
            if (mb_strlen($d['message']) < 10) $errors['message'] = 'Γράψτε ένα μήνυμα τουλάχιστον 10 χαρακτήρων.';
            if (input('consent') !== '1') $errors['consent'] = 'Χρειάζεται η συγκατάθεσή σας για να απαντήσουμε.';
            if (!$errors) {
                insert($db, 'contact_messages', $d + ['ip' => client_ip(), 'created_at' => now()]);
                $to = self::$ctx['company']['email'] ?? '';
                if ($to) Mailer::send($to, 'Νέο μήνυμα από ' . $d['name'], "Από: {$d['name']} <{$d['email']}> {$d['phone']}\n\n{$d['message']}", $d['email']);
                flash('success', 'Ευχαριστούμε! Λάβαμε το μήνυμά σας και θα απαντήσουμε σύντομα.');
                redirect('/contact/');
            }
            $_SESSION['_old'] = $d;
        }
        $pg = row($db, "SELECT * FROM pages WHERE slug = 'contact'") ?? ['title' => 'Επικοινωνία', 'body' => '', 'slug' => 'contact'];
        $seo = self::seo($pg['seo_title'] ?? $pg['title'], $pg['seo_description'] ?? 'Επικοινωνήστε μαζί μας.', '/contact/');
        $seo->crumb($pg['title'], '/contact/');
        self::view('contact', compact('pg', 'errors'), $seo);
        unset($_SESSION['_old']);
    }

    public static function page(string $slug): void
    {
        $pg = row(self::$db, "SELECT * FROM pages WHERE slug = ? AND status = 'published'", [$slug]);
        if (!$pg) abort(404);
        $seo = self::seo($pg['seo_title'] ?: $pg['title'], $pg['seo_description'] ?: Sanitizer::text($pg['body'], 160), '/' . $pg['slug'] . '/');
        $seo->crumb($pg['title'], '/' . $pg['slug'] . '/');
        self::view('page', compact('pg'), $seo);
    }

    private static function feed(string $slug): void
    {
        if (!Shops::feature(self::$shop, 'feeds')) abort(404);
        $f = row(self::$db, "SELECT * FROM feeds WHERE slug = ? AND status = 'active'", [$slug]);
        if (!$f) abort(404);
        $file = Feeds::ensure(self::$db, (int)self::$shop['id'], $f, self::base());
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: public, max-age=300');
        readfile($file);
    }
}
