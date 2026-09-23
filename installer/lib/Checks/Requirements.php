<?php

namespace Eduthon\Installer\Checks;

use Eduthon\Installer\Support\Filesystem;
use Eduthon\Installer\Support\Paths;

/**
 * What this server needs before the portal can be installed.
 */
final class Requirements
{
    private const MIN_FREE_BYTES = 150 * 1024 * 1024;

    public function __construct(private Paths $paths) {}

    /**
     * @return list<array{label: string, ok: bool, required: bool, value: string, help: string}>
     */
    public function check(bool $secure, string $host, string $serverSoftware): array
    {
        $checks = [
            $this->item('PHP 8.1 or newer', PHP_VERSION_ID >= 80100, true, PHP_VERSION, 'Ask your hosting provider to switch this site to PHP 8.1 or newer.'),
        ];

        foreach (['curl' => 'Connects securely to Delwathon.', 'openssl' => 'Encrypted connections.', 'sodium' => 'Verifies package signatures.', 'zip' => 'Opens the portal package.', 'json' => 'Reads configuration.'] as $extension => $purpose) {
            $loaded = extension_loaded($extension);
            $checks[] = $this->item("PHP {$extension} extension", $loaded, true, $loaded ? 'Enabled' : 'Missing', "{$purpose} Enable “{$extension}” in your hosting control panel (Select PHP Version → Extensions).");
        }

        $local = in_array(explode(':', $host)[0], ['localhost', '127.0.0.1'], true);
        $checks[] = $this->item('Secure connection (HTTPS)', $secure || $local, true, $secure ? 'HTTPS' : 'HTTP', 'Your license details must not travel unencrypted. Enable an SSL certificate for this domain (most hosts offer free AutoSSL or Let’s Encrypt), then open the installer with https://.');

        $checks[] = $this->item('Web root is writable', is_writable($this->paths->target), true, $this->paths->target, 'Give the web server write access to this folder (usually permission 755 owned by your hosting account).');
        $checks[] = $this->item('Installer storage is writable', is_writable($this->paths->storage), true, $this->paths->storage, 'The installer keeps downloads and backups here. Make the folder writable (755).');

        $free = Filesystem::freeSpace($this->paths->target);
        $checks[] = $this->item('Free disk space', $free === null || $free >= self::MIN_FREE_BYTES, true, $free === null ? 'Unknown' : human_bytes($free), 'At least 150 MB is needed for the download, staging and a backup.');

        $apache = stripos($serverSoftware, 'apache') !== false || stripos($serverSoftware, 'litespeed') !== false;
        $checks[] = $this->item('Apache or LiteSpeed', $apache, false, $serverSoftware !== '' ? $serverSoftware : 'Unknown', 'Other web servers work, but you will need to add the routing rule shown at the end of the installation yourself.');

        return $checks;
    }

    /**
     * @param  list<array{ok: bool, required: bool}>  $checks
     */
    public static function passes(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['required'] && ! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{label: string, ok: bool, required: bool, value: string, help: string}
     */
    private function item(string $label, bool $ok, bool $required, string $value, string $help): array
    {
        return compact('label', 'ok', 'required', 'value', 'help');
    }
}
