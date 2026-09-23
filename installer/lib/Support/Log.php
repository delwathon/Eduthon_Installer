<?php

namespace Eduthon\Installer\Support;

/**
 * Plain-text installation log kept in storage/ (PHP-guarded).
 */
final class Log
{
    public function __construct(private string $file) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function write(string $level, string $message, array $context = []): void
    {
        if (! is_file($this->file)) {
            @file_put_contents($this->file, "<?php exit; ?>\n");
        }

        $line = sprintf(
            "[%s] %s: %s%s\n",
            gmdate('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : ' '.json_encode($context, JSON_UNESCAPED_SLASHES),
        );

        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * @return list<string>
     */
    public function tail(int $lines = 40): array
    {
        if (! is_file($this->file)) {
            return [];
        }

        $all = array_slice(file($this->file, FILE_IGNORE_NEW_LINES) ?: [], 1);

        return array_slice($all, -$lines);
    }
}
