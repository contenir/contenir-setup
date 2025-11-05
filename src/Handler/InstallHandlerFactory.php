<?php

declare(strict_types=1);

namespace Contenir\Setup\Handler;

use Contenir\Setup\Service\CacheService;
use Contenir\Setup\Service\DatabaseConfigWriter;
use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Service\InstallerService;
use Laminas\Db\Adapter\Adapter;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;

class InstallHandlerFactory
{
    public function __invoke(ContainerInterface $container): InstallHandler
    {
        return new InstallHandler(
            renderer: $container->get(TemplateRendererInterface::class),
            installerService: $container->get(InstallerService::class),
            diagnosticsService: $container->get(DiagnosticsService::class),
            configWriter: $container->get(DatabaseConfigWriter::class),
            cacheService: $container->get(CacheService::class),
            adapter: $container->get(Adapter::class)
        );
    }
}
