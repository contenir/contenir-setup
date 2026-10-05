<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Unit\Service;

use Contenir\Setup\Service\DiagnosticsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The checks that need no filesystem; directory checks are integration tests.
 */
#[Group('unit')]
final class DiagnosticsServiceTest extends TestCase
{
    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function phpVersions(): array
    {
        return [
            'minimum' => ['8.3.0', true, 'PHP version 8.3.0 meets requirements'],
            'newer'   => ['8.5.1', true, 'PHP version 8.5.1 meets requirements'],
            'too old' => ['8.2.29', false, 'PHP version 8.2.29 does not meet minimum requirement 8.3.0'],
        ];
    }

    #[Test]
    public function checksEveryExtensionAfterAMissingOne(): void
    {
        $service = new DiagnosticsService([]);

        $service->checkPhpExtensions(['contenir_missing', 'json']);

        static::assertSame(
            ['success' => true, 'message' => 'Extension json is loaded'],
            $service->getResults()['extension_json'] ?? null,
        );
    }

    #[Test]
    #[DataProvider('phpVersions')]
    public function checksThePhpVersionAgainstTheMinimum(string $version, bool $passes, string $message): void
    {
        $service = new DiagnosticsService([]);

        $service->checkPhpVersion($version);

        static::assertSame(['php_version' => ['success' => $passes, 'message' => $message]], $service->getResults());
    }

    #[Test]
    public function checksTheRunningPhpVersionByDefault(): void
    {
        $service = new DiagnosticsService([]);

        $service->checkPhpVersion();

        static::assertTrue($service->hasPassed());
    }

    #[Test]
    public function failsOnceAnyCheckHasFailed(): void
    {
        $service = new DiagnosticsService([]);

        $service->checkPhpExtensions(['json', 'contenir_missing']);

        static::assertFalse($service->hasPassed());
    }

    #[Test]
    public function recordsALoadedExtension(): void
    {
        $service = new DiagnosticsService([]);

        $service->checkPhpExtensions(['json']);

        static::assertSame(
            ['extension_json' => ['success' => true, 'message' => 'Extension json is loaded']],
            $service->getResults(),
        );
    }

    #[Test]
    public function reportsAMissingExtensionAsAnError(): void
    {
        $service = new DiagnosticsService([]);

        $service->checkPhpExtensions(['contenir_missing']);

        static::assertSame(
            ['extension_contenir_missing' => 'Required PHP extension not loaded: contenir_missing'],
            $service->getErrors(),
        );
    }
}
