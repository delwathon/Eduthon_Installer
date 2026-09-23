<?php

namespace Eduthon\Installer\Deploy;

use Eduthon\Installer\Support\Filesystem;
use ZipArchive;

/**
 * Moves a verified, staged portal build into the web root.
 *
 * Every file that will be overwritten or removed is first saved to a
 * backup archive, so a failed deployment can be rolled back exactly.
 */
final class Deployer
{
    private const BACKUP_MANIFEST = '.eduthon-backup.json';

    public function __construct(private string $target, private string $installerDirectory) {}

    /**
     * @param  array<string, array{size: int, sha256: string}>  $files  New files, by path.
     * @param  array<string, mixed>  $previous  Paths deployed by the last installation.
     */
    public function deploy(string $staging, array $files, array $previous, string $backupFile): void
    {
        $this->backup(array_keys($files), array_keys($previous), $backupFile);

        // Assets first, index.html last, so visitors never load a page whose assets are missing.
        $paths = array_keys($files);
        usort($paths, static fn (string $a, string $b): int => ($a === 'index.html') <=> ($b === 'index.html'));

        foreach ($paths as $path) {
            $this->place($staging.'/'.$path, $this->destination($path));
        }

        foreach (array_diff(array_keys($previous), $paths) as $stale) {
            $file = $this->destination($stale);

            if (is_file($file)) {
                @unlink($file);
                Filesystem::pruneEmptyParents($file, $this->target);
            }
        }
    }

    /**
     * Put the web root back exactly as it was before the deployment.
     */
    public function rollback(string $backupFile): void
    {
        if (! is_file($backupFile)) {
            return;
        }

        $zip = new ZipArchive;

        if ($zip->open($backupFile, ZipArchive::RDONLY) !== true) {
            throw new DeployException('The backup could not be opened, so the previous version could not be restored.');
        }

        try {
            $manifest = json_decode((string) $zip->getFromName(self::BACKUP_MANIFEST), true) ?: ['restore' => [], 'created' => []];

            foreach ($manifest['created'] as $path) {
                $file = $this->destination($path);

                if (is_file($file)) {
                    @unlink($file);
                    Filesystem::pruneEmptyParents($file, $this->target);
                }
            }

            foreach ($manifest['restore'] as $path) {
                $contents = $zip->getFromName($path);

                if ($contents === false) {
                    continue;
                }

                $file = $this->destination($path);
                Filesystem::ensureDirectory(dirname($file));
                file_put_contents($file, $contents, LOCK_EX);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Top-level names already in the web root, excluding the installer.
     *
     * @return list<string>
     */
    public function existingEntries(): array
    {
        $names = array_diff(scandir($this->target) ?: [], ['.', '..', $this->installerDirectory, '.htaccess', 'eduthon.config.json', 'cgi-bin', '.well-known']);

        return array_values($names);
    }

    /**
     * @param  list<string>  $incoming
     * @param  list<string>  $previous
     */
    private function backup(array $incoming, array $previous, string $backupFile): void
    {
        Filesystem::ensureDirectory(dirname($backupFile));

        $zip = new ZipArchive;

        if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new DeployException('Could not create a backup of the current site. Nothing was changed.');
        }

        $restore = [];
        $created = [];

        foreach (array_unique(array_merge($incoming, $previous)) as $path) {
            $file = $this->destination($path);

            if (is_file($file)) {
                $zip->addFile($file, $path);
                $restore[] = $path;
            } elseif (in_array($path, $incoming, true)) {
                $created[] = $path;
            }
        }

        $zip->addFromString(self::BACKUP_MANIFEST, (string) json_encode(['restore' => $restore, 'created' => $created, 'at' => gmdate('c')]));

        if (! $zip->close()) {
            throw new DeployException('Could not write the backup of the current site. Nothing was changed.');
        }
    }

    private function place(string $source, string $destination): void
    {
        Filesystem::ensureDirectory(dirname($destination));

        $temporary = dirname($destination).'/.'.basename($destination).'.'.bin2hex(random_bytes(3)).'.tmp';

        if (! copy($source, $temporary) || ! rename($temporary, $destination)) {
            @unlink($temporary);

            throw new DeployException('Could not write '.substr($destination, strlen($this->target) + 1).'. Check the folder permissions.');
        }

        @chmod($destination, 0644);
    }

    private function destination(string $path): string
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            throw new DeployException("Refusing to touch an unsafe path: {$path}");
        }

        if (strcasecmp(explode('/', $path)[0], $this->installerDirectory) === 0) {
            throw new DeployException("Refusing to modify the installer: {$path}");
        }

        return $this->target.'/'.$path;
    }
}
