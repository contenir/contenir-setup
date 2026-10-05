<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use ArrayAccess;
use Contenir\Service\Migration\MigrationService;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSet;
use RuntimeException;
use User\Manager\UserManager;

use function copy;
use function date;
use function dirname;
use function file_exists;
use function filesize;
use function is_array;
use function is_dir;
use function is_int;
use function is_numeric;
use function is_string;
use function is_writable;
use function mkdir;
use function sprintf;
use function unlink;

/**
 * Installs, validates and repairs the CMS database through the host
 * application's migration service, and creates the first administrator.
 *
 * @api
 *
 * @mago-expect lint:cyclomatic-complexity Install, validate and repair share one 0.x class; splitting it is a suggested follow-up.
 */
class InstallerService
{
    /**
     * @param array<array-key, mixed> $config The application config; reads db.cms.database.
     */
    public function __construct(
        private readonly Adapter $adapter,
        private readonly array $config,
        private readonly MigrationService $migrationService,
        private readonly UserManager $userManager,
    ) {}

    /**
     * Create the initial administrator: the given data with role
     * "administrator" and status "active".
     *
     * @param array<string, mixed> $data Admin user data (username, email, password).
     *
     * @throws Exception When the user manager rejects the user.
     */
    public function createAdminUser(array $data): void
    {
        $data['role_id'] = 'administrator';
        $data['active']  = 'active';

        $this->userManager->createUser($data);
    }

    /**
     * Whether the database file exists and is not empty.
     *
     * @throws RuntimeException When the database path is not configured.
     */
    public function databaseFileExists(): bool
    {
        $dbPath = $this->getDatabasePath();

        return file_exists($dbPath) && 0 < (int) filesize($dbPath);
    }

    /**
     * The CMS database file path, from db.cms.database.
     *
     * @throws RuntimeException When the database path is not configured.
     *
     * @mago-expect analysis:mixed-assignment Config values are untyped; each level is checked here.
     */
    public function getDatabasePath(): string
    {
        $db       = $this->config['db'] ?? null;
        $cms      = is_array($db) ? $db['cms'] ?? null : null;
        $database = is_array($cms) ? $cms['database'] ?? null : null;

        if (! is_string($database) || '' === $database) {
            throw new RuntimeException('CMS database path not configured');
        }

        return $database;
    }

    /**
     * Run every migration, create the administrator when given, and report
     * whether the database is now installed.
     *
     * @param array<string, mixed>|null $adminData Optional admin user data (username, email, password).
     *
     * @throws RuntimeException When any step fails; the cause is the previous exception.
     */
    public function install(?array $adminData = null): bool
    {
        try {
            $dbDir = dirname($this->getDatabasePath());

            if (
                ! is_dir($dbDir)
                && ! Filesystem::silently(static fn(): bool => mkdir($dbDir, permissions: 0o755, recursive: true))
            ) {
                throw new RuntimeException(sprintf('Failed to create database directory: %s', $dbDir));
            }

            if (! is_writable($dbDir)) {
                throw new RuntimeException(sprintf('Database directory is not writable: %s', $dbDir));
            }

            $this->migrationService->migrate();

            if (null !== $adminData) {
                $this->createAdminUser($adminData);
            }

            return $this->isInstalled();
        } catch (Exception $e) {
            throw new RuntimeException(sprintf('Database installation failed: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Whether the database file exists and every migration has been applied.
     * Any failure while checking counts as not installed.
     *
     * @mago-expect analysis:mixed-assignment The host's migration status is untyped; it is checked here.
     */
    public function isInstalled(): bool
    {
        try {
            if (! $this->databaseFileExists()) {
                return false;
            }

            $status = $this->migrationService->getStatus();
        } catch (Exception) {
            return false;
        }

        $version = $status['current_version'] ?? 0;

        return true === ($status['is_up_to_date'] ?? false) && is_int($version) && $version > 0;
    }

    /**
     * Back up the database file (as `<file>.backup.<Y-m-d-His>`), delete it
     * and install afresh.
     *
     * @throws RuntimeException When any step fails; the cause is the previous exception.
     */
    public function repair(): bool
    {
        try {
            $dbPath = $this->getDatabasePath();

            if (file_exists($dbPath)) {
                $backupPath = $dbPath . '.backup.' . date('Y-m-d-His');
                if (
                    0 < (int) filesize($dbPath)
                    && ! Filesystem::silently(static fn(): bool => copy($dbPath, $backupPath))
                ) {
                    throw new RuntimeException(sprintf('Failed to back up the database to %s', $backupPath));
                }

                if (! Filesystem::silently(static fn(): bool => unlink($dbPath))) {
                    throw new RuntimeException(sprintf('Failed to remove the database %s', $dbPath));
                }
            }

            return $this->install();
        } catch (Exception $e) {
            throw new RuntimeException(sprintf('Database repair failed: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Check the installed database: an administrator exists and the default
     * roles (at least three) are present.
     *
     * @return list<string> Problems found; empty when the installation is valid.
     */
    public function validate(): array
    {
        if (! $this->isInstalled()) {
            return ['Database is not installed'];
        }

        $errors = [];

        try {
            if (0 === $this->count("SELECT COUNT(*) as count FROM user WHERE role_id = 'administrator'")) {
                $errors[] = 'No administrator user found';
            }
        } catch (Exception $e) {
            $errors[] = "Failed to validate users: {$e->getMessage()}";
        }

        try {
            if ($this->count('SELECT COUNT(*) as count FROM role') < 3) {
                $errors[] = 'Missing default roles';
            }
        } catch (Exception $e) {
            $errors[] = "Failed to validate roles: {$e->getMessage()}";
        }

        return $errors;
    }

    /**
     * The `count` column of a COUNT(*) query.
     *
     * @throws Exception When the query fails.
     *
     * @mago-expect analysis:mixed-assignment Result rows are untyped; the count is checked here.
     */
    private function count(string $sql): int
    {
        $result = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        $row    = $result instanceof ResultSet ? $result->current() : null;
        $count  = is_array($row) || $row instanceof ArrayAccess ? $row['count'] ?? null : null;

        return is_numeric($count) ? (int) $count : 0;
    }
}
