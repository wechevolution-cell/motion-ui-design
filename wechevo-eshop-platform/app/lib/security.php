<?php
/* Sessions, CSRF, rate limiting, authentication with lockout, audit log, security headers. */

final class Session
{
    /** Separate cookie per area so a storefront session never carries admin rights. */
    public static function start(string $area): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $names = ['super' => 'wc_sa', 'admin' => 'wc_adm', 'store' => 'wc_s'];
        session_name($names[$area] ?? 'wc_s');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', $area === 'store' ? '1209600' : '28800');
        session_set_cookie_params([
            'lifetime' => $area === 'store' ? 1209600 : 0,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $_SESSION['_area'] = $area;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        unset($_SESSION['_csrf']);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            session_destroy();
        }
    }
}

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = random_token(32);
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function check(): void
    {
        $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($t) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $t)) {
            abort(419, 'Η φόρμα έληξε. Ανανεώστε τη σελίδα και δοκιμάστε ξανά.');
        }
    }
}

final class RateLimit
{
    /** Returns false when the key exceeded $max hits inside $window seconds. */
    public static function hit(string $key, int $max, int $window): bool
    {
        $db = DB::platform();
        $now = time();
        $r = row($db, 'SELECT hits, window_start FROM rate_limits WHERE key = ?', [$key]);
        if (!$r || $now - (int)$r['window_start'] >= $window) {
            q($db, 'INSERT INTO rate_limits(key, hits, window_start) VALUES(?, 1, ?) ON CONFLICT(key) DO UPDATE SET hits = 1, window_start = excluded.window_start', [$key, $now]);
            return true;
        }
        q($db, 'UPDATE rate_limits SET hits = hits + 1 WHERE key = ?', [$key]);
        return ((int)$r['hits'] + 1) <= $max;
    }

    public static function cleanup(): void
    {
        q(DB::platform(), 'DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400]);
    }
}

final class Audit
{
    public static function log(string $action, array $o = []): void
    {
        try {
            insert(DB::platform(), 'audit_logs', [
                'actor_user_id' => $o['actor'] ?? Auth::id(),
                'actor_label' => $o['label'] ?? Auth::label(),
                'shop_id' => $o['shop'] ?? (defined('SHOP_ID') ? SHOP_ID : null),
                'action' => $action,
                'entity' => $o['entity'] ?? null,
                'entity_id' => isset($o['id']) ? (string)$o['id'] : null,
                'outcome' => $o['outcome'] ?? 'ok',
                'ip' => client_ip(),
                'meta' => isset($o['meta']) ? json_encode($o['meta'], JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            log_error('audit failed: ' . $e->getMessage());
        }
    }
}

final class Auth
{
    public const MAX_ATTEMPTS = 5;
    public const LOCK_MINUTES = 15;

    public static function user(): ?array
    {
        static $cache = null;
        if (empty($_SESSION['uid'])) {
            return null;
        }
        if ($cache && $cache['id'] == $_SESSION['uid']) {
            return $cache;
        }
        $u = row(DB::platform(), 'SELECT * FROM users WHERE id = ? AND deleted_at IS NULL AND status = ?', [$_SESSION['uid'], 'active']);
        if (!$u) {
            return null;
        }
        $u['perms'] = json_decode($u['permissions'] ?: '[]', true) ?: [];
        return $cache = $u;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
    }

    public static function label(): ?string
    {
        $u = self::user();
        if (!$u) {
            return null;
        }
        $l = $u['email'];
        if (!empty($_SESSION['impersonator'])) {
            $l .= ' (impersonated by superadmin #' . (int)$_SESSION['impersonator'] . ')';
        }
        return $l;
    }

    /**
     * $shopId null = super admin login; otherwise the user must belong to that shop.
     * Returns [user|null, error message|null].
     */
    public static function attempt(string $email, string $password, ?int $shopId): array
    {
        $db = DB::platform();
        $ipKey = 'login:' . ($shopId ?? 0) . ':' . client_ip();
        if (!RateLimit::hit($ipKey, 20, 900)) {
            return [null, 'Πολλές προσπάθειες από αυτή τη διεύθυνση. Δοκιμάστε ξανά σε 15 λεπτά.'];
        }
        $sql = 'SELECT * FROM users WHERE lower(email) = lower(?) AND deleted_at IS NULL AND ' . ($shopId === null ? "shop_id IS NULL AND role = 'superadmin'" : 'shop_id = ?');
        $u = row($db, $sql, $shopId === null ? [$email] : [$email, $shopId]);
        $generic = 'Λάθος email ή κωδικός.';
        if (!$u) {
            password_hash($password, PASSWORD_DEFAULT); // spend similar time as a real check
            Audit::log('login.failed', ['actor' => null, 'label' => $email, 'shop' => $shopId, 'outcome' => 'denied']);
            return [null, $generic];
        }
        if ($u['locked_until'] && $u['locked_until'] > now()) {
            Audit::log('login.locked', ['actor' => $u['id'], 'label' => $email, 'shop' => $shopId, 'outcome' => 'denied']);
            return [null, 'Ο λογαριασμός κλειδώθηκε προσωρινά μετά από ' . self::MAX_ATTEMPTS . ' αποτυχημένες προσπάθειες. Δοκιμάστε ξανά μετά τις ' . dt($u['locked_until'], 'H:i') . '.'];
        }
        if ($u['status'] !== 'active') {
            return [null, 'Ο λογαριασμός είναι ανενεργός.'];
        }
        if (!password_verify($password, $u['password_hash'])) {
            $fails = (int)$u['failed_attempts'] + 1;
            $lock = $fails >= self::MAX_ATTEMPTS ? gmdate('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60) : null;
            update($db, 'users', ['failed_attempts' => $lock ? 0 : $fails, 'locked_until' => $lock], 'id = ?', [$u['id']]);
            Audit::log('login.failed', ['actor' => $u['id'], 'label' => $email, 'shop' => $shopId, 'outcome' => 'denied']);
            return [null, $lock ? 'Ο λογαριασμός κλειδώθηκε για ' . self::LOCK_MINUTES . ' λεπτά μετά από ' . self::MAX_ATTEMPTS . ' αποτυχημένες προσπάθειες.' : $generic];
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            update($db, 'users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        }
        update($db, 'users', ['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()], 'id = ?', [$u['id']]);
        return [$u, null];
    }

    public static function login(array $u, ?int $impersonator = null): void
    {
        Session::regenerate();
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['shop'] = $u['shop_id'] !== null ? (int)$u['shop_id'] : null;
        $_SESSION['impersonator'] = $impersonator;
        $_SESSION['login_at'] = time();
    }

    public static function hashPassword(string $p): string
    {
        return password_hash($p, PASSWORD_DEFAULT);
    }

    public static function passwordProblem(string $p): ?string
    {
        if (mb_strlen($p) < 10) {
            return 'Ο κωδικός πρέπει να έχει τουλάχιστον 10 χαρακτήρες.';
        }
        if (!preg_match('/\d/', $p) || !preg_match('/\p{L}/u', $p)) {
            return 'Ο κωδικός πρέπει να περιέχει γράμματα και αριθμούς.';
        }
        return null;
    }
}

function send_security_headers(bool $private = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    if ($private) {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store, private');
    }
}
