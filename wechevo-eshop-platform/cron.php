<?php
/* Scheduler entry point.
   Hostinger → Advanced → Cron Jobs:  every 15 min →  /usr/bin/php /home/USER/public_html/cron.php
   or via URL (when CLI cron is unavailable): https://PANEL-DOMAIN/cron.php?key=CRON_KEY (key shown in Super Admin → Ρυθμίσεις). */
declare(strict_types=1);
define('ROOT', __DIR__);
require ROOT . '/app/bootstrap.php';

Config::load();
if (!Config::installed()) {
    exit("not installed\n");
}
if (PHP_SAPI !== 'cli') {
    $expected = substr(hash_hmac('sha256', 'cron', (string)Config::get('app_key')), 0, 32);
    if (!hash_equals($expected, (string)($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit('forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}
@set_time_limit(300);
$started = microtime(true);
$log = [];

// 1. subscription statuses, warnings (10/3/1), expiry → lock, notification e-mails
$r = Scheduler::run();
$log[] = "scheduler: checked {$r['checked']} shops, emailed {$r['emailed']} notifications";

// 2. continue imports left running in the background (time-boxed)
foreach (rows(DB::platform(), "SELECT * FROM shops s JOIN subscription_plans p ON p.id = s.plan_id WHERE s.deleted_at IS NULL AND s.status IN ('ACTIVE','EXPIRING','EXPIRED')") as $shop) {
    $sid = (int)$shop['id'];
    if (!is_file(DB::shopPath($sid))) {
        continue;
    }
    $full = Shops::find($sid);
    $db = DB::shop($sid);
    foreach (rows($db, "SELECT id FROM imports WHERE status IN ('queued','processing') ORDER BY id") as $imp) {
        do {
            $p = Importer::batch($db, (int)$imp['id'], 500, Shops::activeLimit($full), Shops::storedLimit($full));
        } while (($p['status'] ?? '') === 'processing' && microtime(true) - $started < 240);
        $log[] = "shop $sid import #{$imp['id']}: {$p['status']} {$p['processed']}/{$p['total']}";
        if (($p['status'] ?? '') === 'done') {
            Feeds::invalidate($sid);
        }
    }
    // 3. refresh active feeds that are older than their frequency
    if (Shops::feature($full, 'feeds')) {
        foreach (rows($db, "SELECT * FROM feeds WHERE status = 'active'") as $f) {
            $file = Feeds::file($sid, $f['slug']);
            if (!is_file($file) || time() - filemtime($file) >= max(5, (int)$f['frequency_minutes']) * 60) {
                $n = Feeds::generate($db, $sid, $f, Shops::url($sid, ''));
                $log[] = "shop $sid feed {$f['slug']}: $n items";
            }
        }
    }
}
$log[] = sprintf('done in %.2fs', microtime(true) - $started);
echo implode("\n", $log), "\n";
