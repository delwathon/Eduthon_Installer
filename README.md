# Eduthon Installer

Deploys the Eduthon school portal onto a school's own web server, the **self-hosted** option. Schools hosted by Delwathon don't need it.

The school uploads the `installer/` folder into its website's root and opens `https://their-school.com/installer/`. The installer then:

1. Checks the server: PHP 8.1+, the curl, sodium and zip extensions, HTTPS, write access and free disk space.
2. Confirms the purchase code and secret key with Delwathon Admin and binds the license to this website.
3. Fetches the school's configuration from Delwathon Admin: the shared multi-tenant backend URL, the school's **tenant ID**, the frontend parent URL, the release channel and support contacts. **None of these are built into the installer.** Change them in Delwathon Admin → Settings → Eduthon servers, and new installations pick them up immediately.
4. Downloads the signed portal release.
5. **Verifies the package before touching it.** It checks the size, the SHA-256 checksum and the Ed25519 signature against the public keys compiled into the installer. Any mismatch stops the installation, deletes the download and reports the failure. Nothing is extracted or run.
6. Inspects every archive entry. It rejects path traversal, absolute paths, symlinks, hidden files, zip bombs, reserved files (`.htaccess`, `.user.ini`, `eduthon.config.json`) and anything that isn't a static web file. The portal is a static site, so a package can never contain PHP or other server-side code.
7. Backs up everything it will overwrite or remove, deploys the portal with `index.html` last, and removes files left over from the previous version.
8. Writes `eduthon.config.json`, which tells the portal where its backend is, and a marked block in `.htaccess` for single-page-app routing and caching. The site owner's other rules are preserved.
9. Tests the result: every deployed file must match the verified package, the portal must respond, and the backend must be reachable. If a step fails after files have changed, the site is **rolled back automatically**.
10. Reports the installed version to Delwathon Admin and marks setup complete.

Each step runs as its own request, which avoids shared-hosting timeouts and shows live progress. Afterwards, `/installer/` becomes an overview page for installing updates. It requires the license's secret key each time, and the key is never stored on the server.

## Layout

```
installer/                 ← the folder schools upload
  index.php, bootstrap.php, config.php, version.php
  lib/                     Plain PHP, no framework and no Composer at run time
    App.php                Routes pages (?page=) and background steps (?action=)
    Engine/                Delwathon Admin API client (HTTPS only, certificate verification on)
    Security/              Ed25519 signature and checksum verification
    Package/               Archive inspection and safe extraction
    Deploy/                Backup, deployment, rollback, runtime config
    Checks/                Server requirements and post-install health checks
    Installation/          The step pipeline, run state and installed state
  views/                   Page templates
  assets/                  Compiled CSS, JS and logos
  storage/                 Downloads, staging, backups and logs (never web-accessible)
bin/build                  Produces dist/eduthon-installer-{version}.zip
bin/serve                  Local development sandbox
resources/installer.css    Tailwind source for assets/installer.css
tests/                     PHPUnit: unit tests and full end-to-end flows
```

Files in `storage/` are stored as PHP files that exit immediately, so they stay private even on servers that ignore `.htaccess`. `storage/` also has its own deny rule.

## Development

```bash
composer install     # PHPUnit
npm install          # Tailwind, for the stylesheet
npm run dev          # sandbox at http://127.0.0.1:8200/installer/
npm run dev:fresh    # wipe the sandbox (including an "installed" portal) and start over
composer test
```

`npm run dev` copies `installer/` into `.sandbox/public_html/installer/` and serves it with PHP's built-in server. The installer can't run from the repository itself, because it deploys into the folder that contains it. The sandbox talks to a local Delwathon Admin at `http://127.0.0.1:8123/api/` by default; set `EDUTHON_ENGINE_URL` to use another. On first run it fetches that engine's release public key, a shortcut meant for development only. To try a full installation locally:

1. In Delwathon Admin, set `ENGINE_SIGNING_KEY`, then upload and publish a frontend release (Releases → Upload, or `php artisan releases:upload frontend 2.5.0 dist.zip --publish`).
2. Issue a license for a school set to **Client-hosted frontend**. It uses the shared backend and its tenant ID automatically.
3. Run `npm run dev` here, open the installer and enter the license.

After changing installer code, rerun `npm run dev` to copy it into the sandbox.

Overrides go in `installer/config.local.php`. That file is ignored by git and never overwritten.

## Building a release

```bash
EDUTHON_RELEASE_PUBLIC_KEYS="<public key from Delwathon Admin → Releases>" php bin/build
```

This produces `dist/eduthon-installer-{version}.zip`, with the trusted keys written into `config.php`. The build refuses to run without a valid key. Use `--engine=https://…/api/` to point a build at a different Delwathon Admin, such as staging. Publish the zip as an `installer` release so schools can download it:

```bash
php artisan releases:upload installer 3.0.0 dist/eduthon-installer-3.0.0.zip --publish
```

To rotate the signing key, build the next installer with both keys (`KEY_OLD,KEY_NEW`), switch Delwathon Admin to the new key once schools have updated, then drop the old key.

## The portal's side of the contract

The installer writes `eduthon.config.json` in the web root:

```json
{
  "config_version": 2,
  "version": "2.5.0",
  "school": "Heritage College",
  "tenant": "01jk8z3v5t6m9q2w4e7r1y0u3p",
  "tenant_header": "X-Eduthon-Tenant",
  "api_url": "https://api.eduthon.ng/api/",
  "backend_url": "https://api.eduthon.ng/",
  "portal_url": "https://portal.heritage.sch.ng/",
  "engine_url": "https://engine.delwathon.com/api/",
  "installed_at": "2026-09-23T08:32:22+00:00"
}
```

The backend is multi-tenant and is never installed on the school's server (see Delwathon Admin's `docs/multi-tenancy.md`). At start-up the Eduthon frontend must load `/eduthon.config.json`, then send `tenant_header: tenant` on every request to `api_url`. A portal is never connected without a tenant ID. If the file is missing, as on Delwathon-hosted portals, it falls back to `GET {engine}/directory?host=…`. The frontend currently hard-codes its API URL in `src/main.js`, so it needs this change before self-hosted portals can reach their backend. Its build is also broken on Linux: `src/router/index.js` imports `permissions/show.vue`, but the file is named `Show.vue`.

## What changed from the previous installer

The previous installer was a fork of the open-source rachidlaasri/LaravelInstaller package. It ran inside the Eduthon **backend** and configured the backend's `.env`, database, Passport keys and owner account. Under the current architecture Delwathon hosts every backend, so none of that belongs on a school's server. It also:

- stored the license secret key and the owner's password in plain cookies;
- offered a raw `.env` editor before installation finished;
- ran `php artisan` through `shell_exec`;
- created users with hard-coded role and permission IDs;
- shipped 20 language packs and two icon fonts, and targeted PHP 7.0 and Laravel 5.

It has been replaced entirely. It remains in git history before version 3.0.0.
