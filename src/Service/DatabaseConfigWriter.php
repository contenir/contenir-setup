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
 * Writes the database configuration entered in the setup wizard to a PHP
 * config file, `config/autoload/db.local.php` (relative to the working
 * directory, which Mezzio sets to the application root) by default.
 *
 * @api
 *
 * @mago-expect lint:cyclomatic-complexity Each filesystem precondition is reported separately, as in 0.x.
 */
class DatabaseConfigWriter
{
    private const string DEFAULT_PATH = 'config/autoload/db.local.php';

    /**
     * Form fields copied to db.site, with the config key they become.
     *
     * @mago-expect lint:no-literal-password These are config key names, not credentials.
     */
    private const array SITE_FIELDS = [
        'site_hostname' => 'hostname',
        'site_port'     => 'port',
        'site_database' => 'database',
        'site_username' => 'username',
        'site_password' => 'password',
    ];

    private string $configPath;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? self::DEFAULT_PATH;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array{db: array<string, array<string, mixed>>}
     *
     * @mago-expect analysis:mixed-assignment Form values are untyped and written as entered.
     */
    private static function buildConfig(array $data): array
    {
        $config = ['db' => []];

        if (null !== ($data['cms_database'] ?? null)) {
            $config['db']['cms'] = ['database' => $data['cms_database']];
        }

        $site = [];
        foreach (self::SITE_FIELDS as $field => $key) {
            $value = $data[$field] ?? '';
            if ('' !== $value) {
                $site[$key] = 'port' === $key ? (int) $value : $value;
            }
        }

        if ([] !== $site) {
            $config['db']['site'] = $site;
        }

        return $config;
    }

    /**
     * Render an array as indented short-array PHP source.
     *
     * @param array<array-key, mixed> $data
     * @param non-negative-int $indent
     *
     * @mago-expect analysis:mixed-assignment Config values are exported whatever their type.
     */
    private static function export(array $data, int $indent = 0): string
    {
        $output    = "[\n";
        $indentStr = str_repeat('    ', $indent + 1);

        foreach ($data as $key => $value) {
            $output .= $indentStr . var_export($key, return: true) . ' => ';
            $output .= is_array($value) ? self::export($value, $indent + 1) : var_export($value, return: true);
            $output .= ",\n";
        }

        return $output . str_repeat('    ', $indent) . ']';
    }

    /**
     * Whether write() can create or replace the config file.
     */
    public function isWritable(): bool
    {
        $configDir = dirname($this->configPath);

        if (! is_dir($configDir) || ! is_writable($configDir)) {
            return false;
        }

        return ! file_exists($this->configPath) || is_writable($this->configPath);
    }

    /**
     * Write the database configuration built from the setup form data:
     * `cms_database` becomes db.cms.database, and the non-empty `site_*`
     * fields become db.site (with the port as an integer).
     *
     * @param array<array-key, mixed> $config Form data.
     *
     * @return true
     *
     * @throws RuntimeException If unable to write the configuration.
     */
    public function write(array $config): bool
    {
        $configDir = dirname($this->configPath);
        if (
            ! is_dir($configDir)
            && ! Filesystem::silently(static fn(): bool => mkdir(
                $configDir,
                permissions: 0o755,
                recursive: true,
            ))
        ) {
            throw new RuntimeException(sprintf('Failed to create config directory: %s', $configDir));
        }

        if (! is_writable($configDir)) {
            throw new RuntimeException(sprintf('Config directory is not writable: %s', $configDir));
        }

        if (file_exists($this->configPath) && ! is_writable($this->configPath)) {
            throw new RuntimeException(sprintf('Config file is not writable: %s', $this->configPath));
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . self::export(self::buildConfig($config)) . ";\n";
        $path    = $this->configPath;

        if (! Filesystem::silently(static fn(): bool => false !== file_put_contents($path, $content))) {
            throw new RuntimeException(sprintf('Failed to write config file: %s', $this->configPath));
        }

        return true;
    }
}
