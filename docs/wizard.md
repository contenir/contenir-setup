# The setup wizard

`Handler\InstallHandler` serves `/setup`. Every step posts a form with an
`action` field; the handler does the work and redirects (post/redirect/get)
to `/setup?step=<next>` with `success=1`, `error=1` or a specific message.
The following GET renders `setup::install` with that step.

| Step | POST `action` | Does | Redirects to |
| --- | --- | --- | --- |
| Welcome | `diagnostics` | Clears the cache, runs `DiagnosticsService::runAll()` | `step=diagnostics` with `success=1` or `error=1` |
| Diagnostics | `database-config` with `proceed` | Nothing | `step=database-config` |
| Database configuration | `database-config` | `DatabaseConfigWriter::write($post)`, clears the cache | `step=test-connection&success=1`, or back with the error |
| Test connection | `test-connection` | Runs `SELECT 1` on the `Laminas\Db\Adapter\Adapter` service | `step=admin-user&success=1`, or back with the error |
| Administrator | `install` | `InstallerService::install(['username', 'email', 'password'])`, clears the cache | `/setup/complete`, or `step=admin-user` with the error (or `validation-failed`) |

A POST with any other action renders the page like a GET.

## Template variables

`setup::install` receives:

| Variable | Value |
| --- | --- |
| `title` | `Contenir CMS Setup` |
| `step` | The `step` query parameter; without one, `installed` when the database file exists (unless `force` is set), otherwise `welcome` |
| `success`, `error` | Messages: `1` becomes a generic message, any other string is shown as given (escape it) |
| `diagnostics` | The diagnostics report on the `diagnostics` step, otherwise `null` |
| `formData` | Always `[]` |
| `isInstalled` | `InstallerService::isInstalled()` |
| `dbExists` | `InstallerService::databaseFileExists()`, or `false` while `db.cms.database` is not configured |

On the `diagnostics` step the diagnostics run again for the page, and their
result replaces the success or error message.

`Handler\CompleteHandler` serves `/setup/complete`. It redirects to `/setup`
until `InstallerService::isInstalled()` is true, then renders
`setup::complete` with `title` and `errors` (the list from
`InstallerService::validate()`).

## Templates

`ConfigProvider` maps the `setup` template namespace to the package's
`templates/setup` directory. Override a template by adding your own path for
the `setup` namespace before it. The shipped templates use the laminas-view
`escapeHtml` and `escapeHtmlAttr` helpers.

## After installation

The wizard stays reachable after installation: `/setup?force=1` restarts it
so credentials can be changed or another administrator created. The package
does not restrict access. Put `/setup` behind authentication, or remove its
routes, once the site is installed.
