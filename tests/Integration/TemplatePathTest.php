<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Integration;

use Contenir\Setup\ConfigProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class TemplatePathTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function templates(): array
    {
        return [
            'install'  => ['install.phtml'],
            'complete' => ['complete.phtml'],
        ];
    }

    #[Test]
    #[DataProvider('templates')]
    public function findsTheTemplateUnderTheConfiguredPath(string $template): void
    {
        $path = (new ConfigProvider())->getTemplates()['paths']['setup'][0];

        static::assertFileExists("{$path}/{$template}");
    }
}
