<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use RuntimeException;

use function dirname;
use function file_exists;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_writable;
use function mkdir;
use function sprintf;
use function str_repeat;
use function var_export;

/**
 * Database Configuration Writer
 *
 * Writes database configuration to config/autoload/db.local.php
 */
class DatabaseConfigWriter
{
    private string $configPath;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? __DIR__ . '/../../../../config/autoload/db.local.php';
    }

    /**
     * Write database configuration
     *
     * @param array $config Database configuration array
     * @return bool True on success
     * @throws RuntimeException If unable to write configuration.
     */
    public function write(array $config): bool
    {
        // Ensure config directory exists
        $configDir = dirname($this->configPath);
        if (! is_dir($configDir)) {
            if (! mkdir($configDir, 0755, true) && ! is_dir($configDir)) {
                throw new RuntimeException(
                    sprintf('Failed to create config directory: %s', $configDir)
                );
            }
        }

        // Check if directory is writable
        if (! is_writable($configDir)) {
            throw new RuntimeException(
                sprintf('Config directory is not writable: %s', $configDir)
            );
        }

        // Check if file exists and is writable
        if (file_exists($this->configPath) && ! is_writable($this->configPath)) {
            throw new RuntimeException(
                sprintf('Config file is not writable: %s', $this->configPath)
            );
        }

        // Build configuration array
        $dbConfig = $this->buildConfig($config);

        // Generate PHP config file content
        $content  = "<?php\n\n";
        $content .= "declare(strict_types=1);\n\n";
        $content .= "return " . $this->varExportPretty($dbConfig) . ";\n";

        // Write to file
        $result = file_put_contents($this->configPath, $content);

        if ($result === false) {
            throw new RuntimeException(
                sprintf('Failed to write config file: %s', $this->configPath)
            );
        }

        return true;
    }

    /**
     * Build database configuration array from form data
     */
    private function buildConfig(array $data): array
    {
        $config = ['db' => []];

        // CMS database (SQLite)
        if (isset($data['cms_database'])) {
            $config['db']['cms'] = [
                'database' => $data['cms_database'],
            ];
        }

        // Site database (MySQL)
        $siteConfig = [];

        if (isset($data['site_hostname']) && $data['site_hostname'] !== '') {
            $siteConfig['hostname'] = $data['site_hostname'];
        }

        if (isset($data['site_port']) && $data['site_port'] !== '') {
            $siteConfig['port'] = (int) $data['site_port'];
        }

        if (isset($data['site_database']) && $data['site_database'] !== '') {
            $siteConfig['database'] = $data['site_database'];
        }

        if (isset($data['site_username']) && $data['site_username'] !== '') {
            $siteConfig['username'] = $data['site_username'];
        }

        if (isset($data['site_password']) && $data['site_password'] !== '') {
            $siteConfig['password'] = $data['site_password'];
        }

        if (! empty($siteConfig)) {
            $config['db']['site'] = $siteConfig;
        }

        return $config;
    }

    /**
     * Pretty print var_export for readable config files
     */
    private function varExportPretty(array $data, int $indent = 0): string
    {
        $output    = "[\n";
        $indentStr = str_repeat('    ', $indent + 1);

        foreach ($data as $key => $value) {
            $output .= $indentStr . var_export($key, true) . ' => ';

            if (is_array($value)) {
                $output .= $this->varExportPretty($value, $indent + 1);
            } else {
                $output .= var_export($value, true);
            }

            $output .= ",\n";
        }

        $output .= str_repeat('    ', $indent) . ']';

        return $output;
    }

    /**
     * Test if configuration file is writable
     */
    public function isWritable(): bool
    {
        $configDir = dirname($this->configPath);

        // Check directory exists and is writable
        if (! is_dir($configDir) || ! is_writable($configDir)) {
            return false;
        }

        // If file doesn't exist, check if we can create it (directory is writable)
        if (! file_exists($this->configPath)) {
            return true;
        }

        // File exists - check if it's writable
        return is_writable($this->configPath);
    }
}
