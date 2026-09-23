<?php

namespace Eduthon\Installer\Installation;

use Eduthon\Installer\Checks\HealthCheck;
use Eduthon\Installer\Deploy\DeployException;
use Eduthon\Installer\Deploy\Deployer;
use Eduthon\Installer\Deploy\RuntimeConfig;
use Eduthon\Installer\Engine\EngineClient;
use Eduthon\Installer\Engine\EngineException;
use Eduthon\Installer\Package\Extractor;
use Eduthon\Installer\Package\PackageException;
use Eduthon\Installer\Package\PackageInspector;
use Eduthon\Installer\Security\SignatureVerifier;
use Eduthon\Installer\Security\VerificationException;
use Eduthon\Installer\Support\Config;
use Eduthon\Installer\Support\Filesystem;
use Eduthon\Installer\Support\Log;
use Eduthon\Installer\Support\Paths;
use Throwable;

/**
 * The ordered steps that take a signed release from Delwathon to a live
 * portal. Each step runs in its own request so shared-hosting time limits
 * are never hit, and the browser can show progress as it goes.
 */
final class Pipeline
{
    /**
     * @var array<string, string>
     */
    public const TASKS = [
        'download' => 'Download the portal package',
        'verify' => 'Verify the Delwathon signature',
        'inspect' => 'Check and unpack the package',
        'deploy' => 'Deploy to your website',
        'configure' => 'Connect the portal to your school',
        'check' => 'Test the installation',
        'report' => 'Register the installation with Delwathon',
    ];

    public const LAST_TASK = 'report';

    /**
     * Steps after which the web root has changed and must be restored on failure.
     */
    private const CHANGES_SITE = ['deploy', 'configure', 'check'];

    public function __construct(
        private Config $config,
        private Paths $paths,
        private SignatureVerifier $verifier,
        private PackageInspector $inspector,
        private Extractor $extractor,
        private Deployer $deployer,
        private RuntimeConfig $runtime,
        private HealthCheck $health,
        private InstallState $state,
        private Log $log,
    ) {}

    /**
     * @return array{status: string, message: string}
     *
     * @throws TaskFailed
     */
    public function run(Run $run, string $task, EngineClient $engine): array
    {
        if (! array_key_exists($task, self::TASKS)) {
            throw new TaskFailed('Unknown installation step.');
        }

        $this->assertInOrder($run, $task);

        $lock = fopen($this->paths->storage('run.lock'), 'c');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new TaskFailed('Another installation step is already running. Wait for it to finish.');
        }

        @set_time_limit(max(120, $this->config->int('download_timeout', 600) + 60));

        try {
            $result = match ($task) {
                'download' => $this->download($run, $engine),
                'verify' => $this->verify($run),
                'inspect' => $this->inspect($run),
                'deploy' => $this->deploy($run),
                'configure' => $this->configure($run),
                'check' => $this->check($run),
                'report' => $this->report($run, $engine),
            };

            $run->mark($task, $result['status'], $result['message']);
            $this->log->info("{$task}: {$result['message']}", ['version' => $run->release('version')]);

            return $result;
        } catch (Throwable $exception) {
            throw $this->fail($run, $task, $exception, $engine);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function assertInOrder(Run $run, string $task): void
    {
        foreach (array_keys(self::TASKS) as $name) {
            if ($name === $task) {
                return;
            }

            if (! in_array($run->taskStatus($name), ['done', 'warning'], true)) {
                throw new TaskFailed('Steps must run in order. Reload the page to continue where you left off.');
            }
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function download(Run $run, EngineClient $engine): array
    {
        Filesystem::ensureDirectory($this->paths->storage('downloads'));
        $file = $this->packageFile($run);
        @unlink($file);

        try {
            $engine->download((string) $run->release('download_url'), $file, $this->config->int('max_package_bytes', 300 * 1024 * 1024), $this->config->int('download_timeout', 600));
        } catch (EngineException $exception) {
            if ($exception->status !== 403) {
                throw $exception;
            }

            // Download links expire after a few minutes; ask for a fresh one once.
            $release = $engine->latestRelease($this->config->get('component'), $run->config('releases.channel'), null);

            if (($release['version'] ?? null) !== $run->release('version')) {
                throw new EngineException('The release changed while you were installing. Start again to install the newest version.');
            }

            $engine->download((string) $release['download_url'], $file, $this->config->int('max_package_bytes', 300 * 1024 * 1024), $this->config->int('download_timeout', 600));
        }

        return ['status' => 'done', 'message' => 'Downloaded '.human_bytes((int) filesize($file)).'.'];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function verify(Run $run): array
    {
        $file = $this->packageFile($run);

        try {
            $digest = $this->verifier->verify($file, (string) $run->release('sha256'), (string) $run->release('signature'), (int) $run->release('size') ?: null);
        } catch (VerificationException $exception) {
            @unlink($file);

            throw $exception;
        }

        $run->set('verified_sha256', $digest);

        return ['status' => 'done', 'message' => 'Signature and checksum verified. The package is authentic.'];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function inspect(Run $run): array
    {
        $file = $this->packageFile($run);

        // Re-check the digest: nothing unverified is ever unpacked.
        if (! hash_equals((string) $run->get('verified_sha256'), hash_file('sha256', $file))) {
            throw new VerificationException('The package changed after it was verified and has been rejected.');
        }

        $plan = $this->inspector->inspect($file);
        $files = $this->extractor->extract($file, $plan, $this->stagingDir($run));
        $run->set('files', $files);

        return ['status' => 'done', 'message' => sprintf('%s files (%s) checked and unpacked.', number_format(count($files)), human_bytes($plan->totalBytes))];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function deploy(Run $run): array
    {
        $backup = $this->paths->storage('backups/'.gmdate('Ymd-His').'-before-'.$run->release('version').'.zip');
        $run->set('backup', $backup);
        $run->set('deployed', true);

        $this->deployer->deploy($this->stagingDir($run), (array) $run->get('files'), $this->state->files(), $backup);

        return ['status' => 'done', 'message' => 'The new portal files are in place. The previous site was backed up.'];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function configure(Run $run): array
    {
        $htaccess = $this->paths->target.'/.htaccess';
        $run->set('previous_runtime', [
            'config' => $this->runtime->readConfig(),
            'htaccess' => is_file($htaccess) ? (string) file_get_contents($htaccess) : null,
        ]);

        $apiUrl = $run->config('backend.api_url');

        if (! is_string($apiUrl) || $apiUrl === '') {
            throw new DeployException('Delwathon has not assigned a backend to this school yet, so the portal cannot be connected.');
        }

        $tenant = $run->config('backend.tenant');

        if (! is_string($tenant) || $tenant === '') {
            throw new DeployException('Delwathon did not provide a tenant ID for this school, so the portal cannot be connected.');
        }

        $this->runtime->writeConfig([
            'config_version' => 2,
            'version' => $run->release('version'),
            'school' => $run->config('school.name'),
            'tenant' => $tenant,
            'tenant_header' => $run->config('backend.tenant_header') ?? 'X-Eduthon-Tenant',
            'api_url' => $apiUrl,
            'backend_url' => $run->config('backend.url'),
            'portal_url' => $run->get('portal_url'),
            'engine_url' => $run->config('servers.engine_url'),
            'installed_at' => gmdate('c'),
        ]);

        $this->runtime->writeHtaccess((string) parse_url((string) $run->get('portal_url'), PHP_URL_PATH));

        return ['status' => 'done', 'message' => 'The portal will use '.$apiUrl];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function check(Run $run): array
    {
        $mismatched = $this->health->mismatchedFiles((array) $run->get('files'));

        if ($mismatched !== []) {
            throw new DeployException(count($mismatched).' deployed file(s) do not match the verified package, for example '.$mismatched[0].'.');
        }

        $warnings = [];
        $portal = $this->health->portal((string) $run->get('portal_url'), (string) $run->release('version'));
        $backend = $this->health->backend($run->config('backend.health_url'), [
            (string) ($run->config('backend.tenant_header') ?? 'X-Eduthon-Tenant') => (string) $run->config('backend.tenant'),
        ]);

        foreach ([$portal, $backend] as $result) {
            if (! $result['ok']) {
                $warnings[] = $result['message'];
            }
        }

        $run->set('warnings', $warnings);

        return $warnings === []
            ? ['status' => 'done', 'message' => 'Every file matches the signed package, the portal is live and the backend is responding.']
            : ['status' => 'warning', 'message' => 'Every file matches the signed package. '.implode(' ', $warnings)];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function report(Run $run, EngineClient $engine): array
    {
        $this->state->record([
            'version' => $run->release('version'),
            'channel' => $run->config('releases.channel'),
            'purchase_code' => $run->get('purchase_code'),
            'school' => $run->config('school.name'),
            'portal_url' => $run->get('portal_url'),
            'backend_api_url' => $run->config('backend.api_url'),
            'tenant' => $run->config('backend.tenant'),
            'files' => $run->get('files'),
        ], $run->get('mode') === 'update' ? 'updated' : 'installed');

        $this->cleanUp($run);

        $warnings = (array) $run->get('warnings', []);

        try {
            $engine->report((string) $this->config->get('component'), (string) $run->release('version'), true, $warnings === [] ? null : implode(' ', $warnings), (string) $run->get('portal_url'));

            if ($run->get('mode') === 'install') {
                $engine->installStatus((string) $run->get('purchase_code'), 'completed');
            }
        } catch (EngineException $exception) {
            return ['status' => 'warning', 'message' => 'Installed, but Delwathon could not be notified: '.$exception->getMessage()];
        }

        return ['status' => 'done', 'message' => 'Delwathon has recorded version '.$run->release('version').' for your school.'];
    }

    private function fail(Run $run, string $task, Throwable $exception, EngineClient $engine): TaskFailed
    {
        $message = match (true) {
            $exception instanceof TaskFailed => $exception->getMessage(),
            $exception instanceof VerificationException => 'Security check failed: '.$exception->getMessage().' Nothing was installed.',
            $exception instanceof PackageException => 'The package was rejected: '.$exception->getMessage().' Nothing was installed.',
            $exception instanceof EngineException, $exception instanceof DeployException => $exception->getMessage(),
            default => 'Something went wrong: '.$exception->getMessage(),
        };

        $rolledBack = false;

        if (in_array($task, self::CHANGES_SITE, true) && $run->get('deployed')) {
            $rolledBack = $this->rollback($run);
            $message .= $rolledBack ? ' Your site has been restored to how it was before.' : ' The automatic restore also failed; contact Delwathon support.';
        }

        $run->mark($task, 'failed', $message);
        $this->log->error("{$task} failed: {$exception->getMessage()}", ['version' => $run->release('version'), 'exception' => $exception::class]);

        try {
            $engine->report((string) $this->config->get('component'), (string) ($run->release('version') ?? 'unknown'), false, "{$task}: {$message}", (string) $run->get('portal_url'));
        } catch (Throwable) {
            // Reporting is best effort; the local log keeps the details.
        }

        return new TaskFailed($message, $rolledBack, $exception instanceof EngineException ? $exception->detail : null);
    }

    private function rollback(Run $run): bool
    {
        try {
            $this->deployer->rollback((string) $run->get('backup'));

            $previous = (array) $run->get('previous_runtime', []);

            if (array_key_exists('config', $previous)) {
                $previous['config'] === [] ? @unlink($this->paths->target.'/'.RuntimeConfig::CONFIG_FILE) : $this->runtime->writeConfig($previous['config']);
            }

            if (array_key_exists('htaccess', $previous)) {
                $previous['htaccess'] === null ? @unlink($this->paths->target.'/.htaccess') : file_put_contents($this->paths->target.'/.htaccess', $previous['htaccess']);
            }

            $run->set('deployed', false);
            $this->log->info('Rolled back to the previous site.');

            return true;
        } catch (Throwable $exception) {
            $this->log->error('Rollback failed: '.$exception->getMessage());

            return false;
        }
    }

    private function cleanUp(Run $run): void
    {
        @unlink($this->packageFile($run));
        Filesystem::deleteDirectory($this->stagingDir($run));

        $backups = glob($this->paths->storage('backups/*.zip')) ?: [];
        rsort($backups);

        foreach (array_slice($backups, max(1, $this->config->int('keep_backups', 3))) as $old) {
            @unlink($old);
        }
    }

    private function packageFile(Run $run): string
    {
        return $this->paths->storage('downloads/'.$run->get('id').'.zip');
    }

    private function stagingDir(Run $run): string
    {
        return $this->paths->storage('staging/'.$run->get('id'));
    }
}
