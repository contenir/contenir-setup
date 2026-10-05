<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use function chmod;
use function extension_loaded;
use function getcwd;
use function is_dir;
use function is_writable;
use function mkdir;
use function version_compare;

use const PHP_VERSION;

/**
 * Checks the PHP version and extensions, and that the application's data and
 * config directories exist and are writable. With auto-fix on, missing
 * directories are created and read-only ones made writable (0755).
 *
 * Directories are resolved against the base path: the working directory by
 * default, which Mezzio sets to the application root.
 *
 * @psalm-type Result = array{success: bool, message: string}
 * @psalm-type Report = array{success: bool, results: array<string, Result>, errors: array<string, string>}
 *
 * @api
 *
 * @mago-expect lint:too-many-methods The ten public methods are the 0.x API.
 */
class DiagnosticsService
{
    public const string MINIMUM_PHP_VERSION = '8.3.0';

    public const array REQUIRED_EXTENSIONS = ['pdo', 'pdo_sqlite', 'json', 'mbstring', 'openssl', 'session'];

    /** Directories that must exist, relative to the base path. */
    private const array REQUIRED_DIRECTORIES = ['data', 'data/cms', 'data/cache', 'config', 'config/autoload'];

    /** Directories that must be writable, relative to the base path. */
    private const array WRITABLE_DIRECTORIES = ['data', 'data/cms', 'data/cache', 'config/autoload'];

    /** @var array<string, Result> */
    private array $results = [];

    /** @var array<string, string> */
    private array $errors = [];

    private readonly string $basePath;

    /**
     * @param array<array-key, mixed> $config The application config (currently unused).
     * @param string|null $basePath The application root; defaults to the working directory.
     *
     * @mago-expect analysis:unused-property Kept for constructor compatibility with earlier releases.
     */
    public function __construct(
        private readonly array $config,
        private readonly bool $autoFix = true,
        ?string $basePath = null,
    ) {
        $cwd            = getcwd();
        $this->basePath = $basePath ?? (false === $cwd ? '.' : $cwd);
    }

    /**
     * Check the application config directory exists and is writable.
     */
    public function checkConfiguration(): void
    {
        $configDir = "{$this->basePath}/config/autoload";

        if (is_dir($configDir) && is_writable($configDir)) {
            $this->addResult('config_directory', 'Configuration directory is writable');

            return;
        }

        $this->addError('config_directory', 'Configuration directory is not writable or does not exist');
    }

    /**
     * Check the required directories exist, creating them when auto-fix is on.
     */
    public function checkDirectories(): void
    {
        foreach (self::REQUIRED_DIRECTORIES as $name) {
            $path = "{$this->basePath}/{$name}";

            $this->checkOrFix(
                "dir_{$name}",
                static fn(): bool => is_dir($path),
                static fn(): bool => mkdir($path, permissions: 0o755, recursive: true),
                ["Directory exists: {$name}", "Directory created: {$name}"],
                ["Directory does not exist: {$name}", "Failed to create directory: {$name}"],
            );
        }
    }

    /**
     * Check the writable directories that exist are writable, fixing their
     * permissions when auto-fix is on.
     */
    public function checkPermissions(): void
    {
        foreach (self::WRITABLE_DIRECTORIES as $name) {
            $path = "{$this->basePath}/{$name}";

            if (! is_dir($path)) {
                continue;
            }

            $this->checkOrFix(
                "writable_{$name}",
                static fn(): bool => is_writable($path),
                static fn(): bool => chmod($path, permissions: 0o755),
                ["Directory is writable: {$name}", "Directory permissions fixed: {$name}"],
                ["Directory is not writable: {$name}", "Failed to make directory writable: {$name}"],
            );
        }
    }

    /**
     * Check the required PHP extensions are loaded.
     *
     * @param list<non-empty-string> $requiredExtensions Defaults to REQUIRED_EXTENSIONS.
     */
    public function checkPhpExtensions(array $requiredExtensions = self::REQUIRED_EXTENSIONS): void
    {
        foreach ($requiredExtensions as $extension) {
            if (! extension_loaded($extension)) {
                $this->addError("extension_{$extension}", "Required PHP extension not loaded: {$extension}");

                continue;
            }

            $this->addResult("extension_{$extension}", "Extension {$extension} is loaded");
        }
    }

    /**
     * Check the PHP version meets the minimum requirement.
     *
     * @param string $currentVersion Defaults to the running PHP version.
     */
    public function checkPhpVersion(string $currentVersion = PHP_VERSION): void
    {
        $minVersion = self::MINIMUM_PHP_VERSION;

        if (version_compare($currentVersion, $minVersion, operator: '>=')) {
            $this->addResult('php_version', "PHP version {$currentVersion} meets requirements");

            return;
        }

        $this->addError(
            'php_version',
            "PHP version {$currentVersion} does not meet minimum requirement {$minVersion}",
        );
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, Result>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Whether the checks run so far found no errors.
     */
    public function hasPassed(): bool
    {
        return [] === $this->errors;
    }

    /**
     * Run every check and return the report.
     *
     * @return Report
     */
    public function runAll(): array
    {
        $this->results = [];
        $this->errors  = [];

        $this->checkPhpVersion();
        $this->checkPhpExtensions();
        $this->checkDirectories();
        $this->checkPermissions();
        $this->checkConfiguration();

        return [
            'success' => $this->hasPassed(),
            'results' => $this->results,
            'errors'  => $this->errors,
        ];
    }

    private function addError(string $key, string $message): void
    {
        $this->errors[$key]  = $message;
        $this->results[$key] = ['success' => false, 'message' => $message];
    }

    private function addResult(string $key, string $message): void
    {
        $this->results[$key] = ['success' => true, 'message' => $message];
    }

    /**
     * Record a passing check, or try the fix when auto-fix is on.
     *
     * @param callable(): bool $check
     * @param callable(): bool $fix
     * @param array{string, string} $passed Messages when it passes, and when the fix worked.
     * @param array{string, string} $failed Messages when auto-fix is off, and when the fix failed.
     */
    private function checkOrFix(string $key, callable $check, callable $fix, array $passed, array $failed): void
    {
        if ($check()) {
            $this->addResult($key, $passed[0]);

            return;
        }

        if (! $this->autoFix) {
            $this->addError($key, $failed[0]);

            return;
        }

        if (Filesystem::silently($fix)) {
            $this->addResult($key, $passed[1]);

            return;
        }

        $this->addError($key, $failed[1]);
    }
}
