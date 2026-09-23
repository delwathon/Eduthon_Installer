<?php

namespace Eduthon\Installer\Support;

/**
 * Read-only installer configuration: config.php merged with config.local.php.
 */
final class Config
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(private array $values) {}

    public static function load(string $installerDir): self
    {
        $values = require $installerDir.'/config.php';
        $local = $installerDir.'/config.local.php';

        if (is_file($local)) {
            $values = array_replace($values, (array) require $local);
        }

        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function engineUrl(): string
    {
        return rtrim((string) $this->get('engine_url'), '/').'/';
    }

    /**
     * @return list<string>
     */
    public function trustedKeys(): array
    {
        return array_values(array_filter(
            (array) $this->get('trusted_keys', []),
            static fn ($key): bool => is_string($key) && $key !== '' && ! str_starts_with($key, '@'),
        ));
    }

    public function int(string $key, int $default): int
    {
        return (int) $this->get($key, $default);
    }
}
