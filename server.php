<?php

/**
 * Router for PHP's built-in web server.
 *
 * Serves existing files in public/ directly (CSS, JS, images) and hands
 * everything else to Laravel's front controller. Without this, passing
 * public/index.php as the router makes every request — including asset
 * requests — go through Laravel, which returns redirects instead of CSS.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$public = __DIR__ . '/public';

if ($uri !== '/' && file_exists($public . $uri) && ! is_dir($public . $uri)) {
    return false; // Let the built-in server serve the file as-is.
}

require_once $public . '/index.php';
