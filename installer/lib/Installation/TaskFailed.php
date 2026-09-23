<?php

namespace Eduthon\Installer\Installation;

use RuntimeException;

final class TaskFailed extends RuntimeException
{
    public function __construct(string $message, public readonly bool $rolledBack = false, public readonly ?string $detail = null)
    {
        parent::__construct($message);
    }
}
