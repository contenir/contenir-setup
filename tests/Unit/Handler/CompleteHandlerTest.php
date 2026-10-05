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
    public function redirectsToSetupUntilInstalled(): void
    {
        $installer = $this->createStub(InstallerService::class);
        $installer->method('isInstalled')->willReturn(false);

        $response = (new CompleteHandler($this->createStub(TemplateRendererInterface::class), $installer))->handle(
            new ServerRequest(),
        );

        static::assertSame([302, '/setup'], [$response->getStatusCode(), $response->getHeaderLine('Location')]);
    }

    #[Test]
    public function rendersTheCompletionPageWithValidationWarnings(): void
    {
        $installer = $this->createStub(InstallerService::class);
        $installer->method('isInstalled')->willReturn(true);
        $installer->method('validate')->willReturn(['Missing default roles']);

        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with('setup::complete', ['title' => 'Installation Complete', 'errors' => ['Missing default roles']])
            ->willReturn('<p>done</p>');

        $response = (new CompleteHandler($renderer, $installer))->handle(new ServerRequest());

        static::assertSame('<p>done</p>', (string) $response->getBody());
    }
}
