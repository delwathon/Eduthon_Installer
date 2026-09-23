<?php

namespace Eduthon\Installer\Http;

/**
 * A short-lived, hardened PHP session holding the wizard's progress and the
 * engine API token. The license secret key is never stored.
 */
class Session
{
    private const TTL = 3600;

    public function start(bool $secure, string $path): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('eduthon_installer');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $path,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        if (isset($_SESSION['_touched']) && time() - (int) $_SESSION['_touched'] > self::TTL) {
            $_SESSION = [];
            session_regenerate_id(true);
        }

        $_SESSION['_touched'] = time();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function csrfToken(): string
    {
        if (! is_string($_SESSION['_csrf'] ?? null)) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public function validCsrf(string $token): bool
    {
        return is_string($_SESSION['_csrf'] ?? null) && hash_equals($_SESSION['_csrf'], $token);
    }
}
