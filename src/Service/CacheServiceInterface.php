<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

/**
 * Clears the application's file cache. The setup handlers depend on this
 * interface; the container resolves it to CacheService.
 *
 * @api
 */
interface CacheServiceInterface
{
    /**
     * Delete every cached file.
     *
     * @return array{success: bool, message: string, deleted_count?: int}
     */
    public function clearAll(): array;
}
