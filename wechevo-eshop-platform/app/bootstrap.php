<?php
declare(strict_types=1);

define('APP', ROOT . '/app');
define('DATA', ROOT . '/data');
define('UPLOADS', ROOT . '/uploads');
define('APP_VERSION', '1.0.0-phase1');

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
error_reporting(E_ALL);

foreach (['core', 'security', 'sanitize', 'platform', 'catalog', 'services', 'commerce', 'feeds', 'importer', 'seo', 'template'] as $lib) {
    require APP . '/lib/' . $lib . '.php';
}

set_error_handler(function (int $no, string $str, string $file, int $line) {
    if (!(error_reporting() & $no)) {
        return false;
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});

final class App
{
    public static string $area = 'platform';

    public static function run(): void
    {
        ob_start();
        try {
            self::dispatch();
        } catch (HttpError $e) {
            self::error($e->status, $e->getMessage());
        } catch (Throwable $e) {
            log_error(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
            self::error(500, '');
        }
        if (ob_get_level()) {
            ob_end_flush();
        }
    }

    private static function dispatch(): void
    {
        foreach (['shops', 'logs', 'backups', 'imports', 'feeds'] as $d) {
            if (!is_dir(DATA . '/' . $d)) {
                @mkdir(DATA . '/' . $d, 0775, true);
            }
        }
        Config::load();
        $path = req_path();

        if (!Config::installed()) {
            self::$area = 'super';
            require APP . '/routes/superadmin.php';
            SuperRoutes::setup($path);
            return;
        }

        $host = req_host();
        $platformHost = Config::get('platform_host');
        Scheduler::maybeRun();

        $onPlatformHost = preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', (string)$platformHost);
        $shop = Shops::byDomain($host);
        $shopHere = $shop && $shop['status'] !== 'DELETED';

        // Super Admin always lives under /superadmin/ on the platform domain. When a shop is also
        // mapped to that domain (single-domain mode), every other path belongs to that shop.
        if ($onPlatformHost && ($path === '/superadmin' || str_starts_with($path, '/superadmin/') || !$shopHere)) {
            self::$area = 'super';
            require APP . '/routes/superadmin.php';
            SuperRoutes::dispatch($path);
            return;
        }

        if (!$shop || $shop['status'] === 'DELETED') {
            self::$area = 'none';
            abort(404, 'Δεν βρέθηκε κατάστημα για αυτό το domain.');
        }
        define('SHOP_ID', (int)$shop['id']);

        // canonical host (non-www, primary domain) and HTTPS
        $primary = Shops::primaryDomain((int)$shop['id']);
        $local = str_ends_with($host, '.localhost') || $host === 'localhost';
        $forceHttps = !$local && setting_get(DB::platform(), 'force_https', true);
        if (($primary && $host !== $primary) || ($forceHttps && !is_https())) {
            $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
            redirect(($forceHttps ? 'https://' : (is_https() ? 'https://' : 'http://')) . $primary . req_port_suffix() . ($_SERVER['REQUEST_URI'] ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/') . $qs, 301);
        }

        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            self::$area = 'admin';
            require APP . '/routes/admin.php';
            AdminRoutes::dispatch($shop, $path);
            return;
        }
        self::$area = 'store';
        require APP . '/routes/store.php';
        StoreRoutes::dispatch($shop, $path);
    }

    public static function error(int $code, string $msg): void
    {
        while (ob_get_level() > 1) {
            ob_end_clean();
        }
        if (ob_get_level()) {
            ob_clean();
        }
        http_response_code($code);
        $titles = [400 => 'Μη έγκυρο αίτημα', 403 => 'Δεν έχετε πρόσβαση', 404 => 'Η σελίδα δεν βρέθηκε', 405 => 'Μη επιτρεπτή μέθοδος', 410 => 'Η σελίδα δεν υπάρχει πια', 419 => 'Η σελίδα έληξε', 429 => 'Πολλά αιτήματα', 500 => 'Κάτι πήγε στραβά', 503 => 'Προσωρινά μη διαθέσιμο'];
        $title = $titles[$code] ?? 'Σφάλμα';
        if ($code === 500) {
            $msg = 'Παρουσιάστηκε απρόσμενο σφάλμα. Δοκιμάστε ξανά σε λίγο. Αν επιμένει, επικοινωνήστε με την υποστήριξη.';
        }
        if (self::$area === 'store' && defined('SHOP_ID') && class_exists('StoreRoutes', false)) {
            try {
                StoreRoutes::errorPage($code, $title, $msg);
                return;
            } catch (Throwable $e) {
                log_error('error page: ' . $e->getMessage());
            }
        }
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $msg ?: $title], JSON_UNESCAPED_UNICODE);
            return;
        }
        send_security_headers(true);
        echo render(APP . '/views/shared/error.php', ['code' => $code, 'title' => $title, 'msg' => $msg]);
    }
}
