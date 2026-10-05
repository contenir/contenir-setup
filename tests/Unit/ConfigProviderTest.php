<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Unit;

use Contenir\Setup\ConfigProvider;
use Contenir\Setup\Handler\CompleteHandler;
use Contenir\Setup\Handler\CompleteHandlerFactory;
use Contenir\Setup\Handler\InstallHandler;
use Contenir\Setup\Handler\InstallHandlerFactory;
use Contenir\Setup\Service\CacheService;
use Contenir\Setup\Service\CacheServiceFactory;
use Contenir\Setup\Service\CacheServiceInterface;
use Contenir\Setup\Service\DatabaseConfigWriter;
use Contenir\Setup\Service\DatabaseConfigWriterFactory;
use Contenir\Setup\Service\DatabaseConfigWriterInterface;
use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Service\DiagnosticsServiceFactory;
use Contenir\Setup\Service\DiagnosticsServiceInterface;
use Contenir\Setup\Service\InstallerService;
use Contenir\Setup\Service\InstallerServiceFactory;
use Contenir\Setup\Service\InstallerServiceInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;

#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function pointsTheSetupTemplateNamespaceAtThePackageTemplates(): void
    {
        static::assertSame(
            ['paths' => ['setup' => [dirname(__DIR__, levels: 2) . '/templates/setup']]],
            (new ConfigProvider())()['templates'],
        );
    }

    #[Test]
    public function registersAFactoryForEveryHandlerAndService(): void
    {
        static::assertSame(
            [
                'aliases'   => [
                    InstallerServiceInterface::class     => InstallerService::class,
                    DiagnosticsServiceInterface::class   => DiagnosticsService::class,
                    CacheServiceInterface::class         => CacheService::class,
                    DatabaseConfigWriterInterface::class => DatabaseConfigWriter::class,
                ],
                'factories' => [
                    InstallHandler::class       => InstallHandlerFactory::class,
                    CompleteHandler::class      => CompleteHandlerFactory::class,
                    InstallerService::class     => InstallerServiceFactory::class,
                    DiagnosticsService::class   => DiagnosticsServiceFactory::class,
                    CacheService::class         => CacheServiceFactory::class,
                    DatabaseConfigWriter::class => DatabaseConfigWriterFactory::class,
                ],
            ],
            (new ConfigProvider())()['dependencies'],
        );
    }
}
