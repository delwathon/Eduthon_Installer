<?php

namespace Tests\Support;

use Eduthon\Installer\Engine\Transport;
use Eduthon\Installer\Engine\TransportException;
use Eduthon\Installer\Engine\TransportResponse;

/**
 * Stands in for the network. Routes are matched by "METHOD path-substring".
 */
final class FakeTransport implements Transport
{
    /** @var array<string, callable(string, string, ?array): (TransportResponse|string)> */
    private array $routes = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?array}> */
    public array $requests = [];

    public function on(string $method, string $path, callable|array|TransportResponse $response, int $status = 200): self
    {
        $this->routes[strtoupper($method).' '.$path] = is_callable($response)
            ? $response
            : static fn () => $response instanceof TransportResponse ? $response : new TransportResponse($status, (string) json_encode($response));

        return $this;
    }

    /**
     * Serve a file download (the body is written to the sink).
     */
    public function file(string $path, string $contents, int $status = 200): self
    {
        $this->routes['GET '.$path] = static fn () => ['file' => $contents, 'status' => $status];

        return $this;
    }

    public function unreachable(string $path): self
    {
        $this->routes['* '.$path] = static fn () => throw new TransportException('Connection refused');

        return $this;
    }

    public function send(string $method, string $url, array $headers = [], ?string $body = null, ?string $sink = null, int $maxBytes = 0, int $timeout = 30): TransportResponse
    {
        $decoded = $body === null ? null : json_decode($body, true);
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $decoded];

        foreach ($this->routes as $key => $handler) {
            [$routeMethod, $path] = explode(' ', $key, 2);

            if (($routeMethod === '*' || $routeMethod === $method) && str_contains($url, $path)) {
                $result = $handler($method, $url, $decoded);

                if (is_array($result) && isset($result['file'])) {
                    if ($maxBytes > 0 && strlen($result['file']) > $maxBytes) {
                        throw new TransportException('The download is larger than allowed and was stopped.');
                    }

                    file_put_contents((string) $sink, $result['file']);

                    return new TransportResponse($result['status'], '');
                }

                return $result instanceof TransportResponse ? $result : new TransportResponse(200, (string) json_encode($result));
            }
        }

        return new TransportResponse(404, '{"message":"Not found"}');
    }

    public function sent(string $path): ?array
    {
        foreach (array_reverse($this->requests) as $request) {
            if (str_contains($request['url'], $path)) {
                return $request;
            }
        }

        return null;
    }
}
