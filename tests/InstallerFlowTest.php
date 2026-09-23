<?php

namespace Tests;

use Eduthon\Installer\App;
use Eduthon\Installer\Http\Request;
use Eduthon\Installer\Http\Response;
use Eduthon\Installer\Installation\Pipeline;
use Eduthon\Installer\Support\Container;
use Eduthon\Installer\Engine\TransportResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeTransport;
use Tests\Support\TestSession;

/**
 * Drives the installer exactly as a browser would, against a fake Delwathon.
 */
final class InstallerFlowTest extends TestCase
{
    private string $webRoot;

    private string $installerDir;

    private FakeTransport $engine;

    /** @var array{secret: string, public: string} */
    private array $keys;

    private string $package;

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = ['_csrf' => 'csrf-token'];
        $this->keys = $this->keyPair();
        $this->webRoot = $this->temporaryDirectory().'/public_html';
        $this->installerDir = $this->webRoot.'/installer';
        $this->copyInstaller(dirname(__DIR__).'/installer', $this->installerDir);

        file_put_contents($this->installerDir.'/config.local.php', '<?php return '.var_export([
            'engine_url' => 'https://engine.delwathon.com/api/',
            'trusted_keys' => [$this->keys['public']],
        ], true).';');

        $this->package = $this->zip($this->portalFiles('2.5.0'));
        $this->engine = $this->fakeEngine($this->package, $this->sign($this->package, $this->keys['secret']), '2.5.0');
    }

    #[Test]
    public function a_school_installs_the_portal_from_start_to_finish(): void
    {
        file_put_contents($this->webRoot.'/index.html', 'Default hosting page');

        $this->assertRedirect($this->post('page=license', ['purchase_code' => 'edu-7kq2-m9xd-p4tr-w3hz', 'secret_key' => 'dsk_secret']), 'page=review');
        $this->assertSame(['purchase_code' => 'EDU-7KQ2-M9XD-P4TR-W3HZ', 'url' => 'heritage.sch.ng/'], $this->engine->sent('/verify')['body']);
        $this->assertArrayNotHasKey('secret_key', $_SESSION, 'The secret key is never kept.');

        $review = $this->get('page=review');
        $this->assertStringContainsString('Heritage College', $review->body);
        $this->assertStringContainsString('https://api.delwathon.com/', $review->body);
        $this->assertStringContainsString('01jk8z3v5t6m9q2w4e7r1y0u3p', $review->body);
        $this->assertStringContainsString('This website already has content', $review->body);

        $this->assertRedirect($this->post('page=review'), 'page=install');

        foreach (array_keys(Pipeline::TASKS) as $task) {
            $result = $this->task($task);
            $this->assertContains($result['status'], ['done', 'warning'], "{$task}: {$result['message']}");
        }

        $this->assertStringContainsString('Eduthon 2.5.0', file_get_contents($this->webRoot.'/index.html'));
        $this->assertFileExists($this->webRoot.'/assets/app-2.5.0.js');

        $config = json_decode(file_get_contents($this->webRoot.'/eduthon.config.json'), true);
        $this->assertSame('https://api.delwathon.com/api/', $config['api_url']);
        $this->assertSame('01jk8z3v5t6m9q2w4e7r1y0u3p', $config['tenant']);
        $this->assertSame('X-Eduthon-Tenant', $config['tenant_header']);
        $this->assertSame('01jk8z3v5t6m9q2w4e7r1y0u3p', $this->engine->sent('api/verify-install')['headers']['X-Eduthon-Tenant'], 'The health check identifies the tenant.');
        $this->assertSame('2.5.0', $config['version']);
        $this->assertStringContainsString('# BEGIN Eduthon', file_get_contents($this->webRoot.'/.htaccess'));

        $report = $this->engine->sent('installation/report')['body'];
        $this->assertSame(['frontend', '2.5.0', 'installed'], [$report['component'], $report['version'], $report['status']]);
        $this->assertSame('completed', $this->engine->sent('update_install_status')['body']['status']);

        $done = $this->get('page=done');
        $this->assertStringContainsString('Your portal is live', $done->body);
        $this->assertStringContainsString('Eduthon portal', $this->get('')->body, 'Afterwards the installer shows its overview.');
        $this->assertSame([], glob($this->installerDir.'/storage/staging/*'), 'Staging is cleaned up.');
        $this->assertSame([], glob($this->installerDir.'/storage/downloads/*.zip'), 'The download is cleaned up.');
        $this->assertCount(1, glob($this->installerDir.'/storage/backups/*.zip'), 'A backup of the previous site is kept.');
    }

    #[Test]
    public function a_tampered_package_is_rejected_before_anything_is_unpacked(): void
    {
        $tampered = $this->zip($this->portalFiles('2.5.0') + ['assets/miner.js' => 'evil()']);
        $this->engine->file('releases/1/download', file_get_contents($tampered));

        $this->startInstall();
        $this->assertSame('done', $this->task('download')['status']);

        $result = $this->task('verify');

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('Security check failed', $result['message']);
        $this->assertStringContainsString('Nothing was installed', $result['message']);
        $this->assertFileDoesNotExist($this->webRoot.'/index.html');
        $this->assertSame([], glob($this->installerDir.'/storage/downloads/*.zip'), 'The rejected package is deleted.');
        $this->assertSame('failed', $this->engine->sent('installation/report')['body']['status'], 'Delwathon is told about the failure.');

        $this->assertSame('failed', $this->task('inspect')['status'], 'Later steps refuse to run.');
    }

    #[Test]
    public function a_package_signed_with_an_unknown_key_is_rejected(): void
    {
        $attacker = $this->keyPair();
        $this->engine = $this->fakeEngine($this->package, $this->sign($this->package, $attacker['secret']), '2.5.0');

        $this->startInstall();
        $this->task('download');
        $result = $this->task('verify');

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('does not match any key this installer trusts', $result['message']);
    }

    #[Test]
    public function a_failure_after_deployment_restores_the_previous_site(): void
    {
        file_put_contents($this->webRoot.'/index.html', 'Default hosting page');
        $this->startInstall();

        foreach (['download', 'verify', 'inspect', 'deploy'] as $task) {
            $this->assertSame('done', $this->task($task)['status']);
        }

        // Simulate the web root becoming read-only for the config file.
        mkdir($this->webRoot.'/eduthon.config.json.tmp');

        $result = $this->task('configure');

        $this->assertSame('failed', $result['status']);
        $this->assertTrue($result['rolled_back']);
        $this->assertStringContainsString('restored', $result['message']);
        $this->assertSame('Default hosting page', file_get_contents($this->webRoot.'/index.html'));
        $this->assertDirectoryDoesNotExist($this->webRoot.'/assets');
    }

    #[Test]
    public function a_portal_is_never_connected_without_a_tenant(): void
    {
        $config = $this->installerConfig();
        $config['backend']['tenant'] = null;
        $this->engine->on('GET', 'installer/config', $config);
        file_put_contents($this->webRoot.'/index.html', 'Default hosting page');
        $this->startInstall();

        foreach (['download', 'verify', 'inspect', 'deploy'] as $task) {
            $this->task($task);
        }

        $result = $this->task('configure');

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('tenant ID', $result['message']);
        $this->assertSame('Default hosting page', file_get_contents($this->webRoot.'/index.html'));
    }

    #[Test]
    public function an_installed_portal_is_updated_and_old_files_removed(): void
    {
        $this->installVersion();

        $new = $this->zip($this->portalFiles('2.6.0'));
        $this->engine = $this->fakeEngine($new, $this->sign($new, $this->keys['secret']), '2.6.0');

        $manage = $this->get('');
        $this->assertStringContainsString('v2.5.0', $manage->body);

        $this->assertRedirect($this->post('page=license', ['purchase_code' => 'EDU-7KQ2-M9XD-P4TR-W3HZ', 'secret_key' => 'dsk_secret']), 'page=review');
        $this->assertStringContainsString('v2.5.0 → v2.6.0', $this->get('page=review')->body);
        $this->post('page=review');

        foreach (array_keys(Pipeline::TASKS) as $task) {
            $this->assertContains($this->task($task)['status'], ['done', 'warning']);
        }

        $this->assertFileExists($this->webRoot.'/assets/app-2.6.0.js');
        $this->assertFileDoesNotExist($this->webRoot.'/assets/app-2.5.0.js');
        $this->assertSame('2.6.0', json_decode(file_get_contents($this->webRoot.'/eduthon.config.json'), true)['version']);
        $this->assertNull($this->engine->sent('update_install_status'), 'Updates do not re-report the setup wizard.');
    }

    #[Test]
    public function an_installed_site_cannot_be_taken_over_with_another_license(): void
    {
        $this->installVersion();

        $response = $this->post('page=license', ['purchase_code' => 'EDU-AAAA-BBBB-CCCC-DDDD', 'secret_key' => 'dsk_other']);

        $this->assertRedirect($response, 'page=license');
        $this->assertStringContainsString('different purchase code', $_SESSION['_flash']['error']);
    }

    #[Test]
    public function installation_is_blocked_until_delwathon_has_prepared_the_backend(): void
    {
        $this->engine->on('GET', 'installer/config', $this->installerConfig(backendReady: false));
        $this->post('page=license', ['purchase_code' => 'EDU-7KQ2-M9XD-P4TR-W3HZ', 'secret_key' => 'dsk_secret']);

        $this->assertStringContainsString('still being prepared', $this->get('page=review')->body);
        $this->assertRedirect($this->post('page=review'), 'page=review');
    }

    #[Test]
    public function wrong_license_details_are_explained(): void
    {
        $this->engine->on('POST', 'authenticate', ['message' => 'Invalid Secret Key'], 401);

        $this->assertRedirect($this->post('page=license', ['purchase_code' => 'EDU-7KQ2-M9XD-P4TR-W3HZ', 'secret_key' => 'wrong']), 'page=license');
        $this->assertStringContainsString('secret key was not recognised', $_SESSION['_flash']['error']);
    }

    #[Test]
    public function posts_without_the_csrf_token_are_rejected(): void
    {
        $response = $this->respond(new Request(['page' => 'license'], ['purchase_code' => 'x', 'secret_key' => 'y', '_token' => 'forged'], $this->server('POST')));

        $this->assertSame(303, $response->status);
        $this->assertNull($this->engine->sent('authenticate'));
    }

    #[Test]
    public function steps_cannot_be_skipped(): void
    {
        $this->startInstall();

        $result = $this->task('deploy');

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('must run in order', $result['message']);
    }

    private function installVersion(): void
    {
        $this->startInstall();

        foreach (array_keys(Pipeline::TASKS) as $task) {
            $this->task($task);
        }

        $this->get('page=done');
    }

    private function startInstall(): void
    {
        $this->post('page=license', ['purchase_code' => 'EDU-7KQ2-M9XD-P4TR-W3HZ', 'secret_key' => 'dsk_secret']);
        $this->assertRedirect($this->post('page=review'), 'page=install');
    }

    /**
     * @return array<string, mixed>
     */
    private function task(string $name): array
    {
        $response = $this->respond(new Request(['action' => 'task', 'name' => $name], [], $this->server('POST', ['HTTP_X_CSRF_TOKEN' => 'csrf-token', 'HTTP_ACCEPT' => 'application/json'])));

        return json_decode($response->body, true);
    }

    private function get(string $query): Response
    {
        parse_str($query, $params);

        return $this->respond(new Request($params, [], $this->server('GET')));
    }

    /**
     * @param  array<string, string>  $input
     */
    private function post(string $query, array $input = []): Response
    {
        parse_str($query, $params);

        return $this->respond(new Request($params, $input + ['_token' => 'csrf-token'], $this->server('POST')));
    }

    private function respond(Request $request): Response
    {
        return (new App(Container::boot($this->installerDir, $this->engine), $request, new TestSession))->respond();
    }

    private function assertRedirect(Response $response, string $query): void
    {
        $this->assertSame(303, $response->status, $response->body);
        $this->assertStringEndsWith($query, $response->headers['Location']);
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function server(string $method, array $extra = []): array
    {
        return $extra + [
            'REQUEST_METHOD' => $method,
            'HTTPS' => 'on',
            'HTTP_HOST' => 'heritage.sch.ng',
            'SCRIPT_NAME' => '/installer/index.php',
            'SERVER_SOFTWARE' => 'Apache/2.4',
        ];
    }

    private function fakeEngine(string $package, string $signature, string $version): FakeTransport
    {
        return (new FakeTransport)
            ->on('POST', 'authenticate', ['message' => 'success', 'token' => 'tok_abc', 'user' => ['id' => 1]])
            ->on('POST', '/verify', ['message' => 'success', 'success' => 'Validated'])
            ->on('GET', 'installer/config', $this->installerConfig())
            ->on('GET', 'releases/latest', fn (string $method, string $url) => [
                'update_available' => ! str_contains($url, 'current_version='.$version),
                'release' => [
                    'version' => $version, 'channel' => 'stable', 'notes' => 'Faster report cards.', 'size' => filesize($package),
                    'sha256' => hash_file('sha256', $package), 'signature' => $signature, 'signature_algorithm' => 'ed25519',
                    'download_url' => 'https://engine.delwathon.com/api/releases/1/download?license=1&signature=abc',
                ],
            ])
            ->file('releases/1/download', file_get_contents($package))
            ->on('POST', 'installation/report', ['success' => 'Report received'])
            ->on('POST', 'update_install_status', ['success' => 'Status updated successfully'])
            ->on('GET', 'heritage.sch.ng/eduthon.config.json', fn () => new TransportResponse(200, (string) file_get_contents($this->webRoot.'/eduthon.config.json')))
            ->on('GET', 'api/verify-install', ['status' => 'Installed']);
    }

    /**
     * @return array<string, mixed>
     */
    private function installerConfig(bool $backendReady = true): array
    {
        return [
            'config_version' => 1,
            'license' => ['purchase_code' => 'EDU-7KQ2-M9XD-P4TR-W3HZ', 'status' => 'active'],
            'school' => ['name' => 'Heritage College', 'hosting' => 'self_hosted'],
            'servers' => ['backend_parent_url' => 'https://api.delwathon.com', 'frontend_parent_url' => 'https://eduthon.ng', 'engine_url' => 'https://engine.delwathon.com/api/'],
            'backend' => $backendReady
                ? ['ready' => true, 'tenant' => '01jk8z3v5t6m9q2w4e7r1y0u3p', 'tenant_header' => 'X-Eduthon-Tenant', 'dedicated' => false, 'url' => 'https://api.delwathon.com/', 'api_url' => 'https://api.delwathon.com/api/', 'health_url' => 'https://api.delwathon.com/api/verify-install']
                : ['ready' => false, 'tenant' => null, 'url' => null, 'api_url' => null, 'health_url' => null],
            'releases' => ['channel' => 'stable', 'frontend' => '2.5.0'],
            'support' => ['company' => 'Delwathon IT Solutions', 'email' => 'support@delwathon.com'],
        ];
    }

    private function copyInstaller(string $from, string $to): void
    {
        mkdir($to, 0755, true);

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $item) {
            $destination = $to.'/'.substr($item->getPathname(), strlen($from) + 1);

            if (str_contains($destination, '/storage/') && ! in_array(basename($destination), ['.htaccess', 'index.html'], true)) {
                continue;
            }

            $item->isDir() ? @mkdir($destination, 0755, true) : copy($item->getPathname(), $destination);
        }
    }
}
