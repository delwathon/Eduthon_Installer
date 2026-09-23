<?php

namespace Eduthon\Installer\Engine;

interface Transport
{
    /**
     * Send an HTTP request. When $sink is given the response body is streamed
     * to that file (never exceeding $maxBytes) instead of being returned.
     *
     * @param  array<string, string>  $headers
     *
     * @throws TransportException when the server cannot be reached.
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null, ?string $sink = null, int $maxBytes = 0, int $timeout = 30): TransportResponse;
}
