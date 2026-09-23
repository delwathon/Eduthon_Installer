<?php

namespace Eduthon\Installer\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class Filesystem
{
    public static function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Could not create the folder {$path}.");
        }
    }

    public static function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }

    /**
     * Remove empty parent folders of a file up to (not including) $root.
     */
    public static function pruneEmptyParents(string $file, string $root): void
    {
        $directory = dirname($file);

        while (str_starts_with($directory, $root.'/') && $directory !== $root) {
            if (@rmdir($directory) === false) {
                return;
            }

            $directory = dirname($directory);
        }
    }

    public static function freeSpace(string $path): ?float
    {
        $free = @disk_free_space($path);

        return $free === false ? null : (float) $free;
    }
}
