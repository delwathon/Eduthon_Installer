<?php

namespace Eduthon\Installer\Security;

use RuntimeException;

/**
 * The package failed an integrity or authenticity check. Nothing from it may
 * be extracted or executed.
 */
final class VerificationException extends RuntimeException {}
