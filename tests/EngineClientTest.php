<?php

namespace Tests;

use Eduthon\Installer\Engine\EngineClient;
use Eduthon\Installer\Engine\EngineException;
use Eduthon\Installer\Engine\TransportResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeTransport;

final class EngineClientTest extends TestCase
{
    #[Test]
    public function the_engine_address_must_use_https(): void
    {
        $this->expectExceptionMessage('must use HTTPS');

        new EngineClient(new FakeTransport, 'http://engine.delwathon.com/api/');
    }

    #[Test]
    public function authentication_sends_the_secret_once_and_returns_a_token(): void
    {
        $transport = (new FakeTransport)->on('POST', 'authenticate', ['message' => 'success', 'token' => 'tok_123', 'user' => ['id' => 4]]);

        $result = (new EngineClient($transport, 'https://engine.delwathon.com/api/'))->authenticate('dsk_secret');

        $this->assertSame('tok_123', $result['token']);
        $this->assertSame(['secret_key' => 'dsk_secret'], $transport->sent('authenticate')['body']);
        $this->assertArrayNotHasKey('Authorization', $transport->sent('authenticate')['headers']);
    }

    #[Test]
    public function later_calls_carry_the_bearer_token(): void
    {
        $transport = (new FakeTransport)->on('GET', 'installer/config', ['config_version' => 1]);

        (new EngineClient($transport, 'https://engine.delwathon.com/api/'))->withToken('tok_123')->installerConfig();

        $this->assertSame('Bearer tok_123', $transport->sent('installer/config')['headers']['Authorization']);
    }

    #[Test]
    public function errors_are_translated_into_plain_language(): void
    {
        $client = fn (FakeTransport $transport) => (new EngineClient($transport, 'https://engine.delwathon.com/api/'))->withToken('t');

        $cases = [
            [(new FakeTransport)->on('POST', 'authenticate', ['message' => 'Invalid Secret Key'], 401), fn ($c) => $c->authenticate('x'), 'secret key was not recognised'],
            [(new FakeTransport)->on('POST', 'verify', ['message' => 'This purchase code is already in use by another installation.'], 403), fn ($c) => $c->verify('EDU', 'x.com/'), 'already in use'],
            [(new FakeTransport)->on('GET', 'installer/config', new TransportResponse(503, 'down')), fn ($c) => $c->installerConfig(), 'having trouble'],
            [(new FakeTransport)->on('GET', 'installer/config', ['message' => 'slow down'], 429), fn ($c) => $c->installerConfig(), 'Too many attempts'],
            [(new FakeTransport)->unreachable('installer/config'), fn ($c) => $c->installerConfig(), 'could not reach Delwathon at engine.delwathon.com'],
        ];

        foreach ($cases as [$transport, $call, $expected]) {
            try {
                $call($client($transport));
                $this->fail("Expected: {$expected}");
            } catch (EngineException $exception) {
                $this->assertStringContainsString($expected, $exception->getMessage());
            }
        }
    }

    #[Test]
    public function oversized_downloads_are_stopped(): void
    {
        $transport = (new FakeTransport)->file('releases/1/download', str_repeat('x', 2048));
        $destination = $this->temporaryDirectory().'/package.zip';

        $this->expectExceptionMessage('larger than allowed');

        (new EngineClient($transport, 'https://engine.delwathon.com/api/'))->download('https://engine.delwathon.com/api/releases/1/download', $destination, 1024, 60);
    }

    #[Test]
    public function downloads_over_plain_http_are_refused(): void
    {
        $this->expectExceptionMessage('insecure connection');

        (new EngineClient(new FakeTransport, 'https://engine.delwathon.com/api/'))->download('http://mirror.example.com/p.zip', '/tmp/x', 1024, 60);
    }
}
