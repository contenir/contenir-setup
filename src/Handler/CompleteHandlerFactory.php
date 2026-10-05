<?php

declare(strict_types=1);

namespace Contenir\Setup\Handler;

use Contenir\Setup\Service\InstallerServiceInterface;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class CompleteHandlerFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): CompleteHandler
    {
        return new CompleteHandler(
            renderer: $container->get(TemplateRendererInterface::class),
            installerService: $container->get(InstallerServiceInterface::class),
        );
    }
}
