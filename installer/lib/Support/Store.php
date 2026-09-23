<?php

namespace Eduthon\Installer\Support;

use RuntimeException;

/**
 * Persists small JSON documents inside PHP files that exit immediately, so
 * the contents stay private even on servers that ignore .htaccess rules.
 */
final class Store
{
    private const GUARD = "<?php exit; ?>\n";

    public function __construct(private string $directory) {}

    /**
     * @return array<string, mixed>
     */
    public function read(string $name): array
    {
        $file = $this->path($name);

        if (! is_file($file)) {
            return [];
        }

        $contents = (string) file_get_contents($file);
        $json = str_starts_with($contents, self::GUARD) ? substr($contents, strlen(self::GUARD)) : $contents;
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function write(string $name, array $data): void
    {
        $file = $this->path($name);
        $temporary = $file.'.'.bin2hex(random_bytes(4)).'.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($temporary, self::GUARD.$json, LOCK_EX) === false || ! rename($temporary, $file)) {
            @unlink($temporary);

            throw new RuntimeException("Could not write {$name}. Check that the installer's storage folder is writable.");
        }
    }

    public function forget(string $name): void
    {
        @unlink($this->path($name));
    }

    private function path(string $name): string
    {
        return $this->directory.'/'.preg_replace('/[^a-z0-9_-]/', '', $name).'.php';
    }
}
