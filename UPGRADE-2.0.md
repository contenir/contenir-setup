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

## The wizard closes after installation (security)

Once `InstallerService::isInstalled()` is true, `InstallHandler` and
`CompleteHandler` answer every request with an empty `404 Not Found`, and
call nothing but that check: no config write, cache clear, migration,
connection test or user creation.

```text
# before: on an installed site
GET  /setup?force=1                        -> the wizard, ready to reconfigure
POST /setup action=install&admin_username=x -> a new administrator

# 2.0
GET  /setup?force=1                        -> 404
POST /setup action=install&...             -> 404
```

To reconfigure an installed site, edit `config/autoload/db.local.php`
directly. To repair the database, call `InstallerService::repair()` from code
you control (for example a CLI command); it has no HTTP route.

404 was chosen over 403 so that an installed site does not confirm that a
setup endpoint exists.

## The administrator has no defaults

```php
// before: missing fields fell back to defaults
['action' => 'install'] // installed with username "admin" and an empty password

// 2.0: all three are required, the password at least 8 characters
[
    'action'         => 'install',
    'admin_username' => 'jo',
    'admin_email'    => 'jo@example.com',
    'admin_password' => '...',          // InstallHandler::MINIMUM_PASSWORD_LENGTH
]
// anything less redirects back to step=admin-user with an error
```

The completion page no longer claims an "admin / admin" login.

## Completion page

The final install step now renders `setup::complete` in its own response
(with `title` and `errors`) instead of redirecting to `/setup/complete`.
`CompleteHandler` stays for compatibility: it redirects to `/setup` before
installation and answers 404 after. The `/setup/complete` route can be
removed. If you override `setup::complete`, it still receives the same
variables.

## Every concrete class is final; substitute through interfaces

Every class is `final`: `ConfigProvider`, both handlers, the four services
and every factory. The extension points are four new interfaces, which the
handlers and their factories now depend on:

| Service | Interface (fetched by the handler factories) | Default alias target |
| --- | --- | --- |
| Installer | `Service\InstallerServiceInterface` | `Service\InstallerService` |
| Diagnostics | `Service\DiagnosticsServiceInterface` | `Service\DiagnosticsService` |
| Database config | `Service\DatabaseConfigWriterInterface` | `Service\DatabaseConfigWriter` |
| Cache | `Service\CacheServiceInterface` | `Service\CacheService` |

`ConfigProvider` registers each interface as an alias of its service, so to
replace a service, point the alias at your implementation instead of
extending the class:

```php
// before
class SiteInstallerService extends InstallerService { /* ... */ }

'dependencies' => [
    'factories' => [InstallerService::class => SiteInstallerServiceFactory::class],
],

// 2.0
final class SiteInstallerService implements InstallerServiceInterface { /* ... */ }

'dependencies' => [
    'aliases'   => [InstallerServiceInterface::class => SiteInstallerService::class],
    'factories' => [SiteInstallerService::class => SiteInstallerServiceFactory::class],
],
```

An implementation may also wrap the shipped service (decoration) rather than
reimplement it. The handler constructors now take the interfaces, a wider
type, so code that constructs them with the shipped services keeps working.
Factories are replaced the same way: register your own factory for the
service instead of extending ours.

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
(the version to check, the extensions to require):

```php
// 2.0
$diagnostics->checkPhpVersion('8.2.29');      // check a given version
$diagnostics->checkPhpExtensions(['intl']);   // require given extensions
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
