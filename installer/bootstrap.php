<?php

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'The Eduthon installer needs PHP 8.1 or newer. This server runs PHP '.PHP_VERSION.'. Ask your hosting provider to upgrade PHP, then reload this page.';
    exit;
}

defined('EDUTHON_INSTALLER') || define('EDUTHON_INSTALLER', __DIR__);

// Never print PHP notices into pages or JSON responses; surface them as
// exceptions so each step fails cleanly (and rolls back) instead.
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (! (error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

spl_autoload_register(static function (string $class): void {
    $prefix = 'Eduthon\\Installer\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__.'/lib/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($file)) {
        require $file;
    }
});
