<?php

declare(strict_types=1);

namespace Contenir\Setup\Handler;

use Contenir\Setup\Service\InstallerService;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;

class CompleteHandlerFactory
{
    public function __invoke(ContainerInterface $container): CompleteHandler
    {
        return new CompleteHandler(
            renderer: $container->get(TemplateRendererInterface::class),
            installerService: $container->get(InstallerService::class)
        );
    }
}
