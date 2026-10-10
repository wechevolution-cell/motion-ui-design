<?php
/* Super Admin (platform owner). Lives on the platform host under /superadmin/. */

final class SuperRoutes
{
    private static ?array $user = null;

    public static function setup(string $path): void
    {
        Session::start('super');
        send_security_headers(true);
        if ($path !== '/superadmin/setup') {
            redirect('/superadmin/setup');
        }
        $errors = [];
        if (is_post()) {
            Csrf::check();
            $name = Sanitizer::text(input('platform_name'), 80) ?: 'WeChevo';
            $host = Domains::normalize(input('platform_host')) ?? (req_host() === 'localhost' ? 'localhost' : null);
            $email = strtolower(input('email'));
            $pass = (string)($_POST['password'] ?? '');
            if (!$host) $errors['platform_host'] = 'Μη έγκυρο domain πίνακα ελέγχου.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Μη έγκυρο email.';
            if ($p = Auth::passwordProblem($pass)) $errors['password'] = $p;
            if ($pass !== ($_POST['password2'] ?? '')) $errors['password2'] = 'Οι κωδικοί δεν ταιριάζουν.';
            if (!$errors) {
                Config::write(['app_key' => bin2hex(random_bytes(32)), 'platform_host' => $host, 'installed_at' => now()]);
                $db = DB::platform();
                if (!val($db, "SELECT 1 FROM users WHERE role = 'superadmin'")) {
                    self::seedPlatform($db, $name, input('notify_email') ?: $email, $host);
                    $uid = insert($db, 'users', ['shop_id' => null, 'email' => $email, 'name' => Sanitizer::text(input('name'), 120), 'password_hash' => Auth::hashPassword($pass), 'role' => 'superadmin', 'created_at' => now()]);
                    Audit::log('platform.installed', ['actor' => $uid, 'label' => $email, 'shop' => null]);
                }
                flash('success', 'Η εγκατάσταση ολοκληρώθηκε. Συνδεθείτε με τον λογαριασμό σας.');
                redirect('/superadmin/login');
            }
        }
        echo render(APP . '/views/superadmin/setup.php', ['errors' => $errors]);
    }

    /** Initial data. Every value here is editable later from the panel (nothing is enforced from code). */
    private static function seedPlatform(PDO $db, string $name, string $notify, string $host): void
    {
        setting_set($db, 'platform_name', $name);
        setting_set($db, 'notify_email', $notify);
        setting_set($db, 'grace_days', 3);
        setting_set($db, 'warning_days', [10, 3, 1]);
        setting_set($db, 'mail_mode', str_ends_with($host, 'localhost') ? 'log' : 'mail');
        setting_set($db, 'mail_from', 'no-reply@' . $host);
        setting_set($db, 'force_https', !str_ends_with($host, 'localhost'));
        $features = json_encode(['import' => true, 'export' => true, 'feeds' => true, 'popups' => true, 'seo_advanced' => true, 'staff' => true, 'custom_domain' => true]);
        foreach ([
            ['Starter', 'starter', 20, 20, 2, 1],
            ['Business', 'business', 45, 50, 5, 2],
            ['Pro', 'pro', 80, 100, 10, 3],
        ] as [$n, $slug, $price, $limit, $staff, $sort]) {
            insert($db, 'subscription_plans', ['name' => $n, 'slug' => $slug, 'price' => $price, 'setup_fee' => 150, 'active_product_limit' => $limit, 'stored_product_limit' => 10000,
                'staff_limit' => $staff, 'duration_options' => '[1,3,6,12]', 'features' => $features, 'sort' => $sort, 'created_at' => now()]);
        }
        $tpls = [
            ['t01', 'Bento Market', 'Παιχνιδιάρικο, χρωματιστό, mobile-first. Για τρόφιμα, δώρα, παιχνίδια, αθλητικά, lifestyle.', 'available'],
            ['t02', 'Swiss Clarity', 'Καθαρό πλέγμα, αναζήτηση & φίλτρα. Για ηλεκτρονικά, εργαλεία, μεγάλους καταλόγους.', 'coming_soon'],
            ['t03', 'Editorial Boutique', 'Περιοδικό ύφος, μεγάλες εικόνες. Για μόδα, κεραμικά, διακόσμηση, καλλυντικά.', 'coming_soon'],
            ['t04', 'Template 04', 'Σε σχεδιασμό.', 'coming_soon'], ['t05', 'Template 05', 'Σε σχεδιασμό.', 'coming_soon'],
            ['t06', 'Template 06', 'Σε σχεδιασμό.', 'coming_soon'], ['t07', 'Template 07', 'Σε σχεδιασμό.', 'coming_soon'],
            ['t08', 'Template 08', 'Σε σχεδιασμό.', 'coming_soon'], ['t09', 'Template 09', 'Σε σχεδιασμό.', 'coming_soon'],
            ['t10', 'Template 10', 'Σε σχεδιασμό.', 'coming_soon'],
        ];
        foreach ($tpls as $i => [$code, $n, $d, $st]) {
            insert($db, 'templates', ['code' => $code, 'name' => $n, 'description' => $d, 'status' => Theme::exists($code) ? 'available' : $st, 'sort' => $i]);
        }
        foreach ([['manual', 'Χειροκίνητη καταχώριση', 1], ['bank_transfer', 'Τραπεζική κατάθεση', 1], ['stripe', 'Stripe', 0], ['viva', 'Viva Wallet', 0]] as [$c, $n, $en]) {
            insert($db, 'payment_providers', ['code' => $c, 'name' => $n, 'enabled' => $en]);
        }
    }

    public static function dispatch(string $path): void
    {
        Session::start('super');
        send_security_headers(true);
        if ($path === '/') {
            echo render(APP . '/views/superadmin/home.php', ['name' => setting_get(DB::platform(), 'platform_name', 'WeChevo')]);
            return;
        }
        if (!str_starts_with($path, '/superadmin')) {
            abort(404);
        }
        if ($path === '/superadmin') {
            redirect('/superadmin/');
        }
        if (is_post()) {
            Csrf::check();
        }
        if ($path === '/superadmin/setup') {
            redirect('/superadmin/login');
        }
        if ($path === '/superadmin/login') {
            self::login();
            return;
        }
        $u = Auth::user();
        if (!$u || $u['role'] !== 'superadmin' || $u['shop_id'] !== null || ($_SESSION['shop'] ?? null) !== null) {
            redirect('/superadmin/login');
        }
        self::$user = $u;

        $r = new Router();
        $r->add('POST', '/superadmin/logout', function () {
            Audit::log('logout');
            Session::destroy();
            redirect('/superadmin/login');
        });
        $r->add('GET', '/superadmin/', [self::class, 'dashboard']);
        $r->add('GET', '/superadmin/shops', [self::class, 'shops']);
        $r->add('GET|POST', '/superadmin/shops/new', [self::class, 'shopNew']);
        $r->add('GET', '/superadmin/shops/(?P<id>\d+)', [self::class, 'shopView']);
        $r->add('POST', '/superadmin/shops/(?P<id>\d+)/(?P<action>renew|plan|status|edit|domains|delete|purge|restore|impersonate|payment|owner-password|backup|scheduler)', [self::class, 'shopAction']);
        $r->add('GET|POST', '/superadmin/plans', [self::class, 'plans']);
        $r->add('GET|POST', '/superadmin/plans/(?P<id>\d+|new)', [self::class, 'planEdit']);
        $r->add('GET|POST', '/superadmin/templates', [self::class, 'templates']);
        $r->add('GET|POST', '/superadmin/users', [self::class, 'users']);
        $r->add('GET', '/superadmin/audit', [self::class, 'audit']);
        $r->add('GET|POST', '/superadmin/notifications', [self::class, 'notifications']);
        $r->add('GET|POST', '/superadmin/settings', [self::class, 'settings']);
        $r->add('GET|POST', '/superadmin/backups', [self::class, 'backups']);
        $r->add('GET', '/superadmin/backups/download', [self::class, 'backupDownload']);
        $r->add('GET|POST', '/superadmin/account', [self::class, 'account']);
        $r->dispatch($path === '/superadmin/' ? $path : rtrim($path, '/'));
    }

    private static function page(string $title, string $view, array $vars = [], string $active = ''): void
    {
        $db = DB::platform();
        $unread = (int)val($db, "SELECT COUNT(*) FROM notifications WHERE read_at IS NULL AND audience IN ('superadmin','both')");
        $nav = [
            ['key' => 'dash', 'href' => '/superadmin/', 'label' => 'Πίνακας'],
            ['key' => 'shops', 'href' => '/superadmin/shops', 'label' => 'Καταστήματα'],
            ['key' => 'new', 'href' => '/superadmin/shops/new', 'label' => '+ Νέο κατάστημα'],
            ['key' => 'notif', 'href' => '/superadmin/notifications', 'label' => 'Ειδοποιήσεις', 'badge' => $unread],
            ['sep' => 'Πλατφόρμα'],
            ['key' => 'plans', 'href' => '/superadmin/plans', 'label' => 'Πακέτα'],
            ['key' => 'templates', 'href' => '/superadmin/templates', 'label' => 'Πρότυπα'],
            ['key' => 'users', 'href' => '/superadmin/users', 'label' => 'Χρήστες συστήματος'],
            ['key' => 'audit', 'href' => '/superadmin/audit', 'label' => 'Αρχείο ενεργειών'],
            ['key' => 'backups', 'href' => '/superadmin/backups', 'label' => 'Αντίγραφα ασφαλείας'],
            ['key' => 'settings', 'href' => '/superadmin/settings', 'label' => 'Ρυθμίσεις'],
            ['key' => 'account', 'href' => '/superadmin/account', 'label' => 'Ο λογαριασμός μου'],
        ];
        $content = render(APP . '/views/superadmin/' . $view . '.php', $vars + ['db' => $db, 'me' => self::$user]);
        echo render(APP . '/views/shared/admin_layout.php', [
            'title' => $title, 'content' => $content, 'nav' => $nav, 'area' => 'super', 'user' => self::$user,
            'brand' => setting_get($db, 'platform_name', 'WeChevo'), 'active' => $active,
        ]);
    }

    private static function login(): void
    {
        $error = null;
        if (is_post()) {
            [$u, $error] = Auth::attempt(input('email'), (string)($_POST['password'] ?? ''), null);
            if ($u) {
                Auth::login($u);
                Audit::log('login', ['actor' => $u['id'], 'label' => $u['email'], 'shop' => null]);
                redirect('/superadmin/');
            }
        }
        if (Auth::user() && (Auth::user()['role'] ?? '') === 'superadmin') {
            redirect('/superadmin/');
        }
        echo render(APP . '/views/shared/login.php', ['error' => $error, 'title' => 'Σύνδεση Super Admin', 'brand' => setting_get(DB::platform(), 'platform_name', 'WeChevo'), 'action' => '/superadmin/login', 'super' => true]);
    }

    public static function dashboard(): void
    {
        $db = DB::platform();
        $by = [];
        foreach (rows($db, 'SELECT status, COUNT(*) n FROM shops WHERE deleted_at IS NULL GROUP BY status') as $r) {
            $by[$r['status']] = (int)$r['n'];
        }
        $shops = rows($db, "SELECT s.*, p.name plan_name, p.active_product_limit FROM shops s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.deleted_at IS NULL ORDER BY s.subscription_expires");
        $totals = ['active' => 0, 'stored' => 0];
        foreach ($shops as &$s) {
            $s['counts'] = self::counts((int)$s['id']);
            $totals['active'] += $s['counts']['active'];
            $totals['stored'] += $s['counts']['stored'];
        }
        unset($s);
        $soon = array_filter($shops, fn($s) => in_array($s['status'], ['EXPIRING', 'EXPIRED'], true) || (Shops::daysLeft($s) !== null && Shops::daysLeft($s) <= 10 && Shops::daysLeft($s) >= 0));
        $notes = rows($db, "SELECT * FROM notifications WHERE read_at IS NULL AND audience IN ('superadmin','both') ORDER BY id DESC LIMIT 8");
        $mrr = (float)val($db, "SELECT COALESCE(SUM(p.price),0) FROM shops s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.status IN ('ACTIVE','EXPIRING','EXPIRED') AND s.deleted_at IS NULL");
        self::page('Πίνακας ελέγχου', 'dashboard', compact('by', 'shops', 'totals', 'soon', 'notes', 'mrr'), 'dash');
    }

    /** Product counts per shop, read from each tenant DB (cached for 5 min in the platform DB). */
    public static function counts(int $shopId): array
    {
        if (!is_file(DB::shopPath($shopId))) {
            return ['active' => 0, 'stored' => 0];
        }
        $db = DB::shop($shopId);
        return ['active' => Catalog::activeCount($db), 'stored' => Catalog::storedCount($db)];
    }

    public static function shops(): void
    {
        $db = DB::platform();
        $where = ['1=1'];
        $p = [];
        $status = input('status');
        if ($status === 'deleted') {
            $where[] = 's.deleted_at IS NOT NULL';
        } else {
            $where[] = 's.deleted_at IS NULL';
            if ($status && in_array($status, Shops::STATUSES, true)) {
                $where[] = 's.status = ?';
                $p[] = $status;
            }
        }
        if ($plan = (int)input('plan')) {
            $where[] = 's.plan_id = ?';
            $p[] = $plan;
        }
        if ($q = input('q')) {
            $where[] = '(s.name LIKE ? OR s.email LIKE ? OR s.id IN (SELECT shop_id FROM shop_domains WHERE domain LIKE ?))';
            array_push($p, "%$q%", "%$q%", "%$q%");
        }
        $shops = rows($db, 'SELECT s.*, p.name plan_name, p.active_product_limit, (SELECT domain FROM shop_domains d WHERE d.shop_id = s.id ORDER BY is_primary DESC LIMIT 1) domain
            FROM shops s JOIN subscription_plans p ON p.id = s.plan_id WHERE ' . implode(' AND ', $where) . ' ORDER BY s.created_at DESC LIMIT 500', $p);
        foreach ($shops as &$s) {
            $s['counts'] = self::counts((int)$s['id']);
        }
        unset($s);
        self::page('Καταστήματα', 'shops', ['shops' => $shops, 'plans' => Plans::all()], 'shops');
    }

    public static function shopNew(): void
    {
        $db = DB::platform();
        $errors = [];
        $created = null;
        if (is_post()) {
            try {
                [$id, $pass] = Provision::create($_POST);
                $created = ['id' => $id, 'password' => $pass, 'email' => strtolower(input('admin_email')), 'url' => Shops::url($id, '/admin/')];
                flash('success', 'Το κατάστημα δημιουργήθηκε.');
                $_SESSION['created_shop'] = $created;
                redirect('/superadmin/shops/' . $id);
            } catch (DomainException $e) {
                $errors[] = $e->getMessage();
                $_SESSION['_old'] = $_POST;
            }
        }
        $templates = rows($db, 'SELECT * FROM templates ORDER BY sort');
        $suggest = str_ends_with((string)Config::get('platform_host'), 'localhost') ? '.localhost' : '.gr';
        self::page('Νέο κατάστημα', 'shop_new', ['plans' => Plans::all(true), 'templates' => $templates, 'errors' => $errors, 'suggest' => $suggest], 'new');
        unset($_SESSION['_old']);
    }

    public static function shopView(string $id): void
    {
        $db = DB::platform();
        $shop = Shops::find((int)$id);
        if (!$shop) {
            abort(404);
        }
        $created = $_SESSION['created_shop'] ?? null;
        if ($created && (int)$created['id'] !== (int)$id) {
            $created = null;
        }
        unset($_SESSION['created_shop']);
        $data = [
            'shop' => $shop,
            'plan' => Plans::find((int)$shop['plan_id']),
            'plans' => Plans::all(),
            'domains' => rows($db, 'SELECT * FROM shop_domains WHERE shop_id = ? ORDER BY is_primary DESC, id', [$id]),
            'history' => rows($db, 'SELECT h.*, p.name plan_name, u.email actor FROM subscription_history h LEFT JOIN subscription_plans p ON p.id = h.plan_id LEFT JOIN users u ON u.id = h.actor_user_id WHERE h.shop_id = ? ORDER BY h.id DESC LIMIT 100', [$id]),
            'payments' => rows($db, 'SELECT * FROM payments WHERE shop_id = ? ORDER BY id DESC LIMIT 50', [$id]),
            'users' => rows($db, 'SELECT * FROM users WHERE shop_id = ? AND deleted_at IS NULL ORDER BY id', [$id]),
            'audit' => rows($db, 'SELECT * FROM audit_logs WHERE shop_id = ? ORDER BY id DESC LIMIT 30', [$id]),
            'templates' => rows($db, 'SELECT * FROM templates ORDER BY sort'),
            'counts' => self::counts((int)$id),
            'created' => $created,
            'downgrade' => $_SESSION['downgrade_confirm'] ?? null,
        ];
        unset($_SESSION['downgrade_confirm']);
        self::page($shop['name'], 'shop_view', $data, 'shops');
    }

    public static function shopAction(string $id, string $action): void
    {
        $id = (int)$id;
        $db = DB::platform();
        $shop = Shops::find($id);
        if (!$shop) {
            abort(404);
        }
        $back = '/superadmin/shops/' . $id;
        try {
            switch ($action) {
                case 'renew':
                    $months = (int)input('months');
                    $res = Subscriptions::renew($id, $months, ['amount' => to_float(input('amount')), 'method' => input('method') ?: 'manual', 'reference' => Sanitizer::text(input('reference'), 120), 'note' => Sanitizer::text(input('note'), 300)]);
                    flash('success', 'Η συνδρομή ανανεώθηκε έως ' . dt($res['expires']) . '. Τα δεδομένα του καταστήματος δεν άλλαξαν.');
                    break;
                case 'plan':
                    $res = Subscriptions::changePlan($id, (int)input('plan_id'), input('confirm_deactivate') === '1');
                    if ($res['needs_confirmation']) {
                        $_SESSION['downgrade_confirm'] = $res + ['plan_id' => (int)input('plan_id')];
                        flash('warning', "Το νέο πακέτο επιτρέπει {$res['limit']} ενεργά προϊόντα ενώ το κατάστημα έχει {$res['active']}. Επιβεβαιώστε την αυτόματη απενεργοποίηση {$res['excess']} προϊόντων.");
                    } else {
                        flash('success', 'Το πακέτο άλλαξε.' . ($res['deactivated'] ? ' Απενεργοποιήθηκαν ' . count($res['deactivated']) . ' προϊόντα (κανένα δεν διαγράφηκε).' : ''));
                    }
                    break;
                case 'status':
                    $to = input('to');
                    $note = Sanitizer::text(input('note'), 300) ?: null;
                    if ($to === 'lock') {
                        Shops::setStatus($id, 'LOCKED', 'locked', $note ?? 'Χειροκίνητο κλείδωμα');
                        Notify::add('shop.locked', "Κλειδώθηκε: {$shop['name']}", 'Χειροκίνητο κλείδωμα από Super Admin.', $id, 'danger');
                    } elseif ($to === 'suspend') {
                        Shops::setStatus($id, 'SUSPENDED', 'suspended', $note ?? 'Αναστολή');
                    } elseif ($to === 'activate') {
                        if ($shop['subscription_expires'] && $shop['subscription_expires'] <= now()) {
                            throw new DomainException('Η συνδρομή έχει λήξει. Χρησιμοποιήστε «Ανανέωση» για να ενεργοποιηθεί ξανά.');
                        }
                        Shops::setStatus($id, 'ACTIVE', $shop['status'] === 'LOCKED' ? 'unlocked' : 'reactivated', $note);
                        Scheduler::refreshShop($id);
                    } else {
                        throw new DomainException('Άγνωστη ενέργεια.');
                    }
                    Audit::log('shop.status', ['shop' => $id, 'entity' => 'shop', 'id' => $id, 'meta' => ['to' => $to]]);
                    flash('success', 'Η κατάσταση ενημερώθηκε.');
                    break;
                case 'edit':
                    $tpl = input('template_code');
                    if (!val($db, "SELECT 1 FROM templates WHERE code = ? AND status = 'available'", [$tpl]) || !Theme::exists($tpl)) {
                        throw new DomainException('Το πρότυπο δεν είναι διαθέσιμο.');
                    }
                    $ao = input('active_limit_override');
                    $so = input('stored_limit_override');
                    update($db, 'shops', [
                        'name' => Sanitizer::text(input('name'), 150), 'owner_name' => Sanitizer::text(input('owner_name'), 150),
                        'email' => Sanitizer::text(input('email'), 160), 'phone' => Sanitizer::text(input('phone'), 40),
                        'vat_number' => Sanitizer::text(input('vat_number'), 20), 'address' => Sanitizer::text(input('address'), 250),
                        'hosting_provider' => Sanitizer::text(input('hosting_provider'), 100), 'hosting_status' => in_array(input('hosting_status'), ['ok', 'pending', 'issue'], true) ? input('hosting_status') : 'ok',
                        'hosting_notes' => Sanitizer::text(input('hosting_notes'), 2000), 'notes' => Sanitizer::text(input('notes'), 4000),
                        'template_code' => $tpl,
                        'active_limit_override' => $ao === '' ? null : max(0, (int)$ao),
                        'stored_limit_override' => $so === '' ? null : max(1, (int)$so),
                        'updated_at' => now(),
                    ], 'id = ?', [$id]);
                    if ($tpl !== $shop['template_code']) {
                        Audit::log('shop.template_changed', ['shop' => $id, 'entity' => 'shop', 'id' => $id, 'meta' => ['from' => $shop['template_code'], 'to' => $tpl]]);
                    }
                    if ($ao !== '' && (int)$ao < Catalog::activeCount(DB::shop($id))) {
                        flash('warning', 'Το νέο όριο ενεργών είναι μικρότερο από τα τρέχοντα ενεργά προϊόντα. Τα υπάρχοντα παραμένουν· δεν θα επιτρέπεται νέα ενεργοποίηση μέχρι να μειωθούν.');
                    }
                    Audit::log('shop.updated', ['shop' => $id, 'entity' => 'shop', 'id' => $id]);
                    flash('success', 'Τα στοιχεία αποθηκεύτηκαν.');
                    break;
                case 'domains':
                    $op = input('op');
                    if ($op === 'add') {
                        $d = Domains::normalize(input('domain'));
                        if (!$d) throw new DomainException('Μη έγκυρο domain.');
                        if (val($db, 'SELECT 1 FROM shop_domains WHERE domain = ?', [$d])) throw new DomainException("Το $d χρησιμοποιείται ήδη.");
                        insert($db, 'shop_domains', ['shop_id' => $id, 'domain' => $d, 'is_primary' => 0, 'created_at' => now()]);
                    } else {
                        $did = (int)input('domain_id');
                        $dom = row($db, 'SELECT * FROM shop_domains WHERE id = ? AND shop_id = ?', [$did, $id]);
                        if (!$dom) throw new DomainException('Το domain δεν βρέθηκε.');
                        if ($op === 'primary') {
                            q($db, 'UPDATE shop_domains SET is_primary = (id = ?) WHERE shop_id = ?', [$did, $id]);
                        } elseif ($op === 'remove') {
                            if ($dom['is_primary']) throw new DomainException('Ορίστε πρώτα άλλο κύριο domain.');
                            q($db, 'DELETE FROM shop_domains WHERE id = ?', [$did]);
                        } elseif ($op === 'status') {
                            update($db, 'shop_domains', ['ssl_status' => in_array(input('ssl'), ['pending', 'active', 'error'], true) ? input('ssl') : 'pending', 'dns_status' => in_array(input('dns'), ['pending', 'ok', 'error'], true) ? input('dns') : 'pending'], 'id = ?', [$did]);
                        }
                    }
                    Audit::log('shop.domains', ['shop' => $id, 'entity' => 'shop', 'id' => $id, 'meta' => ['op' => $op]]);
                    flash('success', 'Τα domains ενημερώθηκαν.');
                    break;
                case 'payment':
                    insert($db, 'payments', ['shop_id' => $id, 'type' => input('type') === 'setup' ? 'setup' : 'subscription', 'amount' => to_float(input('amount')) ?? 0, 'method' => Sanitizer::text(input('method'), 40), 'status' => 'paid', 'reference' => Sanitizer::text(input('reference'), 120), 'paid_at' => now(), 'created_at' => now()]);
                    Audit::log('shop.payment_recorded', ['shop' => $id, 'entity' => 'shop', 'id' => $id]);
                    flash('success', 'Η πληρωμή καταχωρήθηκε.');
                    break;
                case 'owner-password':
                    $uid = (int)input('user_id');
                    $user = row($db, 'SELECT * FROM users WHERE id = ? AND shop_id = ?', [$uid, $id]);
                    if (!$user) throw new DomainException('Ο χρήστης δεν βρέθηκε.');
                    $pass = Provision::password();
                    update($db, 'users', ['password_hash' => Auth::hashPassword($pass), 'must_change_password' => 1, 'failed_attempts' => 0, 'locked_until' => null], 'id = ?', [$uid]);
                    Audit::log('user.password_reset', ['shop' => $id, 'entity' => 'user', 'id' => $uid]);
                    $_SESSION['created_shop'] = ['id' => $id, 'password' => $pass, 'email' => $user['email'], 'url' => Shops::url($id, '/admin/')];
                    flash('success', 'Δημιουργήθηκε νέος προσωρινός κωδικός.');
                    break;
                case 'impersonate':
                    $t = Impersonation::issue(self::$user['id'], $id);
                    Audit::log('impersonation.issued', ['shop' => $id, 'entity' => 'shop', 'id' => $id]);
                    redirect(Shops::url($id, '/admin/impersonate?t=' . urlencode($t)));
                case 'backup':
                    $f = Backup::shop($id, 'manual');
                    Audit::log('shop.backup', ['shop' => $id, 'entity' => 'shop', 'id' => $id]);
                    flash('success', 'Δημιουργήθηκε αντίγραφο: ' . basename((string)$f));
                    break;
                case 'scheduler':
                    Scheduler::refreshShop($id);
                    flash('success', 'Η κατάσταση συνδρομής επανελέγχθηκε.');
                    break;
                case 'delete':
                    if (Domains::normalize(input('confirm_domain')) !== Shops::primaryDomain($id)) {
                        throw new DomainException('Πληκτρολογήστε ακριβώς το κύριο domain για επιβεβαίωση.');
                    }
                    Backup::shop($id, 'before-delete');
                    update($db, 'shops', ['status' => 'DELETED', 'deleted_at' => now(), 'status_changed_at' => now()], 'id = ?', [$id]);
                    History::add($id, 'deleted', ['note' => 'Το κατάστημα διαγράφηκε (τα δεδομένα διατηρούνται μέχρι οριστική διαγραφή).']);
                    Audit::log('shop.deleted', ['shop' => $id, 'entity' => 'shop', 'id' => $id]);
                    flash('success', 'Το κατάστημα διαγράφηκε. Τα δεδομένα του διατηρούνται και μπορεί να γίνει επαναφορά.');
                    break;
                case 'restore':
                    if ($shop['status'] !== 'DELETED') throw new DomainException('Το κατάστημα δεν είναι διαγραμμένο.');
                    update($db, 'shops', ['status' => 'LOCKED', 'deleted_at' => null, 'status_changed_at' => now()], 'id = ?', [$id]);
                    History::add($id, 'restored', ['note' => 'Επαναφορά ως κλειδωμένο. Ανανεώστε ή ξεκλειδώστε για ενεργοποίηση.']);
                    Audit::log('shop.restored', ['shop' => $id, 'entity' => 'shop', 'id' => $id]);
                    flash('success', 'Το κατάστημα επανήλθε σε κατάσταση «Κλειδωμένο».');
                    break;
                case 'purge':
                    if ($shop['status'] !== 'DELETED') throw new DomainException('Η οριστική διαγραφή επιτρέπεται μόνο για ήδη διαγραμμένα καταστήματα.');
                    if (input('confirm_domain') !== 'ΟΡΙΣΤΙΚΗ ΔΙΑΓΡΑΦΗ ' . Shops::primaryDomain($id)) {
                        throw new DomainException('Πληκτρολογήστε ακριβώς τη φράση επιβεβαίωσης.');
                    }
                    Backup::shop($id, 'before-purge');
                    Backup::platform('before-purge-shop-' . $id);
                    DB::forget($id);
                    foreach ([DB::shopPath($id), DB::shopPath($id) . '-wal', DB::shopPath($id) . '-shm'] as $f) @unlink($f);
                    self::rrmdir(UPLOADS . '/' . $id);
                    self::rrmdir(DATA . '/feeds/' . $id);
                    self::rrmdir(DATA . '/imports/' . $id);
                    q($db, 'DELETE FROM shop_domains WHERE shop_id = ?', [$id]);
                    q($db, "UPDATE users SET deleted_at = ?, status = 'disabled' WHERE shop_id = ?", [now(), $id]);
                    update($db, 'shops', ['notes' => trim(($shop['notes'] ?? '') . "\nΟριστική διαγραφή δεδομένων " . now())], 'id = ?', [$id]);
                    Audit::log('shop.purged', ['shop' => $id, 'entity' => 'shop', 'id' => $id]);
                    flash('success', 'Τα δεδομένα του καταστήματος διαγράφηκαν οριστικά. Κρατήθηκε αντίγραφο ασφαλείας στο data/backups.');
                    redirect('/superadmin/shops?status=deleted');
            }
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            Audit::log('shop.' . $action, ['shop' => $id, 'entity' => 'shop', 'id' => $id, 'outcome' => 'error', 'meta' => ['message' => $e->getMessage()]]);
        }
        redirect($back);
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    public static function plans(): void
    {
        $db = DB::platform();
        $plans = Plans::all();
        foreach ($plans as &$p) {
            $p['shops'] = (int)val($db, 'SELECT COUNT(*) FROM shops WHERE plan_id = ? AND deleted_at IS NULL', [$p['id']]);
        }
        unset($p);
        self::page('Πακέτα συνδρομής', 'plans', ['plans' => $plans], 'plans');
    }

    public static function planEdit(string $id): void
    {
        $db = DB::platform();
        $plan = $id === 'new' ? null : Plans::find((int)$id);
        if ($id !== 'new' && !$plan) abort(404);
        $errors = [];
        if (is_post()) {
            $name = Sanitizer::text(input('name'), 60);
            $durations = array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s]+/', input('duration_options'))), fn($x) => $x > 0 && $x <= 60)));
            sort($durations);
            $features = [];
            foreach (array_keys(Plans::FEATURES) as $f) {
                $features[$f] = !empty($_POST['features'][$f]);
            }
            $data = [
                'name' => $name, 'price' => to_float(input('price')) ?? 0, 'setup_fee' => to_float(input('setup_fee')) ?? 0,
                'active_product_limit' => max(0, (int)input('active_product_limit')), 'stored_product_limit' => max(1, (int)input('stored_product_limit')),
                'staff_limit' => max(1, (int)input('staff_limit')), 'storage_mb' => max(50, (int)input('storage_mb')),
                'duration_options' => json_encode($durations), 'features' => json_encode($features), 'status' => input('status') === 'archived' ? 'archived' : 'active',
                'sort' => (int)input('sort'), 'updated_at' => now(),
            ];
            if ($name === '') $errors[] = 'Συμπληρώστε όνομα.';
            if (!$durations) $errors[] = 'Ορίστε τουλάχιστον μία διάρκεια (μήνες).';
            if (!$errors) {
                if ($plan) {
                    update($db, 'subscription_plans', $data, 'id = ?', [$plan['id']]);
                    Audit::log('plan.updated', ['shop' => null, 'entity' => 'plan', 'id' => $plan['id']]);
                } else {
                    $data['slug'] = slugify($name) . '-' . substr(random_token(3), 0, 4);
                    $data['created_at'] = now();
                    $pid = insert($db, 'subscription_plans', $data);
                    Audit::log('plan.created', ['shop' => null, 'entity' => 'plan', 'id' => $pid]);
                }
                flash('success', 'Το πακέτο αποθηκεύτηκε.');
                redirect('/superadmin/plans');
            }
        }
        self::page($plan ? 'Πακέτο: ' . $plan['name'] : 'Νέο πακέτο', 'plan_edit', ['plan' => $plan, 'errors' => $errors], 'plans');
    }

    public static function templates(): void
    {
        $db = DB::platform();
        if (is_post()) {
            $code = input('code');
            $st = input('status');
            if (in_array($st, ['available', 'coming_soon', 'disabled'], true)) {
                if ($st === 'available' && !Theme::exists($code)) {
                    flash('error', 'Το πρότυπο δεν έχει υλοποιηθεί ακόμη και δεν μπορεί να γίνει διαθέσιμο.');
                } else {
                    update($db, 'templates', ['status' => $st], 'code = ?', [$code]);
                    Audit::log('template.status', ['shop' => null, 'entity' => 'template', 'id' => $code, 'meta' => ['status' => $st]]);
                    flash('success', 'Ενημερώθηκε.');
                }
            }
            redirect('/superadmin/templates');
        }
        $tpls = rows($db, 'SELECT t.*, (SELECT COUNT(*) FROM shops s WHERE s.template_code = t.code AND s.deleted_at IS NULL) shops FROM templates t ORDER BY sort');
        self::page('Πρότυπα καταστημάτων', 'templates', ['tpls' => $tpls], 'templates');
    }

    public static function users(): void
    {
        $db = DB::platform();
        if (is_post()) {
            $op = input('op');
            if ($op === 'create') {
                $email = strtolower(input('email'));
                $pass = (string)($_POST['password'] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    flash('error', 'Μη έγκυρο email.');
                } elseif ($p = Auth::passwordProblem($pass)) {
                    flash('error', $p);
                } elseif (val($db, "SELECT 1 FROM users WHERE lower(email) = ? AND shop_id IS NULL AND deleted_at IS NULL", [$email])) {
                    flash('error', 'Υπάρχει ήδη χρήστης με αυτό το email.');
                } else {
                    $uid = insert($db, 'users', ['email' => $email, 'name' => Sanitizer::text(input('name'), 120), 'password_hash' => Auth::hashPassword($pass), 'role' => 'superadmin', 'created_by' => self::$user['id'], 'created_at' => now()]);
                    Audit::log('superadmin.created', ['shop' => null, 'entity' => 'user', 'id' => $uid]);
                    flash('success', 'Ο χρήστης δημιουργήθηκε.');
                }
            } elseif ($op === 'disable' || $op === 'enable') {
                $uid = (int)input('id');
                if ($uid === (int)self::$user['id']) {
                    flash('error', 'Δεν μπορείτε να απενεργοποιήσετε τον εαυτό σας.');
                } else {
                    update($db, 'users', ['status' => $op === 'disable' ? 'disabled' : 'active'], "id = ? AND role = 'superadmin'", [$uid]);
                    Audit::log('superadmin.' . $op, ['shop' => null, 'entity' => 'user', 'id' => $uid]);
                    flash('success', 'Ενημερώθηκε.');
                }
            }
            redirect('/superadmin/users');
        }
        $users = rows($db, "SELECT * FROM users WHERE role = 'superadmin' AND deleted_at IS NULL ORDER BY id");
        self::page('Χρήστες συστήματος', 'users', ['users' => $users], 'users');
    }

    public static function audit(): void
    {
        $db = DB::platform();
        $where = ['1=1'];
        $p = [];
        if ($s = (int)input('shop')) { $where[] = 'a.shop_id = ?'; $p[] = $s; }
        if ($a = input('action')) { $where[] = 'a.action LIKE ?'; $p[] = $a . '%'; }
        $page = max(1, (int)input('page', 1));
        $total = (int)val($db, 'SELECT COUNT(*) FROM audit_logs a WHERE ' . implode(' AND ', $where), $p);
        $logs = rows($db, 'SELECT a.*, s.name shop_name FROM audit_logs a LEFT JOIN shops s ON s.id = a.shop_id WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC LIMIT 100 OFFSET ' . (($page - 1) * 100), $p);
        self::page('Αρχείο ενεργειών', 'audit', ['logs' => $logs, 'total' => $total, 'page' => $page, 'shops' => rows($db, 'SELECT id, name FROM shops ORDER BY name')], 'audit');
    }

    public static function notifications(): void
    {
        $db = DB::platform();
        if (is_post()) {
            if (input('op') === 'read_all') {
                q($db, 'UPDATE notifications SET read_at = ? WHERE read_at IS NULL', [now()]);
            } else {
                update($db, 'notifications', ['read_at' => now()], 'id = ?', [(int)input('id')]);
            }
            redirect('/superadmin/notifications');
        }
        $list = rows($db, "SELECT n.*, s.name shop_name FROM notifications n LEFT JOIN shops s ON s.id = n.shop_id WHERE n.audience IN ('superadmin','both') ORDER BY n.id DESC LIMIT 200");
        self::page('Ειδοποιήσεις', 'notifications', ['list' => $list], 'notif');
    }

    public static function settings(): void
    {
        $db = DB::platform();
        if (is_post()) {
            $warn = array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s]+/', input('warning_days'))), fn($x) => $x > 0 && $x < 366)));
            rsort($warn);
            setting_set($db, 'platform_name', Sanitizer::text(input('platform_name'), 80) ?: 'WeChevo');
            setting_set($db, 'notify_email', filter_var(input('notify_email'), FILTER_VALIDATE_EMAIL) ? input('notify_email') : '');
            setting_set($db, 'grace_days', max(0, min(60, (int)input('grace_days'))));
            setting_set($db, 'warning_days', $warn ?: [10, 3, 1]);
            setting_set($db, 'force_https', input('force_https') === '1');
            setting_set($db, 'mail_mode', in_array(input('mail_mode'), ['log', 'mail', 'smtp'], true) ? input('mail_mode') : 'log');
            setting_set($db, 'mail_from', filter_var(input('mail_from'), FILTER_VALIDATE_EMAIL) ? input('mail_from') : setting_get($db, 'mail_from'));
            $smtp = setting_get($db, 'smtp', []);
            $smtp = ['host' => Sanitizer::text(input('smtp_host'), 120), 'port' => (int)input('smtp_port') ?: 465, 'user' => Sanitizer::text(input('smtp_user'), 160),
                'pass' => ($_POST['smtp_pass'] ?? '') !== '' ? (string)$_POST['smtp_pass'] : ($smtp['pass'] ?? '')];
            setting_set($db, 'smtp', $smtp);
            Audit::log('platform.settings', ['shop' => null]);
            if (input('test_email')) {
                $ok = Mailer::send(input('test_email'), 'Δοκιμαστικό email', 'Αν το βλέπετε, η αποστολή email λειτουργεί.');
                flash($ok ? 'success' : 'error', $ok ? 'Στάλθηκε δοκιμαστικό email (ή καταγράφηκε στο data/logs/mail.log σε λειτουργία log).' : 'Η αποστολή απέτυχε. Δείτε το data/logs/error.log.');
            }
            flash('success', 'Οι ρυθμίσεις αποθηκεύτηκαν.');
            redirect('/superadmin/settings');
        }
        $last = (int)setting_get($db, 'scheduler_last_run', 0);
        self::page('Ρυθμίσεις πλατφόρμας', 'settings', ['last' => $last], 'settings');
    }

    public static function backups(): void
    {
        if (is_post()) {
            if (input('op') === 'platform') {
                Backup::platform('manual');
            } else {
                foreach (rows(DB::platform(), 'SELECT id FROM shops WHERE deleted_at IS NULL') as $s) {
                    Backup::shop((int)$s['id'], 'manual-all');
                }
                Backup::platform('manual-all');
            }
            Audit::log('backup.created', ['shop' => null]);
            flash('success', 'Τα αντίγραφα ασφαλείας δημιουργήθηκαν.');
            redirect('/superadmin/backups');
        }
        self::page('Αντίγραφα ασφαλείας', 'backups', ['list' => Backup::list()], 'backups');
    }

    public static function backupDownload(): void
    {
        $scope = basename(input('scope'));
        $file = basename(input('file'));
        $path = DATA . '/backups/' . $scope . '/' . $file;
        if (!preg_match('/^(platform|shop-\d+)$/', $scope) || !preg_match('/^[\w.-]+\.sqlite$/', $file) || !is_file($path)) {
            abort(404);
        }
        Audit::log('backup.downloaded', ['shop' => null, 'meta' => ['file' => "$scope/$file"]]);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $scope . '-' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public static function account(): void
    {
        $db = DB::platform();
        if (is_post()) {
            $cur = (string)($_POST['current'] ?? '');
            $new = (string)($_POST['password'] ?? '');
            if (!password_verify($cur, self::$user['password_hash'])) {
                flash('error', 'Ο τρέχων κωδικός είναι λάθος.');
            } elseif ($p = Auth::passwordProblem($new)) {
                flash('error', $p);
            } else {
                update($db, 'users', ['password_hash' => Auth::hashPassword($new), 'must_change_password' => 0, 'updated_at' => now()], 'id = ?', [self::$user['id']]);
                Audit::log('user.password_changed', ['shop' => null]);
                flash('success', 'Ο κωδικός άλλαξε.');
            }
            redirect('/superadmin/account');
        }
        self::page('Ο λογαριασμός μου', 'account', [], 'account');
    }
}
