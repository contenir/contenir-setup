<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Unit\Service;

use Contenir\Service\Database\AdapterManager;
use Contenir\Service\Migration\MigrationService;
use Contenir\Setup\Service\CacheService;
use Contenir\Setup\Service\CacheServiceFactory;
use Contenir\Setup\Service\DatabaseConfigWriter;
use Contenir\Setup\Service\DatabaseConfigWriterFactory;
use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Service\DiagnosticsServiceFactory;
use Contenir\Setup\Service\InstallerServiceFactory;
use Contenir\Setup\Tests\TestAsset\Container\InMemoryContainer;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Manager\UserManager;

#[Group('unit')]
final class ServiceFactoriesTest extends TestCase
{
    #[Test]
    public function buildsTheCacheService(): void
    {
        static::assertInstanceOf(
            CacheService::class,
            (new CacheServiceFactory())(new InMemoryContainer(['config' => ['cache_dir' => 'cache']])),
        );
    }

    #[Test]
    public function buildsTheDatabaseConfigWriter(): void
    {
        static::assertInstanceOf(
            DatabaseConfigWriter::class,
            (new DatabaseConfigWriterFactory())(new InMemoryContainer()),
        );
    }

    #[Test]
    public function buildsTheDiagnosticsService(): void
    {
        static::assertInstanceOf(
            DiagnosticsService::class,
            (new DiagnosticsServiceFactory())(new InMemoryContainer(['config' => []])),
        );
    }

    #[Test]
    public function buildsTheInstallerServiceOnTheCmsAdapter(): void
    {
        $adapters = $this->createMock(AdapterManager::class);
        $adapters->expects($this->once())
            ->method('getAdapter')
            ->with('cms')
            ->willReturn($this->createStub(Adapter::class));

        $installer = (new InstallerServiceFactory())(new InMemoryContainer([
            AdapterManager::class   => $adapters,
            'config'                => ['db' => ['cms' => ['database' => 'data/cms/cms.db']]],
            MigrationService::class => $this->createStub(MigrationService::class),
            UserManager::class      => $this->createStub(UserManager::class),
        ]));

        static::assertSame('data/cms/cms.db', $installer->getDatabasePath());
    }
}
