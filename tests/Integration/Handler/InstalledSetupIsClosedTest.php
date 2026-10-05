<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Integration\Handler;

use Contenir\Service\Migration\MigrationService;
use Contenir\Setup\Handler\CompleteHandler;
use Contenir\Setup\Handler\InstallHandler;
use Contenir\Setup\Service\CacheService;
use Contenir\Setup\Service\DatabaseConfigWriter;
use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Service\InstallerService;
use Contenir\Setup\Tests\Trait\TemporaryDirectoryTrait;
use Laminas\Db\Adapter\Adapter;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Manager\UserManager;

use function file_get_contents;
use function file_put_contents;
use function md5_file;
use function mkdir;

/**
 * An installed site, with the real installer, config writer and cache
 * services on a temporary application root and SQLite database: every setup
 * request answers 404 and leaves the files, database and users untouched.
 */
#[Group('integration')]
#[Group('security')]
final class InstalledSetupIsClosedTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private const string FORM_VALUE = 'long enough value';

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function posts(): array
    {
        return [
            'diagnostics'       => [['action' => 'diagnostics']],
            'database config'   => [['action' => 'database-config', 'cms_database' => '/tmp/other.db']],
            'test connection'   => [['action' => 'test-connection']],
            'new administrator' => [[
                'action'         => 'install',
                'admin_username' => 'intruder',
                'admin_email'    => 'intruder@example.com',
                'admin_password' => self::FORM_VALUE,
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[Test]
    #[DataProvider('posts')]
    public function refusesEveryPostAndWritesNothing(array $body): void
    {
        $before = $this->fingerprint();

        $response = $this->handler()->handle((new ServerRequest(method: 'POST'))->withParsedBody($body));

        static::assertSame(404, $response->getStatusCode());
        static::assertSame($before, $this->fingerprint());
    }

    #[Test]
    public function refusesTheCompletionPage(): void
    {
        $response = (new CompleteHandler(
            $this->createStub(TemplateRendererInterface::class),
            $this->installer(),
        ))->handle(new ServerRequest());

        static::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function refusesTheForcedReinstallPage(): void
    {
        $response = $this->handler()->handle((new ServerRequest())->withQueryParams(['force' => '1']));

        static::assertSame(404, $response->getStatusCode());
    }

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        mkdir($this->path('data/cms'), recursive: true);
        mkdir($this->path('data/cache'), recursive: true);
        mkdir($this->path('config/autoload'), recursive: true);
        file_put_contents($this->path('data/cache/config-cache.php'), data: '<?php return [];');
        file_put_contents($this->path('config/autoload/db.local.php'), data: '<?php return ["original" => true];');

        $adapter = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => $this->path('data/cms/cms.db')]);
        $adapter->query('CREATE TABLE user (role_id TEXT)', Adapter::QUERY_MODE_EXECUTE);
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    /**
     * @return array<string, string|false>
     */
    private function fingerprint(): array
    {
        return [
            'db.local.php' => file_get_contents($this->path('config/autoload/db.local.php')),
            'cache'        => file_get_contents($this->path('data/cache/config-cache.php')),
            'database'     => md5_file($this->path('data/cms/cms.db')),
        ];
    }

    private function handler(): InstallHandler
    {
        return new InstallHandler(
            $this->createStub(TemplateRendererInterface::class),
            $this->installer(),
            new DiagnosticsService([], autoFix: true, basePath: $this->tmpDir),
            new DatabaseConfigWriter($this->path('config/autoload/db.local.php')),
            new CacheService(['cache_dir' => $this->path('data/cache')]),
            new Adapter(['driver' => 'Pdo_Sqlite', 'database' => $this->path('data/cms/cms.db')]),
        );
    }

    private function installer(): InstallerService
    {
        $migrations = $this->createMock(MigrationService::class);
        $migrations->method('getStatus')->willReturn(['current_version' => 3, 'is_up_to_date' => true]);
        $migrations->expects($this->never())->method('migrate');
        $users = $this->createMock(UserManager::class);
        $users->expects($this->never())->method('createUser');

        return new InstallerService(
            new Adapter(['driver' => 'Pdo_Sqlite', 'database' => $this->path('data/cms/cms.db')]),
            ['db' => ['cms' => ['database' => $this->path('data/cms/cms.db')]]],
            $migrations,
            $users,
        );
    }
}
