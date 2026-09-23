<?php

namespace Eduthon\Installer\Engine;

/**
 * Client for the Delwathon Admin installation API.
 */
final class EngineClient
{
    private ?string $token = null;

    public function __construct(
        private Transport $transport,
        private string $baseUrl,
        private int $timeout = 30,
    ) {
        if (! str_starts_with($this->baseUrl, 'https://') && ! str_starts_with($this->baseUrl, 'http://localhost') && ! str_starts_with($this->baseUrl, 'http://127.0.0.1')) {
            throw new EngineException('The installer is configured with an insecure Delwathon address. It must use HTTPS.');
        }
    }

    public function withToken(?string $token): self
    {
        $client = clone $this;
        $client->token = $token;

        return $client;
    }

    public function host(): string
    {
        return (string) parse_url($this->baseUrl, PHP_URL_HOST);
    }

    /**
     * @return array{token: string, expires_at: ?string, user: array<string, mixed>}
     */
    public function authenticate(string $secretKey): array
    {
        $data = $this->call('POST', 'authenticate', ['secret_key' => $secretKey], authenticated: false);

        if (! is_string($data['token'] ?? null)) {
            throw new EngineException('Delwathon did not return an access token. Please try again.');
        }

        return ['token' => $data['token'], 'expires_at' => $data['expires_at'] ?? null, 'user' => (array) ($data['user'] ?? [])];
    }

    public function verify(string $purchaseCode, string $url): string
    {
        $data = $this->call('POST', 'verify', ['purchase_code' => $purchaseCode, 'url' => $url]);

        return (string) ($data['success'] ?? 'Validated');
    }

    /**
     * @return array<string, mixed>
     */
    public function installerConfig(): array
    {
        return $this->call('GET', 'installer/config');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestRelease(string $component, ?string $channel, ?string $currentVersion): ?array
    {
        $query = http_build_query(array_filter([
            'component' => $component,
            'channel' => $channel,
            'current_version' => $currentVersion,
        ]));

        $data = $this->call('GET', 'releases/latest?'.$query);

        return is_array($data['release'] ?? null) ? $data['release'] + ['update_available' => (bool) ($data['update_available'] ?? false)] : null;
    }

    public function download(string $url, string $destination, int $maxBytes, int $timeout): void
    {
        if (! str_starts_with($url, 'https://') && ! str_starts_with($url, $this->baseUrl)) {
            throw new EngineException('Refusing to download the package over an insecure connection.');
        }

        try {
            $response = $this->transport->send('GET', $url, ['Accept' => 'application/zip'], null, $destination, $maxBytes, $timeout);
        } catch (TransportException $exception) {
            @unlink($destination);

            throw new EngineException('The package download did not complete: '.$exception->getMessage());
        }

        if (! $response->successful()) {
            @unlink($destination);

            throw new EngineException($response->status === 403
                ? 'The download link has expired or is not valid for this license. Start the installation again.'
                : "The package could not be downloaded (HTTP {$response->status}).", $response->status);
        }
    }

    public function report(string $component, string $version, bool $installed, ?string $message, string $portalUrl): void
    {
        $this->call('POST', 'installation/report', [
            'component' => $component,
            'version' => $version,
            'status' => $installed ? 'installed' : 'failed',
            'message' => $message === null ? null : mb_substr($message, 0, 1000),
            'portal_url' => $portalUrl,
        ]);
    }

    public function installStatus(string $purchaseCode, string $status): void
    {
        $this->call('POST', 'update_install_status', ['purchase_code' => $purchaseCode, 'status' => $status], authenticated: false);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, ?array $payload = null, bool $authenticated = true): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        if ($authenticated) {
            if ($this->token === null) {
                throw new EngineException('Your session with Delwathon has expired. Enter your license details again.', 401);
            }

            $headers['Authorization'] = 'Bearer '.$this->token;
        }

        try {
            $response = $this->transport->send($method, $this->baseUrl.$path, $headers, $payload === null ? null : json_encode($payload), timeout: $this->timeout);
        } catch (TransportException $exception) {
            throw new EngineException(
                "This server could not reach Delwathon at {$this->host()}. Check that it can make outbound HTTPS connections, then try again.",
                0,
                $exception->getMessage(),
            );
        }

        $data = $response->json();

        if ($response->successful() && $data !== null) {
            return $data;
        }

        $message = is_string($data['message'] ?? null) ? $data['message'] : null;

        throw new EngineException(match (true) {
            $response->status === 401 && $path === 'authenticate' => 'That secret key was not recognised. Check it against the email from Delwathon.',
            $response->status === 401 => $message ?? 'Delwathon could not confirm these license details.',
            $response->status === 403 => $message ?? 'This license is not allowed to do that.',
            $response->status === 422 => $message ?? 'Delwathon rejected the request. Check the details you entered.',
            $response->status === 429 => 'Too many attempts. Wait a minute and try again.',
            $response->status >= 500 => 'Delwathon is having trouble right now. Please try again in a few minutes.',
            default => $message ?? "Unexpected response from Delwathon (HTTP {$response->status}).",
        }, $response->status, $response->body === '' ? null : mb_substr($response->body, 0, 500));
    }
}
