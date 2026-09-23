<?php

namespace Eduthon\Installer\Engine;

/**
 * cURL transport with certificate verification always on and HTTPS-only
 * redirects for downloads.
 */
final class CurlTransport implements Transport
{
    public function __construct(private string $userAgent) {}

    public function send(string $method, string $url, array $headers = [], ?string $body = null, ?string $sink = null, int $maxBytes = 0, int $timeout = 30): TransportResponse
    {
        $handle = curl_init($url);
        $responseHeaders = [];
        $file = null;
        $received = 0;
        $tooLarge = false;

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => array_map(static fn ($name, $value) => "{$name}: {$value}", array_keys($headers), $headers),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => $sink !== null,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }

                return strlen($line);
            },
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        if ($sink !== null) {
            $file = fopen($sink, 'wb');

            if ($file === false) {
                throw new TransportException('Could not create the download file.');
            }

            $options[CURLOPT_WRITEFUNCTION] = static function ($curl, string $chunk) use (&$file, &$received, &$tooLarge, $maxBytes): int {
                $received += strlen($chunk);

                if ($maxBytes > 0 && $received > $maxBytes) {
                    $tooLarge = true;

                    return 0;
                }

                return fwrite($file, $chunk);
            };
        } else {
            $options[CURLOPT_RETURNTRANSFER] = true;
        }

        curl_setopt_array($handle, $options);
        $result = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($file !== null) {
            fclose($file);
        }

        if ($tooLarge) {
            throw new TransportException('The download is larger than allowed and was stopped.');
        }

        if ($result === false) {
            throw new TransportException($error !== '' ? $error : 'The request failed.');
        }

        return new TransportResponse($status, $sink === null ? (string) $result : '', $responseHeaders);
    }
}
