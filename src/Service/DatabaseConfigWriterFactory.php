<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Psr\Container\ContainerInterface;

/**
 * @api
 */
final class DatabaseConfigWriterFactory
{
    /**
     * @mago-expect analysis:unused-parameter The writer needs nothing from the container.
     */
    public function __invoke(ContainerInterface $container): DatabaseConfigWriter
    {
        return new DatabaseConfigWriter();
    }
}
