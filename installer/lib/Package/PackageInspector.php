<?php

namespace Eduthon\Installer\Package;

use ZipArchive;

/**
 * Reads a package's table of contents and rejects anything that could
 * escape the deployment folder or execute on the server. The portal is a
 * static site, so only static file types are allowed.
 */
final class PackageInspector
{
    /**
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = [
        'html', 'htm', 'js', 'mjs', 'css', 'map', 'json', 'webmanifest', 'txt', 'xml',
        'svg', 'png', 'jpg', 'jpeg', 'jfif', 'gif', 'webp', 'avif', 'ico', 'bmp',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'mp4', 'webm', 'mp3', 'ogg', 'wav', 'pdf', 'csv', 'wasm',
    ];

    /**
     * Files the installer writes itself and packages must not provide.
     *
     * @var list<string>
     */
    public const RESERVED = ['eduthon.config.json', '.htaccess', '.user.ini', 'web.config'];

    public function __construct(
        private int $maxFiles,
        private int $maxUnpackedBytes,
        private string $installerDirectory,
    ) {}

    /**
     * @throws PackageException
     */
    public function inspect(string $zipPath): PackagePlan
    {
        if (! class_exists(ZipArchive::class)) {
            throw new PackageException('This server cannot open packages because the PHP zip extension is missing.');
        }

        $zip = new ZipArchive;
        $opened = $zip->open($zipPath, ZipArchive::RDONLY | ZipArchive::CHECKCONS);

        if ($opened !== true) {
            throw new PackageException('The package is not a valid zip archive.');
        }

        try {
            if ($zip->numFiles > $this->maxFiles) {
                throw new PackageException("The package contains {$zip->numFiles} entries, more than the allowed {$this->maxFiles}.");
            }

            $entries = [];
            $total = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = (string) ($stat['name'] ?? '');

                if ($this->isJunk($name)) {
                    continue;
                }

                $this->assertSafeName($name);

                if ($this->isSymlink($zip, $index)) {
                    throw new PackageException("The package contains a symbolic link ({$name}). Links are not allowed.");
                }

                if (str_ends_with($name, '/')) {
                    continue;
                }

                $size = (int) ($stat['size'] ?? 0);
                $compressed = max(1, (int) ($stat['comp_size'] ?? 0));

                if ($size > 10 * 1024 * 1024 && $size / $compressed > 200) {
                    throw new PackageException("The package entry {$name} has a suspicious compression ratio and was rejected.");
                }

                $total += $size;

                if ($total > $this->maxUnpackedBytes) {
                    throw new PackageException('The package would unpack to more than '.human_bytes($this->maxUnpackedBytes).'.');
                }

                $entries[] = ['name' => $name, 'path' => $name, 'size' => $size];
            }
        } finally {
            $zip->close();
        }

        if ($entries === []) {
            throw new PackageException('The package is empty.');
        }

        [$entries, $prefix] = $this->stripCommonFolder($entries);

        $paths = array_column($entries, 'path');

        if (! in_array('index.html', $paths, true)) {
            throw new PackageException('The package has no index.html at its root, so it is not a portal build.');
        }

        foreach ($entries as $entry) {
            $this->assertAllowedFile($entry['path']);
        }

        return new PackagePlan($entries, $total, $prefix);
    }

    private function isJunk(string $name): bool
    {
        return str_starts_with($name, '__MACOSX/') || basename($name) === '.DS_Store' || basename($name) === 'Thumbs.db';
    }

    private function assertSafeName(string $name): void
    {
        $unsafe = $name === ''
            || str_contains($name, "\0")
            || str_contains($name, '\\')
            || str_starts_with($name, '/')
            || preg_match('/^[A-Za-z]:/', $name)
            || preg_match('#(^|/)\.{1,2}(/|$)#', $name)
            || str_contains(rtrim($name, '/'), '//');

        if ($unsafe) {
            throw new PackageException("The package contains an unsafe path ({$name}) and was rejected.");
        }
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attributes = 0;

        if (! $zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return false;
        }

        return $opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000;
    }

    /**
     * Builds often zip a single folder (e.g. dist/). Deploy its contents.
     *
     * @param  list<array{name: string, path: string, size: int}>  $entries
     * @return array{0: list<array{name: string, path: string, size: int}>, 1: string}
     */
    private function stripCommonFolder(array $entries): array
    {
        if (in_array('index.html', array_column($entries, 'path'), true)) {
            return [$entries, ''];
        }

        $first = explode('/', $entries[0]['path'])[0];

        foreach ($entries as $entry) {
            if (! str_starts_with($entry['path'], $first.'/')) {
                return [$entries, ''];
            }
        }

        $prefix = $first.'/';

        return [
            array_map(static fn (array $entry): array => ['path' => substr($entry['path'], strlen($prefix))] + $entry, $entries),
            $prefix,
        ];
    }

    private function assertAllowedFile(string $path): void
    {
        $segments = explode('/', $path);
        $base = end($segments);

        if (strcasecmp($segments[0], $this->installerDirectory) === 0) {
            throw new PackageException("The package tries to write into the installer folder ({$path}).");
        }

        if (in_array(strtolower($base), self::RESERVED, true)) {
            throw new PackageException("The package contains {$base}, which the installer manages itself.");
        }

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.')) {
                throw new PackageException("The package contains a hidden file or folder ({$path}).");
            }
        }

        $extension = strtolower(pathinfo($base, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new PackageException("The package contains {$path}, which is not a static web file. Portal packages may not contain server-side code.");
        }
    }
}
