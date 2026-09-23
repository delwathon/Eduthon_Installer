<?php

namespace Eduthon\Installer\Support;

use Eduthon\Installer\Checks\HealthCheck;
use Eduthon\Installer\Checks\Requirements;
use Eduthon\Installer\Deploy\Deployer;
use Eduthon\Installer\Deploy\RuntimeConfig;
use Eduthon\Installer\Engine\CurlTransport;
use Eduthon\Installer\Engine\EngineClient;
use Eduthon\Installer\Engine\Transport;
use Eduthon\Installer\Installation\InstallState;
use Eduthon\Installer\Installation\Pipeline;
use Eduthon\Installer\Package\Extractor;
use Eduthon\Installer\Package\PackageInspector;
use Eduthon\Installer\Security\SignatureVerifier;

/**
 * Builds the installer's services from configuration.
 */
final class Container
{
    public readonly string $version;

    public readonly Paths $paths;

    public readonly Store $store;

    public readonly Log $log;

    public readonly InstallState $state;

    public readonly SignatureVerifier $verifier;

    public readonly Deployer $deployer;

    public readonly Requirements $requirements;

    public function __construct(
        public readonly Config $config,
        string $installerDir,
        public readonly Transport $transport,
    ) {
        require_once __DIR__.'/helpers.php';

        $this->version = (string) require $installerDir.'/version.php';
        $this->paths = Paths::resolve($installerDir, $config);
        Filesystem::ensureDirectory($this->paths->storage);
        $this->store = new Store($this->paths->storage);
        $this->log = new Log($this->paths->storage('install-log.php'));
        $this->state = new InstallState($this->store);
        $this->verifier = new SignatureVerifier($config->trustedKeys());
        $this->deployer = new Deployer($this->paths->target, $this->paths->installerRelative());
        $this->requirements = new Requirements($this->paths);
    }

    public static function boot(string $installerDir, ?Transport $transport = null): self
    {
        require_once __DIR__.'/helpers.php';

        $version = (string) require $installerDir.'/version.php';

        return new self(Config::load($installerDir), $installerDir, $transport ?? new CurlTransport('EduthonInstaller/'.$version.' (+https://delwathon.com)'));
    }

    public function engine(?string $token = null): EngineClient
    {
        return (new EngineClient($this->transport, $this->config->engineUrl(), $this->config->int('timeout', 30)))->withToken($token);
    }

    public function pipeline(): Pipeline
    {
        return new Pipeline(
            $this->config,
            $this->paths,
            $this->verifier,
            new PackageInspector($this->config->int('max_files', 20000), $this->config->int('max_unpacked_bytes', 1024 * 1024 * 1024), $this->paths->installerRelative()),
            new Extractor,
            $this->deployer,
            $this->runtime(),
            new HealthCheck($this->transport, $this->paths->target),
            $this->state,
            $this->log,
        );
    }

    public function runtime(): RuntimeConfig
    {
        return new RuntimeConfig($this->paths->target, $this->paths->installerRelative(), $this->paths->storage);
    }
}
