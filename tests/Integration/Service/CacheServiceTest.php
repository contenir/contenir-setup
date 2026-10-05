<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Integration\Service;

use Contenir\Setup\Service\CacheService;
use Contenir\Setup\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_put_contents;
use function mkdir;

#[Group('integration')]
final class CacheServiceTest extends TestCase
{
    use TemporaryDirectoryTrait;

    #[Test]
    public function defaultsToTheDataCacheDirectoryOfTheWorkingDirectory(): void
    {
        mkdir($this->path('data/cache'), recursive: true);
        file_put_contents($this->path('data/cache/item.php'), data: '<?php return [];');

        $result = (new CacheService([]))->clearAll();

        static::assertSame(1, $result['deleted_count'] ?? null);
    }

    #[Test]
    public function deletesEveryCachedFileButKeepsTheDirectories(): void
    {
        mkdir($this->path('config'));
        file_put_contents($this->path('app-config.php'), data: '<?php return [];');
        file_put_contents($this->path('config/module.php'), data: '<?php return [];');

        $result = (new CacheService(['cache_dir' => $this->tmpDir]))->clearAll();

        static::assertSame(
            ['success' => true, 'message' => 'Successfully cleared 2 cache file(s)', 'deleted_count' => 2],
            $result,
        );
        static::assertDirectoryExists($this->path('config'));
        static::assertFileDoesNotExist($this->path('config/module.php'));
    }

    #[Test]
    public function failsForAReadOnlyCacheDirectory(): void
    {
        $this->skipWhenRunningAsRoot();
        chmod($this->tmpDir, permissions: 0o555);

        $result = (new CacheService(['cache_dir' => $this->tmpDir]))->clearAll();

        static::assertSame(
            [
                'success'       => false,
                'message'       => "Failed to clear cache: Directory is not writable: {$this->tmpDir}",
                'deleted_count' => 0,
            ],
            $result,
        );
    }

    #[Test]
    public function hasNothingToClearWithoutACacheDirectory(): void
    {
        $result = (new CacheService(['cache_dir' => $this->path('missing')]))->clearAll();

        static::assertSame(
            ['success' => true, 'message' => 'Cache directory does not exist - nothing to clear'],
            $result,
        );
    }

    #[Test]
    public function skipsFilesItCannotDelete(): void
    {
        $this->skipWhenRunningAsRoot();
        mkdir($this->path('locked'));
        file_put_contents($this->path('locked/kept.php'), data: '<?php return [];');
        file_put_contents($this->path('gone.php'), data: '<?php return [];');
        chmod($this->path('locked'), permissions: 0o555);

        $result = (new CacheService(['cache_dir' => $this->tmpDir]))->clearAll();

        static::assertSame(1, $result['deleted_count'] ?? null);
    }

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }
}
