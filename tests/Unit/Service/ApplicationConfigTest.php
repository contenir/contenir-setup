<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Unit\Service;

use Contenir\Setup\Service\ApplicationConfig;
use Contenir\Setup\Tests\TestAsset\Container\InMemoryContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ApplicationConfigTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, array<array-key, mixed>}>
     */
    public static function containers(): array
    {
        return [
            'config service'         => [['config' => ['cache_dir' => 'x']], ['cache_dir' => 'x']],
            'no config service'      => [[], []],
            'config is not an array' => [['config' => 'nope'], []],
        ];
    }

    /**
     * @param array<string, mixed> $services
     * @param array<array-key, mixed> $expected
     */
    #[Test]
    #[DataProvider('containers')]
    public function readsTheConfigServiceAsAnArray(array $services, array $expected): void
    {
        static::assertSame($expected, ApplicationConfig::from(new InMemoryContainer($services)));
    }
}
