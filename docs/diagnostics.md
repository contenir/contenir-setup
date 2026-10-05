# Diagnostics

`Service\DiagnosticsService` checks that the server can run the CMS.

```php
$diagnostics = new DiagnosticsService($config, autoFix: true, basePath: '/var/www/site');
$report      = $diagnostics->runAll();
```

`runAll()` clears earlier results, runs every check and returns:

```php
[
    'success' => false,
    'results' => [
        'php_version'    => ['success' => true, 'message' => 'PHP version 8.4.3 meets requirements'],
        'dir_data/cache' => ['success' => true, 'message' => 'Directory created: data/cache'],
        'extension_intl' => ['success' => false, 'message' => 'Required PHP extension not loaded: intl'],
    ],
    'errors' => ['extension_intl' => 'Required PHP extension not loaded: intl'],
]
```

## Checks

| Method | Result keys | Checks |
| --- | --- | --- |
| `checkPhpVersion(string $version = PHP_VERSION)` | `php_version` | At least `MINIMUM_PHP_VERSION` (8.3.0); the running version by default |
| `checkPhpExtensions(array $extensions = REQUIRED_EXTENSIONS)` | `extension_<name>` | Each of `REQUIRED_EXTENSIONS` (pdo, pdo_sqlite, json, mbstring, openssl, session) is loaded |
| `checkDirectories()` | `dir_<path>` | `data`, `data/cms`, `data/cache`, `config`, `config/autoload` exist |
| `checkPermissions()` | `writable_<path>` | `data`, `data/cms`, `data/cache`, `config/autoload` are writable (missing ones are skipped) |
| `checkConfiguration()` | `config_directory` | `config/autoload` exists and is writable |

Each check can be called on its own; `getResults()`, `getErrors()` and
`hasPassed()` report what has run since construction or the last
`runAll()`.

## Auto-fix

With `autoFix` on (the default, and what the factory uses), a missing
directory is created (0755, recursively) and a read-only one is changed to
0755. The result then says `Directory created` or `Directory permissions
fixed`. When the fix fails, or with `autoFix` off, the check is an error.

## Base path

Directories are resolved against `basePath`, which defaults to the working
directory. A Mezzio application's `public/index.php` changes to the
application root, so the defaults point at the site's `data` and `config`
directories.
