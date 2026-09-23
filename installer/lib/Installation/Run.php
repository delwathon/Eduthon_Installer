<?php

namespace Eduthon\Installer\Installation;

use Eduthon\Installer\Support\Store;

/**
 * One installation or update in progress: the release being deployed and
 * the outcome of each step. Persisted so a page reload can resume.
 */
final class Run
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(private Store $store, private array $data) {}

    public static function current(Store $store): ?self
    {
        $data = $store->read('run');

        return $data === [] ? null : new self($store, $data);
    }

    /**
     * @param  array<string, mixed>  $config  From Delwathon's installer/config endpoint.
     * @param  array<string, mixed>  $release  From releases/latest.
     */
    public static function start(Store $store, string $mode, array $config, array $release, string $portalUrl, string $purchaseCode): self
    {
        $run = new self($store, [
            'id' => bin2hex(random_bytes(8)),
            'mode' => $mode,
            'started_at' => gmdate('c'),
            'portal_url' => $portalUrl,
            'purchase_code' => $purchaseCode,
            'config' => $config,
            'release' => array_intersect_key($release, array_flip(['version', 'channel', 'notes', 'size', 'sha256', 'signature', 'download_url', 'published_at'])),
            'tasks' => [],
        ]);
        $run->save();

        return $run;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->save();
    }

    public function release(string $key): mixed
    {
        return $this->data['release'][$key] ?? null;
    }

    public function config(string $path): mixed
    {
        $value = $this->data['config'];

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @return array<string, array{status: string, message: string, at: string}>
     */
    public function tasks(): array
    {
        return (array) $this->data['tasks'];
    }

    public function taskStatus(string $task): ?string
    {
        return $this->data['tasks'][$task]['status'] ?? null;
    }

    public function mark(string $task, string $status, string $message): void
    {
        $this->data['tasks'][$task] = ['status' => $status, 'message' => $message, 'at' => gmdate('c')];
        $this->save();
    }

    public function isComplete(): bool
    {
        return in_array($this->taskStatus(Pipeline::LAST_TASK), ['done', 'warning'], true);
    }

    public function hasFailed(): bool
    {
        foreach ($this->tasks() as $task) {
            if ($task['status'] === 'failed') {
                return true;
            }
        }

        return false;
    }

    public function discard(): void
    {
        $this->store->forget('run');
    }

    private function save(): void
    {
        $this->store->write('run', $this->data);
    }
}
