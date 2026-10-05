<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

use RuntimeException;

/**
 * Writes the database configuration entered in the setup wizard. The setup
 * handlers depend on this interface; the container resolves it to
 * DatabaseConfigWriter.
 *
 * @api
 */
interface DatabaseConfigWriterInterface
{
    /**
     * Whether write() can create or replace the config file.
     */
    public function isWritable(): bool;

    /**
     * Write the database configuration built from the setup form data.
     *
     * @param array<array-key, mixed> $config Form data.
     *
     * @return true
     *
     * @throws RuntimeException If unable to write the configuration.
     */
    public function write(array $config): bool;
}
