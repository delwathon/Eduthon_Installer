<?php

namespace Eduthon\Installer\Package;

use Eduthon\Installer\Support\Filesystem;
use ZipArchive;

/**
 * Extracts an inspected package entry by entry into a staging folder,
 * never trusting the archive's own paths or sizes.
 */
final class Extractor
{
    /**
     * @return array<string, array{size: int, sha256: string}>  Files by relative path.
     *
     * @throws PackageException
     */
    public function extract(string $zipPath, PackagePlan $plan, string $staging): array
    {
        Filesystem::deleteDirectory($staging);
        Filesystem::ensureDirectory($staging);
        $root = realpath($staging);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new PackageException('The package could not be reopened for extraction.');
        }

        $files = [];

        try {
            foreach ($plan->entries as $entry) {
                $destination = $root.'/'.$entry['path'];
                Filesystem::ensureDirectory(dirname($destination));

                $parent = realpath(dirname($destination));

                if ($parent === false || ($parent !== $root && ! str_starts_with($parent, $root.DIRECTORY_SEPARATOR))) {
                    throw new PackageException("Refusing to extract {$entry['path']} outside the staging folder.");
                }

                $files[$entry['path']] = $this->copyEntry($zip, $entry, $destination);
            }
        } finally {
            $zip->close();
        }

        return $files;
    }

    /**
     * @param  array{name: string, path: string, size: int}  $entry
     * @return array{size: int, sha256: string}
     */
    private function copyEntry(ZipArchive $zip, array $entry, string $destination): array
    {
        $in = $zip->getStream($entry['name']);
        $out = fopen($destination, 'xb');

        if ($in === false || $out === false) {
            throw new PackageException("Could not extract {$entry['path']}.");
        }

        $hash = hash_init('sha256');
        $written = 0;

        try {
            while (! feof($in)) {
                $chunk = fread($in, 65536);

                if ($chunk === false) {
                    throw new PackageException("Could not read {$entry['path']} from the package.");
                }

                $written += strlen($chunk);

                if ($written > $entry['size']) {
                    throw new PackageException("{$entry['path']} is larger than the package declares and was rejected.");
                }

                hash_update($hash, $chunk);
                fwrite($out, $chunk);
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        if ($written !== $entry['size']) {
            throw new PackageException("{$entry['path']} was truncated in the package.");
        }

        return ['size' => $written, 'sha256' => hash_final($hash)];
    }
}
