<?php

namespace Tests;

use Eduthon\Installer\Deploy\DeployException;
use Eduthon\Installer\Deploy\Deployer;
use Eduthon\Installer\Deploy\RuntimeConfig;
use Eduthon\Installer\Package\Extractor;
use Eduthon\Installer\Package\PackageInspector;
use PHPUnit\Framework\Attributes\Test;

final class DeployerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = $this->temporaryDirectory();
        mkdir($this->root.'/public_html/installer/storage', 0755, true);
        file_put_contents($this->root.'/public_html/installer/index.php', 'installer');
    }

    private function target(): string
    {
        return $this->root.'/public_html';
    }

    /**
     * @param  array<string, string>  $files
     * @return array{0: string, 1: array<string, array{size: int, sha256: string}>}
     */
    private function stage(array $files): array
    {
        $zip = $this->zip($files);
        $staging = $this->root.'/staging-'.bin2hex(random_bytes(3));

        return [$staging, (new Extractor)->extract($zip, (new PackageInspector(1000, 1 << 26, 'installer'))->inspect($zip), $staging)];
    }

    #[Test]
    public function the_first_deployment_backs_up_what_it_replaces(): void
    {
        file_put_contents($this->target().'/index.html', 'Default hosting page');
        file_put_contents($this->target().'/contact.html', 'Existing page the school keeps');
        [$staging, $files] = $this->stage($this->portalFiles());

        (new Deployer($this->target(), 'installer'))->deploy($staging, $files, [], $this->root.'/backup.zip');

        $this->assertStringContainsString('Eduthon 2.5.0', file_get_contents($this->target().'/index.html'));
        $this->assertSame('Existing page the school keeps', file_get_contents($this->target().'/contact.html'));
        $this->assertSame('installer', file_get_contents($this->target().'/installer/index.php'));
        $this->assertFileExists($this->root.'/backup.zip');
    }

    #[Test]
    public function an_update_removes_files_that_are_no_longer_part_of_the_portal(): void
    {
        $deployer = new Deployer($this->target(), 'installer');
        [$staging, $old] = $this->stage($this->portalFiles('2.4.0'));
        $deployer->deploy($staging, $old, [], $this->root.'/b1.zip');

        [$staging, $new] = $this->stage($this->portalFiles('2.5.0'));
        $deployer->deploy($staging, $new, $old, $this->root.'/b2.zip');

        $this->assertFileDoesNotExist($this->target().'/assets/app-2.4.0.js');
        $this->assertFileExists($this->target().'/assets/app-2.5.0.js');
    }

    #[Test]
    public function rollback_restores_the_previous_site_exactly(): void
    {
        $deployer = new Deployer($this->target(), 'installer');
        [$staging, $old] = $this->stage($this->portalFiles('2.4.0'));
        $deployer->deploy($staging, $old, [], $this->root.'/b1.zip');

        [$staging, $new] = $this->stage($this->portalFiles('2.5.0'));
        $deployer->deploy($staging, $new, $old, $this->root.'/b2.zip');
        $deployer->rollback($this->root.'/b2.zip');

        $this->assertStringContainsString('Eduthon 2.4.0', file_get_contents($this->target().'/index.html'));
        $this->assertFileExists($this->target().'/assets/app-2.4.0.js');
        $this->assertFileDoesNotExist($this->target().'/assets/app-2.5.0.js');
        $this->assertDirectoryExists($this->target().'/installer');
    }

    #[Test]
    public function rolling_back_a_first_installation_restores_the_original_files(): void
    {
        file_put_contents($this->target().'/index.html', 'Default hosting page');
        $deployer = new Deployer($this->target(), 'installer');
        [$staging, $files] = $this->stage($this->portalFiles());

        $deployer->deploy($staging, $files, [], $this->root.'/b.zip');
        $deployer->rollback($this->root.'/b.zip');

        $this->assertSame('Default hosting page', file_get_contents($this->target().'/index.html'));
        $this->assertDirectoryDoesNotExist($this->target().'/assets');
    }

    #[Test]
    public function the_installer_folder_can_never_be_touched(): void
    {
        $this->expectException(DeployException::class);

        (new Deployer($this->target(), 'installer'))->deploy($this->root, ['installer/index.php' => ['size' => 1, 'sha256' => '']], [], $this->root.'/b.zip');
    }

    #[Test]
    public function the_htaccess_block_is_added_once_and_keeps_existing_rules(): void
    {
        file_put_contents($this->target().'/.htaccess', "# cPanel PHP version\nAddHandler application/x-httpd-ea-php82 .php\n");
        $runtime = new RuntimeConfig($this->target(), 'installer', $this->target().'/installer/storage');

        $runtime->writeHtaccess('/');
        $runtime->writeHtaccess('/');

        $contents = file_get_contents($this->target().'/.htaccess');
        $this->assertSame(1, substr_count($contents, '# BEGIN Eduthon'));
        $this->assertStringContainsString('AddHandler application/x-httpd-ea-php82', $contents);
        $this->assertStringContainsString('RewriteRule ^installer(/|$) - [L]', $contents);
        $this->assertStringContainsString('RewriteRule . index.html [L]', $contents);
        $this->assertFileExists($this->target().'/installer/storage/htaccess.original');
    }

    #[Test]
    public function a_portal_in_a_sub_folder_gets_the_right_rewrite_base(): void
    {
        $runtime = new RuntimeConfig($this->target(), 'installer', $this->target().'/installer/storage');
        $runtime->writeHtaccess('/portal/');

        $this->assertStringContainsString('RewriteBase /portal/', file_get_contents($this->target().'/.htaccess'));
    }
}
