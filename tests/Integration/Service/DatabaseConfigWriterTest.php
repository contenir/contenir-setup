<?php

declare(strict_types=1);

namespace Contenir\Setup\Tests\Integration\Service;

use Contenir\Setup\Service\DatabaseConfigWriter;
use Contenir\Setup\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function chdir;
use function chmod;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function mkdir;

#[Group('integration')]
final class DatabaseConfigWriterTest extends TestCase
{
    private const string FIELD_VALUE = 'p@ss';

    use TemporaryDirectoryTrait;

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function formData(): array
    {
        return [
            'cms and full site'  => [
                [
                    'cms_database'  => 'data/cms/cms.db',
                    'site_hostname' => 'db.example.com',
                    'site_port'     => '3307',
                    'site_database' => 'site',
                    'site_username' => 'site_user',
                    'site_password' => self::FIELD_VALUE,
                ],
                [
                    'db' => [
                        'cms'  => ['database' => 'data/cms/cms.db'],
                        'site' => [
                            'hostname' => 'db.example.com',
                            'port'     => 3307,
                            'database' => 'site',
                            'username' => 'site_user',
                            'password' => self::FIELD_VALUE,
                        ],
                    ],
                ],
            ],
            'empty site fields'  => [
                ['cms_database' => 'cms.db', 'site_hostname' => '', 'site_port' => ''],
                ['db' => ['cms' => ['database' => 'cms.db']]],
            ],
            'nothing configured' => [[], ['db' => []]],
        ];
    }

    /**
     * @return array<string, array{callable(string): void, bool}>
     */
    public static function writability(): array
    {
        return [
            'new file in writable directory' => [static function (string $dir): void {}, true],
            'writable existing file'         => [
                static function (string $dir): void {
                    file_put_contents("{$dir}/db.local.php", data: '<?php return [];');
                },
                true,
            ],
            'read-only existing file'        => [
                static function (string $dir): void {
                    file_put_contents("{$dir}/db.local.php", data: '<?php return [];');
                    chmod("{$dir}/db.local.php", permissions: 0o444);
                },
                false,
            ],
            'read-only directory'            => [
                static function (string $dir): void {
                    chmod($dir, permissions: 0o555);
                },
                false,
            ],
        ];
    }

    #[Test]
    public function cannotWriteIntoAMissingDirectory(): void
    {
        static::assertFalse((new DatabaseConfigWriter($this->path('missing/db.local.php')))->isWritable());
    }

    #[Test]
    public function createsTheConfigDirectoryReadableByEveryone(): void
    {
        (new DatabaseConfigWriter($this->path('config/autoload/db.local.php')))->write([]);

        static::assertSame(0o755, $this->permissionsOf('config/autoload'));
    }

    #[Test]
    public function failsForAReadOnlyDirectory(): void
    {
        $this->skipWhenRunningAsRoot();
        chmod($this->tmpDir, permissions: 0o555);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Config directory is not writable: {$this->tmpDir}");

        (new DatabaseConfigWriter($this->path('db.local.php')))->write([]);
    }

    #[Test]
    public function failsForAReadOnlyFile(): void
    {
        $this->skipWhenRunningAsRoot();
        $path = $this->path('db.local.php');
        file_put_contents($path, data: '<?php return [];');
        chmod($path, permissions: 0o444);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Config file is not writable: {$path}");

        (new DatabaseConfigWriter($path))->write([]);
    }

    #[Test]
    public function failsWhenTheDirectoryCannotBeCreated(): void
    {
        $this->skipWhenRunningAsRoot();
        chmod($this->tmpDir, permissions: 0o555);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Failed to create config directory: {$this->tmpDir}/config");

        (new DatabaseConfigWriter($this->path('config/db.local.php')))->write([]);
    }

    #[Test]
    public function failsWhenTheFileCannotBeWritten(): void
    {
        $path = $this->path('db.local.php');
        mkdir($path);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Failed to write config file: {$path}");

        (new DatabaseConfigWriter($path))->write([]);
    }

    #[Test]
    public function replacesAnExistingFile(): void
    {
        $path = $this->path('db.local.php');
        file_put_contents($path, data: '<?php return ["old" => true];');

        (new DatabaseConfigWriter($path))->write(['cms_database' => 'new.db']);

        static::assertSame(['db' => ['cms' => ['database' => 'new.db']]], require $path);
    }

    /**
     * @param callable(string): void $arrange
     */
    #[Test]
    #[DataProvider('writability')]
    public function reportsWhetherTheFileCanBeWritten(callable $arrange, bool $writable): void
    {
        $this->skipWhenRunningAsRoot();
        $arrange($this->tmpDir);

        static::assertSame($writable, (new DatabaseConfigWriter($this->path('db.local.php')))->isWritable());
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('formData')]
    public function writesALoadableConfigFile(array $data, array $expected): void
    {
        $path = $this->path('config/autoload/db.local.php');

        $written = (new DatabaseConfigWriter($path))->write($data);

        static::assertTrue($written);
        static::assertSame($expected, require $path);
    }

    #[Test]
    public function writesStrictTypesShortArraySource(): void
    {
        $path = $this->path('db.local.php');

        (new DatabaseConfigWriter($path))->write(['cms_database' => 'cms.db']);

        static::assertSame(
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'db' => [\n        'cms' => [\n            'database' => 'cms.db',\n        ],\n    ],\n];\n",
            file_get_contents($path),
        );
    }

    #[Test]
    public function writesToConfigAutoloadInTheWorkingDirectoryByDefault(): void
    {
        $cwd = (string) getcwd();
        chdir($this->tmpDir);

        try {
            (new DatabaseConfigWriter())->write(['cms_database' => 'cms.db']);
        } finally {
            chdir($cwd);
        }

        static::assertFileExists($this->path('config/autoload/db.local.php'));
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
