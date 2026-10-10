<?php
/* Shop seeding, mail, payments abstraction, permissions guard helpers. */

final class ShopSeed
{
    public const TEMPLATE_TEXT = 'Κείμενο προτύπου — επαληθεύστε πριν τη δημοσίευση.';

    public static function run(int $shopId, array $in = []): void
    {
        $db = DB::shop($shopId);
        if (val($db, 'SELECT COUNT(*) FROM settings') > 0) {
            return;
        }
        $name = $in['name'] ?? 'Κατάστημα';
        setting_set($db, 'company', [
            'name' => $name, 'legal_name' => $name, 'address' => $in['address'] ?? '', 'phone' => $in['phone'] ?? '',
            'email' => $in['email'] ?? ($in['admin_email'] ?? ''), 'hours' => '', 'vat_number' => $in['vat_number'] ?? '',
            'social' => ['facebook' => '', 'instagram' => '', 'tiktok' => '', 'youtube' => ''],
        ]);
        setting_set($db, 'theme', ['tokens' => [], 'logo' => null, 'favicon' => null]);
        setting_set($db, 'shipping', ['flat' => 3.5, 'free_over' => 50, 'islands_extra' => 2, 'note' => '']);
        setting_set($db, 'payments', [
            'cod' => ['enabled' => true, 'fee' => 1.5],
            'bank' => ['enabled' => true, 'details' => "Τράπεζα: —\nIBAN: —\nΔικαιούχος: —"],
            'card' => ['provider' => 'none', 'stripe' => ['publishable_key' => '', 'secret_key' => '', 'webhook_secret' => ''], 'viva' => ['env' => 'demo', 'client_id' => '', 'client_secret' => '', 'source_code' => '']],
        ]);
        setting_set($db, 'homepage', [
            'hero' => ['eyebrow' => 'Νέα συλλογή', 'title' => 'Καλώς ήρθατε στο ' . $name, 'subtitle' => self::TEMPLATE_TEXT, 'cta_label' => 'Δείτε τα προϊόντα', 'cta_url' => '/products/', 'image' => null],
            'promo' => ['title' => 'Δωρεάν αποστολή', 'text' => 'για παραγγελίες άνω των 50 €'],
            'marquee' => ['Αποστολή σε όλη την Ελλάδα', 'Πληρωμή με κάρτα', 'Αντικαταβολή', 'Ασφαλείς αγορές'],
            'show_categories' => true, 'show_featured' => true, 'featured_title' => 'Νέα στο ράφι',
            'values' => [
                ['title' => 'Γρήγορη αποστολή', 'text' => self::TEMPLATE_TEXT],
                ['title' => 'Τρόποι πληρωμής', 'text' => 'Κάρτα, αντικαταβολή, τραπεζική κατάθεση.'],
                ['title' => 'Εύκολες επιστροφές', 'text' => self::TEMPLATE_TEXT],
            ],
            'newsletter' => ['show' => true, 'title' => 'Μπείτε στη λίστα', 'text' => 'Νέα προϊόντα και προσφορές στο email σας.'],
            'faq' => [],
        ]);
        setting_set($db, 'popup', [
            'active' => false, 'title' => 'Γίνετε μέλος', 'body' => 'Εγγραφείτε για να μαθαίνετε πρώτοι τα νέα μας.',
            'button_text' => 'Εγγραφή', 'link' => '', 'collect_email' => true, 'consent_required' => true,
            'consent_text' => 'Συμφωνώ να λαμβάνω ενημερωτικά email και αποδέχομαι την πολιτική απορρήτου.', 'delay' => 8,
        ]);
        setting_set($db, 'seo', ['home_title' => '', 'home_description' => '', 'ga4_id' => '', 'gsc_verification' => '', 'same_as' => [], 'llms_extra' => '']);
        foreach ([
            ['about', 'Σχετικά με εμάς', '<p>' . self::TEMPLATE_TEXT . '</p><p>Περιγράψτε ποιοι είστε, τι πουλάτε και πού βρίσκεστε.</p>'],
            ['contact', 'Επικοινωνία', '<p>Στείλτε μας μήνυμα με τη φόρμα. Απαντάμε όσο πιο γρήγορα γίνεται.</p>'],
            ['shipping', 'Αποστολές', '<p>' . self::TEMPLATE_TEXT . '</p><ul><li>Χρόνος παράδοσης: —</li><li>Κόστος: σύμφωνα με τις ρυθμίσεις αποστολής</li></ul>'],
            ['returns', 'Επιστροφές', '<p>' . self::TEMPLATE_TEXT . '</p><p>Ο καταναλωτής έχει δικαίωμα υπαναχώρησης εντός 14 ημερών (ν. 2251/1994). Συμπληρώστε τη διαδικασία σας.</p>'],
            ['terms', 'Όροι χρήσης', '<p>' . self::TEMPLATE_TEXT . '</p>'],
            ['privacy', 'Πολιτική απορρήτου', '<p>' . self::TEMPLATE_TEXT . '</p><p>Περιγράψτε ποια δεδομένα συλλέγετε (παραγγελίες, newsletter), για ποιο σκοπό και πώς ασκούνται τα δικαιώματα GDPR.</p>'],
            ['cookies', 'Πολιτική cookies', '<p>' . self::TEMPLATE_TEXT . '</p><p>Το κατάστημα χρησιμοποιεί απαραίτητα cookies για το καλάθι. Τα cookies στατιστικών (GA4) φορτώνονται μόνο μετά από συγκατάθεση.</p>'],
        ] as $i => [$slug, $title, $body]) {
            insert($db, 'pages', ['slug' => $slug, 'title' => $title, 'body' => $body, 'status' => 'published', 'in_footer' => 1, 'sort' => $i, 'updated_at' => now()]);
        }
        insert($db, 'menus', ['code' => 'main', 'name' => 'Κύριο μενού']);
        insert($db, 'menus', ['code' => 'footer', 'name' => 'Μενού υποσέλιδου']);
        insert($db, 'feeds', ['name' => 'Skroutz', 'slug' => 'skroutz', 'type' => 'skroutz', 'status' => 'inactive', 'created_at' => now()]);
        insert($db, 'feeds', ['name' => 'BestPrice', 'slug' => 'bestprice', 'type' => 'bestprice', 'status' => 'inactive', 'created_at' => now()]);
        insert($db, 'feeds', ['name' => 'Όλα τα προϊόντα (XML)', 'slug' => 'products', 'type' => 'generic', 'status' => 'active', 'fields' => json_encode(Feeds::GENERIC_FIELDS), 'created_at' => now()]);
        if (!empty($in['demo_catalog'])) {
            self::demo($db);
        }
    }

    /** Optional demo catalogue for QA. Clearly labelled as demo data. */
    public static function demo(PDO $db): void
    {
        $cats = [];
        foreach (['Κεραμικά', 'Φωτισμός', 'Υφάσματα', 'Αξεσουάρ'] as $i => $n) {
            $cats[$n] = insert($db, 'categories', ['name' => $n, 'slug' => slugify($n), 'description' => 'Περιγραφή κατηγορίας (δεδομένα επίδειξης).', 'sort' => $i, 'created_at' => now()]);
        }
        $cats['Βάζα'] = insert($db, 'categories', ['parent_id' => $cats['Κεραμικά'], 'name' => 'Βάζα', 'slug' => 'vaza', 'sort' => 0, 'created_at' => now()]);
        $items = [
            ['Κεραμικό βάζο «Ακτή»', 'Βάζα', 38, null, 14, ['Χρώμα' => ['Λευκό', 'Ώχρα', 'Θαλασσί']]],
            ['Χειροποίητη κούπα', 'Κεραμικά', 14, 11.2, 40, ['Χρώμα' => ['Θαλασσί', 'Λευκό']]],
            ['Επιτραπέζιο φωτιστικό «Φάρος»', 'Φωτισμός', 89, null, 6, []],
            ['Λινό ριχτάρι', 'Υφάσματα', 54, null, 9, ['Μέγεθος' => ['130×170', '150×200']]],
            ['Κερί σόγιας «Θυμάρι»', 'Φωτισμός', 18, null, 25, []],
            ['Πήλινο μπολ σερβιρίσματος', 'Κεραμικά', 26, null, 18, []],
            ['Δερμάτινη τσάντα αγοράς', 'Αξεσουάρ', 72, null, 2, []],
            ['Ξύλινο σκαμπό', 'Αξεσουάρ', 120, null, 0, []],
        ];
        foreach ($items as $i => [$name, $cat, $price, $sale, $stock, $variants]) {
            $pid = insert($db, 'products', [
                'name' => $name, 'slug' => slugify($name), 'sku' => 'DEMO-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
                'short_description' => 'Σύντομη περιγραφή (δεδομένα επίδειξης).', 'description' => '<p>Περιγραφή προϊόντος (δεδομένα επίδειξης).</p>',
                'price' => $price, 'sale_price' => $sale, 'stock' => $stock, 'category_id' => $cats[$cat], 'brand' => 'Demo',
                'status' => 'active', 'activated_at' => gmdate('Y-m-d H:i:s', time() - (100 - $i) * 60), 'featured' => $i < 4 ? 1 : 0, 'created_at' => now(),
            ]);
            Catalog::syncCategories($db, $pid, [$cats[$cat]], $cats[$cat]);
            foreach ($variants as $opt => $values) {
                foreach ($values as $j => $v) {
                    insert($db, 'product_variants', ['product_id' => $pid, 'sku' => 'DEMO-' . ($i + 1) . '-' . ($j + 1), 'options' => json_encode([$opt => $v], JSON_UNESCAPED_UNICODE), 'stock' => max(0, intdiv($stock, count($values))), 'sort' => $j]);
                }
            }
            insert($db, 'product_attributes', ['product_id' => $pid, 'name' => 'Υλικό', 'value' => 'Δεδομένα επίδειξης', 'sort' => 0]);
        }
    }
}

final class Mailer
{
    /** Modes (platform setting mail_mode): log | mail | smtp. Returns true on hand-off. */
    public static function send(string $to, string $subject, string $text, ?string $replyTo = null): bool
    {
        $db = DB::platform();
        $mode = setting_get($db, 'mail_mode', 'log');
        $from = setting_get($db, 'mail_from', 'no-reply@' . (Config::get('platform_host') ?: 'localhost'));
        $fromName = setting_get($db, 'platform_name', 'WeChevo');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'From' => '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
        ];
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers['Reply-To'] = $replyTo;
        }
        try {
            if ($mode === 'mail') {
                return mail($to, $subjectEnc, $text, $headers, '-f' . $from);
            }
            if ($mode === 'smtp') {
                return self::smtp(setting_get($db, 'smtp', []), $from, $to, $subjectEnc, $text, $headers);
            }
        } catch (Throwable $e) {
            log_error('mail: ' . $e->getMessage());
            return false;
        }
        if (!is_dir(DATA . '/logs')) {
            mkdir(DATA . '/logs', 0775, true);
        }
        file_put_contents(DATA . '/logs/mail.log', "=== " . now() . "\nTo: $to\nSubject: $subject\n\n$text\n\n", FILE_APPEND | LOCK_EX);
        return true;
    }

    /** Minimal SMTP client (SSL on 465 or STARTTLS on 587) with AUTH LOGIN. */
    private static function smtp(array $c, string $from, string $to, string $subject, string $body, array $headers): bool
    {
        $host = $c['host'] ?? '';
        $port = (int)($c['port'] ?? 465);
        if (!$host) {
            throw new RuntimeException('SMTP host missing');
        }
        $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $fp = stream_socket_client($remote, $errno, $err, 15);
        if (!$fp) {
            throw new RuntimeException("SMTP connect: $err");
        }
        stream_set_timeout($fp, 15);
        $read = function () use ($fp) {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = function (string $c, array $ok) use ($fp, $read) {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int)substr($r, 0, 3), $ok, true)) {
                throw new RuntimeException('SMTP: ' . trim($r));
            }
            return $r;
        };
        $read();
        $ehlo = Config::get('platform_host') ?: 'localhost';
        $cmd("EHLO $ehlo", [250]);
        if ($port !== 465) {
            $cmd('STARTTLS', [220]);
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $cmd("EHLO $ehlo", [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['user'] ?? ''), [334]);
        $cmd(base64_encode($c['pass'] ?? ''), [235]);
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        $h = "To: <$to>\r\nSubject: $subject\r\nDate: " . date('r') . "\r\nMessage-ID: <" . random_token(12) . "@$ehlo>\r\n";
        foreach ($headers as $k => $v) {
            $h .= "$k: $v\r\n";
        }
        $body = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $body));
        $cmd($h . "\r\n" . $body . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return true;
    }
}

/* ---------- payments abstraction (orders inside a shop) ---------- */
interface PaymentGateway
{
    public function code(): string;
    public function configured(): bool;
    /** Returns a URL to redirect the customer to. */
    public function start(array $order, string $returnUrl, string $cancelUrl): string;
    /** Called on return/webhook; returns 'paid' | 'pending' | 'failed'. */
    public function verify(array $order, array $request): string;
}

final class Payments
{
    public const METHODS = ['card' => 'Κάρτα', 'cod' => 'Αντικαταβολή', 'bank' => 'Τραπεζική κατάθεση'];
    public const STATUS = ['pending' => 'Εκκρεμεί', 'awaiting_transfer' => 'Αναμονή κατάθεσης', 'paid' => 'Πληρώθηκε', 'failed' => 'Απέτυχε', 'refunded' => 'Επιστράφηκε'];

    public static function gateway(array $cfg): ?PaymentGateway
    {
        $card = $cfg['card'] ?? [];
        return match ($card['provider'] ?? 'none') {
            'stripe' => new StripeGateway($card['stripe'] ?? []),
            'viva' => new VivaGateway($card['viva'] ?? []),
            default => null,
        };
    }

    /** Payment methods available at checkout for this shop. */
    public static function available(array $cfg): array
    {
        $out = [];
        $g = self::gateway($cfg);
        if ($g && $g->configured()) {
            $out['card'] = ['label' => 'Κάρτα', 'note' => $g->code() === 'stripe' ? 'Ασφαλής πληρωμή μέσω Stripe' : 'Ασφαλής πληρωμή μέσω Viva Wallet', 'fee' => 0];
        }
        if (!empty($cfg['cod']['enabled'])) {
            $fee = (float)($cfg['cod']['fee'] ?? 0);
            $out['cod'] = ['label' => 'Αντικαταβολή', 'note' => $fee > 0 ? 'Επιβάρυνση ' . money($fee) : 'Χωρίς επιβάρυνση', 'fee' => $fee];
        }
        if (!empty($cfg['bank']['enabled'])) {
            $out['bank'] = ['label' => 'Τραπεζική κατάθεση', 'note' => 'Τα στοιχεία λογαριασμού εμφανίζονται μετά την παραγγελία', 'fee' => 0];
        }
        return $out;
    }
}

function http_json(string $method, string $url, array $headers, $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        throw new RuntimeException('Payment provider unreachable: ' . $err);
    }
    return [$code, json_decode($res, true) ?: []];
}

/** Stripe Checkout (hosted page). NOT tested against live credentials in this phase. */
final class StripeGateway implements PaymentGateway
{
    public function __construct(private array $c) {}
    public function code(): string { return 'stripe'; }
    public function configured(): bool { return str_starts_with($this->c['secret_key'] ?? '', 'sk_'); }

    public function start(array $order, string $returnUrl, string $cancelUrl): string
    {
        $params = [
            'mode' => 'payment',
            'success_url' => $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $order['number'],
            'customer_email' => $order['email'],
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => 'eur',
            'line_items[0][price_data][unit_amount]' => (int)round($order['total'] * 100),
            'line_items[0][price_data][product_data][name]' => 'Παραγγελία ' . $order['number'],
            'metadata[order_number]' => $order['number'],
        ];
        [$code, $r] = http_json('POST', 'https://api.stripe.com/v1/checkout/sessions', ['Authorization: Bearer ' . $this->c['secret_key'], 'Content-Type: application/x-www-form-urlencoded'], http_build_query($params));
        if ($code >= 300 || empty($r['url'])) {
            throw new RuntimeException('Stripe: ' . ($r['error']['message'] ?? 'unknown error'));
        }
        return $r['url'];
    }

    public function verify(array $order, array $request): string
    {
        $sid = $request['session_id'] ?? '';
        if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sid)) {
            return 'pending';
        }
        [$code, $r] = http_json('GET', 'https://api.stripe.com/v1/checkout/sessions/' . $sid, ['Authorization: Bearer ' . $this->c['secret_key']]);
        if ($code === 200 && ($r['client_reference_id'] ?? '') === $order['number'] && ($r['payment_status'] ?? '') === 'paid' && (int)$r['amount_total'] === (int)round($order['total'] * 100)) {
            return 'paid';
        }
        return 'pending';
    }
}

/** Viva Wallet Smart Checkout. NOT tested against live credentials in this phase. */
final class VivaGateway implements PaymentGateway
{
    public function __construct(private array $c) {}
    public function code(): string { return 'viva'; }
    public function configured(): bool { return !empty($this->c['client_id']) && !empty($this->c['client_secret']) && !empty($this->c['source_code']); }

    private function hosts(): array
    {
        return ($this->c['env'] ?? 'demo') === 'live'
            ? ['https://accounts.vivapayments.com', 'https://api.vivapayments.com', 'https://www.vivapayments.com']
            : ['https://demo-accounts.vivapayments.com', 'https://demo-api.vivapayments.com', 'https://demo.vivapayments.com'];
    }

    private function token(): string
    {
        [$acc] = $this->hosts();
        [$code, $r] = http_json('POST', $acc . '/connect/token', ['Authorization: Basic ' . base64_encode($this->c['client_id'] . ':' . $this->c['client_secret']), 'Content-Type: application/x-www-form-urlencoded'], 'grant_type=client_credentials');
        if ($code !== 200 || empty($r['access_token'])) {
            throw new RuntimeException('Viva: authentication failed');
        }
        return $r['access_token'];
    }

    public function start(array $order, string $returnUrl, string $cancelUrl): string
    {
        [, $api, $web] = $this->hosts();
        $body = json_encode([
            'amount' => (int)round($order['total'] * 100), 'customerTrns' => 'Παραγγελία ' . $order['number'],
            'customer' => ['email' => $order['email'], 'fullName' => $order['name'], 'phone' => $order['phone'], 'requestLang' => 'el-GR'],
            'sourceCode' => $this->c['source_code'], 'merchantTrns' => $order['number'],
        ]);
        [$code, $r] = http_json('POST', $api . '/checkout/v2/orders', ['Authorization: Bearer ' . $this->token(), 'Content-Type: application/json'], $body);
        if ($code >= 300 || empty($r['orderCode'])) {
            throw new RuntimeException('Viva: could not create payment order');
        }
        return $web . '/web/checkout?ref=' . $r['orderCode'];
    }

    public function verify(array $order, array $request): string
    {
        [, $api] = $this->hosts();
        $t = $request['t'] ?? '';
        if (!preg_match('/^[a-f0-9-]{36}$/i', $t)) {
            return 'pending';
        }
        [$code, $r] = http_json('GET', $api . '/checkout/v2/transactions/' . $t, ['Authorization: Bearer ' . $this->token()]);
        if ($code === 200 && ($r['merchantTrns'] ?? '') === $order['number'] && ($r['statusId'] ?? '') === 'F' && abs((float)$r['amount'] - (float)$order['total']) < 0.01) {
            return 'paid';
        }
        return 'pending';
    }
}
