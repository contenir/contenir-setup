<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Contenir\Service\Migration\MigrationService;
use Exception;
use Laminas\Db\Adapter\Adapter;
use RuntimeException;
use User\Manager\UserManager;

use function copy;
use function date;
use function dirname;
use function file_exists;
use function filesize;
use function is_dir;
use function is_writable;
use function mkdir;
use function sprintf;
use function unlink;

/**
 * Installer Service
 *
 * Handles database installation and validation for the CMS system.
 * Uses the migration system to create and verify database structure.
 */
class InstallerService
{
    private Adapter $adapter;
    private array $config;
    private MigrationService $migrationService;
    private UserManager $userManager;

    public function __construct(
        Adapter $adapter,
        array $config,
        MigrationService $migrationService,
        UserManager $userManager
    ) {
        $this->adapter          = $adapter;
        $this->config           = $config;
        $this->migrationService = $migrationService;
        $this->userManager      = $userManager;
    }

    /**
     * Check if the database is installed and valid
     */
    public function isInstalled(): bool
    {
        try {
            // Check if database file exists and has content
            if (! $this->databaseFileExists()) {
                return false;
            }

            // Check migrations are up to date
            $status = $this->migrationService->getStatus();
            return $status['is_up_to_date'] && $status['current_version'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if database file exists and has content
     */
    public function databaseFileExists(): bool
    {
        $dbPath = $this->getDatabasePath();

        if (! file_exists($dbPath)) {
            return false;
        }

        // Check if file has content (not just an empty file)
        return filesize($dbPath) > 0;
    }

    /**
     * Get the database file path from configuration
     */
    public function getDatabasePath(): string
    {
        $config = $this->config['db']['cms'] ?? [];

        if (! isset($config['database'])) {
            throw new RuntimeException('CMS database path not configured');
        }

        return $config['database'];
    }

    /**
     * Install the database using migrations
     *
     * @param array|null $adminData Optional admin user data (username, email, password)
     */
    public function install(?array $adminData = null): bool
    {
        try {
            // Ensure database directory exists
            $dbPath = $this->getDatabasePath();
            $dbDir  = dirname($dbPath);

            if (! is_dir($dbDir)) {
                if (! mkdir($dbDir, 0755, true) && ! is_dir($dbDir)) {
                    throw new RuntimeException(
                        sprintf('Failed to create database directory: %s', $dbDir)
                    );
                }
            }

            // Ensure database directory is writable
            if (! is_writable($dbDir)) {
                throw new RuntimeException(
                    sprintf('Database directory is not writable: %s', $dbDir)
                );
            }

            // Run all migrations
            $this->migrationService->migrate();

            // Create admin user if provided
            if ($adminData !== null) {
                $this->createAdminUser($adminData);
            }

            // Verify installation
            return $this->isInstalled();
        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Database installation failed: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Create initial admin user
     *
     * @param array $data Admin user data (username, email, password)
     * @throws RuntimeException If user creation fails.
     */
    public function createAdminUser(array $data): void
    {
        // Set administrator role
        $data['role_id'] = 'administrator';

        // Set active status
        $data['active'] = 'active';

        // Create the user via UserManager
        $this->userManager->createUser($data);
    }

    /**
     * Validate database structure and data integrity
     */
    public function validate(): array
    {
        $errors = [];

        // Check if installed
        if (! $this->isInstalled()) {
            $errors[] = 'Database is not installed';
            return $errors;
        }

        // Check if default admin user exists
        try {
            $result = $this->adapter->query(
                "SELECT COUNT(*) as count FROM user WHERE role_id = 'administrator'",
                Adapter::QUERY_MODE_EXECUTE
            );

            $row = $result->current();
            if ($row['count'] === 0) {
                $errors[] = 'No administrator user found';
            }
        } catch (Exception $e) {
            $errors[] = 'Failed to validate users: ' . $e->getMessage();
        }

        // Check if default roles exist
        try {
            $result = $this->adapter->query(
                "SELECT COUNT(*) as count FROM role",
                Adapter::QUERY_MODE_EXECUTE
            );

            $row = $result->current();
            if ($row['count'] < 3) {
                $errors[] = 'Missing default roles';
            }
        } catch (Exception $e) {
            $errors[] = 'Failed to validate roles: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Repair the database by reinstalling
     */
    public function repair(): bool
    {
        try {
            // Backup existing database if it exists
            $dbPath = $this->getDatabasePath();
            if (file_exists($dbPath) && filesize($dbPath) > 0) {
                $backupPath = $dbPath . '.backup.' . date('Y-m-d-His');
                copy($dbPath, $backupPath);
            }

            // Remove existing database
            if (file_exists($dbPath)) {
                unlink($dbPath);
            }

            // Reinstall
            return $this->install();
        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Database repair failed: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
