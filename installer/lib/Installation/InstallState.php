<?php

namespace Eduthon\Installer\Installation;

use Eduthon\Installer\Support\Store;

/**
 * What is currently installed on this server. Contains no secrets.
 */
final class InstallState
{
    public function __construct(private Store $store) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->store->read('state');
    }

    public function isInstalled(): bool
    {
        return (bool) ($this->all()['installed'] ?? false);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * @return array<string, array{size: int, sha256: string}>
     */
    public function files(): array
    {
        return (array) $this->get('files', []);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function record(array $values, string $event): void
    {
        $state = $this->all();
        $history = (array) ($state['history'] ?? []);
        array_unshift($history, ['event' => $event, 'version' => $values['version'] ?? null, 'at' => gmdate('c')]);

        $this->store->write('state', array_merge($state, $values, [
            'installed' => true,
            'updated_at' => gmdate('c'),
            'installed_at' => $state['installed_at'] ?? gmdate('c'),
            'history' => array_slice($history, 0, 20),
        ]));
    }
}
