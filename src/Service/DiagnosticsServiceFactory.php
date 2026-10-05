<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class DiagnosticsServiceFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): DiagnosticsService
    {
        return new DiagnosticsService(ApplicationConfig::from($container), autoFix: true);
    }
}
