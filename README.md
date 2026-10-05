# contenir/contenir-setup

[![Continuous Integration](https://github.com/contenir/contenir-setup/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-setup/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-setup/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-setup)

Web installer for [Contenir CMS](https://github.com/contenir) on
[Mezzio](https://docs.mezzio.dev/). It walks an operator through system
diagnostics, database configuration, a connection test and the first
administrator account, then validates the installation.

The package provides the request handlers, the services behind them and the
`setup::` templates. The application provides the routes, a template
renderer, and the Contenir CMS services the installer drives (see
[Requirements](#requirements)).

## Requirements

- PHP 8.3, 8.4 or 8.5
- mezzio/mezzio 3.18+ with a template renderer for the `setup::` templates
  (the shipped templates use laminas-view helpers)
- laminas/laminas-db 2.20+
- From the Contenir CMS application, registered in the container:
  `Contenir\Service\Database\AdapterManager` (with a `cms` adapter),
  `Contenir\Service\Migration\MigrationService` and
  `User\Manager\UserManager`. These are not declared as Composer
  dependencies because no package provides them yet.

There is no earlier tagged release; see [UPGRADE-2.0.md](UPGRADE-2.0.md) for
changes from the `master` branch before 2.0.

## Install

```bash
composer require contenir/contenir-setup
```

With [laminas-component-installer](https://docs.laminas.dev/laminas-component-installer/)
the `Contenir\Setup\ConfigProvider` is added to your configuration
automatically. Then route the two handlers:

```php
// config/routes.php
$app->route('/setup', Contenir\Setup\Handler\InstallHandler::class, ['GET', 'POST'], 'setup');
$app->get('/setup/complete', Contenir\Setup\Handler\CompleteHandler::class, 'setup.complete');
```

The handlers redirect to these exact paths. Protect `/setup` once the site is
installed (see [the wizard](docs/wizard.md#after-installation)).

## Usage

| Class | Purpose |
| --- | --- |
| `Handler\InstallHandler` | The wizard: renders `setup::install` and handles each step's POST |
| `Handler\CompleteHandler` | Renders `setup::complete` with validation warnings |
| `Service\DiagnosticsService` | PHP version, extensions, required and writable directories, with auto-fix |
| `Service\DatabaseConfigWriter` | Writes `config/autoload/db.local.php` from the database form |
| `Service\CacheService` | Deletes the files under the cache directory |
| `Service\InstallerService` | Migrates, creates the administrator, validates and repairs the CMS database |
| `ConfigProvider` and the `*Factory` classes | Container wiring and the `setup` template path |

The services can be used on their own, for example from a CLI command:

```php
$report = $container->get(DiagnosticsService::class)->runAll();
// ['success' => bool, 'results' => [key => ['success' => bool, 'message' => string]], 'errors' => [key => message]]

$installer = $container->get(InstallerService::class);
if (! $installer->isInstalled()) {
    $installer->install(['username' => 'admin', 'email' => 'admin@example.com', 'password' => $password]);
}
$problems = $installer->validate(); // list<string>
```

The [docs](docs/) folder covers each area:

- [The setup wizard](docs/wizard.md)
- [Diagnostics](docs/diagnostics.md)
- [Database configuration](docs/database-config.md)
- [Installer service](docs/installer.md)
- [Cache clearing](docs/cache.md)

## Configuration

| Key | Used by | Default |
| --- | --- | --- |
| `db.cms.database` | `InstallerService` | none; the wizard writes it |
| `cache_dir` | `CacheService` | `data/cache` |

Relative paths resolve against the working directory, which a Mezzio
application's `public/index.php` sets to the application root.

## Development

The QA toolchain is [php-db/phpdb-qa-tools](https://github.com/php-db/phpdb-qa-tools).
[Mago](https://mago.carthage.software/) is a standalone binary, installed
separately (`brew install mago`).

```bash
composer check             # everything below
composer cs-check          # mago format --check && mago lint
composer static-analysis   # mago analyze
composer test              # unit suite: collaborators doubled, no I/O
composer test-integration  # integration suite: temp directories, SQLite, laminas-view templates
composer test-coverage     # both suites, clover.xml for Codecov
```

`stubs/` declares the three Contenir CMS application classes that
`InstallerService` uses, with their real signatures, so that Mago can
analyse `src/` and the tests can double them. They are autoloaded for
development only and are not part of the distributed package.

Some integration tests change file permissions and are skipped when run as
root.

## License

MIT. See the `license` field in [composer.json](composer.json).
