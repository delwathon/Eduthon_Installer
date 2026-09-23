<?php

/**
 * Eduthon Installer — https://delwathon.com
 *
 * Deploys the signed Eduthon school portal onto this server.
 */

require __DIR__.'/bootstrap.php';

(new Eduthon\Installer\App(Eduthon\Installer\Support\Container::boot(__DIR__)))->handle();
