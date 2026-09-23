<?php

namespace Eduthon\Installer\Http;

final class Response
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {}

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self((string) json_encode($data, JSON_UNESCAPED_SLASHES), $status, ['Content-Type' => 'application/json']);
    }

    public static function redirect(string $url): self
    {
        return new self('', 303, ['Location' => $url]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        $headers = $this->headers + [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'",
        ];

        foreach ($headers as $name => $value) {
            header($name.': '.$value);
        }

        echo $this->body;
    }
}
