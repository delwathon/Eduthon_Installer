<?php

namespace Tests\Support;

use Eduthon\Installer\Http\Session;

final class TestSession extends Session
{
    public function start(bool $secure, string $path): void
    {
        $_SESSION ??= [];
    }

    public function regenerate(): void {}
}
