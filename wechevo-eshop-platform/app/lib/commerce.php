<?php
/* Cart (session) and order placement. Orders snapshot product data so later edits,
   deactivation or deletion never change past orders. */

final class Cart
{
    public static function raw(): array
    {
        return is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
    }

    public static function count(): int
    {
        return array_sum(array_map(fn($l) => (int)$l['q'], self::raw()));
    }

    public static function add(PDO $db, int $pid, ?int $vid, int $qty): string
    {
        $p = row($db, "SELECT * FROM products WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$pid]);
        if (!$p) {
            throw new DomainException('Το προϊόν δεν είναι διαθέσιμο.');
        }
        $variants = Catalog::variants($db, $pid);
        if ($variants) {
            $v = null;
            foreach ($variants as $x) {
                if ((int)$x['id'] === (int)$vid) {
                    $v = $x;
                }
            }
            if (!$v) {
                throw new DomainException('Επιλέξτε παραλλαγή (π.χ. χρώμα ή μέγεθος).');
            }
            $stock = (int)$v['stock'];
        } else {
            $vid = null;
            $stock = (int)$p['stock'];
        }
        $key = $pid . ':' . ($vid ?? 0);
        $cart = self::raw();
        $have = (int)($cart[$key]['q'] ?? 0);
        $qty = max(1, min(99, $qty));
        if ($p['manage_stock'] && $have + $qty > $stock) {
            throw new DomainException($stock > 0 ? "Διαθέσιμα μόνο $stock τεμάχια." : 'Το προϊόν έχει εξαντληθεί.');
        }
        $cart[$key] = ['p' => $pid, 'v' => $vid, 'q' => $have + $qty];
        $_SESSION['cart'] = $cart;
        return $key;
    }

    public static function setQty(string $key, int $qty): void
    {
        $cart = self::raw();
        if (!isset($cart[$key])) {
            return;
        }
        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['q'] = min(99, $qty);
        }
        $_SESSION['cart'] = $cart;
    }

    public static function clear(): void
    {
        $_SESSION['cart'] = [];
    }

    /** Resolve lines against the DB. Inactive/deleted products are dropped (and reported). */
    public static function lines(PDO $db, array &$notices = []): array
    {
        $cart = self::raw();
        if (!$cart) {
            return [];
        }
        $pids = array_unique(array_map(fn($l) => (int)$l['p'], $cart));
        $in = implode(',', array_fill(0, count($pids), '?'));
        $prods = [];
        foreach (rows($db, "SELECT * FROM products WHERE id IN ($in) AND status = 'active' AND deleted_at IS NULL", $pids) as $p) {
            $prods[$p['id']] = $p;
        }
        $covers = Catalog::coverMap($db, array_keys($prods));
        $lines = [];
        $changed = false;
        foreach ($cart as $key => $l) {
            $p = $prods[$l['p']] ?? null;
            if (!$p) {
                $notices[] = 'Ένα προϊόν αφαιρέθηκε από το καλάθι γιατί δεν είναι πλέον διαθέσιμο.';
                unset($cart[$key]);
                $changed = true;
                continue;
            }
            $v = null;
            if ($l['v']) {
                $v = row($db, 'SELECT * FROM product_variants WHERE id = ? AND product_id = ?', [$l['v'], $p['id']]);
                if (!$v) {
                    unset($cart[$key]);
                    $changed = true;
                    continue;
                }
            }
            $price = $v && $v['price'] !== null ? (float)$v['price'] : Catalog::effectivePrice($p);
            $stock = $v ? (int)$v['stock'] : (int)$p['stock'];
            $qty = (int)$l['q'];
            if ($p['manage_stock'] && $qty > $stock) {
                if ($stock <= 0) {
                    $notices[] = "Το «{$p['name']}» εξαντλήθηκε και αφαιρέθηκε από το καλάθι.";
                    unset($cart[$key]);
                    $changed = true;
                    continue;
                }
                $qty = $stock;
                $cart[$key]['q'] = $qty;
                $changed = true;
                $notices[] = "Η ποσότητα για «{$p['name']}» μειώθηκε στα $stock διαθέσιμα τεμάχια.";
            }
            $opts = $v ? (json_decode($v['options'], true) ?: []) : [];
            $lines[] = [
                'key' => $key, 'product' => $p, 'variant' => $v, 'options' => $opts,
                'options_label' => implode(' · ', array_map(fn($k, $x) => "$k: $x", array_keys($opts), $opts)),
                'price' => $price, 'qty' => $qty, 'total' => round($price * $qty, 2),
                'image' => $covers[$p['id']] ?? null, 'sku' => $v['sku'] ?? $p['sku'],
            ];
        }
        if ($changed) {
            $_SESSION['cart'] = $cart;
        }
        return $lines;
    }

    public static function totals(array $lines, array $shipCfg, string $region = 'mainland', float $fee = 0): array
    {
        $sub = round(array_sum(array_column($lines, 'total')), 2);
        $ship = 0.0;
        if ($sub > 0) {
            $free = (float)($shipCfg['free_over'] ?? 0);
            $ship = ($free > 0 && $sub >= $free) ? 0 : (float)($shipCfg['flat'] ?? 0);
            if ($region === 'islands') {
                $ship += (float)($shipCfg['islands_extra'] ?? 0);
            }
        }
        return ['subtotal' => $sub, 'shipping' => round($ship, 2), 'fee' => round($fee, 2), 'total' => round($sub + $ship + $fee, 2),
            'free_over' => (float)($shipCfg['free_over'] ?? 0), 'to_free' => max(0, round((float)($shipCfg['free_over'] ?? 0) - $sub, 2))];
    }
}

final class Orders
{
    public const STATUS = ['new' => 'Νέα', 'processing' => 'Σε επεξεργασία', 'shipped' => 'Απεστάλη', 'completed' => 'Ολοκληρώθηκε', 'cancelled' => 'Ακυρώθηκε'];
    public const SHIPPING = ['not_shipped' => 'Δεν έχει αποσταλεί', 'shipped' => 'Απεστάλη', 'delivered' => 'Παραδόθηκε', 'returned' => 'Επιστράφηκε'];

    /** Validate customer input; returns [clean data, errors]. */
    public static function validate(array $in, array $methods): array
    {
        $e = [];
        $d = [];
        foreach (['name' => 120, 'email' => 160, 'phone' => 30, 'street' => 200, 'city' => 80, 'postcode' => 10, 'customer_note' => 1000, 'company_name' => 150, 'vat_number' => 15, 'tax_office' => 80] as $k => $max) {
            $d[$k] = Sanitizer::text($in[$k] ?? '', $max);
        }
        $d['email'] = strtolower($d['email']);
        if (mb_strlen($d['name']) < 3) $e['name'] = 'Συμπληρώστε ονοματεπώνυμο.';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $e['email'] = 'Συμπληρώστε έγκυρο email.';
        if (!preg_match('/^[+0-9 ()-]{10,20}$/', $d['phone'])) $e['phone'] = 'Συμπληρώστε έγκυρο τηλέφωνο.';
        if (mb_strlen($d['street']) < 3) $e['street'] = 'Συμπληρώστε διεύθυνση.';
        if (mb_strlen($d['city']) < 2) $e['city'] = 'Συμπληρώστε πόλη.';
        if (!preg_match('/^\d{3}\s?\d{2}$/', $d['postcode'])) $e['postcode'] = 'Ο Τ.Κ. έχει 5 ψηφία.';
        $d['region'] = ($in['region'] ?? '') === 'islands' ? 'islands' : 'mainland';
        $d['payment_method'] = (string)($in['payment_method'] ?? '');
        if (!isset($methods[$d['payment_method']])) $e['payment_method'] = 'Επιλέξτε τρόπο πληρωμής.';
        $d['invoice_type'] = ($in['invoice_type'] ?? '') === 'invoice' ? 'invoice' : 'receipt';
        if ($d['invoice_type'] === 'invoice' && (!preg_match('/^\d{9}$/', $d['vat_number']) || $d['company_name'] === '')) {
            $e['vat_number'] = 'Για τιμολόγιο απαιτούνται επωνυμία και ΑΦΜ 9 ψηφίων.';
        }
        if (empty($in['terms'])) $e['terms'] = 'Πρέπει να αποδεχτείτε τους όρους χρήσης.';
        return [$d, $e];
    }

    /** Place an order atomically: re-check stock, decrement, snapshot items. */
    public static function place(PDO $db, array $d, array $methods, array $shipCfg): array
    {
        $db->exec('BEGIN IMMEDIATE');
        try {
            $notices = [];
            $lines = Cart::lines($db, $notices);
            if (!$lines) {
                throw new DomainException('Το καλάθι είναι άδειο.');
            }
            if ($notices) {
                throw new DomainException(implode(' ', $notices) . ' Ελέγξτε το καλάθι σας.');
            }
            $fee = (float)($methods[$d['payment_method']]['fee'] ?? 0);
            $t = Cart::totals($lines, $shipCfg, $d['region'], $fee);
            foreach ($lines as $l) {
                if (!$l['product']['manage_stock']) {
                    continue;
                }
                $ok = $l['variant']
                    ? q($db, 'UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?', [$l['qty'], $l['variant']['id'], $l['qty']])->rowCount()
                    : q($db, 'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?', [$l['qty'], $l['product']['id'], $l['qty']])->rowCount();
                if (!$ok) {
                    throw new DomainException("Το «{$l['product']['name']}» δεν έχει πλέον επαρκές απόθεμα.");
                }
                if ($l['variant']) {
                    q($db, 'UPDATE products SET stock = (SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = ?) WHERE id = ?', [$l['product']['id'], $l['product']['id']]);
                }
            }
            $cid = val($db, 'SELECT id FROM customers WHERE email = ?', [$d['email']]);
            if ($cid) {
                update($db, 'customers', ['name' => $d['name'], 'phone' => $d['phone'], 'updated_at' => now()], 'id = ?', [$cid]);
            } else {
                $cid = insert($db, 'customers', ['email' => $d['email'], 'name' => $d['name'], 'phone' => $d['phone'], 'created_at' => now()]);
            }
            if (!val($db, 'SELECT 1 FROM addresses WHERE customer_id = ? AND street = ? AND postcode = ?', [$cid, $d['street'], $d['postcode']])) {
                insert($db, 'addresses', ['customer_id' => $cid, 'name' => $d['name'], 'phone' => $d['phone'], 'street' => $d['street'], 'city' => $d['city'], 'postcode' => $d['postcode'], 'region' => $d['region'], 'is_default' => 1, 'created_at' => now()]);
            }
            $paymentStatus = $d['payment_method'] === 'bank' ? 'awaiting_transfer' : 'pending';
            $oid = insert($db, 'orders', [
                'number' => 'TMP-' . random_token(6), 'access_token' => random_token(18), 'customer_id' => $cid,
                'email' => $d['email'], 'name' => $d['name'], 'phone' => $d['phone'], 'street' => $d['street'], 'city' => $d['city'], 'postcode' => $d['postcode'],
                'region' => $d['region'], 'invoice_type' => $d['invoice_type'], 'company_name' => $d['company_name'] ?: null,
                'vat_number' => $d['vat_number'] ?: null, 'tax_office' => $d['tax_office'] ?: null, 'customer_note' => $d['customer_note'] ?: null,
                'subtotal' => $t['subtotal'], 'shipping' => $t['shipping'], 'fee' => $t['fee'], 'total' => $t['total'],
                'payment_method' => $d['payment_method'], 'payment_status' => $paymentStatus, 'status' => 'new', 'ip' => client_ip(), 'created_at' => now(),
            ]);
            $number = gmdate('y') . str_pad((string)$oid, 5, '0', STR_PAD_LEFT);
            update($db, 'orders', ['number' => $number], 'id = ?', [$oid]);
            foreach ($lines as $l) {
                insert($db, 'order_items', [
                    'order_id' => $oid, 'product_id' => $l['product']['id'], 'variant_id' => $l['variant']['id'] ?? null,
                    'name' => $l['product']['name'], 'sku' => $l['sku'], 'options' => $l['options_label'] ?: null,
                    'price' => $l['price'], 'qty' => $l['qty'], 'total' => $l['total'],
                ]);
            }
            $db->exec('COMMIT');
        } catch (Throwable $e) {
            $db->exec('ROLLBACK');
            throw $e;
        }
        Cart::clear();
        return row($db, 'SELECT * FROM orders WHERE id = ?', [$oid]);
    }

    public static function emails(PDO $db, array $order, array $company, string $orderUrl, array $payCfg): void
    {
        $items = rows($db, 'SELECT * FROM order_items WHERE order_id = ?', [$order['id']]);
        $lines = implode("\n", array_map(fn($i) => "- {$i['qty']} × {$i['name']}" . ($i['options'] ? " ({$i['options']})" : '') . ' = ' . money($i['total']), $items));
        $body = "Ευχαριστούμε για την παραγγελία σας!\n\nΑριθμός παραγγελίας: {$order['number']}\n\n$lines\n\nΥποσύνολο: " . money($order['subtotal']) .
            "\nΑποστολή: " . money($order['shipping']) . ($order['fee'] > 0 ? "\nΕπιβάρυνση: " . money($order['fee']) : '') . "\nΣύνολο: " . money($order['total']) .
            "\nΤρόπος πληρωμής: " . (Payments::METHODS[$order['payment_method']] ?? $order['payment_method']) . "\n";
        if ($order['payment_method'] === 'bank') {
            $body .= "\nΣτοιχεία κατάθεσης:\n" . ($payCfg['bank']['details'] ?? '') . "\nΑιτιολογία: {$order['number']}\n";
        }
        $body .= "\nΠροβολή παραγγελίας: $orderUrl\n\n" . ($company['name'] ?? '');
        Mailer::send($order['email'], 'Παραγγελία ' . $order['number'] . ' — ' . ($company['name'] ?? ''), $body, $company['email'] ?? null);
        if (!empty($company['email'])) {
            Mailer::send($company['email'], 'Νέα παραγγελία ' . $order['number'] . ' (' . money($order['total']) . ')', "Νέα παραγγελία από {$order['name']} <{$order['email']}>, τηλ. {$order['phone']}\n\n$lines\n\nΣύνολο: " . money($order['total']));
        }
    }
}
