<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class CacheServiceFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): CacheService
    {
        return new CacheService(ApplicationConfig::from($container));
    }
}
