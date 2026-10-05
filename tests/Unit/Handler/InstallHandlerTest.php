<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Unit\Handler;

use Contenir\Setup\Handler\InstallHandler;
use Contenir\Setup\Service\CacheService;
use Contenir\Setup\Service\DatabaseConfigWriter;
use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Service\InstallerService;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Exception\RuntimeException as DbRuntimeException;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

#[Group('unit')]
final class InstallHandlerTest extends TestCase
{
    private const string ADMIN_FORM_VALUE = 'correct horse battery';

    /** One character short of InstallHandler::MINIMUM_PASSWORD_LENGTH. */
    private const string SHORT_FORM_VALUE = '1234567';

    /** @var array<string, mixed> */
    private array $rendered = [];

    private InstallerService&Stub $installer;

    private DiagnosticsService&Stub $diagnostics;

    private DatabaseConfigWriter&Stub $writer;

    private Adapter&Stub $adapter;

    /**
     * @return array<string, array{bool, string}>
     */
    public static function diagnosticsOutcomes(): array
    {
        return [
            'passed' => [true, '/setup?step=diagnostics&success=1'],
            'failed' => [false, '/setup?step=diagnostics&error=1'],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function incompleteAdministrators(): array
    {
        $complete = [
            'admin_username' => 'root',
            'admin_email'    => 'root@example.com',
            'admin_password' => self::ADMIN_FORM_VALUE,
        ];

        return [
            'nothing given'     => [[]],
            'no username'       => [[...$complete, 'admin_username' => null]],
            'blank username'    => [[...$complete, 'admin_username' => '  ']],
            'no email'          => [[...$complete, 'admin_email' => null]],
            'blank email'       => [[...$complete, 'admin_email' => '']],
            'no password'       => [[...$complete, 'admin_password' => null]],
            'short password'    => [[...$complete, 'admin_password' => self::SHORT_FORM_VALUE]],
            'non-string values' => [['admin_username' => ['root'], 'admin_email' => 1, 'admin_password' => true]],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, string|null, string|null}>
     */
    public static function messageQueries(): array
    {
        return [
            'generic success'           => [
                ['step' => 'test-connection', 'success' => '1'],
                'Operation completed successfully.',
                null,
            ],
            'generic error'             => [
                ['step' => 'database-config', 'error' => '1'],
                null,
                'Operation failed. Please check the details below.',
            ],
            'specific messages'         => [
                ['step' => 'admin-user', 'success' => 'Saved', 'error' => 'Disk a+b full'],
                'Saved',
                'Disk a+b full',
            ],
            'non-string message'        => [['step' => 'admin-user', 'error' => ['x']], null, null],
            'messages ignored off-step' => [['success' => '1', 'error' => '1'], null, null],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, bool, string}>
     */
    public static function stepQueries(): array
    {
        return [
            'welcome without a database' => [[], false, 'welcome'],
            'installed with a database'  => [[], true, 'installed'],
            'forced reinstall'           => [['force' => '1'], true, 'welcome'],
            'explicit step'              => [['step' => 'admin-user'], true, 'admin-user'],
            'non-string step is ignored' => [['step' => ['x']], false, 'welcome'],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unknownPostBodies(): array
    {
        return [
            'unknown action' => [['action' => 'drop-tables']],
            'no action'      => [[]],
            'no parsed body' => [null],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function adminForm(): array
    {
        return [
            'action'         => 'install',
            'admin_username' => 'root',
            'admin_email'    => 'root@example.com',
            'admin_password' => self::ADMIN_FORM_VALUE,
        ];
    }

    #[Test]
    public function continuesToTheAdminStepWhenTheDatabaseAnswers(): void
    {
        $adapter = $this->createMock(Adapter::class);
        $adapter->expects($this->once())->method('query')->with('SELECT 1', Adapter::QUERY_MODE_EXECUTE);
        $this->adapter = $adapter;

        $response = $this->post(['action' => 'test-connection']);

        static::assertSame('/setup?step=admin-user&success=1', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function installsWithTheAdministratorFromTheForm(): void
    {
        $installer = $this->createMock(InstallerService::class);
        $installer->expects($this->once())
            ->method('install')
            ->with(['username' => 'root', 'email' => 'root@example.com', 'password' => self::ADMIN_FORM_VALUE])
            ->willReturn(true);
        $this->installer = $installer;
        $cache           = $this->createMock(CacheService::class);
        $cache->expects($this->once())->method('clearAll')->willReturn(['success' => true, 'message' => '']);

        $installer->method('validate')->willReturn(['Missing default roles']);

        $response = $this->post(self::adminForm(), $cache);

        static::assertSame('setup::complete', (string) $response->getBody());
        static::assertSame(
            ['title' => 'Installation Complete', 'errors' => ['Missing default roles']],
            $this->rendered,
        );
    }

    #[Test]
    public function passesThePageStateToTheTemplate(): void
    {
        $this->installer->method('isInstalled')->willReturn(false);
        $this->installer->method('databaseFileExists')->willReturn(true);

        $this->handle(new ServerRequest());

        static::assertSame(
            [
                'title'       => 'Contenir CMS Setup',
                'step'        => 'installed',
                'error'       => null,
                'success'     => null,
                'diagnostics' => null,
                'formData'    => [],
                'isInstalled' => false,
                'dbExists'    => true,
            ],
            $this->rendered,
        );
    }

    #[Test]
    #[DataProvider('diagnosticsOutcomes')]
    public function redirectsAfterRunningTheDiagnostics(bool $passed, string $location): void
    {
        $this->diagnostics->method('runAll')->willReturn(['success' => $passed, 'results' => [], 'errors' => []]);
        $cache = $this->createMock(CacheService::class);
        $cache->expects($this->once())->method('clearAll')->willReturn(['success' => true, 'message' => '']);

        $response = $this->post(['action' => 'diagnostics'], $cache);

        static::assertSame($location, $response->getHeaderLine('Location'));
    }

    /**
     * @param array<string, mixed> $form
     */
    #[Test]
    #[DataProvider('incompleteAdministrators')]
    public function refusesToInstallWithoutCompleteAdministratorCredentials(array $form): void
    {
        $installer = $this->createMock(InstallerService::class);
        $installer->expects($this->never())->method('install');
        $this->installer = $installer;

        $response = $this->post(['action' => 'install', ...$form]);

        static::assertSame(
            '/setup?step=admin-user&error=An+administrator+username%2C+email+and+a+password+of+at+least+8+characters+are+required.',
            $response->getHeaderLine('Location'),
        );
    }

    #[Test]
    #[DataProvider('unknownPostBodies')]
    public function rendersThePageForAPostWithoutAKnownAction(mixed $body): void
    {
        $request = (new ServerRequest(method: 'POST'))->withParsedBody($body);

        $response = $this->handle($request);

        static::assertSame([200, 'welcome'], [$response->getStatusCode(), $this->rendered['step']]);
    }

    /**
     * @param array<string, mixed> $query
     */
    #[Test]
    #[DataProvider('stepQueries')]
    public function rendersTheStepForTheRequest(array $query, bool $dbExists, string $step): void
    {
        $this->installer->method('databaseFileExists')->willReturn($dbExists);

        $this->handle((new ServerRequest())->withQueryParams($query));

        static::assertSame($step, $this->rendered['step']);
    }

    #[Test]
    public function rendersTheWelcomeStepBeforeTheDatabasePathIsConfigured(): void
    {
        $this->installer
            ->method('databaseFileExists')
            ->willThrowException(new RuntimeException('CMS database path not configured'));

        $this->handle(new ServerRequest());

        static::assertSame(['welcome', false], [$this->rendered['step'], $this->rendered['dbExists']]);
    }

    #[Test]
    public function reportsFailedDiagnosticsOnTheDiagnosticsStep(): void
    {
        $this->diagnostics
            ->method('runAll')
            ->willReturn(['success' => false, 'results' => [], 'errors' => ['php_version' => 'Too old']]);

        $this->handle((new ServerRequest())->withQueryParams(['step' => 'diagnostics', 'error' => '1']));

        static::assertSame(
            [null, 'Some system checks failed. Please resolve the issues before continuing.'],
            [$this->rendered['success'], $this->rendered['error']],
        );
    }

    #[Test]
    public function returnsToTheAdminStepWhenTheInstallationDoesNotValidate(): void
    {
        $this->installer->method('install')->willReturn(false);

        $response = $this->post(self::adminForm());

        static::assertSame('/setup?step=admin-user&error=validation-failed', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function returnsToTheAdminStepWhenTheInstallationFails(): void
    {
        $this->installer->method('install')->willThrowException(new RuntimeException('Database installation failed'));

        $response = $this->post(self::adminForm());

        static::assertSame(
            '/setup?step=admin-user&error=Database+installation+failed',
            $response->getHeaderLine('Location'),
        );
    }

    #[Test]
    public function returnsToTheConnectionTestWhenTheDatabaseFails(): void
    {
        $this->adapter->method('query')->willThrowException(new DbRuntimeException('Connection refused'));

        $response = $this->post(['action' => 'test-connection']);

        static::assertSame(
            '/setup?step=test-connection&error=Connection+refused',
            $response->getHeaderLine('Location'),
        );
    }

    #[Test]
    public function returnsToTheDatabaseFormWhenTheConfigCannotBeWritten(): void
    {
        $this->writer->method('write')->willThrowException(new RuntimeException('Config directory is not writable'));

        $response = $this->post(['action' => 'database-config']);

        static::assertSame(
            '/setup?step=database-config&error=Config+directory+is+not+writable',
            $response->getHeaderLine('Location'),
        );
    }

    #[Test]
    public function runsTheDiagnosticsOnTheDiagnosticsStep(): void
    {
        $report = ['success' => true, 'results' => [], 'errors' => []];
        $this->diagnostics->method('runAll')->willReturn($report);

        $this->handle((new ServerRequest())->withQueryParams(['step' => 'diagnostics', 'success' => '1']));

        static::assertSame(
            [$report, 'All system checks passed.', null],
            [$this->rendered['diagnostics'], $this->rendered['success'], $this->rendered['error']],
        );
    }

    #[Test]
    public function savesTheDatabaseConfigAndClearsTheCache(): void
    {
        $data   = ['action' => 'database-config', 'cms_database' => 'data/cms/cms.db'];
        $writer = $this->createMock(DatabaseConfigWriter::class);
        $writer->expects($this->once())->method('write')->with($data)->willReturn(true);
        $this->writer = $writer;
        $cache        = $this->createMock(CacheService::class);
        $cache->expects($this->once())->method('clearAll')->willReturn(['success' => true, 'message' => '']);

        $response = $this->post($data, $cache);

        static::assertSame('/setup?step=test-connection&success=1', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function showsTheDatabaseFormWhenProceedingFromDiagnostics(): void
    {
        $response = $this->post(['action' => 'database-config', 'proceed' => '1']);

        static::assertSame('/setup?step=database-config', $response->getHeaderLine('Location'));
    }

    /**
     * @param array<string, mixed> $query
     */
    #[Test]
    #[DataProvider('messageQueries')]
    public function showsTheMessagesFromTheRedirect(array $query, ?string $success, ?string $error): void
    {
        $this->handle((new ServerRequest())->withQueryParams($query));

        static::assertSame([$success, $error], [$this->rendered['success'], $this->rendered['error']]);
    }

    protected function setUp(): void
    {
        $this->rendered    = [];
        $this->installer   = $this->createStub(InstallerService::class);
        $this->diagnostics = $this->createStub(DiagnosticsService::class);
        $this->writer      = $this->createStub(DatabaseConfigWriter::class);
        $this->adapter     = $this->createStub(Adapter::class);
    }

    private function handle(ServerRequest $request, ?CacheService $cache = null): ResponseInterface
    {
        $renderer = $this->createStub(TemplateRendererInterface::class);
        $renderer->method('render')
            ->willReturnCallback(function (string $name, array $params): string {
                $this->rendered = $params;

                return $name;
            });

        return (new InstallHandler(
            $renderer,
            $this->installer,
            $this->diagnostics,
            $this->writer,
            $cache ?? $this->createStub(CacheService::class),
            $this->adapter,
        ))->handle($request);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(array $body, ?CacheService $cache = null): ResponseInterface
    {
        return $this->handle((new ServerRequest(method: 'POST'))->withParsedBody($body), $cache);
    }
}
