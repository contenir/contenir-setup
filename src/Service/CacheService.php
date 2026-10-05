<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function is_string;
use function is_writable;
use function sprintf;
use function unlink;

/**
 * Clears the application's file cache: every file below the `cache_dir`
 * config value, or `data/cache` (relative to the working directory, which
 * Mezzio sets to the application root). Directories are kept.
 *
 * @api
 */
class CacheService
{
    private const string DEFAULT_CACHE_DIR = 'data/cache';

    /**
     * @param array<array-key, mixed> $config The application config; reads cache_dir.
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * Delete the files below a directory; returns how many were deleted.
     * Files that cannot be deleted are skipped.
     */
    private static function clearDirectory(string $directory): int
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        $deleted = 0;
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $path = $file->getPathname();
            if ($file->isFile() && Filesystem::silently(static fn(): bool => unlink($path))) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    /**
     * Delete every cached file.
     *
     * @return array{success: bool, message: string, deleted_count?: int}
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; the type is checked here.
     */
    public function clearAll(): array
    {
        $cacheDir = $this->config['cache_dir'] ?? null;
        $cacheDir = is_string($cacheDir) && '' !== $cacheDir ? $cacheDir : self::DEFAULT_CACHE_DIR;

        if (! is_dir($cacheDir)) {
            return [
                'success' => true,
                'message' => 'Cache directory does not exist - nothing to clear',
            ];
        }

        if (! is_writable($cacheDir)) {
            return [
                'success'       => false,
                'message'       => sprintf('Failed to clear cache: Directory is not writable: %s', $cacheDir),
                'deleted_count' => 0,
            ];
        }

        $deletedCount = self::clearDirectory($cacheDir);

        return [
            'success'       => true,
            'message'       => sprintf('Successfully cleared %d cache file(s)', $deletedCount),
            'deleted_count' => $deletedCount,
        ];
    }
}
