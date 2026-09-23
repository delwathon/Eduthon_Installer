<?php

namespace Eduthon\Installer\Support;

use RuntimeException;

/**
 * Filesystem locations used by the installer.
 */
final class Paths
{
    public function __construct(
        public readonly string $installer,
        public readonly string $target,
        public readonly string $storage,
    ) {}

    public static function resolve(string $installerDir, Config $config): self
    {
        $installer = rtrim(str_replace('\\', '/', realpath($installerDir) ?: $installerDir), '/');
        $target = $config->get('target_dir') ?: dirname($installer);
        $target = rtrim(str_replace('\\', '/', realpath($target) ?: $target), '/');

        if ($target === $installer || str_starts_with($installer.'/', $target.'/') === false) {
            throw new RuntimeException('The installer must be placed inside the folder it deploys to.');
        }

        return new self($installer, $target, $installer.'/storage');
    }

    public function storage(string $path = ''): string
    {
        return $this->storage.($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    /**
     * The installer folder's name relative to the target, e.g. "installer".
     * Deployments never write into it.
     */
    public function installerRelative(): string
    {
        return ltrim(substr($this->installer, strlen($this->target)), '/');
    }
}
