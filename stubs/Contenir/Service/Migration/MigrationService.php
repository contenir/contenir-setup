<?php

declare(strict_types=1);

namespace Contenir\Service\Migration;

use LogicException;

/**
 * Development stub of the Contenir CMS migration service. Not shipped; see the README "Development" section.
 */
class MigrationService
{
    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        throw new LogicException('Stub: provided by the Contenir CMS application.');
    }

    /**
     * @return array<array-key, mixed>
     */
    public function migrate(?int $targetVersion = null): array
    {
        throw new LogicException('Stub: provided by the Contenir CMS application.');
    }
}
