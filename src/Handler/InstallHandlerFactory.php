<?php

declare(strict_types=1);

namespace Contenir\Setup\Handler;

use Contenir\Setup\Service\CacheServiceInterface;
use Contenir\Setup\Service\DatabaseConfigWriterInterface;
use Contenir\Setup\Service\DiagnosticsServiceInterface;
use Contenir\Setup\Service\InstallerServiceInterface;
use Laminas\Db\Adapter\Adapter;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class InstallHandlerFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): InstallHandler
    {
        return new InstallHandler(
            renderer: $container->get(TemplateRendererInterface::class),
            installerService: $container->get(InstallerServiceInterface::class),
            diagnosticsService: $container->get(DiagnosticsServiceInterface::class),
            configWriter: $container->get(DatabaseConfigWriterInterface::class),
            cacheService: $container->get(CacheServiceInterface::class),
            adapter: $container->get(Adapter::class),
        );
    }
}
