<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function is_dir;
use function is_writable;
use function sprintf;
use function unlink;

/**
 * Cache Service
 *
 * Handles cache clearing operations for the CMS.
 */
class CacheService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Clear all cache directories
     */
    public function clearAll(): array
    {
        $results  = [];
        $cacheDir = $this->config['cache_dir'] ?? __DIR__ . '/../../../data/cache';

        if (! is_dir($cacheDir)) {
            return [
                'success' => true,
                'message' => 'Cache directory does not exist - nothing to clear',
            ];
        }

        try {
            $deletedCount = $this->clearDirectory($cacheDir);

            $results = [
                'success'       => true,
                'message'       => sprintf('Successfully cleared %d cache file(s)', $deletedCount),
                'deleted_count' => $deletedCount,
            ];
        } catch (RuntimeException $e) {
            $results = [
                'success'       => false,
                'message'       => 'Failed to clear cache: ' . $e->getMessage(),
                'deleted_count' => 0,
            ];
        }

        return $results;
    }

    /**
     * Clear a specific cache directory
     */
    private function clearDirectory(string $directory): int
    {
        if (! is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory does not exist: %s', $directory));
        }

        if (! is_writable($directory)) {
            throw new RuntimeException(sprintf('Directory is not writable: %s', $directory));
        }

        $deletedCount = 0;
        $iterator     = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                if (unlink($file->getPathname())) {
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }
}
