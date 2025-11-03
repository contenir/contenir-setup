<?php

declare(strict_types=1);

namespace Contenir\Setup;

use Contenir\Setup\Handler\CompleteHandler;
use Contenir\Setup\Handler\CompleteHandlerFactory;
use Contenir\Setup\Handler\InstallHandler;
use Contenir\Setup\Handler\InstallHandlerFactory;
use Contenir\Setup\Service\CacheService;
use Contenir\Setup\Service\CacheServiceFactory;
use Contenir\Setup\Service\DatabaseConfigWriter;
use Contenir\Setup\Service\DatabaseConfigWriterFactory;
use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Service\DiagnosticsServiceFactory;
use Contenir\Setup\Service\InstallerService;
use Contenir\Setup\Service\InstallerServiceFactory;

use function dirname;

/**
 * Configuration provider for Contenir Setup module
 */
class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'templates'    => $this->getTemplates(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'factories' => [
                // Setup Handlers
                InstallHandler::class  => InstallHandlerFactory::class,
                CompleteHandler::class => CompleteHandlerFactory::class,

                // Installer Services
                InstallerService::class     => InstallerServiceFactory::class,
                DiagnosticsService::class   => DiagnosticsServiceFactory::class,
                CacheService::class         => CacheServiceFactory::class,
                DatabaseConfigWriter::class => DatabaseConfigWriterFactory::class,
            ],
        ];
    }

    public function getTemplates(): array
    {
        $moduleRoot = dirname(__DIR__, 2);

        return [
            'paths' => [
                'setup' => [$moduleRoot . '/templates/setup'],
            ],
        ];
    }
}