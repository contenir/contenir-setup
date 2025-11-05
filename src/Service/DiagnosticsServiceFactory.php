<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Psr\Container\ContainerInterface;

class DiagnosticsServiceFactory
{
    public function __invoke(ContainerInterface $container): DiagnosticsService
    {
        $config = $container->get('config');
        return new DiagnosticsService($config, true);
    }
}
