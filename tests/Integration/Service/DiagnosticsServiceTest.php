<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Integration\Service;

use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Service\DiagnosticsServiceFactory;
use Contenir\Setup\Tests\TestAsset\Container\InMemoryContainer;
use Contenir\Setup\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_keys;
use function array_slice;
use function chdir;
use function chmod;
use function is_dir;
use function is_writable;
use function mkdir;
use function reset;
use function rmdir;
use function symlink;

#[Group('integration')]
final class DiagnosticsServiceTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private const array DIRECTORIES = ['data', 'data/cms', 'data/cache', 'config', 'config/autoload'];

    #[Test]
    public function checksTheConfigurationDirectory(): void
    {
        $this->createDirectories();
        $service = $this->service();

        $service->checkConfiguration();

        static::assertSame(
            ['config_directory' => ['success' => true, 'message' => 'Configuration directory is writable']],
            $service->getResults(),
        );
    }

    #[Test]
    public function checksTheDirectories(): void
    {
        $this->createDirectories();
        $service = $this->service();

        $service->checkDirectories();

        static::assertSame(
            ['dir_data', 'dir_data/cms', 'dir_data/cache', 'dir_config', 'dir_config/autoload'],
            array_keys($service->getResults()),
        );
    }

    #[Test]
    public function checksTheGivenBasePathRatherThanTheWorkingDirectory(): void
    {
        mkdir($this->path('site/config/autoload'), recursive: true);
        $service = new DiagnosticsService([], autoFix: false, basePath: $this->path('site'));

        $service->checkConfiguration();

        static::assertSame([], $service->getErrors());
    }

    #[Test]
    public function checksThePermissionsOfTheDirectoriesThatExist(): void
    {
        mkdir($this->path('config/autoload'), recursive: true);
        $service = $this->service();

        $service->checkPermissions();

        static::assertSame(
            ['writable_config/autoload' => ['success' => true, 'message' => 'Directory is writable: config/autoload']],
            $service->getResults(),
        );
    }

    #[Test]
    public function checksThePhpVersionAndExtensions(): void
    {
        $this->createDirectories();

        $results = $this->service()->runAll()['results'];

        static::assertSame(
            [
                'php_version',
                'extension_pdo',
                'extension_pdo_sqlite',
                'extension_json',
                'extension_mbstring',
                'extension_openssl',
                'extension_session',
            ],
            array_slice(array_keys($results), offset: 0, length: 7),
        );
    }

    #[Test]
    public function checksTheWorkingDirectoryByDefault(): void
    {
        $this->createDirectories();

        $report = (new DiagnosticsService([]))->runAll();

        static::assertSame('Directory exists: config/autoload', $report['results']['dir_config/autoload']['message']);
    }

    #[Test]
    public function createsMissingDirectoriesByDefault(): void
    {
        (new DiagnosticsService([], basePath: $this->tmpDir))->runAll();

        static::assertDirectoryExists($this->path('data/cache'));
    }

    #[Test]
    public function createsMissingDirectoriesReadableByEveryone(): void
    {
        $this->service()->runAll();

        static::assertSame(0o755, $this->permissionsOf('data/cms'));
    }

    #[Test]
    public function createsMissingDirectoriesWhenBuiltByTheFactory(): void
    {
        (new DiagnosticsServiceFactory())(new InMemoryContainer(['config' => []]))->runAll();

        static::assertDirectoryExists($this->path('data/cache'));
    }

    #[Test]
    public function createsMissingDirectoriesWithAutoFix(): void
    {
        $results = $this->service()->runAll()['results'];

        static::assertSame('Directory created: data/cms', $results['dir_data/cms']['message']);
        static::assertDirectoryExists($this->path('config/autoload'));
    }

    #[Test]
    public function makesReadOnlyDirectoriesWritableWithAutoFix(): void
    {
        $this->skipWhenRunningAsRoot();
        $this->createDirectories();
        chmod($this->path('data/cache'), permissions: 0o555);

        $results = $this->service()->runAll()['results'];

        static::assertSame('Directory permissions fixed: data/cache', $results['writable_data/cache']['message']);
        static::assertSame(0o755, $this->permissionsOf('data/cache'));
    }

    #[Test]
    public function passesForAPreparedApplication(): void
    {
        $this->createDirectories();

        $report = $this->service()->runAll();

        static::assertSame([], $report['errors']);
        static::assertTrue($report['success']);
    }

    #[Test]
    public function reportsDirectoriesItCannotCreate(): void
    {
        $this->skipWhenRunningAsRoot();
        chmod($this->tmpDir, permissions: 0o555);

        $errors = $this->service()->runAll()['errors'];

        static::assertSame('Failed to create directory: data', $errors['dir_data']);
    }

    #[Test]
    public function reportsDirectoriesItCannotMakeWritable(): void
    {
        $this->skipWhenRunningAsRoot();
        $this->createDirectories();
        $this->replaceWithRootOwnedDirectory('data/cms');

        $errors = $this->service()->runAll()['errors'];

        static::assertSame('Failed to make directory writable: data/cms', $errors['writable_data/cms']);
    }

    #[Test]
    public function reportsMissingDirectoriesWithoutAutoFix(): void
    {
        $report = $this->service(autoFix: false)->runAll();

        static::assertSame(
            [
                'dir_data'            => 'Directory does not exist: data',
                'dir_data/cms'        => 'Directory does not exist: data/cms',
                'dir_data/cache'      => 'Directory does not exist: data/cache',
                'dir_config'          => 'Directory does not exist: config',
                'dir_config/autoload' => 'Directory does not exist: config/autoload',
                'config_directory'    => 'Configuration directory is not writable or does not exist',
            ],
            $report['errors'],
        );
    }

    #[Test]
    public function reportsReadOnlyDirectoriesWithoutAutoFix(): void
    {
        $this->skipWhenRunningAsRoot();
        $this->createDirectories();
        chmod($this->path('config/autoload'), permissions: 0o555);

        $errors = $this->service(autoFix: false)->runAll()['errors'];

        static::assertSame(
            [
                'writable_config/autoload' => 'Directory is not writable: config/autoload',
                'config_directory'         => 'Configuration directory is not writable or does not exist',
            ],
            $errors,
        );
    }

    #[Test]
    public function resolvesAgainstTheCurrentDirectoryWhenTheWorkingDirectoryIsGone(): void
    {
        $this->createDirectories();
        $gone = $this->path('gone');
        mkdir($gone);
        chdir($gone);
        rmdir($gone);

        $service = new DiagnosticsService([], autoFix: false);
        chdir($this->tmpDir);

        static::assertTrue($service->runAll()['success']);
    }

    #[Test]
    public function startsEachRunAfresh(): void
    {
        $service = $this->service(autoFix: false);
        $service->runAll();
        $this->createDirectories();

        static::assertTrue($service->runAll()['success']);
    }

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    private function createDirectories(): void
    {
        foreach (self::DIRECTORIES as $directory) {
            mkdir($this->path($directory), recursive: true);
        }
    }

    /**
     * Point the directory at one owned by root, so chmod() fails.
     */
    private function replaceWithRootOwnedDirectory(string $directory): void
    {
        $rootOwned = array_filter(
            ['/usr', '/bin'],
            static fn(string $dir): bool => is_dir($dir) && ! is_writable($dir),
        );
        if ([] === $rootOwned) {
            static::markTestSkipped('No read-only root-owned directory available.');
        }

        chmod($this->path($directory), permissions: 0o755);
        rmdir($this->path($directory));
        symlink((string) reset($rootOwned), $this->path($directory));
    }

    private function service(bool $autoFix = true): DiagnosticsService
    {
        return new DiagnosticsService([], $autoFix, $this->tmpDir);
    }
}
