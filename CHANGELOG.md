# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [2.0.0] - Unreleased

First tagged release, aligned with the Contenir 2.x packages: PHP 8.3+, the
php-db QA toolchain, and fixes for paths that broke when the module was
extracted from the CMS. See [UPGRADE-2.0.md](UPGRADE-2.0.md).

### Changed

- Requires PHP 8.3, 8.4 or 8.5, laminas/laminas-db 2.20+,
  laminas/laminas-servicemanager 3.22+, laminas/laminas-view 2.33+ and
  laminas/laminas-diactoros 3.3+.
- `ConfigProvider` and every `*Factory` class are `final`.
- `DatabaseConfigWriter`, `CacheService` and `DiagnosticsService` resolve
  their default paths against the working directory (the application root
  in Mezzio) instead of the package's own location.
- `DiagnosticsService` requires PHP 8.3.0 (was 8.1.0), exposes
  `MINIMUM_PHP_VERSION` and `REQUIRED_EXTENSIONS`, and takes an optional
  `$basePath`; `checkPhpVersion()` and `checkPhpExtensions()` take optional
  arguments.
- `InstallerService::isInstalled()` requires `is_up_to_date` to be `true`
  and `current_version` to be an integer.
- Filesystem failures (creating, chmod-ing, copying or deleting) are
  reported through results and exceptions only, without PHP warnings.
- Factories read a missing or non-array `config` service as empty.

### Fixed

- The `setup` template path pointed outside the package, so the templates
  were never found.
- The default `db.local.php`, cache directory and diagnostics base path
  resolved inside `vendor/`.
- `/setup` failed with "CMS database path not configured" before the
  database had been configured; it now shows the welcome step.
- Messages in the `success` and `error` query parameters were URL-decoded
  twice, turning `+` into spaces and mangling `%` sequences.
- `InstallerService::repair()` deleted the database even when its backup
  copy failed.
- Auto-fix failures in `DiagnosticsService` raised PHP warnings instead of
  being reported (the `try`/`catch (Exception)` never caught them).
- `DiagnosticsService` accepted PHP 8.1 and 8.2, which the package does not
  support.

### Added

- Continuous integration on PHP 8.3, 8.4 and 8.5 against lowest, locked and
  latest dependencies, with coverage reported to Codecov.
- Unit (no I/O) and integration (temp directories, SQLite, laminas-view
  template rendering) test suites, with 100% line and branch coverage.
- README and `docs/` pages for the wizard, diagnostics, database
  configuration, installer service and cache clearing.

### Removed

- `phpstan/phpstan`, `laminas/laminas-coding-standard`, `phpcs.xml` and
  `phpstan.neon`, replaced by Mago via `php-db/phpdb-qa-tools`.

## Before 2.0 (untagged)

- Initial extraction of the setup module from Contenir CMS: install and
  complete handlers, installer, diagnostics, cache and database config
  services, and templates; then code quality tooling and a PSR-4 layout.
