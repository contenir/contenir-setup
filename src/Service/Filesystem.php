<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use function restore_error_handler;
use function set_error_handler;

/**
 * Runs filesystem calls whose failure the caller reports itself.
 *
 * @internal
 */
final class Filesystem
{
    /**
     * Run a filesystem call, reporting failure through its return value
     * rather than the warning it raises.
     *
     * @param callable(): bool $operation
     */
    public static function silently(callable $operation): bool
    {
        set_error_handler(static fn(): bool => true);

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
