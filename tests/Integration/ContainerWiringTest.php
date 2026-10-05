<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Integration;

use Contenir\Service\Database\AdapterManager;
use Contenir\Service\Migration\MigrationService;
use Contenir\Setup\ConfigProvider;
use Contenir\Setup\Handler\CompleteHandler;
use Contenir\Setup\Handler\InstallHandler;
use Contenir\Setup\Service\CacheService;
use Contenir\Setup\Service\CacheServiceInterface;
use Contenir\Setup\Service\DatabaseConfigWriter;
use Contenir\Setup\Service\DatabaseConfigWriterInterface;
use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Service\DiagnosticsServiceInterface;
use Contenir\Setup\Service\InstallerService;
use Contenir\Setup\Service\InstallerServiceInterface;
use Laminas\Db\Adapter\Adapter;
use Laminas\Diactoros\ServerRequest;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Manager\UserManager;

/**
 * The ConfigProvider's services in a real ServiceManager, with the host
 * application's services doubled.
 */
#[Group('integration')]
final class ContainerWiringTest extends TestCase
{
    /**
     * @return array<string, array{class-string, class-string}>
     */
    public static function services(): array
    {
        return [
            'installer'      => [InstallerServiceInterface::class, InstallerService::class],
            'diagnostics'    => [DiagnosticsServiceInterface::class, DiagnosticsService::class],
            'cache'          => [CacheServiceInterface::class, CacheService::class],
            'config writer'  => [DatabaseConfigWriterInterface::class, DatabaseConfigWriter::class],
            'install page'   => [InstallHandler::class, InstallHandler::class],
            'complete route' => [CompleteHandler::class, CompleteHandler::class],
        ];
    }

    #[Test]
    public function letsTheHostReplaceAServiceThroughItsInterface(): void
    {
        $container = $this->container();
        $installer = $this->createStub(InstallerServiceInterface::class);
        $container->setAlias(InstallerServiceInterface::class, 'HostInstaller');
        $container->setService('HostInstaller', $installer);
        $installer->method('isInstalled')->willReturn(true);

        $response = $container->get(CompleteHandler::class)->handle(new ServerRequest());

        static::assertSame(404, $response->getStatusCode());
    }

    /**
     * @param class-string $name
     * @param class-string $expected
     */
    #[Test]
    #[DataProvider('services')]
    public function resolvesEveryService(string $name, string $expected): void
    {
        static::assertInstanceOf($expected, $this->container()->get($name));
    }

    private function container(): ServiceManager
    {
        $adapters = $this->createStub(AdapterManager::class);
        $adapters->method('getAdapter')->willReturn($this->createStub(Adapter::class));

        $container = new ServiceManager((new ConfigProvider())->getDependencies());
        $container->setAllowOverride(true);
        $container->setService('config', ['db' => ['cms' => ['database' => 'data/cms/cms.db']]]);
        $container->setService(AdapterManager::class, $adapters);
        $container->setService(MigrationService::class, $this->createStub(MigrationService::class));
        $container->setService(UserManager::class, $this->createStub(UserManager::class));
        $container->setService(TemplateRendererInterface::class, $this->createStub(TemplateRendererInterface::class));
        $container->setService(Adapter::class, $this->createStub(Adapter::class));

        return $container;
    }
}
