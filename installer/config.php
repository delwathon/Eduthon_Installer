<?php

/*
|--------------------------------------------------------------------------
| Eduthon Installer configuration
|--------------------------------------------------------------------------
|
| Only two things are fixed in the installer: where Delwathon Admin lives
| and the public keys that release packages must be signed with. Every
| other setting (backend and frontend server URLs, release channel, the
| school's details) is fetched from Delwathon Admin during installation.
|
| Put local overrides in config.local.php; installer updates never touch it.
|
*/

return [

    // Delwathon Admin installation API. Must use HTTPS.
    'engine_url' => 'https://engine.delwathon.com/api/',

    // Ed25519 public keys (base64) trusted to sign release packages.
    // A package is rejected unless its signature matches one of these.
    // Written by `bin/build` from EDUTHON_RELEASE_PUBLIC_KEYS.
    'trusted_keys' => [
        // '@trusted-keys@'
    ],

    // What this installer deploys.
    'component' => 'frontend',

    // Where the portal is deployed. null = the folder containing this installer.
    'target_dir' => null,

    // Safety limits for downloaded packages.
    'max_package_bytes' => 300 * 1024 * 1024,
    'max_unpacked_bytes' => 1024 * 1024 * 1024,
    'max_files' => 20000,

    // Backups of previous deployments to keep for rollback.
    'keep_backups' => 3,

    // Network timeouts in seconds.
    'timeout' => 30,
    'download_timeout' => 600,

];
