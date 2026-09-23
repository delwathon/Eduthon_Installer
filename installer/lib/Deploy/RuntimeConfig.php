<?php

namespace Eduthon\Installer\Deploy;

/**
 * Writes the files that connect the deployed portal to its backend.
 */
final class RuntimeConfig
{
    public const CONFIG_FILE = 'eduthon.config.json';

    private const BEGIN = '# BEGIN Eduthon';

    private const END = '# END Eduthon';

    public function __construct(private string $target, private string $installerDirectory, private string $storage) {}

    /**
     * The portal reads this at start-up instead of having URLs built in.
     *
     * @param  array<string, mixed>  $values
     */
    public function writeConfig(array $values): void
    {
        $file = $this->target.'/'.self::CONFIG_FILE;
        $json = json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

        if (@file_put_contents($file.'.tmp', $json, LOCK_EX) === false || ! @rename($file.'.tmp', $file)) {
            throw new DeployException('Could not write the portal configuration file.');
        }

        @chmod($file, 0644);
    }

    /**
     * @return array<string, mixed>
     */
    public function readConfig(): array
    {
        $file = $this->target.'/'.self::CONFIG_FILE;

        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }

    /**
     * Add or refresh the installer's block in .htaccess (single-page app
     * routing and caching). Rules outside the block are left untouched.
     */
    public function writeHtaccess(string $basePath): void
    {
        $file = $this->target.'/.htaccess';
        $current = is_file($file) ? (string) file_get_contents($file) : '';

        if ($current !== '' && ! str_contains($current, self::BEGIN) && ! is_file($this->storage.'/htaccess.original')) {
            @copy($file, $this->storage.'/htaccess.original');
        }

        $pattern = '/'.preg_quote(self::BEGIN, '/').'.*?'.preg_quote(self::END, '/').'\R?/s';
        $existing = trim((string) preg_replace($pattern, '', $current));
        $contents = $this->block($basePath).($existing === '' ? '' : "\n".$existing."\n");

        if (file_put_contents($file, $contents, LOCK_EX) === false) {
            throw new DeployException('Could not update .htaccess in the web root.');
        }
    }

    public function removeHtaccessBlock(): void
    {
        $file = $this->target.'/.htaccess';

        if (! is_file($file)) {
            return;
        }

        $pattern = '/'.preg_quote(self::BEGIN, '/').'.*?'.preg_quote(self::END, '/').'\R?/s';
        file_put_contents($file, ltrim((string) preg_replace($pattern, '', (string) file_get_contents($file))), LOCK_EX);
    }

    private function block(string $basePath): string
    {
        $base = '/'.trim($basePath, '/').'/';
        $base = $base === '//' ? '/' : $base;
        $installer = preg_quote($this->installerDirectory, '#');

        return <<<HTACCESS
        # BEGIN Eduthon
        # Managed by the Eduthon installer. Edits between these markers are replaced on update.
        Options -Indexes
        DirectoryIndex index.html

        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteBase {$base}
            RewriteRule ^index\.html$ - [L]
            RewriteRule ^{$installer}(/|$) - [L]
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule . index.html [L]
        </IfModule>

        <IfModule mod_headers.c>
            Header always set X-Content-Type-Options "nosniff"
            Header always set Referrer-Policy "strict-origin-when-cross-origin"
            <FilesMatch "\.(js|css|woff2?|ttf|png|jpe?g|gif|svg|webp|avif|ico)$">
                Header set Cache-Control "public, max-age=31536000, immutable"
            </FilesMatch>
            <FilesMatch "^(index\.html|eduthon\.config\.json)$">
                Header set Cache-Control "no-cache"
            </FilesMatch>
        </IfModule>
        # END Eduthon

        HTACCESS;
    }
}
