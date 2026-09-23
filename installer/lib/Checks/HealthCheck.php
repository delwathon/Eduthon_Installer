<?php

namespace Eduthon\Installer\Checks;

use Eduthon\Installer\Engine\Transport;
use Eduthon\Installer\Engine\TransportException;

/**
 * Confirms a deployment works: files on disk match the verified package,
 * the portal answers over HTTP and the school's backend is reachable.
 */
final class HealthCheck
{
    public function __construct(private Transport $transport, private string $target) {}

    /**
     * @param  array<string, array{size: int, sha256: string}>  $files
     * @return list<string> Paths whose contents do not match.
     */
    public function mismatchedFiles(array $files): array
    {
        $bad = [];

        foreach ($files as $path => $meta) {
            $file = $this->target.'/'.$path;

            if (! is_file($file) || filesize($file) !== $meta['size'] || ! hash_equals($meta['sha256'], hash_file('sha256', $file))) {
                $bad[] = $path;
            }
        }

        return $bad;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function portal(string $portalUrl, string $version): array
    {
        try {
            $response = $this->transport->send('GET', rtrim($portalUrl, '/').'/eduthon.config.json?check='.time(), ['Accept' => 'application/json'], timeout: 15);
        } catch (TransportException $exception) {
            return ['ok' => false, 'message' => 'This server could not load its own portal over the internet ('.$exception->getMessage().'). Many hosts block such requests; open the portal in your browser to confirm it works.'];
        }

        $data = $response->json();

        if ($response->successful() && ($data['version'] ?? null) === $version) {
            return ['ok' => true, 'message' => 'The portal is live and serving version '.$version.'.'];
        }

        return ['ok' => false, 'message' => "The portal answered with HTTP {$response->status} instead of the new configuration. A cache or CDN in front of the site may need purging."];
    }

    /**
     * @param  array<string, string>  $tenantHeaders  Identify the school to the multi-tenant backend.
     * @return array{ok: bool, message: string}
     */
    public function backend(?string $healthUrl, array $tenantHeaders = []): array
    {
        if ($healthUrl === null) {
            return ['ok' => false, 'message' => 'Delwathon has not assigned a backend to this school yet.'];
        }

        try {
            $response = $this->transport->send('GET', $healthUrl, ['Accept' => 'application/json'] + $tenantHeaders, timeout: 20);
        } catch (TransportException $exception) {
            return ['ok' => false, 'message' => 'The Eduthon backend could not be reached from this server: '.$exception->getMessage()];
        }

        return $response->successful()
            ? ['ok' => true, 'message' => 'The Eduthon backend is responding.']
            : ['ok' => false, 'message' => "The Eduthon backend answered with HTTP {$response->status}."];
    }
}
