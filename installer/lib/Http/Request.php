<?php

namespace Eduthon\Installer\Http;

final class Request
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $post
     * @param  array<string, mixed>  $server
     */
    public function __construct(
        private array $query,
        private array $post,
        private array $server,
    ) {}

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_'.strtoupper(str_replace('-', '_', $name));

        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    public function wantsJson(): bool
    {
        return str_contains((string) $this->header('Accept'), 'application/json');
    }

    public function isSecure(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));

        return ($https !== '' && $https !== 'off')
            || (int) ($this->server['SERVER_PORT'] ?? 0) === 443
            || strtolower((string) $this->header('X-Forwarded-Proto')) === 'https';
    }

    public function host(): string
    {
        $host = strtolower((string) ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost'));

        return preg_match('/^[a-z0-9.-]+(:\d+)?$/', $host) ? $host : 'localhost';
    }

    /**
     * The installer's own URL path, e.g. "/installer/".
     */
    public function installerPath(): string
    {
        $script = str_replace('\\', '/', (string) ($this->server['SCRIPT_NAME'] ?? '/installer/index.php'));

        return rtrim(dirname($script), '/').'/';
    }

    /**
     * The public URL of the portal: the folder that contains the installer.
     */
    public function portalUrl(): string
    {
        $path = rtrim(dirname(rtrim($this->installerPath(), '/')), '/');

        return ($this->isSecure() ? 'https' : 'http').'://'.$this->host().($path === '' ? '' : $path).'/';
    }

    public function installerUrl(string $query = ''): string
    {
        return $this->installerPath().($query === '' ? '' : '?'.$query);
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }
}
