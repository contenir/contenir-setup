<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Unit\Service;

use Closure;
use Contenir\Setup\Service\Filesystem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function restore_error_handler;
use function set_error_handler;
use function trigger_error;

use const E_USER_WARNING;

#[Group('unit')]
final class FilesystemTest extends TestCase
{
    #[Test]
    public function reportsAFailedOperationWithoutItsWarning(): void
    {
        $result = Filesystem::silently(static function (): bool {
            trigger_error('failed', E_USER_WARNING);

            return false;
        });

        static::assertFalse($result);
    }

    #[Test]
    public function restoresThePreviousErrorHandler(): void
    {
        Filesystem::silently(static fn(): bool => true);

        $current = set_error_handler(static fn(): bool => false);
        restore_error_handler();

        static::assertNotInstanceOf(Closure::class, $current);
    }

    #[Test]
    public function returnsTheOperationResult(): void
    {
        static::assertTrue(Filesystem::silently(static fn(): bool => true));
    }
}
