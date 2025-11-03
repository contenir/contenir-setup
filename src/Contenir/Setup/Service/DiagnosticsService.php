<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Exception;

use function chmod;
use function extension_loaded;
use function is_dir;
use function is_writable;
use function mkdir;
use function realpath;
use function version_compare;

use const PHP_VERSION;

/**
 * Diagnostics Service
 *
 * Performs system diagnostics and auto-repairs common issues.
 * Checks file permissions, required directories, PHP extensions, and configuration.
 */
class DiagnosticsService
{
    private array $config;
    private array $results = [];
    private array $errors  = [];
    private bool $autoFix;

    public function __construct(array $config, bool $autoFix = true)
    {
        $this->config  = $config;
        $this->autoFix = $autoFix;
    }

    /**
     * Run all diagnostic tests
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
            'success' => empty($this->errors),
            'results' => $this->results,
            'errors'  => $this->errors,
        ];
    }

    /**
     * Check PHP version meets minimum requirements
     */
    public function checkPhpVersion(): void
    {
        $minVersion     = '8.1.0';
        $currentVersion = PHP_VERSION;

        if (version_compare($currentVersion, $minVersion, '>=')) {
            $this->addResult('php_version', true, "PHP version {$currentVersion} meets requirements");
        } else {
            $this->addError(
                'php_version',
                "PHP version {$currentVersion} does not meet minimum requirement {$minVersion}"
            );
        }
    }

    /**
     * Check required PHP extensions are loaded
     */
    public function checkPhpExtensions(): void
    {
        $requiredExtensions = [
            'pdo',
            'pdo_sqlite',
            'json',
            'mbstring',
            'openssl',
            'session',
        ];

        foreach ($requiredExtensions as $extension) {
            if (extension_loaded($extension)) {
                $this->addResult(
                    "extension_{$extension}",
                    true,
                    "Extension {$extension} is loaded"
                );
            } else {
                $this->addError(
                    "extension_{$extension}",
                    "Required PHP extension not loaded: {$extension}"
                );
            }
        }
    }

    /**
     * Check required directories exist
     */
    public function checkDirectories(): void
    {
        $basePath = $this->getBasePath();

        $requiredDirs = [
            'data'            => $basePath . '/data',
            'data/cms'        => $basePath . '/data/cms',
            'data/cache'      => $basePath . '/data/cache',
            'config'          => $basePath . '/config',
            'config/autoload' => $basePath . '/config/autoload',
        ];

        foreach ($requiredDirs as $name => $path) {
            if (is_dir($path)) {
                $this->addResult("dir_{$name}", true, "Directory exists: {$name}");
            } else {
                if ($this->autoFix) {
                    if ($this->createDirectory($path)) {
                        $this->addResult(
                            "dir_{$name}",
                            true,
                            "Directory created: {$name}"
                        );
                    } else {
                        $this->addError("dir_{$name}", "Failed to create directory: {$name}");
                    }
                } else {
                    $this->addError("dir_{$name}", "Directory does not exist: {$name}");
                }
            }
        }
    }

    /**
     * Check file and directory permissions
     */
    public function checkPermissions(): void
    {
        $basePath = $this->getBasePath();

        $writableDirs = [
            'data'            => $basePath . '/data',
            'data/cms'        => $basePath . '/data/cms',
            'data/cache'      => $basePath . '/data/cache',
            'config/autoload' => $basePath . '/config/autoload',
        ];

        foreach ($writableDirs as $name => $path) {
            if (! is_dir($path)) {
                continue;
            }

            if (is_writable($path)) {
                $this->addResult(
                    "writable_{$name}",
                    true,
                    "Directory is writable: {$name}"
                );
            } else {
                if ($this->autoFix) {
                    if ($this->makeWritable($path)) {
                        $this->addResult(
                            "writable_{$name}",
                            true,
                            "Directory permissions fixed: {$name}"
                        );
                    } else {
                        $this->addError(
                            "writable_{$name}",
                            "Failed to make directory writable: {$name}"
                        );
                    }
                } else {
                    $this->addError("writable_{$name}", "Directory is not writable: {$name}");
                }
            }
        }
    }

    /**
     * Check configuration files exist and are valid
     */
    public function checkConfiguration(): void
    {
        $basePath = $this->getBasePath();

        // Check that config directories exist - individual config files are optional
        // since they may be created during setup
        $configDir = $basePath . '/config/autoload';

        if (is_dir($configDir) && is_writable($configDir)) {
            $this->addResult(
                'config_directory',
                true,
                'Configuration directory is writable'
            );
        } else {
            $this->addError(
                'config_directory',
                'Configuration directory is not writable or does not exist'
            );
        }
    }

    /**
     * Create a directory with appropriate permissions
     */
    private function createDirectory(string $path): bool
    {
        try {
            if (mkdir($path, 0755, true) || is_dir($path)) {
                chmod($path, 0755);
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Make a directory writable
     */
    private function makeWritable(string $path): bool
    {
        try {
            return chmod($path, 0755);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Add a successful result
     */
    private function addResult(string $key, bool $success, string $message): void
    {
        $this->results[$key] = [
            'success' => $success,
            'message' => $message,
        ];
    }

    /**
     * Add an error
     */
    private function addError(string $key, string $message): void
    {
        $this->errors[$key]  = $message;
        $this->results[$key] = [
            'success' => false,
            'message' => $message,
        ];
    }

    /**
     * Get the base path of the application
     */
    private function getBasePath(): string
    {
        return realpath(__DIR__ . '/../../../../');
    }

    /**
     * Get results
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if diagnostics passed
     */
    public function hasPassed(): bool
    {
        return empty($this->errors);
    }
}
