<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Unit\Handler;

use Contenir\Setup\Handler\CompleteHandler;
use Contenir\Setup\Service\InstallerService;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CompleteHandlerTest extends TestCase
{
    #[Test]
    public function answersNotFoundOnceInstalled(): void
    {
        $installer = $this->createMock(InstallerService::class);
        $installer->method('isInstalled')->willReturn(true);
        $installer->expects($this->never())->method('validate');
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->never())->method('render');

        $response = (new CompleteHandler($renderer, $installer))->handle(new ServerRequest());

        static::assertSame([404, ''], [$response->getStatusCode(), (string) $response->getBody()]);
    }

    #[Test]
    public function redirectsToSetupUntilInstalled(): void
    {
        $installer = $this->createStub(InstallerService::class);
        $installer->method('isInstalled')->willReturn(false);

        $response = (new CompleteHandler($this->createStub(TemplateRendererInterface::class), $installer))->handle(
            new ServerRequest(),
        );

        static::assertSame([302, '/setup'], [$response->getStatusCode(), $response->getHeaderLine('Location')]);
    }
}
