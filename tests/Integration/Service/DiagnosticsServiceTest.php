<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Integration\Service;

use Contenir\Setup\Service\DiagnosticsService;
use Contenir\Setup\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function chdir;
use function chmod;
use function getcwd;
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
    public function checksTheWorkingDirectoryByDefault(): void
    {
        $this->createDirectories();
        $cwd = (string) getcwd();
        chdir($this->tmpDir);

        try {
            $report = (new DiagnosticsService([]))->runAll();
        } finally {
            chdir($cwd);
        }

        static::assertSame('Directory exists: config/autoload', $report['results']['dir_config/autoload']['message']);
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
        static::assertTrue(is_writable($this->path('data/cache')));
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
        $cwd  = (string) getcwd();
        $gone = $this->path('gone');
        mkdir($gone);
        chdir($gone);
        rmdir($gone);

        try {
            $service = new DiagnosticsService([], autoFix: false);
            chdir($this->tmpDir);
            $passed = $service->runAll()['success'];
        } finally {
            chdir($cwd);
        }

        static::assertTrue($passed);
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
