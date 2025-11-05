<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Contenir\Service\Database\AdapterManager;
use Contenir\Service\Migration\MigrationService;
use Psr\Container\ContainerInterface;
use User\Manager\UserManager;

class InstallerServiceFactory
{
    public function __invoke(ContainerInterface $container): InstallerService
    {
        $adapterManager   = $container->get(AdapterManager::class);
        $config           = $container->get('config');
        $migrationService = $container->get(MigrationService::class);
        $userManager      = $container->get(UserManager::class);

        // Use CMS database adapter
        $adapter = $adapterManager->getAdapter('cms');

        return new InstallerService($adapter, $config, $migrationService, $userManager);
    }
}
