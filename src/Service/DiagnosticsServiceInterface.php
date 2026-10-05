<?php

declare(strict_types=1);

namespace Contenir\Setup\Service;

/**
 * Checks that the server can run the CMS. The setup handlers depend on this
 * interface; the container resolves it to DiagnosticsService.
 *
 * @psalm-type Result = array{success: bool, message: string}
 * @psalm-type Report = array{success: bool, results: array<string, Result>, errors: array<string, string>}
 *
 * @api
 */
interface DiagnosticsServiceInterface
{
    /**
     * Errors from the checks run so far, keyed by check.
     *
     * @return array<string, string>
     */
    public function getErrors(): array;

    /**
     * Results of the checks run so far, keyed by check.
     *
     * @return array<string, Result>
     */
    public function getResults(): array;

    /**
     * Whether the checks run so far found no errors.
     */
    public function hasPassed(): bool;

    /**
     * Run every check and return the report.
     *
     * @return Report
     */
    public function runAll(): array;
}
