<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Unit\Handler;

use Contenir\Setup\Handler\CompleteHandler;
use Contenir\Setup\Handler\CompleteHandlerFactory;
use Contenir\Setup\Handler\InstallHandler;
use Contenir\Setup\Handler\InstallHandlerFactory;
use Contenir\Setup\Service\CacheService;
use Contenir\Setup\Service\DatabaseConfigWriter;
use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Service\InstallerService;
use Contenir\Setup\Tests\TestAsset\Container\InMemoryContainer;
use Laminas\Db\Adapter\Adapter;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class HandlerFactoriesTest extends TestCase
{
    #[Test]
    public function buildsTheCompleteHandler(): void
    {
        static::assertInstanceOf(CompleteHandler::class, (new CompleteHandlerFactory())($this->container()));
    }

    #[Test]
    public function buildsTheInstallHandler(): void
    {
        static::assertInstanceOf(InstallHandler::class, (new InstallHandlerFactory())($this->container()));
    }

    private function container(): InMemoryContainer
    {
        return new InMemoryContainer([
            TemplateRendererInterface::class => $this->createStub(TemplateRendererInterface::class),
            InstallerService::class          => $this->createStub(InstallerService::class),
            DiagnosticsService::class        => $this->createStub(DiagnosticsService::class),
            DatabaseConfigWriter::class      => $this->createStub(DatabaseConfigWriter::class),
            CacheService::class              => $this->createStub(CacheService::class),
            Adapter::class                   => $this->createStub(Adapter::class),
        ]);
    }
}
