<?php

namespace Eduthon\Installer\Engine;

use RuntimeException;

/**
 * A failure talking to Delwathon Admin, with a message fit for the user.
 */
final class EngineException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly ?string $detail = null)
    {
        parent::__construct($message);
    }
}
