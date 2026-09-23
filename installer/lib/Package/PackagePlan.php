<?php

namespace Eduthon\Installer\Package;

/**
 * The validated list of files a package will place on disk.
 */
final class PackagePlan
{
    /**
     * @param  list<array{name: string, path: string, size: int}>  $entries
     */
    public function __construct(
        public readonly array $entries,
        public readonly int $totalBytes,
        public readonly string $stripPrefix,
    ) {}

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return array_column($this->entries, 'path');
    }
}
