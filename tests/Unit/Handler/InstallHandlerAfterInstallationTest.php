<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Unit\Handler;

use Contenir\Setup\Handler\InstallHandler;
use Contenir\Setup\Service\CacheServiceInterface;
use Contenir\Setup\Service\DatabaseConfigWriterInterface;
use Contenir\Setup\Service\DiagnosticsServiceInterface;
use Contenir\Setup\Service\InstallerServiceInterface;
use Laminas\Db\Adapter\Adapter;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Once installed, the wizard answers 404 to every request and touches no
 * collaborator other than the installed check.
 */
#[Group('unit')]
#[Group('security')]
final class InstallHandlerAfterInstallationTest extends TestCase
{
    private const string FORM_VALUE = 'long enough value';

    /**
     * @return array<string, array{ServerRequestInterface}>
     */
    public static function requests(): array
    {
        $post = static fn(array $body): ServerRequestInterface => (new ServerRequest(method: 'POST'))->withParsedBody(
            $body,
        );

        return [
            'GET'                    => [new ServerRequest()],
            'GET forced reinstall'   => [(new ServerRequest())->withQueryParams(['force' => '1'])],
            'GET diagnostics step'   => [(new ServerRequest())->withQueryParams(['step' => 'diagnostics'])],
            'GET admin step'         => [(new ServerRequest())->withQueryParams(['step' => 'admin-user'])],
            'POST diagnostics'       => [$post(['action' => 'diagnostics'])],
            'POST proceed'           => [$post(['action' => 'database-config', 'proceed' => '1'])],
            'POST database config'   => [$post(['action' => 'database-config', 'cms_database' => '/tmp/evil.db'])],
            'POST test connection'   => [$post(['action' => 'test-connection'])],
            'POST install new admin' => [$post([
                'action'         => 'install',
                'admin_username' => 'intruder',
                'admin_email'    => 'intruder@example.com',
                'admin_password' => self::FORM_VALUE,
            ])],
            'POST unknown action'    => [$post(['action' => 'repair'])],
        ];
    }

    #[Test]
    #[DataProvider('requests')]
    public function answersNotFoundWithoutActing(ServerRequestInterface $request): void
    {
        $installer = $this->createMock(InstallerServiceInterface::class);
        $installer->expects($this->atLeastOnce())->method('isInstalled')->willReturn(true);
        foreach (['install', 'repair', 'createAdminUser', 'validate', 'databaseFileExists'] as $method) {
            $installer->expects($this->never())->method($method);
        }

        $writer = $this->createMock(DatabaseConfigWriterInterface::class);
        $writer->expects($this->never())->method('write');
        $cache = $this->createMock(CacheServiceInterface::class);
        $cache->expects($this->never())->method('clearAll');
        $diagnostics = $this->createMock(DiagnosticsServiceInterface::class);
        $diagnostics->expects($this->never())->method('runAll');
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->never())->method('query');
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->never())->method('render');

        $response = (new InstallHandler($renderer, $installer, $diagnostics, $writer, $cache, $adapter))->handle(
            $request,
        );

        static::assertSame([404, ''], [$response->getStatusCode(), (string) $response->getBody()]);
    }
}
