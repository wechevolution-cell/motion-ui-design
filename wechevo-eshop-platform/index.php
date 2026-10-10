<?php
/* Front controller. Local: php -S localhost:8000 index.php */
declare(strict_types=1);
define('ROOT', __DIR__);

if (PHP_SAPI === 'cli-server') {
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $f = realpath(ROOT . $p);
    if ($f && is_file($f) && preg_match('#^/(assets|uploads)/#', $p) && !preg_match('/\.(php\d?|phtml|phar)$/i', $f) && str_starts_with($f, ROOT)) {
        return false; // let the built-in server send static files
    }
}

require ROOT . '/app/bootstrap.php';
App::run();
