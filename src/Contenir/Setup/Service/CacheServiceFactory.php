<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Psr\Container\ContainerInterface;

class CacheServiceFactory
{
    public function __invoke(ContainerInterface $container): CacheService
    {
        $config = $container->get('config');

        return new CacheService($config);
    }
}
