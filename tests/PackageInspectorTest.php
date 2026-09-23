<?php

namespace Tests;

use Eduthon\Installer\Package\Extractor;
use Eduthon\Installer\Package\PackageException;
use Eduthon\Installer\Package\PackageInspector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class PackageInspectorTest extends TestCase
{
    private function inspector(): PackageInspector
    {
        return new PackageInspector(1000, 50 * 1024 * 1024, 'installer');
    }

    #[Test]
    public function a_static_portal_build_is_accepted(): void
    {
        $plan = $this->inspector()->inspect($this->zip($this->portalFiles()));

        $this->assertContains('index.html', $plan->paths());
        $this->assertContains('assets/app-2.5.0.js', $plan->paths());
    }

    #[Test]
    public function a_build_zipped_inside_a_folder_is_deployed_from_that_folder(): void
    {
        $files = [];

        foreach ($this->portalFiles() as $name => $contents) {
            $files['dist/'.$name] = $contents;
        }

        $plan = $this->inspector()->inspect($this->zip($files));

        $this->assertSame('dist/', $plan->stripPrefix);
        $this->assertContains('index.html', $plan->paths());
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function maliciousPackages(): array
    {
        return [
            'parent directory escape' => [['index.html' => 'x', '../../public_html/index.php' => '<?php'], 'unsafe path'],
            'nested escape' => [['index.html' => 'x', 'assets/../../evil.js' => 'x'], 'unsafe path'],
            'absolute path' => [['index.html' => 'x', '/etc/passwd' => 'x'], 'unsafe path'],
            'windows path' => [['index.html' => 'x', 'C:/boot.ini' => 'x'], 'unsafe path'],
            'backslashes' => [['index.html' => 'x', 'assets\\..\\evil.js' => 'x'], 'unsafe path'],
            'php script' => [['index.html' => 'x', 'shell.php' => '<?php system($_GET[1]);'], 'server-side code'],
            'disguised php' => [['index.html' => 'x', 'image.php.png.phtml' => '<?php'], 'server-side code'],
            'htaccess override' => [['index.html' => 'x', 'assets/.htaccess' => 'AddType application/x-httpd-php .js'], 'installer manages itself'],
            'user.ini override' => [['index.html' => 'x', '.user.ini' => 'auto_prepend_file=x'], 'installer manages itself'],
            'hidden file' => [['index.html' => 'x', '.env' => 'SECRET=1'], 'hidden file'],
            'config override' => [['index.html' => 'x', 'eduthon.config.json' => '{"api_url":"https://evil"}'], 'installer manages itself'],
            'installer overwrite' => [['index.html' => 'x', 'installer/index.php.js' => 'x'], 'installer folder'],
            'no index' => [['assets/app.js' => 'x'], 'no index.html'],
            'executable' => [['index.html' => 'x', 'run.sh' => '#!/bin/sh'], 'server-side code'],
        ];
    }

    /**
     * @param  array<string, string>  $files
     */
    #[Test]
    #[DataProvider('maliciousPackages')]
    public function dangerous_packages_are_rejected(array $files, string $reason): void
    {
        $this->expectException(PackageException::class);
        $this->expectExceptionMessage($reason);

        $this->inspector()->inspect($this->zip($files));
    }

    #[Test]
    public function symbolic_links_are_rejected(): void
    {
        $this->expectExceptionMessage('symbolic link');

        $this->inspector()->inspect($this->zip(['index.html' => 'x'], ['assets/link.js' => '/etc/passwd']));
    }

    #[Test]
    public function packages_that_unpack_beyond_the_limit_are_rejected(): void
    {
        $this->expectExceptionMessage('would unpack to more than');

        (new PackageInspector(1000, 1024, 'installer'))->inspect($this->zip(['index.html' => str_repeat('a', 4096)]));
    }

    #[Test]
    public function too_many_files_are_rejected(): void
    {
        $this->expectExceptionMessage('more than the allowed');

        (new PackageInspector(2, 1024 * 1024, 'installer'))->inspect($this->zip($this->portalFiles()));
    }

    #[Test]
    public function extraction_writes_exactly_the_planned_files_with_their_hashes(): void
    {
        $zip = $this->zip($this->portalFiles());
        $staging = $this->temporaryDirectory().'/staging';

        $files = (new Extractor)->extract($zip, $this->inspector()->inspect($zip), $staging);

        $this->assertSame(['index.html', 'assets/app-2.5.0.js', 'assets/app-2.5.0.css', 'favicon.ico'], array_keys($files));
        $this->assertSame(hash('sha256', 'body{margin:0}'), $files['assets/app-2.5.0.css']['sha256']);
        $this->assertFileExists($staging.'/assets/app-2.5.0.js');
    }

    #[Test]
    public function the_invalid_archive_is_reported_clearly(): void
    {
        $file = $this->temporaryDirectory().'/broken.zip';
        file_put_contents($file, 'not a zip');

        $this->expectExceptionMessage('not a valid zip archive');

        $this->inspector()->inspect($file);
    }
}
