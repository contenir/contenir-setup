<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use Exception;
use RuntimeException;

/**
 * Installs, validates and repairs the CMS database. The setup handlers
 * depend on this interface; the container resolves it to InstallerService.
 *
 * @api
 */
interface InstallerServiceInterface
{
    /**
     * Create the initial administrator.
     *
     * @param array<string, mixed> $data Admin user data (username, email, password).
     *
     * @throws Exception When the user cannot be created.
     */
    public function createAdminUser(array $data): void;

    /**
     * Whether the database file exists and is not empty.
     *
     * @throws RuntimeException When the database path is not configured.
     */
    public function databaseFileExists(): bool;

    /**
     * The CMS database file path.
     *
     * @throws RuntimeException When the database path is not configured.
     */
    public function getDatabasePath(): string;

    /**
     * Run every migration, create the administrator when given, and report
     * whether the database is now installed.
     *
     * @param array<string, mixed>|null $adminData Optional admin user data (username, email, password).
     *
     * @throws RuntimeException When any step fails.
     */
    public function install(?array $adminData = null): bool;

    /**
     * Whether the CMS is installed. Must not throw: any failure while
     * checking counts as not installed.
     */
    public function isInstalled(): bool;

    /**
     * Back up the database, delete it and install afresh.
     *
     * @throws RuntimeException When any step fails.
     */
    public function repair(): bool;

    /**
     * Check the installed database.
     *
     * @return list<string> Problems found; empty when the installation is valid.
     */
    public function validate(): array;
}
