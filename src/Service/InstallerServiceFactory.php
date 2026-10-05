<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Contenir\Service\Database\AdapterManager;
use Contenir\Service\Migration\MigrationService;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use User\Manager\UserManager;

/**
 * Builds the InstallerService on the "cms" adapter of the host application's
 * AdapterManager, with its MigrationService and UserManager.
 *
 * @api
 */
final class InstallerServiceFactory
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): InstallerService
    {
        return new InstallerService(
            $container->get(AdapterManager::class)->getAdapter('cms'),
            ApplicationConfig::from($container),
            $container->get(MigrationService::class),
            $container->get(UserManager::class),
        );
    }
}
