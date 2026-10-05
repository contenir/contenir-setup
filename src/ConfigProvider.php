<?php

declare(strict_types=1);

namespace Contenir\Setup;

use Contenir\Setup\Handler\CompleteHandler;
use Contenir\Setup\Handler\CompleteHandlerFactory;
use Contenir\Setup\Handler\InstallHandler;
use Contenir\Setup\Handler\InstallHandlerFactory;
use Contenir\Setup\Service\CacheService;
use Contenir\Setup\Service\CacheServiceFactory;
use Contenir\Setup\Service\CacheServiceInterface;
use Contenir\Setup\Service\DatabaseConfigWriter;
use Contenir\Setup\Service\DatabaseConfigWriterFactory;
use Contenir\Setup\Service\DatabaseConfigWriterInterface;
use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Service\DiagnosticsServiceFactory;
use Contenir\Setup\Service\DiagnosticsServiceInterface;
use Contenir\Setup\Service\InstallerService;
use Contenir\Setup\Service\InstallerServiceFactory;
use Contenir\Setup\Service\InstallerServiceInterface;

use function dirname;

/**
 * Configuration provider for the Contenir Setup module: the setup handlers,
 * their services and the "setup" template path.
 *
 * @api
 */
final class ConfigProvider
{
    /**
     * @return array{aliases: array<class-string, class-string>, factories: array<class-string, class-string>}
     */
    public function getDependencies(): array
    {
        return [
            'aliases'   => [
                InstallerServiceInterface::class     => InstallerService::class,
                DiagnosticsServiceInterface::class   => DiagnosticsService::class,
                CacheServiceInterface::class         => CacheService::class,
                DatabaseConfigWriterInterface::class => DatabaseConfigWriter::class,
            ],
            'factories' => [
                InstallHandler::class       => InstallHandlerFactory::class,
                CompleteHandler::class      => CompleteHandlerFactory::class,
                InstallerService::class     => InstallerServiceFactory::class,
                DiagnosticsService::class   => DiagnosticsServiceFactory::class,
                CacheService::class         => CacheServiceFactory::class,
                DatabaseConfigWriter::class => DatabaseConfigWriterFactory::class,
            ],
        ];
    }

    /**
     * @return array{paths: array{setup: list<string>}}
     */
    public function getTemplates(): array
    {
        return [
            'paths' => [
                'setup' => [dirname(__DIR__) . '/templates/setup'],
            ],
        ];
    }

    /**
     * @return array{
     *     dependencies: array{aliases: array<class-string, class-string>, factories: array<class-string, class-string>},
     *     templates: array{paths: array{setup: list<string>}}
     * }
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'templates'    => $this->getTemplates(),
        ];
    }
}
