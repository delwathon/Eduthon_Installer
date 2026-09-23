<?php

namespace Tests;

use Eduthon\Installer\Support\Filesystem;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ZipArchive;

abstract class TestCase extends BaseTestCase
{
    /** @var list<string> */
    private array $temporary = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            is_dir($path) ? Filesystem::deleteDirectory($path) : @unlink($path);
        }

        parent::tearDown();
    }

    protected function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir().'/eduthon-installer-'.bin2hex(random_bytes(5));
        mkdir($path, 0755, true);
        $this->temporary[] = $path = realpath($path);

        return $path;
    }

    /**
     * @param  array<string, string>  $files
     * @param  array<string, int>  $symlinks  name => target (stored as a Unix symlink entry)
     */
    protected function zip(array $files, array $symlinks = []): string
    {
        $path = sys_get_temp_dir().'/eduthon-package-'.bin2hex(random_bytes(5)).'.zip';
        $this->temporary[] = $path;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        foreach ($symlinks as $name => $target) {
            $zip->addFromString($name, (string) $target);
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, (0120777 << 16));
        }

        $zip->close();

        return $path;
    }

    /**
     * @return array<string, string>
     */
    protected function portalFiles(string $version = '2.5.0'): array
    {
        return [
            'index.html' => "<!doctype html><title>Eduthon {$version}</title><div id=\"app\"></div>",
            'assets/app-'.$version.'.js' => 'console.log("'.$version.'")',
            'assets/app-'.$version.'.css' => 'body{margin:0}',
            'favicon.ico' => 'ico',
        ];
    }

    /**
     * @return array{secret: string, public: string}
     */
    protected function keyPair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return ['secret' => sodium_crypto_sign_secretkey($pair), 'public' => base64_encode(sodium_crypto_sign_publickey($pair))];
    }

    protected function sign(string $file, string $secret): string
    {
        return base64_encode(sodium_crypto_sign_detached(hash_file('sha256', $file), $secret));
    }
}
