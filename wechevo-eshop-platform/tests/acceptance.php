<?php
/**
 * End-to-end acceptance tests (spec §74–§80 + purchase journey + security).
 * Runs against a local dev server on a FRESH install:
 *
 *   rm -rf data/*.sqlite data/config.php data/shops data/feeds data/imports uploads/[0-9]*
 *   php -S 127.0.0.1:8000 index.php &
 *   php tests/acceptance.php
 *
 * Uses hosts localhost (platform) and a.localhost / b.localhost / c.localhost (shops).
 */
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
$PORT = (int)($argv[1] ?? 8000);
$fails = 0;
$passes = 0;
$t0 = microtime(true);

function ok(bool $cond, string $label, string $detail = ''): void
{
    global $fails, $passes;
    if ($cond) {
        $passes++;
        echo "  \033[32m✓\033[0m $label\n";
    } else {
        $fails++;
        echo "  \033[31m✗ $label\033[0m" . ($detail ? "  → $detail" : '') . "\n";
    }
}
function section(string $s): void
{
    echo "\n\033[1m$s\033[0m\n";
}

final class Client
{
    private string $jar;
    public array $last = [];
    public function __construct(public string $name)
    {
        $this->jar = sys_get_temp_dir() . '/wc-test-' . $name . '-' . getmypid() . '.txt';
        @unlink($this->jar);
    }
    public function req(string $method, string $url, array $data = [], array $files = [], array $headers = []): array
    {
        global $PORT;
        $ch = curl_init($url);
        $host = parse_url($url, PHP_URL_HOST);
        $opts = [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RESOLVE => ["$host:$PORT:127.0.0.1"], CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 120,
        ];
        if ($method === 'POST') {
            if ($files) {
                $body = $data;
                foreach ($files as $k => $f) {
                    $body[$k] = new CURLFile($f[0], $f[1] ?? 'application/octet-stream', $f[2] ?? basename($f[0]));
                }
                $opts[CURLOPT_POSTFIELDS] = $body;
            } else {
                $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
            }
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr((string)$raw, 0, $hs);
        $body = substr((string)$raw, $hs);
        preg_match('/^Location:\s*(.+)$/mi', $head, $m);
        return $this->last = ['code' => $code, 'body' => $body, 'location' => trim($m[1] ?? ''), 'head' => $head];
    }
    public function get(string $url, array $h = []): array { return $this->req('GET', $url, [], [], $h); }
    public function post(string $url, array $d, array $files = [], array $h = []): array { return $this->req('POST', $url, $d, $files, $h); }
    /** GET a page, then POST to $action with the page's CSRF token. */
    public function form(string $pageUrl, string $action, array $data, array $files = [], array $h = []): array
    {
        $page = $this->get($pageUrl);
        $data['_csrf'] = self::csrf($page['body']);
        return $this->post($action, $data, $files, $h);
    }
    public static function csrf(string $html): string
    {
        return preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? html_entity_decode($m[1]) : (preg_match('/name="csrf" content="([^"]+)"/', $html, $m2) ? $m2[1] : '');
    }
    public function cookie(string $name): ?string
    {
        foreach (@file($this->jar) ?: [] as $l) {
            $p = explode("\t", trim($l));
            if (count($p) >= 7 && $p[5] === $name) return $p[6];
        }
        return null;
    }
}

function db(string $file): PDO
{
    $p = new PDO('sqlite:' . ROOT . '/data/' . $file);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $p->exec('PRAGMA busy_timeout=5000');
    return $p;
}
function one(PDO $db, string $sql, array $p = [])
{
    $s = $db->prepare($sql);
    $s->execute($p);
    return $s->fetchColumn();
}
function cron(): string
{
    return (string)shell_exec('php ' . escapeshellarg(ROOT . '/cron.php') . ' 2>&1');
}

$P = "http://localhost:$PORT";
$A = "http://a.localhost:$PORT";
$B = "http://b.localhost:$PORT";
$C = "http://c.localhost:$PORT";
$super = new Client('super');

/* ------------------------------------------------------------------ */
section('Εγκατάσταση & Super Admin');
if (is_file(ROOT . '/data/config.php')) {
    echo "  ! Υπάρχει ήδη εγκατάσταση — οι δοκιμές απαιτούν καθαρή εγκατάσταση.\n";
    exit(2);
}
$r = $super->get("$P/");
ok($r['code'] === 302 && str_contains($r['location'], '/superadmin/setup'), 'Πρώτη επίσκεψη → οδηγός εγκατάστασης');
$r = $super->form("$P/superadmin/setup", "$P/superadmin/setup", ['platform_name' => 'WeChevo', 'platform_host' => 'localhost', 'name' => 'Super', 'email' => 'super@example.gr', 'password' => 'short', 'password2' => 'short']);
ok($r['code'] === 200 && str_contains($r['body'], 'τουλάχιστον 10'), 'Αδύναμος κωδικός απορρίπτεται');
$r = $super->form("$P/superadmin/setup", "$P/superadmin/setup", ['platform_name' => 'WeChevo', 'platform_host' => 'localhost', 'name' => 'Super', 'email' => 'super@example.gr', 'password' => 'SuperPass123', 'password2' => 'SuperPass123']);
ok($r['code'] === 302 && str_contains($r['location'], '/superadmin/login'), 'Εγκατάσταση ολοκληρώνεται');
$r = $super->get("$P/superadmin/setup");
ok($r['code'] === 302 && str_contains($r['location'], 'login'), 'Ο οδηγός κλειδώνει μετά την εγκατάσταση');
$plat = db('platform.sqlite');
ok((int)one($plat, 'SELECT COUNT(*) FROM subscription_plans') === 3 && (int)one($plat, "SELECT active_product_limit FROM subscription_plans WHERE slug='starter'") === 20, 'Πακέτα στη βάση (όχι στον κώδικα)');

// login lockout
$bad = new Client('bad');
for ($i = 0; $i < 5; $i++) $r = $bad->form("$P/superadmin/login", "$P/superadmin/login", ['email' => 'super@example.gr', 'password' => 'wrong-password-1']);
ok(str_contains($r['body'], 'κλειδώθηκε'), 'Κλείδωμα λογαριασμού μετά από 5 αποτυχίες');
$r = $bad->form("$P/superadmin/login", "$P/superadmin/login", ['email' => 'super@example.gr', 'password' => 'SuperPass123']);
ok($r['code'] === 200 && str_contains($r['body'], 'κλειδώθηκε'), 'Ακόμη και ο σωστός κωδικός μπλοκάρεται για 15 λεπτά');
$plat->exec("UPDATE users SET locked_until = NULL, failed_attempts = 0 WHERE email = 'super@example.gr'");

$r = $super->post("$P/superadmin/login", ['email' => 'super@example.gr', 'password' => 'SuperPass123', '_csrf' => 'forged']);
ok($r['code'] === 419, 'POST χωρίς έγκυρο CSRF → 419');
$r = $super->form("$P/superadmin/login", "$P/superadmin/login", ['email' => 'super@example.gr', 'password' => 'SuperPass123']);
ok($r['code'] === 302 && str_ends_with($r['location'], '/superadmin/'), 'Σύνδεση Super Admin');
ok(str_contains($super->get("$P/superadmin/")['head'], 'X-Robots-Tag: noindex'), 'Διαχείριση με noindex header');

$planId = fn($slug) => (int)one($plat, 'SELECT id FROM subscription_plans WHERE slug = ?', [$slug]);
$mk = function (string $name, string $domain, string $plan, string $email, bool $demo) use ($super, $P, $planId) {
    return $super->form("$P/superadmin/shops/new", "$P/superadmin/shops/new", [
        'name' => $name, 'email' => $email, 'domain' => $domain, 'plan_id' => $planId($plan), 'months' => 1, 'template' => 't01',
        'admin_email' => $email, 'admin_password' => 'OwnerPass123', 'demo_catalog' => $demo ? '1' : '', 'setup_fee_paid' => '1',
    ]);
};
$r = $mk('Κατάστημα Α', 'a.localhost', 'starter', 'owner-a@example.gr', false);
ok($r['code'] === 302 && preg_match('#/superadmin/shops/(\d+)#', $r['location'], $m), 'Οδηγός δημιουργίας: κατάστημα Α (Starter)');
$shopA = (int)($m[1] ?? 0);
$r = $mk('Κατάστημα Β', 'b.localhost', 'business', 'owner-b@example.gr', true);
preg_match('#/superadmin/shops/(\d+)#', $r['location'], $m);
$shopB = (int)($m[1] ?? 0);
ok($shopB > 0, 'Κατάστημα Β (Business, με demo κατάλογο)');
$r = $mk('Διπλό', 'a.localhost', 'starter', 'x@example.gr', false);
ok($r['code'] === 200 && str_contains($r['body'], 'χρησιμοποιείται ήδη') && (int)one($plat, 'SELECT COUNT(*) FROM shops') === 2, 'Διπλό domain απορρίπτεται με μήνυμα');
ok((int)one($plat, 'SELECT COUNT(*) FROM subscription_history WHERE shop_id = ? AND action = ?', [$shopA, 'created']) === 1, 'Ιστορικό συνδρομής: δημιουργία');
ok((int)one($plat, "SELECT COUNT(*) FROM payments WHERE shop_id = ? AND type = 'setup'", [$shopA]) === 1, 'Καταχώριση setup fee');

/* ------------------------------------------------------------------ */
section('Διαχείριση καταστήματος Α');
$adminA = new Client('adminA');
$r = $adminA->form("$A/admin/login", "$A/admin/login", ['email' => 'owner-a@example.gr', 'password' => 'OwnerPass123']);
ok($r['code'] === 302 && str_contains($r['location'], '/admin/dashboard'), 'Σύνδεση ιδιοκτήτη Α στο δικό του domain');
$r = $adminA->get("$A/admin/dashboard");
ok($r['code'] === 200 && str_contains($r['body'], 'ΕΝΕΡΓΑ ΠΡΟΪΟΝΤΑ: <span class="num">0 / 20'), 'Δείκτης ορίου 0 / 20 στον πίνακα');
$wrongShop = new Client('wrongshop');
$r = $wrongShop->form("$B/admin/login", "$B/admin/login", ['email' => 'owner-a@example.gr', 'password' => 'OwnerPass123']);
ok($r['code'] === 200 && str_contains($r['body'], 'Λάθος email'), 'Ο ιδιοκτήτης Α δεν μπορεί να συνδεθεί στο κατάστημα Β');

/* ------------------------------------------------------------------ */
section('§74 Όριο ενεργών προϊόντων (Starter = 20, εισαγωγή 100)');
$csv = sys_get_temp_dir() . '/wc-100.csv';
$fh = fopen($csv, 'w');
fputcsv($fh, ['Κωδικός', 'Όνομα', 'Τιμή', 'Απόθεμα', 'Κατηγορία', 'Μάρκα']);
for ($i = 1; $i <= 100; $i++) fputcsv($fh, ['T-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT), "Δοκιμαστικό προϊόν $i", number_format(10 + $i / 10, 2, ',', ''), $i % 7, $i % 2 ? 'Είδη > Μικρά' : 'Είδη > Μεγάλα', 'Test']);
fclose($fh);
$r = $adminA->form("$A/admin/import", "$A/admin/import", [], ['file' => [$csv, 'text/csv', 'products.csv']]);
ok($r['code'] === 302 && preg_match('#/admin/import/(\d+)#', $r['location'], $m), 'Ανέβασμα CSV');
$impId = (int)($m[1] ?? 0);
$page = $adminA->get("$A/admin/import/$impId");
ok(str_contains($page['body'], '100</b><span>Γραμμές στο αρχείο'), 'Έλεγχος: 100 γραμμές');
ok(str_contains($page['body'], 'value="sku" selected') && str_contains($page['body'], 'value="name" selected') && str_contains($page['body'], 'value="price" selected'), 'Αυτόματη αντιστοίχιση ελληνικών επικεφαλίδων');
$map = ['map[0]' => 'sku', 'map[1]' => 'name', 'map[2]' => 'price', 'map[3]' => 'stock', 'map[4]' => 'category', 'map[5]' => 'brand'];
$r = $adminA->post("$A/admin/import/$impId", $map + ['create_new' => '1', 'update_existing' => '1', 'activation' => 'up_to_quota', '_csrf' => Client::csrf($page['body'])]);
$tok = Client::csrf($adminA->get("$A/admin/import/$impId")['body']);
for ($i = 0, $st = ''; $i < 20 && $st !== 'done'; $i++) {
    $j = json_decode($adminA->post("$A/admin/import/$impId/run", [], [], ['X-CSRF-Token: ' . $tok, 'X-Requested-With: fetch', 'Accept: application/json'])['body'], true);
    $st = $j['status'] ?? 'error';
}
ok($st === 'done', 'Η εισαγωγή ολοκληρώθηκε σε παρτίδες');
$dbA = db("shops/$shopA.sqlite");
$stored = (int)one($dbA, 'SELECT COUNT(*) FROM products WHERE deleted_at IS NULL');
$active = (int)one($dbA, "SELECT COUNT(*) FROM products WHERE status='active' AND deleted_at IS NULL");
ok($stored === 100 && $active === 20 && $stored - $active === 80, "Αποτέλεσμα: 100 αποθηκευμένα · 20 ενεργά · 80 ανενεργά", "stored=$stored active=$active");
ok((int)one($dbA, "SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL") === 3, 'Δέντρο κατηγοριών από «Είδη > Μικρά/Μεγάλα» χωρίς διπλότυπα');

$inactiveId = (int)one($dbA, "SELECT id FROM products WHERE status='inactive' ORDER BY id LIMIT 1");
$r = $adminA->form("$A/admin/products", "$A/admin/products/$inactiveId/toggle", []);
$after = $adminA->get("$A/admin/products");
ok(str_contains($after['body'], 'Δεν είναι δυνατή η ενεργοποίηση') && (int)one($dbA, "SELECT COUNT(*) FROM products WHERE status='active'") === 20, 'Ενεργοποίηση 21ου → ΜΠΛΟΚΑΡΕΙ με σαφές μήνυμα');
$ids = array_column($dbA->query("SELECT id FROM products WHERE status='inactive' ORDER BY id LIMIT 2")->fetchAll(), 'id');
$r = $adminA->form("$A/admin/products", "$A/admin/products", ['bulk' => 'activate', 'ids' => $ids]);
ok((int)one($dbA, "SELECT COUNT(*) FROM products WHERE status='active'") === 20, 'Μαζική ενεργοποίηση πέρα από το όριο → μπλοκάρεται (όλα ή τίποτα)');
$activeId = (int)one($dbA, "SELECT id FROM products WHERE status='active' ORDER BY id LIMIT 1");
$adminA->form("$A/admin/products", "$A/admin/products/$activeId/toggle", []);
ok((int)one($dbA, "SELECT COUNT(*) FROM products WHERE status='active'") === 19, 'Απενεργοποίηση ενός → 19 ενεργά');
$adminA->form("$A/admin/products", "$A/admin/products/$inactiveId/toggle", []);
ok((int)one($dbA, "SELECT COUNT(*) FROM products WHERE status='active'") === 20, 'Ενεργοποίηση άλλου → ΕΠΙΤΥΧΙΑ (20 ενεργά)');
ok((int)one($dbA, 'SELECT COUNT(*) FROM products') === 100, 'Κανένα προϊόν δεν διαγράφηκε για να τηρηθεί το όριο');
// tamper attempt: forged form field cannot bypass server check
$r = $adminA->form("$A/admin/products/new", "$A/admin/products/new", ['name' => 'Παράκαμψη', 'price' => '5', 'status' => 'active', 'stock' => 1]);
ok((int)one($dbA, "SELECT COUNT(*) FROM products WHERE status='active'") === 20 && (int)one($dbA, "SELECT COUNT(*) FROM products WHERE name='Παράκαμψη' AND status='inactive'") === 1, 'Πλαστό "status=active" από τον client δεν παρακάμπτει το όριο');

/* ------------------------------------------------------------------ */
section('Βιτρίνα: μόνο ενεργά προϊόντα');
$guest = new Client('guest');
$activeSlug = (string)one($dbA, "SELECT slug FROM products WHERE status='active' LIMIT 1");
$inactiveSlug = (string)one($dbA, "SELECT slug FROM products WHERE status='inactive' LIMIT 1");
ok($guest->get("$A/product/$activeSlug/")['code'] === 200, 'Ενεργό προϊόν → 200');
ok($guest->get("$A/product/$inactiveSlug/")['code'] === 404, 'Ανενεργό προϊόν → 404');
$list = $guest->get("$A/products/")['body'];
ok(str_contains($list, '20 προϊόντα'), 'Η λίστα δείχνει 20 προϊόντα (όχι 101)');
$sm = $guest->get("$A/sitemap.xml")['body'];
ok(substr_count($sm, '/product/') === 20, 'Sitemap: μόνο τα 20 ενεργά');
$rb = $guest->get("$A/robots.txt")['body'];
ok(str_contains($rb, 'GPTBot') && str_contains($rb, 'ClaudeBot') && str_contains($rb, 'Sitemap:'), 'robots.txt με AI bots & sitemap');
ok(str_contains($guest->get("$A/llms.txt")['body'], '# Κατάστημα Α'), 'llms.txt');
$pp = $guest->get("$A/product/$activeSlug/")['body'];
preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $pp, $m);
$ld = json_decode($m[1] ?? '', true);
$types = array_column($ld['@graph'] ?? [], '@type');
ok(in_array('Product', $types, true) && in_array('BreadcrumbList', $types, true) && in_array('Organization', $types, true), 'JSON-LD @graph: Organization, Product, BreadcrumbList');
$prod = array_values(array_filter($ld['@graph'] ?? [], fn($n) => $n['@type'] === 'Product'))[0] ?? [];
ok(isset($prod['offers']['price'], $prod['offers']['priceCurrency'], $prod['offers']['availability']) && !isset($prod['aggregateRating']), 'Product/Offer με τιμή & διαθεσιμότητα, χωρίς ψεύτικες βαθμολογίες');
ok($guest->get("$A/products")['code'] === 301, 'Κανονικοποίηση trailing slash (301)');

/* ------------------------------------------------------------------ */
section('§76 Απομόνωση tenants');
$sidA = $adminA->cookie('wc_adm');
$hijack = new Client('hijack');
$r = $hijack->get("$B/admin/products", ['Cookie: wc_adm=' . $sidA]);
ok($r['code'] === 403, 'Session του Α στο domain του Β → 403', "got {$r['code']}");
$dbB = db("shops/$shopB.sqlite");
$bProduct = (int)one($dbB, 'SELECT id FROM products ORDER BY id DESC LIMIT 1');
$r = $adminA->get("$A/admin/products/" . ($bProduct + 1000));
ok($r['code'] === 404, 'Id προϊόντος άλλου tenant στο δικό σου panel → 404 (ξεχωριστή βάση)');
$r = $adminA->get("$P/superadmin/");
ok($r['code'] === 302 && str_contains($r['location'], 'login'), 'Ο διαχειριστής καταστήματος δεν έχει πρόσβαση στο Super Admin');
$r = $hijack->get("$P/superadmin/shops", ['Cookie: wc_sa=' . $sidA]);
ok($r['code'] === 302, 'Session καταστήματος ως cookie Super Admin → απορρίπτεται');

/* ------------------------------------------------------------------ */
section('§80 Δικαιώματα προσωπικού');
$r = $adminA->form("$A/admin/users", "$A/admin/users", ['op' => 'create', 'name' => 'Editor', 'email' => 'editor-a@example.gr', 'password' => 'EditorPass123', 'role' => 'editor']);
$r = $adminA->form("$A/admin/users", "$A/admin/users", ['op' => 'create', 'name' => 'PM', 'email' => 'pm-a@example.gr', 'password' => 'PmPass12345', 'role' => 'product_manager']);
ok((int)one($plat, 'SELECT COUNT(*) FROM users WHERE shop_id = ?', [$shopA]) === 2 && str_contains($adminA->get("$A/admin/users")['body'], 'έως 2 χρήστες'), 'Όριο χρηστών πακέτου (Starter = 2) τηρείται');
$plat->exec("UPDATE subscription_plans SET staff_limit = 3 WHERE slug = 'starter'"); // Super Admin raises the plan limit
$r = $adminA->form("$A/admin/users", "$A/admin/users", ['op' => 'create', 'name' => 'PM', 'email' => 'pm-a@example.gr', 'password' => 'PmPass12345', 'role' => 'product_manager']);
ok((int)one($plat, 'SELECT COUNT(*) FROM users WHERE shop_id = ?', [$shopA]) === 3, 'Δημιουργία Editor & Product Manager');
$check = function (string $email, string $pass, array $allowed, array $denied) use ($A, $plat) {
    $plat->prepare('UPDATE users SET must_change_password = 0 WHERE email = ?')->execute([$email]);
    $c = new Client(md5($email));
    $c->form("$A/admin/login", "$A/admin/login", ['email' => $email, 'password' => $pass]);
    $okAll = true;
    $msg = [];
    foreach ($allowed as $p) if (($code = $c->get("$A$p")['code']) !== 200) { $okAll = false; $msg[] = "$p=$code"; }
    foreach ($denied as $p) if (($code = $c->get("$A$p")['code']) !== 403) { $okAll = false; $msg[] = "$p=$code"; }
    return [$okAll, implode(' ', $msg), $c];
};
[$okE, $msgE] = $check('editor-a@example.gr', 'EditorPass123', ['/admin/dashboard', '/admin/pages', '/admin/media', '/admin/seo'], ['/admin/products', '/admin/orders', '/admin/settings', '/admin/users', '/admin/import']);
ok($okE, 'Editor: σελίδες/πολυμέσα/SEO ναι · προϊόντα/παραγγελίες/ρυθμίσεις/χρήστες → 403', $msgE);
[$okP, $msgP, $pm] = $check('pm-a@example.gr', 'PmPass12345', ['/admin/products', '/admin/categories', '/admin/import'], ['/admin/orders', '/admin/customers', '/admin/settings', '/admin/users', '/admin/appearance']);
ok($okP, 'Product Manager: προϊόντα/κατηγορίες/εισαγωγή ναι · παραγγελίες/πελάτες/ρυθμίσεις/χρήστες → 403', $msgP);
$r = $pm->form("$A/admin/products", "$A/admin/users", ['op' => 'create', 'email' => 'evil@example.gr', 'password' => 'EvilPass1234', 'role' => 'owner']);
ok($r['code'] === 403, 'Ο Product Manager δεν μπορεί να δημιουργήσει χρήστες (έλεγχος στον server)');

/* ------------------------------------------------------------------ */
section('Αγορά: καλάθι → παραλλαγή → ταμείο → παραγγελία (κατάστημα Β)');
$buyer = new Client('buyer');
$vazoId = (int)one($dbB, "SELECT id FROM products WHERE slug = 'keramiko-vazo-akti'");
$varId = (int)one($dbB, 'SELECT id FROM product_variants WHERE product_id = ? ORDER BY id LIMIT 1', [$vazoId]);
$stockBefore = (int)one($dbB, 'SELECT stock FROM product_variants WHERE id = ?', [$varId]);
$pg = $buyer->get("$B/product/keramiko-vazo-akti/");
ok($pg['code'] === 200 && str_contains($pg['body'], 'data-variants'), 'Σελίδα προϊόντος με παραλλαγές');
$r = $buyer->post("$B/cart/", ['op' => 'add', 'product_id' => $vazoId, 'variant_id' => '', 'qty' => 1, '_csrf' => Client::csrf($pg['body'])], [], ['X-Requested-With: fetch']);
$j = json_decode($r['body'], true);
ok(!$j['ok'] && str_contains((string)$j['error'], 'παραλλαγή'), 'Χωρίς επιλογή παραλλαγής → μήνυμα');
$r = $buyer->post("$B/cart/", ['op' => 'add', 'product_id' => $vazoId, 'variant_id' => $varId, 'qty' => 2, '_csrf' => Client::csrf($pg['body'])], [], ['X-Requested-With: fetch']);
$j = json_decode($r['body'], true);
ok($j['ok'] && $j['count'] === 2 && str_contains($j['html'], 'Ταμείο'), 'Προσθήκη στο καλάθι (AJAX, cart sheet)');
$wl = $buyer->post("$B/wishlist/", ['product_id' => $vazoId, '_csrf' => Client::csrf($pg['body'])], [], ['X-Requested-With: fetch']);
ok((json_decode($wl['body'], true)['on'] ?? false) === true && str_contains($buyer->get("$B/wishlist/")['body'], 'Κεραμικό βάζο'), 'Αγαπημένα');
$co = $buyer->get("$B/checkout/");
ok($co['code'] === 200 && str_contains($co['body'], 'Αντικαταβολή') && str_contains($co['body'], 'Τραπεζική κατάθεση'), 'Ταμείο: αντικαταβολή & κατάθεση (κάρτα κρυφή χωρίς κλειδιά)');
$r = $buyer->post("$B/checkout/", ['_csrf' => Client::csrf($co['body']), 'name' => 'Α', 'email' => 'bad', 'payment_method' => 'cod']);
ok($r['code'] === 200 && str_contains($r['body'], 'Ελέγξτε τα πεδία'), 'Επικύρωση πεδίων ταμείου');
$r = $buyer->post("$B/checkout/", ['_csrf' => Client::csrf($co['body']), 'name' => 'Μαρία Παπαδοπούλου', 'email' => 'maria@example.gr', 'phone' => '6900000000', 'street' => 'Ερμού 1', 'city' => 'Αθήνα', 'postcode' => '10563', 'region' => 'islands', 'payment_method' => 'cod', 'terms' => '1', 'invoice_type' => 'receipt']);
ok($r['code'] === 302 && preg_match('#/order/(\d+)/\?k=#', $r['location'], $m), 'Η παραγγελία καταχωρήθηκε (PRG)');
$o = $dbB->query('SELECT * FROM orders ORDER BY id DESC LIMIT 1')->fetch();
ok($o && abs((float)$o['total'] - (76 + 0 + 2 + 1.5)) < 0.01, 'Σύνολο: 2×38 + δωρεάν αποστολή + νησιά 2 + αντικαταβολή 1,5 = 79,50', 'total=' . ($o['total'] ?? '?'));
ok((int)one($dbB, 'SELECT stock FROM product_variants WHERE id = ?', [$varId]) === $stockBefore - 2, 'Μείωση αποθέματος παραλλαγής');
ok($buyer->get((str_starts_with($r['location'], 'http') ? '' : $B) . $r['location'])['code'] === 200 && $buyer->get("$B/order/{$o['number']}/?k=wrong")['code'] === 404, 'Σελίδα επιτυχίας μόνο με το σωστό token');
ok(str_contains((string)@file_get_contents(ROOT . '/data/logs/mail.log'), $o['number']), 'Email επιβεβαίωσης παραγγελίας (mail log)');
$adminB = new Client('adminB');
$adminB->form("$B/admin/login", "$B/admin/login", ['email' => 'owner-b@example.gr', 'password' => 'OwnerPass123']);
ok(str_contains($adminB->get("$B/admin/orders")['body'], $o['number']), 'Η παραγγελία εμφανίζεται στο panel του Β');
// deactivating a purchased product keeps order history
$dbB->exec("UPDATE products SET status='inactive' WHERE id = $vazoId");
ok($adminB->get("$B/admin/orders/{$o['id']}")['code'] === 200 && (int)one($dbB, 'SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$o['id']]) === 1, '§53 Απενεργοποιημένο προϊόν: η παραγγελία μένει ανέπαφη');
$dbB->exec("UPDATE products SET status='active' WHERE id = $vazoId");

/* ------------------------------------------------------------------ */
section('Φόρμες: επικοινωνία & newsletter');
$visitor = new Client('visitor');
$cp = $visitor->get("$B/contact/");
preg_match('/name="stamp" value="([^"]+)"/', $cp['body'], $sm1);
$r = $visitor->post("$B/contact/", ['_csrf' => Client::csrf($cp['body']), 'stamp' => $sm1[1], 'website' => '', 'name' => 'Νίκος', 'email' => 'nikos@example.gr', 'message' => 'Γεια σας, έχετε το βάζο σε μπλε;', 'consent' => '1']);
ok($r['code'] === 200 && str_contains($r['body'], 'γρήγορα'), 'Χρονικός έλεγχος (<3s) μπλοκάρει bots');
sleep(3);
$r = $visitor->post("$B/contact/", ['_csrf' => Client::csrf($cp['body']), 'stamp' => $sm1[1], 'website' => 'http://spam', 'name' => 'Bot', 'email' => 'bot@example.gr', 'message' => 'spam spam spam', 'consent' => '1']);
ok((int)one($dbB, "SELECT COUNT(*) FROM contact_messages WHERE email = 'bot@example.gr'") === 0, 'Honeypot: το spam δεν αποθηκεύεται');
$r = $visitor->post("$B/contact/", ['_csrf' => Client::csrf($cp['body']), 'stamp' => $sm1[1], 'website' => '', 'name' => 'Νίκος', 'email' => 'nikos@example.gr', 'message' => 'Γεια σας, έχετε το βάζο σε μπλε;', 'consent' => '1']);
ok($r['code'] === 302 && (int)one($dbB, "SELECT COUNT(*) FROM contact_messages WHERE email = 'nikos@example.gr'") === 1, 'Έγκυρο μήνυμα: αποθήκευση + PRG');
$hp = $visitor->get("$B/");
preg_match('/name="stamp" value="([^"]+)"/', $hp['body'], $sm2);
$r = $visitor->post("$B/newsletter/", ['_csrf' => Client::csrf($hp['body']), 'stamp' => $sm1[1], 'website' => '', 'email' => 'fan@example.gr', 'source' => 'home'], [], ['X-Requested-With: fetch']);
ok($r['code'] === 422, 'Newsletter χωρίς συγκατάθεση → απορρίπτεται');
$r = $visitor->post("$B/newsletter/", ['_csrf' => Client::csrf($hp['body']), 'stamp' => $sm1[1], 'website' => '', 'email' => 'fan@example.gr', 'consent' => '1', 'source' => 'home'], [], ['X-Requested-With: fetch']);
ok($r['code'] === 200 && (int)one($dbB, "SELECT consent FROM subscribers WHERE email = 'fan@example.gr'") === 1, 'Newsletter με καταγραφή συγκατάθεσης');

/* ------------------------------------------------------------------ */
section('Ασφάλεια περιεχομένου & uploads');
$pid = (int)one($dbB, 'SELECT id FROM products ORDER BY id LIMIT 1');
$ep = $adminB->get("$B/admin/products/$pid");
$fake = sys_get_temp_dir() . '/shell.php.jpg';
file_put_contents($fake, "<?php echo 'pwned'; ?>");
$r = $adminB->post("$B/admin/products/$pid", ['_csrf' => Client::csrf($ep['body']), 'op' => 'save', 'name' => 'Κεραμικό βάζο «Ακτή»', 'price' => '38', 'status' => 'active', 'manage_stock' => '1', 'stock' => '10',
    'description' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(2)><a href="javascript:alert(3)">x</a><iframe src="https://evil.example/"></iframe><iframe src="https://www.youtube.com/embed/abc"></iframe>'], ['images[]' => [$fake, 'image/jpeg', 'shell.php.jpg']]);
$desc = (string)one($dbB, 'SELECT description FROM products WHERE id = ?', [$pid]);
ok(!str_contains($desc, '<script') && !str_contains($desc, 'onerror') && !str_contains($desc, 'javascript:') && !str_contains($desc, 'evil.example') && str_contains($desc, 'youtube.com/embed'), 'Sanitizer: αφαιρεί script/on*/javascript:/μη επιτρεπτά iframes');
ok(count(glob(ROOT . "/uploads/$shopB/*/*/shell*") ?: []) === 0, 'Ψεύτικη εικόνα (PHP) απορρίπτεται από getimagesize');
ok(str_contains($adminB->get("$B/admin/products/$pid")['body'], 'μόνο εικόνες JPG'), 'Μήνυμα σφάλματος upload');

/* ------------------------------------------------------------------ */
section('§77 Αλλαγή προτύπου χωρίς απώλεια δεδομένων');
$sig = function () use ($shopB) {
    $d = db("shops/$shopB.sqlite");
    $out = [];
    foreach (['products', 'categories', 'orders', 'order_items', 'customers', 'media', 'pages', 'settings', 'subscribers', 'product_variants'] as $t) {
        $out[$t] = md5(json_encode($d->query("SELECT * FROM $t ORDER BY 1")->fetchAll()));
    }
    return $out;
};
$before = $sig();
$tpl2 = is_file(ROOT . '/app/templates/t02/manifest.php') ? 't02' : null;
if ($tpl2) {
    $plat->exec("UPDATE templates SET status = 'available' WHERE code = 't02'");
    $sv = $super->get("$P/superadmin/shops/$shopB");
    $shopRow = $plat->query("SELECT * FROM shops WHERE id = $shopB")->fetch();
    $r = $super->post("$P/superadmin/shops/$shopB/edit", ['_csrf' => Client::csrf($sv['body']), 'name' => $shopRow['name'], 'email' => $shopRow['email'], 'template_code' => 't02', 'hosting_status' => 'ok']);
    ok((string)one($plat, 'SELECT template_code FROM shops WHERE id = ?', [$shopB]) === 't02', 'Πρότυπο Β: t01 → t02');
    $home = $guest->get("$B/");
    ok($home['code'] === 200 && str_contains($home['body'], '/assets/templates/t02/'), 'Η βιτρίνα αποδίδεται με το νέο πρότυπο');
    ok($sig() === $before, 'Προϊόντα, κατηγορίες, παραγγελίες, πελάτες, πολυμέσα, σελίδες, ρυθμίσεις: αμετάβλητα');
    $super->post("$P/superadmin/shops/$shopB/edit", ['_csrf' => Client::csrf($super->get("$P/superadmin/shops/$shopB")['body']), 'name' => $shopRow['name'], 'email' => $shopRow['email'], 'template_code' => 't01', 'hosting_status' => 'ok']);
    ok($sig() === $before && $guest->get("$B/")['code'] === 200, 'Επιστροφή σε t01: τα δεδομένα παραμένουν ίδια');
} else {
    ok(false, 'Το πρότυπο t02 δεν υπάρχει — η δοκιμή αλλαγής προτύπου δεν εκτελέστηκε');
}

/* ------------------------------------------------------------------ */
section('§75 / §79 Λήξη συνδρομής → κλείδωμα → ανανέωση');
$plat->prepare('UPDATE shops SET subscription_expires = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() + 86400 - 60), $shopA]);
cron();
ok((string)one($plat, 'SELECT status FROM shops WHERE id = ?', [$shopA]) === 'EXPIRING', 'Απομένει 1 ημέρα → EXPIRING');
ok((int)one($plat, "SELECT COUNT(*) FROM notifications WHERE shop_id = ? AND dedupe_key LIKE ?", [$shopA, 'warn:%:1']) === 1, 'Ειδοποίηση «λήγει σε 1 ημέρα» για τον Super Admin');
cron();
ok((int)one($plat, "SELECT COUNT(*) FROM notifications WHERE shop_id = ? AND type = 'subscription.warning'", [$shopA]) === 1, 'Η ειδοποίηση δεν διπλασιάζεται σε επόμενη εκτέλεση');
ok(str_contains($adminA->get("$A/admin/dashboard")['body'], 'λήγει σε'), 'Banner λήξης στη διαχείριση του πελάτη');
$plat->prepare('UPDATE shops SET subscription_expires = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() - 3600), $shopA]);
cron();
ok((string)one($plat, 'SELECT status FROM shops WHERE id = ?', [$shopA]) === 'EXPIRED', 'Μετά τη λήξη → EXPIRED (περίοδος χάριτος 3 ημερών)');
ok($guest->get("$A/")['code'] === 200, 'Κατά την περίοδο χάριτος η βιτρίνα λειτουργεί');
$plat->prepare('UPDATE shops SET subscription_expires = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() - 3 * 86400 - 60), $shopA]);
cron();
ok((string)one($plat, 'SELECT status FROM shops WHERE id = ?', [$shopA]) === 'LOCKED', 'Μετά τις 3 ημέρες → LOCKED');
$leak = false;
foreach (['/', '/products/', "/product/$activeSlug/", '/cart/', '/checkout/', '/sitemap.xml', '/robots.txt', '/llms.txt', '/feed/products.xml', '/admin/login', '/admin/products', '/contact/'] as $p) {
    $r = $guest->get("$A$p");
    if ($r['code'] !== 503 || !str_contains($r['body'], 'προσωρινά μη διαθέσιμο') || str_contains($r['body'], 'Δοκιμαστικό προϊόν') || str_contains($r['body'], '<form')) {
        $leak = true;
        echo "     leak on $p ({$r['code']})\n";
    }
}
ok(!$leak, '§79 Κλειδωμένο: όλες οι διευθύνσεις (βιτρίνα, feeds, sitemap, ταμείο, admin) → σελίδα κλειδώματος 503, χωρίς δεδομένα ή φόρμες');
$r = $adminA->get("$A/admin/dashboard");
ok($r['code'] === 503, 'Ο πελάτης χάνει πρόσβαση στη διαχείριση');
ok((int)one($dbA, 'SELECT COUNT(*) FROM products') === 101, 'Τα δεδομένα διατηρούνται στο κλείδωμα');
$sv = $super->get("$P/superadmin/shops/$shopA");
$r = $super->post("$P/superadmin/shops/$shopA/impersonate", ['_csrf' => Client::csrf($sv['body'])]);
ok($r['code'] === 302 && str_contains($r['location'], 'a.localhost') && str_contains($r['location'], '/admin/impersonate?t='), 'Super Admin: σύνδεσμος υποστήριξης (impersonation)');
$imp = new Client('imp');
$r2 = $imp->get($r['location']);
ok($r2['code'] === 302 && $imp->get("$A/admin/dashboard")['code'] === 200, 'Ο Super Admin μπαίνει στο κλειδωμένο κατάστημα για υποστήριξη');
ok($imp->get($r['location'])['code'] === 403, 'Το token impersonation είναι μίας χρήσης');
ok((int)one($plat, "SELECT COUNT(*) FROM audit_logs WHERE action = 'impersonation.started' AND shop_id = ?", [$shopA]) === 1, 'Καταγραφή impersonation στο audit log');
$r = $super->post("$P/superadmin/shops/$shopA/renew", ['_csrf' => Client::csrf($sv['body']), 'months' => 1, 'amount' => '20', 'method' => 'bank_transfer']);
ok((string)one($plat, 'SELECT status FROM shops WHERE id = ?', [$shopA]) === 'ACTIVE', 'Ανανέωση → ACTIVE');
ok($guest->get("$A/")['code'] === 200, 'Η βιτρίνα επανέρχεται αμέσως');
ok((int)one($dbA, "SELECT COUNT(*) FROM products WHERE status='active'") === 20 && (int)one($dbA, 'SELECT COUNT(*) FROM products') === 101, 'Η ανανέωση δεν άλλαξε προϊόντα');
ok((int)one($plat, "SELECT COUNT(*) FROM subscription_history WHERE shop_id = ? AND action IN ('expiring','expired','locked','renewed')", [$shopA]) >= 4, 'Ιστορικό: expiring → expired → locked → renewed');

/* ------------------------------------------------------------------ */
section('Υποβάθμιση πακέτου (αυτόματη απενεργοποίηση νεότερων)');
$sv = $super->get("$P/superadmin/shops/$shopB");
$dbB->exec("UPDATE products SET status='active', activated_at = datetime('now') WHERE deleted_at IS NULL");
$super->post("$P/superadmin/shops/$shopB/edit", ['_csrf' => Client::csrf($sv['body']), 'name' => 'Κατάστημα Β', 'email' => 'owner-b@example.gr', 'template_code' => 't01', 'hosting_status' => 'ok', 'active_limit_override' => '5']);
$r = $super->post("$P/superadmin/shops/$shopB/plan", ['_csrf' => Client::csrf($sv['body']), 'plan_id' => $planId('starter')]);
ok((string)one($plat, 'SELECT plan_id FROM shops WHERE id = ?', [$shopB]) === (string)$planId('business'), 'Χωρίς επιβεβαίωση η υποβάθμιση δεν εφαρμόζεται');
$r = $super->post("$P/superadmin/shops/$shopB/plan", ['_csrf' => Client::csrf($sv['body']), 'plan_id' => $planId('starter'), 'confirm_deactivate' => '1']);
ok((int)one($dbB, "SELECT COUNT(*) FROM products WHERE status='active'") === 5 && (int)one($dbB, 'SELECT COUNT(*) FROM products WHERE deleted_at IS NULL') === 8, 'Με επιβεβαίωση: 5 ενεργά, κανένα δεν διαγράφηκε');
$plat->exec("UPDATE shops SET active_limit_override = NULL WHERE id = $shopB");

/* ------------------------------------------------------------------ */
section('§78 Εισαγωγή 10.000 προϊόντων XML');
$r = $mk('Κατάστημα Γ', 'c.localhost', 'pro', 'owner-c@example.gr', false);
preg_match('#/superadmin/shops/(\d+)#', $r['location'], $m);
$shopC = (int)($m[1] ?? 0);
$xml = sys_get_temp_dir() . '/wc-10k.xml';
$fh = fopen($xml, 'w');
fwrite($fh, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<mywebstore><products>\n");
for ($i = 1; $i <= 10000; $i++) {
    fwrite($fh, "<product><id>$i</id><mpn>X-$i</mpn><name><![CDATA[Προϊόν XML $i]]></name><price_with_vat>" . (5 + $i % 90) . ".90</price_with_vat><quantity>" . ($i % 5) . "</quantity><category><![CDATA[Κατηγορία " . ($i % 12) . " > Υπο " . ($i % 3) . "]]></category><manufacturer>Brand " . ($i % 40) . "</manufacturer><ean>52" . str_pad((string)$i, 11, '0', STR_PAD_LEFT) . "</ean><image>https://example.com/img/$i.jpg</image></product>\n");
    if ($i === 500) fwrite($fh, "<product><id>dup</id><mpn>X-500</mpn><name>Διπλό</name><price_with_vat>1</price_with_vat></product>\n");
}
fwrite($fh, "</products></mywebstore>\n");
fclose($fh);
$adminC = new Client('adminC');
$adminC->form("$C/admin/login", "$C/admin/login", ['email' => 'owner-c@example.gr', 'password' => 'OwnerPass123']);
$tUp = microtime(true);
$r = $adminC->form("$C/admin/import", "$C/admin/import", [], ['file' => [$xml, 'text/xml', 'products.xml']]);
preg_match('#/admin/import/(\d+)#', $r['location'], $m);
$impC = (int)($m[1] ?? 0);
$page = $adminC->get("$C/admin/import/$impC");
ok(str_contains($page['body'], '10001</b><span>Γραμμές στο αρχείο'), 'XML 10.001 εγγραφές αναγνωρίστηκαν (streaming)');
ok(str_contains($page['body'], 'value="price" selected') && str_contains($page['body'], 'value="brand" selected') && str_contains($page['body'], 'value="barcode" selected') && str_contains($page['body'], 'value="stock" selected'), 'Αυτόματη αντιστοίχιση πεδίων Skroutz XML (price_with_vat, manufacturer, ean, quantity)');
$headers = json_decode((string)one(db("shops/$shopC.sqlite"), 'SELECT options FROM imports WHERE id = ?', [$impC]), true)['headers'];
$map = [];
foreach ($headers as $i => $h) $map["map[$i]"] = ['mpn' => 'sku', 'name' => 'name', 'price_with_vat' => 'price', 'quantity' => 'stock', 'category' => 'category', 'manufacturer' => 'brand', 'ean' => 'barcode', 'image' => 'image'][$h] ?? '';
$adminC->post("$C/admin/import/$impC", $map + ['create_new' => '1', 'update_existing' => '1', 'activation' => 'up_to_quota', '_csrf' => Client::csrf($page['body'])]);
$tok = Client::csrf($adminC->get("$C/admin/import/$impC")['body']);
$maxBatch = 0;
for ($i = 0, $st = ''; $i < 100 && $st !== 'done' && $st !== 'failed'; $i++) {
    $tb = microtime(true);
    $j = json_decode($adminC->post("$C/admin/import/$impC/run", [], [], ['X-CSRF-Token: ' . $tok, 'X-Requested-With: fetch', 'Accept: application/json'])['body'], true);
    $maxBatch = max($maxBatch, microtime(true) - $tb);
    $st = $j['status'] ?? 'error';
}
$dbC = db("shops/$shopC.sqlite");
$storedC = (int)one($dbC, 'SELECT COUNT(*) FROM products');
$activeC = (int)one($dbC, "SELECT COUNT(*) FROM products WHERE status='active'");
ok($st === 'done', sprintf('Ολοκληρώθηκε σε %d παρτίδες χωρίς timeout (μέγιστη παρτίδα %.2fs, σύνολο %.1fs)', $i, $maxBatch, microtime(true) - $tUp));
ok($storedC === 10000, 'Χωρίς διπλότυπα: 10.000 προϊόντα', "stored=$storedC");
ok($activeC === 100, 'Τηρήθηκε το όριο Pro: 100 ενεργά', "active=$activeC");
ok((int)one($dbC, "SELECT COUNT(*) FROM import_rows WHERE import_id = ? AND status = 'duplicate'", [$impC]) === 1, 'Αναφορά: 1 διπλότυπο καταγράφηκε');
ok((int)one($dbC, 'SELECT COUNT(*) FROM import_logs WHERE import_id = ?', [$impC]) >= 1, 'Αρχείο καταγραφής εισαγωγής');
// re-import same file = updates, no new rows
$r = $adminC->form("$C/admin/import", "$C/admin/import", [], ['file' => [$xml, 'text/xml', 'products.xml']]);
preg_match('#/admin/import/(\d+)#', $r['location'], $m);
$imp2 = (int)$m[1];
$page = $adminC->get("$C/admin/import/$imp2");
$adminC->post("$C/admin/import/$imp2", $map + ['create_new' => '1', 'update_existing' => '1', 'activation' => 'keep', '_csrf' => Client::csrf($page['body'])]);
$tUp2 = microtime(true);
echo '     (δεύτερη εισαγωγή μέσω cron.php) ' . trim(explode("\n", cron())[1] ?? '') . "\n";
ok((int)one($dbC, 'SELECT COUNT(*) FROM products') === 10000 && (int)one($dbC, 'SELECT updated FROM imports WHERE id = ?', [$imp2]) === 10000, 'Επανεισαγωγή: 10.000 ενημερώσεις, 0 νέα (ταύτιση SKU) — συνέχεια από cron');
$feed = $guest->get("$C/feed/products.xml");
ok($feed['code'] === 200 && substr_count($feed['body'], '<product>') === 100, 'XML feed: μόνο τα 100 ενεργά');
$csvOut = $adminC->get("$C/admin/export?scope=inactive");
ok($csvOut['code'] === 200 && substr_count($csvOut['body'], "\n") === 9901, 'Εξαγωγή CSV ανενεργών: 9.900 γραμμές + επικεφαλίδα');
$pl = $adminC->get("$C/admin/products?per=50&page=150");
ok($pl['code'] === 200 && str_contains($pl['body'], 'Προϊόντα 7451–7500 από 10.000'), 'Σελιδοποίηση διαχείρισης σε 10.000 προϊόντα');

/* ------------------------------------------------------------------ */
section('Διαγραφή καταστήματος (δύο βήματα)');
$sv = $super->get("$P/superadmin/shops/$shopC");
$super->post("$P/superadmin/shops/$shopC/delete", ['_csrf' => Client::csrf($sv['body']), 'confirm_domain' => 'wrong.localhost']);
ok((string)one($plat, 'SELECT status FROM shops WHERE id = ?', [$shopC]) !== 'DELETED', 'Λάθος επιβεβαίωση domain → καμία διαγραφή');
$super->post("$P/superadmin/shops/$shopC/delete", ['_csrf' => Client::csrf($sv['body']), 'confirm_domain' => 'c.localhost']);
ok((string)one($plat, 'SELECT status FROM shops WHERE id = ?', [$shopC]) === 'DELETED' && is_file(ROOT . "/data/shops/$shopC.sqlite"), 'Διαγραφή: εκτός λειτουργίας, δεδομένα διατηρούνται');
ok($guest->get("$C/")['code'] === 404, 'Το domain του διαγραμμένου καταστήματος δεν σερβίρει τίποτα');
ok(count(glob(ROOT . "/data/backups/shop-$shopC/*before-delete*") ?: []) === 1, 'Αυτόματο αντίγραφο ασφαλείας πριν τη διαγραφή');

/* ------------------------------------------------------------------ */
section('Σύνοψη');
printf("  %d επιτυχίες, %d αποτυχίες · %.1fs\n", $passes, $fails, microtime(true) - $t0);
exit($fails ? 1 : 0);
