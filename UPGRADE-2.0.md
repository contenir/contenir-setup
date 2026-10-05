# Upgrading to 2.0

2.0 is the first tagged release. If you used the `master` branch before it,
these are the changes that can affect you.

| | before 2.0 | 2.0 |
| --- | --- | --- |
| PHP | ^8.3 | 8.3, 8.4 or 8.5 |
| laminas/laminas-db | ^2.0 | ^2.20 |
| laminas/laminas-servicemanager | ^3.0 | ^3.22 |
| laminas/laminas-view | ^2.0 | ^2.33 |
| laminas/laminas-diactoros | ^3.0 | ^3.3 |

```bash
composer require contenir/contenir-setup:^2.0
```

## Final wiring classes

`ConfigProvider`, `Handler\CompleteHandlerFactory`,
`Handler\InstallHandlerFactory` and the `Service\*Factory` classes are
`final`. The handlers and services stay extendable. Replace a factory
instead of extending it:

```php
// before
class MyInstallerServiceFactory extends InstallerServiceFactory { /* ... */ }

// 2.0: register your own factory for the service
'dependencies' => [
    'factories' => [
        InstallerService::class => MyInstallerServiceFactory::class,
    ],
],
```

## Default paths follow the working directory

The defaults now resolve against the working directory, which a Mezzio
application's `public/index.php` sets to the application root. Before, they
were relative to the package source and, once installed with Composer,
pointed inside `vendor/`.

| Service | Default before | Default in 2.0 |
| --- | --- | --- |
| `DatabaseConfigWriter` | `src/Service/../../../../config/autoload/db.local.php` | `config/autoload/db.local.php` |
| `CacheService` (`cache_dir` unset) | `src/Service/../../../data/cache` | `data/cache` |
| `DiagnosticsService` base path | `realpath(src/Service/../../../../)` | `getcwd()` |

If you run the services from a different directory (for example a CLI
script), pass the paths explicitly:

```php
new DatabaseConfigWriter('/var/www/site/config/autoload/db.local.php');
new CacheService(['cache_dir' => '/var/www/site/data/cache']);
new DiagnosticsService($config, autoFix: true, basePath: '/var/www/site');
```

## DiagnosticsService requires PHP 8.3

```php
// before: passed on PHP 8.1 and 8.2
// 2.0
$diagnostics->checkPhpVersion(); // error below DiagnosticsService::MINIMUM_PHP_VERSION (8.3.0)
```

`checkPhpVersion()` and `checkPhpExtensions()` gained optional parameters
(the version to check, the extensions to require). Subclasses that override
them must add the same parameters:

```php
// before
public function checkPhpExtensions(): void

// 2.0
public function checkPhpExtensions(array $requiredExtensions = self::REQUIRED_EXTENSIONS): void
```

## Stricter migration status

`InstallerService::isInstalled()` reads the host `MigrationService` status
strictly:

```php
// before: truthy values were enough
['is_up_to_date' => 1, 'current_version' => '3'] // installed

// 2.0: a bool and an int are required
['is_up_to_date' => true, 'current_version' => 3] // installed
```

The Contenir CMS `MigrationService` already returns these types.

## Repair keeps the database when the backup fails

`InstallerService::repair()` now throws `RuntimeException('Database repair
failed: Failed to back up the database to ...')` and leaves the database in
place when the backup copy cannot be written. Before, it deleted the
database anyway.

## Query messages are no longer decoded twice

The `success` and `error` query parameters are shown exactly as the request
parsed them. Links that pre-encoded a message twice will now show the
encoded form.
