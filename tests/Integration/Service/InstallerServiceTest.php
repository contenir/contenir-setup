<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Integration\Service;

use Contenir\Service\Migration\MigrationService;
use Contenir\Setup\Service\InstallerService;
use Contenir\Setup\Tests\Trait\TemporaryDirectoryTrait;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\ResultSet\ResultSet;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use User\Manager\UserManager;

use function basename;
use function chmod;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;

/**
 * InstallerService against a real SQLite database file in a temp directory,
 * with the host application's migration service and user manager doubled.
 */
#[Group('integration')]
final class InstallerServiceTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private const array UP_TO_DATE = ['current_version' => 3, 'latest_version' => 3, 'is_up_to_date' => true];

    private string $dbPath;

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function incompleteStatuses(): array
    {
        return [
            'pending migrations'           => [['current_version' => 2, 'is_up_to_date' => false]],
            'nothing applied'              => [['current_version' => 0, 'is_up_to_date' => true]],
            'negative version'             => [['current_version' => -1, 'is_up_to_date' => true]],
            'non-integer version'          => [['current_version' => '3', 'is_up_to_date' => true]],
            'up to date without a version' => [['is_up_to_date' => true]],
            'version without up-to-date'   => [['current_version' => 3]],
            'truthy up-to-date flag'       => [['current_version' => 3, 'is_up_to_date' => 1]],
            'empty status'                 => [[]],
        ];
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function unconfiguredDatabases(): array
    {
        return [
            'no db config'       => [[]],
            'db is not an array' => [['db' => 'sqlite']],
            'no cms entry'       => [['db' => ['site' => []]]],
            'cms not an array'   => [['db' => ['cms' => 'cms.db']]],
            'no database key'    => [['db' => ['cms' => []]]],
            'empty database'     => [['db' => ['cms' => ['database' => '']]]],
            'non-string'         => [['db' => ['cms' => ['database' => 42]]]],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unreadableCounts(): array
    {
        return [
            'not a result set'    => ['statement'],
            'no row'              => [[]],
            'array row, no count' => [[['total' => 5]]],
            'non-numeric count'   => [[['count' => 'many']]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function resultSet(array $rows): ResultSet
    {
        $resultSet = new ResultSet(ResultSet::TYPE_ARRAY);
        $resultSet->initialize($rows);

        return $resultSet;
    }

    #[Test]
    public function backsUpADatabaseFileOfOneByte(): void
    {
        mkdir($this->path('cms'));
        file_put_contents($this->dbPath, data: 'x');

        $this->installer()->repair();

        static::assertCount(1, (array) glob("{$this->dbPath}.backup.*"));
    }

    #[Test]
    public function countsAnObjectRowAsZero(): void
    {
        $this->createDatabase();
        $resultSet = $this->createStub(ResultSet::class);
        $resultSet->method('current')->willReturn(new stdClass());
        $adapter = $this->createStub(Adapter::class);
        $adapter->method('query')->willReturn($resultSet);

        $errors = $this->installer($this->migrations(self::UP_TO_DATE), adapter: $adapter)->validate();

        static::assertSame(['No administrator user found', 'Missing default roles'], $errors);
    }

    /**
     * @param 'statement'|list<array<string, mixed>> $rows
     */
    #[Test]
    #[DataProvider('unreadableCounts')]
    public function countsAnUnreadableResultAsZero(string|array $rows): void
    {
        $this->createDatabase();
        $adapter = $this->createStub(Adapter::class);
        $adapter->method('query')
            ->willReturn(
                'statement' === $rows ? $this->createStub(StatementInterface::class) : self::resultSet($rows),
            );

        $errors = $this->installer($this->migrations(self::UP_TO_DATE), adapter: $adapter)->validate();

        static::assertSame(['No administrator user found', 'Missing default roles'], $errors);
    }

    #[Test]
    public function createsTheAdministratorAsAnActiveAdministrator(): void
    {
        $users = $this->createMock(UserManager::class);
        $users->expects($this->once())
            ->method('createUser')
            ->with(['username' => 'root', 'role_id' => 'administrator', 'active' => 'active'])
            ->willReturn(new stdClass());

        $this->installer(users: $users)->createAdminUser(['username' => 'root']);
    }

    #[Test]
    public function createsTheDatabaseDirectoryReadableByEveryone(): void
    {
        $this->installer()->install();

        static::assertSame(0o755, $this->permissionsOf('cms'));
    }

    #[Test]
    public function failsToInstallIntoAReadOnlyDirectory(): void
    {
        $this->skipWhenRunningAsRoot();
        mkdir($this->path('cms'), permissions: 0o555);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            "Database installation failed: Database directory is not writable: {$this->tmpDir}/cms",
        );

        $this->installer()->install();
    }

    #[Test]
    public function failsToInstallWhenTheDatabaseDirectoryCannotBeCreated(): void
    {
        $this->skipWhenRunningAsRoot();
        chmod($this->tmpDir, permissions: 0o555);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            "Database installation failed: Failed to create database directory: {$this->tmpDir}/cms",
        );

        $this->installer()->install();
    }

    #[Test]
    public function failsToRepairWhenTheDatabaseCannotBeRemoved(): void
    {
        $this->skipWhenRunningAsRoot();
        mkdir($this->path('cms'));
        file_put_contents($this->dbPath, data: '');
        chmod($this->path('cms'), permissions: 0o555);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Database repair failed: Failed to remove the database {$this->dbPath}");

        $this->installer()->repair();
    }

    #[Test]
    public function failsToRepairWithoutADatabasePath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database repair failed: CMS database path not configured');
        $this->expectExceptionCode(0);

        $this->installer(config: [])->repair();
    }

    #[Test]
    public function findsADatabaseFileOfOneByte(): void
    {
        mkdir($this->path('cms'));
        file_put_contents($this->dbPath, data: 'x');

        static::assertTrue($this->installer()->databaseFileExists());
    }

    #[Test]
    public function findsADatabaseFileWithContent(): void
    {
        $this->createDatabase();

        static::assertTrue($this->installer()->databaseFileExists());
    }

    #[Test]
    public function installsByMigratingAndCreatingTheAdministrator(): void
    {
        $migrations = $this->createMock(MigrationService::class);
        $migrations->expects($this->once())
            ->method('migrate')
            ->willReturnCallback(function (): array {
                $this->createDatabase();

                return [];
            });
        $migrations->method('getStatus')->willReturn(self::UP_TO_DATE);
        $users = $this->createMock(UserManager::class);
        $users->expects($this->once())->method('createUser')->willReturn(new stdClass());

        static::assertTrue($this->installer($migrations, $users)->install(['username' => 'root']));
        static::assertDirectoryExists($this->path('cms'));
    }

    #[Test]
    public function installsWithoutAnAdministratorWhenNoneIsGiven(): void
    {
        $users = $this->createMock(UserManager::class);
        $users->expects($this->never())->method('createUser');

        static::assertFalse($this->installer(users: $users)->install());
    }

    #[Test]
    public function isInstalledOnceTheFirstMigrationIsApplied(): void
    {
        $this->createDatabase();

        static::assertTrue(
            $this->installer($this->migrations(['current_version' => 1, 'is_up_to_date' => true]))->isInstalled(),
        );
    }

    #[Test]
    public function isInstalledWhenTheDatabaseExistsAndMigrationsAreCurrent(): void
    {
        $this->createDatabase();

        static::assertTrue($this->installer($this->migrations(self::UP_TO_DATE))->isInstalled());
    }

    /**
     * @param array<string, mixed> $status
     */
    #[Test]
    #[DataProvider('incompleteStatuses')]
    public function isNotInstalledUntilEveryMigrationIsApplied(array $status): void
    {
        $this->createDatabase();

        static::assertFalse($this->installer($this->migrations($status))->isInstalled());
    }

    #[Test]
    public function isNotInstalledWhenTheMigrationStatusFails(): void
    {
        $this->createDatabase();
        $migrations = $this->createStub(MigrationService::class);
        $migrations->method('getStatus')->willThrowException(new LogicException('no migrations table'));

        static::assertFalse($this->installer($migrations)->isInstalled());
    }

    #[Test]
    public function isNotInstalledWithoutADatabaseFile(): void
    {
        static::assertFalse($this->installer($this->migrations(self::UP_TO_DATE))->isInstalled());
    }

    #[Test]
    public function isNotInstalledWithoutADatabasePath(): void
    {
        static::assertFalse($this->installer(config: [])->isInstalled());
    }

    #[Test]
    public function keepsTheDatabaseWhenTheBackupFails(): void
    {
        $this->skipWhenRunningAsRoot();
        $this->createDatabase();
        chmod($this->path('cms'), permissions: 0o555);

        try {
            $this->installer()->repair();
            static::fail('Expected the repair to fail.');
        } catch (RuntimeException $e) {
            static::assertStringStartsWith(
                'Database repair failed: Failed to back up the database to ',
                $e->getMessage(),
            );
        }

        static::assertFileExists($this->dbPath);
    }

    #[Test]
    public function namesTheBackupAfterTheRepairTime(): void
    {
        $this->createDatabase();

        $this->installer()->repair();

        $backups = (array) glob("{$this->dbPath}.backup.*");
        static::assertMatchesRegularExpression(
            '/^cms\.db\.backup\.\d{4}-\d{2}-\d{2}-\d{6}$/',
            basename((string) ($backups[0] ?? '')),
        );
    }

    #[Test]
    public function readsANumericStringCount(): void
    {
        $this->createDatabase();
        $adapter = $this->createStub(Adapter::class);
        $adapter->method('query')->willReturn(self::resultSet([['count' => '3']]));

        static::assertSame([], $this->installer($this->migrations(self::UP_TO_DATE), adapter: $adapter)->validate());
    }

    #[Test]
    public function readsTheDatabasePathFromConfig(): void
    {
        static::assertSame($this->dbPath, $this->installer()->getDatabasePath());
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Test]
    #[DataProvider('unconfiguredDatabases')]
    public function rejectsAMissingDatabasePath(array $config): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CMS database path not configured');

        $this->installer(config: $config)->getDatabasePath();
    }

    #[Test]
    public function repairsAMissingDatabaseByInstalling(): void
    {
        $migrations = $this->createMock(MigrationService::class);
        $migrations->expects($this->once())->method('migrate')->willReturn([]);

        static::assertFalse($this->installer($migrations)->repair());
    }

    #[Test]
    public function repairsAnEmptyDatabaseWithoutABackup(): void
    {
        mkdir($this->path('cms'));
        file_put_contents($this->dbPath, data: '');

        $this->installer()->repair();

        static::assertSame([], glob("{$this->dbPath}.backup.*"));
    }

    #[Test]
    public function repairsByBackingUpAndReinstalling(): void
    {
        $this->createDatabase();
        $original   = file_get_contents($this->dbPath);
        $migrations = $this->createMock(MigrationService::class);
        $migrations->expects($this->once())->method('migrate')->willReturn([]);

        $repaired = $this->installer($migrations)->repair();

        $backups = (array) glob("{$this->dbPath}.backup.*");
        static::assertFalse($repaired);
        static::assertCount(1, $backups);
        static::assertSame($original, file_get_contents($backups[0]));
        static::assertFileDoesNotExist($this->dbPath);
    }

    #[Test]
    public function reportsAMissingAdministratorAndRoles(): void
    {
        $adapter = $this->createDatabase();
        $adapter->query("INSERT INTO role VALUES ('guest')", Adapter::QUERY_MODE_EXECUTE);
        $adapter->query("INSERT INTO user VALUES ('member')", Adapter::QUERY_MODE_EXECUTE);

        static::assertSame(
            ['No administrator user found', 'Missing default roles'],
            $this->installer($this->migrations(self::UP_TO_DATE), adapter: $adapter)->validate(),
        );
    }

    #[Test]
    public function reportsAnUninstalledDatabase(): void
    {
        static::assertSame(['Database is not installed'], $this->installer()->validate());
    }

    #[Test]
    public function reportsTablesItCannotQuery(): void
    {
        $adapter = $this->createDatabaseWithoutTables();

        $errors = $this->installer($this->migrations(self::UP_TO_DATE), adapter: $adapter)->validate();

        static::assertStringStartsWith('Failed to validate users: ', $errors[0]);
        static::assertStringStartsWith('Failed to validate roles: ', $errors[1]);
    }

    #[Test]
    public function treatsAnEmptyDatabaseFileAsMissing(): void
    {
        mkdir($this->path('cms'));
        file_put_contents($this->dbPath, data: '');

        static::assertFalse($this->installer()->databaseFileExists());
    }

    #[Test]
    public function validatesAnInstallationWithAnAdministratorAndDefaultRoles(): void
    {
        $adapter = $this->createDatabase();
        $adapter->query(
            "INSERT INTO role VALUES ('guest'), ('member'), ('administrator')",
            Adapter::QUERY_MODE_EXECUTE,
        );
        $adapter->query("INSERT INTO user VALUES ('administrator')", Adapter::QUERY_MODE_EXECUTE);

        static::assertSame([], $this->installer($this->migrations(self::UP_TO_DATE), adapter: $adapter)->validate());
    }

    #[Test]
    public function wrapsMigrationFailures(): void
    {
        $migrations = $this->createStub(MigrationService::class);
        $migrations->method('migrate')->willThrowException(new RuntimeException('syntax error'));

        try {
            $this->installer($migrations)->install();
            static::fail('Expected the installation to fail.');
        } catch (RuntimeException $e) {
            static::assertSame(
                ['Database installation failed: syntax error', 0, 'syntax error'],
                [$e->getMessage(), $e->getCode(), $e->getPrevious()?->getMessage()],
            );
        }
    }

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        $this->dbPath = $this->path('cms/cms.db');
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    /**
     * Create the SQLite database file with empty user and role tables.
     */
    private function createDatabase(): Adapter
    {
        $adapter = $this->createDatabaseWithoutTables();
        $adapter->query('CREATE TABLE IF NOT EXISTS user (role_id TEXT)', Adapter::QUERY_MODE_EXECUTE);
        $adapter->query('CREATE TABLE IF NOT EXISTS role (role_id TEXT)', Adapter::QUERY_MODE_EXECUTE);

        return $adapter;
    }

    /**
     * Create the SQLite database file with only a marker table.
     */
    private function createDatabaseWithoutTables(): Adapter
    {
        if (! is_dir($this->path('cms'))) {
            mkdir($this->path('cms'));
        }

        $adapter = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => $this->dbPath]);
        $adapter->query('CREATE TABLE IF NOT EXISTS marker (id INTEGER)', Adapter::QUERY_MODE_EXECUTE);

        return $adapter;
    }

    /**
     * @param array<array-key, mixed>|null $config
     */
    private function installer(
        ?MigrationService $migrations = null,
        ?UserManager $users = null,
        ?array $config = null,
        ?Adapter $adapter = null,
    ): InstallerService {
        return new InstallerService(
            $adapter ?? $this->createStub(Adapter::class),
            $config ?? ['db' => ['cms' => ['database' => $this->dbPath]]],
            $migrations ?? $this->migrations([]),
            $users ?? $this->createStub(UserManager::class),
        );
    }

    /**
     * @param array<string, mixed> $status
     */
    private function migrations(array $status): MigrationService
    {
        $migrations = $this->createStub(MigrationService::class);
        $migrations->method('getStatus')->willReturn($status);

        return $migrations;
    }
}
