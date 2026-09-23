<?php

namespace Eduthon\Installer\Security;

/**
 * Confirms a downloaded package is exactly the file Delwathon signed.
 *
 * Delwathon signs the package's SHA-256 digest (hex) with Ed25519. The
 * installer trusts only the public keys compiled into it; keys served by
 * the network are never trusted on their own.
 */
final class SignatureVerifier
{
    /**
     * @param  list<string>  $trustedKeys  Base64 Ed25519 public keys.
     */
    public function __construct(private array $trustedKeys) {}

    public function hasTrustedKeys(): bool
    {
        return $this->decodedKeys() !== [];
    }

    /**
     * @return string The verified SHA-256 digest.
     *
     * @throws VerificationException
     */
    public function verify(string $file, string $expectedSha256, string $signature, ?int $expectedSize = null): string
    {
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new VerificationException('This server cannot verify package signatures because the PHP sodium extension is missing.');
        }

        $keys = $this->decodedKeys();

        if ($keys === []) {
            throw new VerificationException('This installer has no trusted signing keys, so it cannot verify packages. Download a fresh installer from Delwathon.');
        }

        if (! is_file($file)) {
            throw new VerificationException('The downloaded package is missing.');
        }

        $size = filesize($file);

        if ($expectedSize !== null && $size !== $expectedSize) {
            throw new VerificationException(sprintf('The package is %s bytes but Delwathon published %s bytes. The download was incomplete or altered.', number_format((int) $size), number_format($expectedSize)));
        }

        $digest = hash_file('sha256', $file);

        if (! preg_match('/^[a-f0-9]{64}$/', strtolower($expectedSha256)) || ! hash_equals(strtolower($expectedSha256), $digest)) {
            throw new VerificationException('The package checksum does not match the one Delwathon published. The file may have been corrupted or tampered with.');
        }

        $decoded = base64_decode($signature, true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new VerificationException('The package signature is malformed.');
        }

        foreach ($keys as $key) {
            if (sodium_crypto_sign_verify_detached($decoded, $digest, $key)) {
                return $digest;
            }
        }

        throw new VerificationException('The package signature does not match any key this installer trusts. The package was not produced by Delwathon and has been rejected.');
    }

    /**
     * @return list<string>
     */
    private function decodedKeys(): array
    {
        $keys = [];

        foreach ($this->trustedKeys as $key) {
            $binary = base64_decode(trim($key), true);

            if ($binary !== false && defined('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES') && strlen($binary) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                $keys[] = $binary;
            }
        }

        return $keys;
    }
}
