<?php
/* Control plane: plans, shops, domains, subscriptions, status machine, notifications, provisioning, backups. */

final class Plans
{
    public static function all(bool $activeOnly = false): array
    {
        return rows(DB::platform(), 'SELECT * FROM subscription_plans' . ($activeOnly ? " WHERE status = 'active'" : '') . ' ORDER BY sort, price');
    }

    public static function find(int $id): ?array
    {
        return row(DB::platform(), 'SELECT * FROM subscription_plans WHERE id = ?', [$id]);
    }

    public static function features(array $plan): array
    {
        return json_decode($plan['features'] ?: '{}', true) ?: [];
    }

    public static function durations(array $plan): array
    {
        $d = json_decode($plan['duration_options'] ?: '[]', true);
        return array_values(array_filter(array_map('intval', is_array($d) ? $d : []), fn($x) => $x > 0));
    }

    public const FEATURES = [
        'import' => 'Εισαγωγή CSV/XML',
        'export' => 'Εξαγωγή CSV',
        'feeds' => 'XML feeds & συγκριτικά τιμών',
        'popups' => 'Pop-up & newsletter',
        'seo_advanced' => 'Προχωρημένο SEO',
        'staff' => 'Πολλαπλοί χρήστες',
        'custom_domain' => 'Δικό σας domain',
    ];
}

final class Shops
{
    /** Status machine. Only these keep the storefront online. */
    public const SERVING = ['ACTIVE', 'EXPIRING', 'EXPIRED'];
    public const STATUSES = ['PENDING', 'ACTIVE', 'EXPIRING', 'EXPIRED', 'LOCKED', 'SUSPENDED', 'DELETED'];
    public const LABELS = [
        'PENDING' => 'Σε αναμονή', 'ACTIVE' => 'Ενεργό', 'EXPIRING' => 'Λήγει σύντομα', 'EXPIRED' => 'Έληξε (περίοδος χάριτος)',
        'LOCKED' => 'Κλειδωμένο', 'SUSPENDED' => 'Σε αναστολή', 'DELETED' => 'Διαγραμμένο',
    ];

    public static function find(int $id): ?array
    {
        return row(DB::platform(), 'SELECT s.*, p.name AS plan_name, p.active_product_limit, p.stored_product_limit, p.staff_limit, p.features, p.price AS plan_price
            FROM shops s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.id = ?', [$id]);
    }

    public static function byDomain(string $host): ?array
    {
        $host = strtolower($host);
        $bare = preg_replace('/^www\./', '', $host);
        $d = row(DB::platform(), 'SELECT shop_id, domain FROM shop_domains WHERE domain IN (?, ?) ORDER BY domain = ? DESC LIMIT 1', [$host, $bare, $host]);
        return $d ? self::find((int)$d['shop_id']) : null;
    }

    public static function primaryDomain(int $shopId): ?string
    {
        return val(DB::platform(), 'SELECT domain FROM shop_domains WHERE shop_id = ? ORDER BY is_primary DESC, id LIMIT 1', [$shopId]);
    }

    public static function url(int $shopId, string $path = '/'): string
    {
        $d = self::primaryDomain($shopId) ?? 'localhost';
        $local = str_ends_with($d, '.localhost') || $d === 'localhost';
        return ($local ? 'http://' : 'https://') . $d . ($local ? req_port_suffix() : '') . $path;
    }

    public static function activeLimit(array $shop): int
    {
        return (int)($shop['active_limit_override'] ?? $shop['active_product_limit']);
    }

    public static function storedLimit(array $shop): int
    {
        return (int)($shop['stored_limit_override'] ?? $shop['stored_product_limit']);
    }

    public static function feature(array $shop, string $f): bool
    {
        $fx = json_decode($shop['features'] ?? '{}', true) ?: [];
        return !empty($fx[$f]);
    }

    public static function isServing(array $shop): bool
    {
        return in_array($shop['status'], self::SERVING, true);
    }

    public static function daysLeft(array $shop): ?int
    {
        if (!$shop['subscription_expires']) {
            return null;
        }
        return (int)ceil((strtotime($shop['subscription_expires'] . ' UTC') - time()) / 86400);
    }

    public static function setStatus(int $shopId, string $status, string $historyAction, ?string $note = null): void
    {
        $s = self::find($shopId);
        if (!$s || $s['status'] === $status) {
            return;
        }
        update(DB::platform(), 'shops', ['status' => $status, 'status_changed_at' => now(), 'updated_at' => now()], 'id = ?', [$shopId]);
        History::add($shopId, $historyAction, ['note' => $note ?? ($s['status'] . ' → ' . $status)]);
    }
}

final class History
{
    public static function add(int $shopId, string $action, array $o = []): void
    {
        insert(DB::platform(), 'subscription_history', [
            'shop_id' => $shopId,
            'action' => $action,
            'plan_id' => $o['plan_id'] ?? null,
            'duration_months' => $o['months'] ?? null,
            'start_at' => $o['start'] ?? null,
            'end_at' => $o['end'] ?? null,
            'actor_user_id' => $o['actor'] ?? Auth::id(),
            'note' => $o['note'] ?? null,
            'created_at' => now(),
        ]);
    }

    public const LABELS = [
        'created' => 'Δημιουργία', 'renewed' => 'Ανανέωση', 'plan_changed' => 'Αλλαγή πακέτου', 'expiring' => 'Λήγει σύντομα',
        'expired' => 'Λήξη', 'locked' => 'Κλείδωμα', 'unlocked' => 'Ξεκλείδωμα', 'suspended' => 'Αναστολή',
        'reactivated' => 'Επανενεργοποίηση', 'deleted' => 'Διαγραφή', 'restored' => 'Επαναφορά', 'activated' => 'Ενεργοποίηση',
    ];
}

final class Subscriptions
{
    public static function addMonths(string $fromUtc, int $months): string
    {
        $d = new DateTime($fromUtc, new DateTimeZone('UTC'));
        $day = (int)$d->format('d');
        $d->modify('first day of this month')->modify("+$months month");
        $d->setDate((int)$d->format('Y'), (int)$d->format('m'), min($day, (int)$d->format('t')));
        return $d->format('Y-m-d H:i:s');
    }

    /**
     * Renew / reset timer. Touches ONLY subscription fields + status. Never products, orders, settings, users.
     * Extends from the current expiry when still in the future, otherwise from now.
     */
    public static function renew(int $shopId, int $months, array $payment = []): array
    {
        $db = DB::platform();
        $shop = Shops::find($shopId);
        if (!$shop) {
            throw new DomainException('Το κατάστημα δεν βρέθηκε.');
        }
        $plan = Plans::find((int)$shop['plan_id']);
        if (!in_array($months, Plans::durations($plan), true)) {
            throw new DomainException('Η διάρκεια δεν επιτρέπεται για το πακέτο ' . $plan['name'] . '.');
        }
        $base = ($shop['subscription_expires'] && $shop['subscription_expires'] > now()) ? $shop['subscription_expires'] : now();
        $end = self::addMonths($base, $months);
        $db->beginTransaction();
        try {
            q($db, "UPDATE subscriptions SET status = 'expired' WHERE shop_id = ? AND status = 'active' AND end_at <= ?", [$shopId, now()]);
            $subId = insert($db, 'subscriptions', [
                'shop_id' => $shopId, 'plan_id' => $plan['id'], 'start_at' => $base, 'end_at' => $end,
                'duration_months' => $months, 'status' => 'active', 'source' => $payment['provider'] ?? 'manual', 'created_at' => now(),
            ]);
            if (!empty($payment['amount'])) {
                insert($db, 'payments', [
                    'shop_id' => $shopId, 'subscription_id' => $subId, 'provider_code' => $payment['provider'] ?? 'manual',
                    'type' => 'subscription', 'amount' => (float)$payment['amount'], 'method' => $payment['method'] ?? null,
                    'status' => 'paid', 'reference' => $payment['reference'] ?? null, 'paid_at' => now(), 'created_at' => now(),
                ]);
            }
            $newStart = $shop['subscription_start'] && $shop['status'] !== 'LOCKED' ? $shop['subscription_start'] : $base;
            update($db, 'shops', [
                'subscription_start' => $newStart, 'subscription_expires' => $end, 'subscription_duration' => $months,
                'updated_at' => now(),
            ], 'id = ?', [$shopId]);
            History::add($shopId, 'renewed', ['plan_id' => $plan['id'], 'months' => $months, 'start' => $base, 'end' => $end, 'note' => $payment['note'] ?? null]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $status = in_array($shop['status'], ['SUSPENDED', 'DELETED', 'PENDING'], true) ? $shop['status'] : 'ACTIVE';
        update($db, 'shops', ['status' => $status, 'status_changed_at' => now()], 'id = ?', [$shopId]);
        Scheduler::refreshShop($shopId);
        Audit::log('subscription.renewed', ['shop' => $shopId, 'entity' => 'shop', 'id' => $shopId, 'meta' => ['months' => $months, 'expires' => $end]]);
        return ['expires' => $end];
    }

    /**
     * Change package. On downgrade with too many active products, the caller must confirm;
     * we then deactivate the most recently activated ones. Never deletes products.
     */
    public static function changePlan(int $shopId, int $planId, bool $confirmAutoDeactivate): array
    {
        $shop = Shops::find($shopId);
        $plan = Plans::find($planId);
        if (!$shop || !$plan) {
            throw new DomainException('Μη έγκυρο κατάστημα ή πακέτο.');
        }
        $newLimit = (int)($shop['active_limit_override'] ?? $plan['active_product_limit']);
        $active = Catalog::activeCount(DB::shop($shopId));
        $excess = max(0, $active - $newLimit);
        if ($excess > 0 && !$confirmAutoDeactivate) {
            return ['needs_confirmation' => true, 'excess' => $excess, 'active' => $active, 'limit' => $newLimit];
        }
        $deactivated = [];
        if ($excess > 0) {
            Backup::shop($shopId, 'before-downgrade');
            $deactivated = Catalog::deactivateNewest(DB::shop($shopId), $excess);
        }
        update(DB::platform(), 'shops', ['plan_id' => $planId, 'updated_at' => now()], 'id = ?', [$shopId]);
        History::add($shopId, 'plan_changed', ['plan_id' => $planId, 'note' => $shop['plan_name'] . ' → ' . $plan['name'] . ($excess ? " · απενεργοποιήθηκαν $excess προϊόντα" : '')]);
        Audit::log('shop.plan_changed', ['shop' => $shopId, 'entity' => 'shop', 'id' => $shopId, 'meta' => ['from' => $shop['plan_id'], 'to' => $planId, 'deactivated' => $deactivated]]);
        if ($excess) {
            Notify::add('quota.downgrade', "Αλλαγή πακέτου: {$shop['name']}", "Απενεργοποιήθηκαν $excess προϊόντα (τα πιο πρόσφατα ενεργοποιημένα) λόγω νέου ορίου $newLimit.", $shopId, 'warning', null, 'both');
        }
        return ['needs_confirmation' => false, 'deactivated' => $deactivated];
    }
}

final class Notify
{
    public static function add(string $type, string $title, string $body, ?int $shopId = null, string $level = 'info', ?string $dedupe = null, string $audience = 'superadmin'): bool
    {
        try {
            insert(DB::platform(), 'notifications', [
                'shop_id' => $shopId, 'type' => $type, 'level' => $level, 'title' => $title, 'body' => $body,
                'dedupe_key' => $dedupe, 'audience' => $audience, 'created_at' => now(),
            ]);
            return true;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                return false; // already notified
            }
            throw $e;
        }
    }

    /** Email pending super-admin notifications to the platform notification address. */
    public static function dispatch(): int
    {
        $db = DB::platform();
        $to = setting_get($db, 'notify_email');
        if (!$to) {
            return 0;
        }
        $n = 0;
        foreach (rows($db, "SELECT * FROM notifications WHERE emailed_at IS NULL AND audience IN ('superadmin','both') ORDER BY id LIMIT 50") as $r) {
            Mailer::send($to, '[WeChevo] ' . $r['title'], $r['body'] . "\n\n— " . setting_get($db, 'platform_name', 'WeChevo'));
            update($db, 'notifications', ['emailed_at' => now()], 'id = ?', [$r['id']]);
            $n++;
        }
        return $n;
    }
}

final class Scheduler
{
    /** Called by cron.php and lazily (at most every 10 min) from web requests. */
    public static function maybeRun(): void
    {
        $db = DB::platform();
        $last = (int)setting_get($db, 'scheduler_last_run', 0);
        if (time() - $last < 600) {
            return;
        }
        setting_set($db, 'scheduler_last_run', time());
        try {
            self::run();
        } catch (Throwable $e) {
            log_error('scheduler: ' . $e->getMessage());
        }
    }

    public static function run(): array
    {
        $out = ['checked' => 0];
        foreach (rows(DB::platform(), "SELECT id FROM shops WHERE status IN ('ACTIVE','EXPIRING','EXPIRED','PENDING') AND deleted_at IS NULL") as $s) {
            self::refreshShop((int)$s['id']);
            $out['checked']++;
        }
        $out['emailed'] = Notify::dispatch();
        RateLimit::cleanup();
        q(DB::platform(), 'DELETE FROM impersonation_tokens WHERE expires_at < ?', [now()]);
        setting_set(DB::platform(), 'scheduler_last_run', time());
        return $out;
    }

    /** ACTIVE → EXPIRING (≤ warn window) → EXPIRED (past expiry, grace) → LOCKED (after grace days). */
    public static function refreshShop(int $shopId): void
    {
        $db = DB::platform();
        $s = Shops::find($shopId);
        if (!$s || !in_array($s['status'], ['ACTIVE', 'EXPIRING', 'EXPIRED', 'PENDING'], true) || !$s['subscription_expires']) {
            return;
        }
        $warnDays = array_map('intval', setting_get($db, 'warning_days', [10, 3, 1]));
        rsort($warnDays);
        $grace = (int)setting_get($db, 'grace_days', 3);
        $now = time();
        $exp = strtotime($s['subscription_expires'] . ' UTC');
        $start = $s['subscription_start'] ? strtotime($s['subscription_start'] . ' UTC') : 0;

        if ($s['status'] === 'PENDING') {
            if ($start && $start <= $now) {
                Shops::setStatus($shopId, 'ACTIVE', 'activated');
                $s['status'] = 'ACTIVE';
            } else {
                return;
            }
        }

        $secondsLeft = $exp - $now;
        $daysLeft = (int)ceil($secondsLeft / 86400);
        if ($secondsLeft <= 0) {
            if ($now >= $exp + $grace * 86400) {
                Shops::setStatus($shopId, 'LOCKED', 'locked', 'Αυτόματο κλείδωμα μετά από ' . $grace . ' ημέρες χάριτος');
                Notify::add('shop.locked', "Κλειδώθηκε: {$s['name']}", "Η συνδρομή έληξε στις " . dt($s['subscription_expires']) . " και πέρασε η περίοδος χάριτος ($grace ημέρες). Το κατάστημα εμφανίζει τη σελίδα «προσωρινά μη διαθέσιμο».", $shopId, 'danger', "locked:$shopId:{$s['subscription_expires']}");
                Audit::log('shop.locked', ['shop' => $shopId, 'actor' => null, 'label' => 'system', 'entity' => 'shop', 'id' => $shopId]);
            } else {
                Shops::setStatus($shopId, 'EXPIRED', 'expired');
                Notify::add('shop.expired', "Έληξε: {$s['name']}", "Η συνδρομή έληξε στις " . dt($s['subscription_expires']) . ". Το κατάστημα θα κλειδώσει στις " . dt(gmdate('Y-m-d H:i:s', $exp + $grace * 86400)) . " αν δεν ανανεωθεί.", $shopId, 'danger', "expired:$shopId:{$s['subscription_expires']}");
            }
            return;
        }
        $maxWarn = $warnDays[0] ?? 10;
        if ($daysLeft <= $maxWarn) {
            Shops::setStatus($shopId, 'EXPIRING', 'expiring');
            // tightest threshold reached: 10 → 3 → 1 days; dedupe so each fires once per expiry date
            $hit = null;
            foreach ($warnDays as $t) {
                if ($daysLeft <= $t) {
                    $hit = $t;
                }
            }
            if ($hit !== null) {
                Notify::add('subscription.warning', "Λήγει σε $daysLeft " . ($daysLeft === 1 ? 'ημέρα' : 'ημέρες') . ": {$s['name']}", "Η συνδρομή του καταστήματος {$s['name']} λήγει στις " . dt($s['subscription_expires']) . ". Ανανεώστε από τη σελίδα του καταστήματος.", $shopId, $hit <= 1 ? 'danger' : 'warning', "warn:$shopId:{$s['subscription_expires']}:$hit");
            }
        } elseif ($s['status'] !== 'ACTIVE') {
            Shops::setStatus($shopId, 'ACTIVE', 'reactivated');
        }
    }
}

final class Provision
{
    /** Shop creation wizard back end. Returns [shopId, ownerPassword]. */
    public static function create(array $in): array
    {
        $db = DB::platform();
        $plan = Plans::find((int)$in['plan_id']);
        if (!$plan) {
            throw new DomainException('Επιλέξτε πακέτο.');
        }
        $months = (int)$in['months'];
        if (!in_array($months, Plans::durations($plan), true)) {
            throw new DomainException('Μη έγκυρη διάρκεια συνδρομής.');
        }
        $tpl = row($db, "SELECT * FROM templates WHERE code = ? AND status = 'available'", [$in['template']]);
        if (!$tpl) {
            throw new DomainException('Το πρότυπο δεν είναι διαθέσιμο.');
        }
        $domain = Domains::normalize($in['domain']);
        if (!$domain) {
            throw new DomainException('Μη έγκυρο domain.');
        }
        if (val($db, 'SELECT 1 FROM shop_domains WHERE domain = ?', [$domain])) {
            throw new DomainException("Το domain $domain χρησιμοποιείται ήδη.");
        }
        $email = strtolower(trim($in['admin_email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Μη έγκυρο email διαχειριστή.');
        }
        $start = !empty($in['start_date']) ? gmdate('Y-m-d H:i:s', strtotime($in['start_date'] . ' 00:00:00 Europe/Athens')) : now();
        $end = Subscriptions::addMonths($start, $months);
        $slug = slugify($in['name']);
        $base = $slug;
        for ($i = 2; val($db, 'SELECT 1 FROM shops WHERE slug = ?', [$slug]); $i++) {
            $slug = $base . '-' . $i;
        }
        $password = $in['admin_password'] ?: self::password();
        if ($p = Auth::passwordProblem($password)) {
            throw new DomainException($p);
        }
        $status = strtotime($start . ' UTC') > time() ? 'PENDING' : ($in['initial_status'] ?? 'ACTIVE');

        $db->beginTransaction();
        try {
            $shopId = insert($db, 'shops', [
                'name' => Sanitizer::text($in['name'], 150), 'slug' => $slug,
                'owner_name' => Sanitizer::text($in['owner_name'] ?? '', 150), 'email' => $in['email'] ?? $email,
                'phone' => Sanitizer::text($in['phone'] ?? '', 40), 'vat_number' => Sanitizer::text($in['vat_number'] ?? '', 20),
                'address' => Sanitizer::text($in['address'] ?? '', 250), 'hosting_provider' => Sanitizer::text($in['hosting_provider'] ?? '', 100),
                'hosting_notes' => Sanitizer::text($in['hosting_notes'] ?? '', 2000),
                'plan_id' => $plan['id'], 'template_code' => $tpl['code'], 'status' => $status,
                'subscription_start' => $start, 'subscription_expires' => $end, 'subscription_duration' => $months,
                'status_changed_at' => now(), 'created_at' => now(),
            ]);
            insert($db, 'shop_domains', ['shop_id' => $shopId, 'domain' => $domain, 'is_primary' => 1, 'created_at' => now()]);
            $subId = insert($db, 'subscriptions', [
                'shop_id' => $shopId, 'plan_id' => $plan['id'], 'start_at' => $start, 'end_at' => $end,
                'duration_months' => $months, 'status' => 'active', 'source' => 'manual', 'created_at' => now(),
            ]);
            if (!empty($in['setup_fee_paid']) && (float)$plan['setup_fee'] > 0) {
                insert($db, 'payments', ['shop_id' => $shopId, 'subscription_id' => $subId, 'type' => 'setup', 'amount' => $plan['setup_fee'], 'status' => 'paid', 'paid_at' => now(), 'created_at' => now(), 'method' => 'manual']);
            }
            insert($db, 'users', [
                'shop_id' => $shopId, 'email' => $email, 'name' => Sanitizer::text($in['admin_name'] ?? $in['owner_name'] ?? '', 150),
                'password_hash' => Auth::hashPassword($password), 'role' => 'owner', 'permissions' => json_encode(array_keys(Perm::MODULES)),
                'must_change_password' => empty($in['admin_password']) ? 1 : 0, 'created_by' => Auth::id(), 'created_at' => now(),
            ]);
            History::add($shopId, 'created', ['plan_id' => $plan['id'], 'months' => $months, 'start' => $start, 'end' => $end]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        ShopSeed::run($shopId, $in);
        Audit::log('shop.created', ['shop' => $shopId, 'entity' => 'shop', 'id' => $shopId, 'meta' => ['domain' => $domain, 'plan' => $plan['slug']]]);
        if (!empty($in['send_credentials'])) {
            Mailer::send($email, 'Το e-shop σας είναι έτοιμο', "Καλώς ήρθατε!\n\nΣύνδεση διαχείρισης: " . Shops::url($shopId, '/admin/') . "\nEmail: $email\nΠροσωρινός κωδικός: $password\n\nΣας ζητείται αλλαγή κωδικού στην πρώτη σύνδεση.");
        }
        return [$shopId, $password];
    }

    public static function password(): string
    {
        return 'Wc-' . substr(random_token(12), 0, 10) . random_int(10, 99);
    }
}

final class Domains
{
    public static function normalize(string $d): ?string
    {
        $d = strtolower(trim($d));
        $d = preg_replace('#^https?://#', '', $d);
        $d = preg_replace('#[/:].*$#', '', $d);
        $d = preg_replace('/^www\./', '', $d);
        if (function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7F]/', $d)) {
            $d = idn_to_ascii($d) ?: $d;
        }
        return preg_match('/^(?=.{3,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/', $d) ? $d : null;
    }
}

final class Backup
{
    /** Consistent copy of a shop DB (VACUUM INTO). Kept in data/backups/. */
    public static function shop(int $shopId, string $reason): ?string
    {
        if (!is_file(DB::shopPath($shopId))) {
            return null;
        }
        $dir = DATA . '/backups/shop-' . $shopId;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . gmdate('Ymd-His') . '-' . slugify($reason) . '.sqlite';
        DB::shop($shopId)->exec('VACUUM INTO ' . DB::shop($shopId)->quote($file));
        self::prune($dir, 15);
        return $file;
    }

    public static function platform(string $reason): string
    {
        $dir = DATA . '/backups/platform';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . gmdate('Ymd-His') . '-' . slugify($reason) . '.sqlite';
        DB::platform()->exec('VACUUM INTO ' . DB::platform()->quote($file));
        self::prune($dir, 30);
        return $file;
    }

    public static function list(): array
    {
        $out = [];
        foreach (glob(DATA . '/backups/*/*.sqlite') ?: [] as $f) {
            $out[] = ['scope' => basename(dirname($f)), 'file' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
        }
        usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    private static function prune(string $dir, int $keep): void
    {
        $files = glob($dir . '/*.sqlite') ?: [];
        rsort($files);
        foreach (array_slice($files, $keep) as $f) {
            @unlink($f);
        }
    }
}

final class Impersonation
{
    public static function issue(int $superId, int $shopId): string
    {
        $t = random_token(32);
        insert(DB::platform(), 'impersonation_tokens', ['token_hash' => hash('sha256', $t), 'superadmin_id' => $superId, 'shop_id' => $shopId, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 120)]);
        return $t;
    }

    /** One-time, 2-minute token. Returns [superadminId, shopId] or null. */
    public static function redeem(string $t, int $shopId): ?array
    {
        $db = DB::platform();
        $r = row($db, 'SELECT * FROM impersonation_tokens WHERE token_hash = ?', [hash('sha256', $t)]);
        if (!$r || $r['used_at'] || $r['expires_at'] < now() || (int)$r['shop_id'] !== $shopId) {
            return null;
        }
        update($db, 'impersonation_tokens', ['used_at' => now()], 'token_hash = ?', [$r['token_hash']]);
        return [(int)$r['superadmin_id'], (int)$r['shop_id']];
    }
}
