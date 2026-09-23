<?php

namespace Tests;

use Eduthon\Installer\Security\SignatureVerifier;
use Eduthon\Installer\Security\VerificationException;
use PHPUnit\Framework\Attributes\Test;

final class SignatureVerifierTest extends TestCase
{
    #[Test]
    public function a_package_signed_by_a_trusted_key_is_accepted(): void
    {
        $keys = $this->keyPair();
        $file = $this->zip($this->portalFiles());

        $digest = (new SignatureVerifier([$keys['public']]))->verify($file, hash_file('sha256', $file), $this->sign($file, $keys['secret']), filesize($file));

        $this->assertSame(hash_file('sha256', $file), $digest);
    }

    #[Test]
    public function any_of_several_trusted_keys_may_sign_during_key_rotation(): void
    {
        $old = $this->keyPair();
        $new = $this->keyPair();
        $file = $this->zip($this->portalFiles());

        (new SignatureVerifier([$old['public'], $new['public']]))->verify($file, hash_file('sha256', $file), $this->sign($file, $new['secret']));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function a_signature_from_an_untrusted_key_is_rejected(): void
    {
        $trusted = $this->keyPair();
        $attacker = $this->keyPair();
        $file = $this->zip($this->portalFiles());

        $this->expectException(VerificationException::class);
        $this->expectExceptionMessage('does not match any key this installer trusts');

        (new SignatureVerifier([$trusted['public']]))->verify($file, hash_file('sha256', $file), $this->sign($file, $attacker['secret']));
    }

    #[Test]
    public function a_file_altered_after_signing_is_rejected_even_with_a_matching_published_checksum(): void
    {
        $keys = $this->keyPair();
        $file = $this->zip($this->portalFiles());
        $signature = $this->sign($file, $keys['secret']);

        file_put_contents($file, 'tampered', FILE_APPEND);

        $this->expectException(VerificationException::class);

        (new SignatureVerifier([$keys['public']]))->verify($file, hash_file('sha256', $file), $signature);
    }

    #[Test]
    public function a_checksum_mismatch_is_rejected(): void
    {
        $keys = $this->keyPair();
        $file = $this->zip($this->portalFiles());

        $this->expectExceptionMessage('checksum does not match');

        (new SignatureVerifier([$keys['public']]))->verify($file, str_repeat('a', 64), $this->sign($file, $keys['secret']));
    }

    #[Test]
    public function an_incomplete_download_is_rejected(): void
    {
        $keys = $this->keyPair();
        $file = $this->zip($this->portalFiles());

        $this->expectExceptionMessage('incomplete or altered');

        (new SignatureVerifier([$keys['public']]))->verify($file, hash_file('sha256', $file), $this->sign($file, $keys['secret']), filesize($file) + 10);
    }

    #[Test]
    public function an_installer_without_trusted_keys_refuses_every_package(): void
    {
        $file = $this->zip($this->portalFiles());
        $verifier = new SignatureVerifier(['@trusted-keys@', 'not-base64!']);

        $this->assertFalse($verifier->hasTrustedKeys());
        $this->expectExceptionMessage('no trusted signing keys');

        $verifier->verify($file, hash_file('sha256', $file), base64_encode(str_repeat('x', 64)));
    }
}
