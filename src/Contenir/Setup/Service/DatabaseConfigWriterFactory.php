<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Psr\Container\ContainerInterface;

class DatabaseConfigWriterFactory
{
    public function __invoke(ContainerInterface $container): DatabaseConfigWriter
    {
        return new DatabaseConfigWriter();
    }
}
