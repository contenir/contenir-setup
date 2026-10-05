<?php

declare(strict_types=1);

namespace Contenir\Service\Database;

use Laminas\Db\Adapter\Adapter;
use LogicException;

/**
 * Development stub of the Contenir CMS database adapter manager. Not shipped; see the README "Development" section.
 */
class AdapterManager
{
    public function getAdapter(string $name = 'site'): Adapter
    {
        throw new LogicException('Stub: provided by the Contenir CMS application.');
    }
}
