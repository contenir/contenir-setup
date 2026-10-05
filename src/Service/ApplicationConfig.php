<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

use function is_array;

/**
 * Reads the application config service.
 *
 * @internal
 */
final class ApplicationConfig
{
    /**
     * The "config" service, or an empty array when it is missing or not an array.
     *
     * @return array<array-key, mixed>
     *
     * @throws ContainerExceptionInterface
     *
     * @mago-expect analysis:mixed-assignment The config service is untyped; its type is checked here.
     */
    public static function from(ContainerInterface $container): array
    {
        $config = $container->has('config') ? $container->get('config') : [];

        return is_array($config) ? $config : [];
    }
}
