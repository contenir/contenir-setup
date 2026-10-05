# Database configuration

`Service\DatabaseConfigWriter` turns the wizard's database form into a PHP
config file.

```php
$writer = new DatabaseConfigWriter('config/autoload/db.local.php'); // the default
$writer->write([
    'cms_database'  => 'data/cms/cms.db',
    'site_hostname' => 'localhost',
    'site_port'     => '3306',
    'site_database' => 'site',
    'site_username' => 'site',
    'site_password' => '...',
]);
```

writes:

```php
<?php

declare(strict_types=1);

return [
    'db' => [
        'cms' => [
            'database' => 'data/cms/cms.db',
        ],
        'site' => [
            'hostname' => 'localhost',
            'port' => 3306,
            'database' => 'site',
            'username' => 'site',
            'password' => '...',
        ],
    ],
];
```

- `cms_database` becomes `db.cms.database` when present.
- Each non-empty `site_*` field becomes a key of `db.site`; `port` is cast
  to an integer. `db.site` is left out when every field is empty.
- Other form fields (such as `action`) are ignored.

The file is replaced on every write. The parent directory is created when
missing.

## Errors

`write()` throws `RuntimeException` with one of:

- `Failed to create config directory: <dir>`
- `Config directory is not writable: <dir>`
- `Config file is not writable: <file>`
- `Failed to write config file: <file>`

`isWritable()` answers whether a write would get past these checks, without
writing.
