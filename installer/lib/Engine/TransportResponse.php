<?php

namespace Eduthon\Installer\Engine;

final class TransportResponse
{
    /**
     * @param  array<string, string>  $headers  Lower-cased header names.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : null;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
