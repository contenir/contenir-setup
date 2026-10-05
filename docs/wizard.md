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
| Administrator | `install` | Requires `admin_username`, `admin_email` and an `admin_password` of at least 8 characters, then `InstallerService::install(['username', 'email', 'password'])` and clears the cache | Renders `setup::complete` directly; or `step=admin-user` with the error (or `validation-failed`) |

A POST with any other action renders the page like a GET.

## Template variables

`setup::install` receives:

| Variable | Value |
| --- | --- |
| `title` | `Contenir CMS Setup` |
| `step` | The `step` query parameter; without one, `installed` (an existing but incomplete installation) when the database file exists (unless `force` is set), otherwise `welcome` |
| `success`, `error` | Messages: `1` becomes a generic message, any other string is shown as given (escape it) |
| `diagnostics` | The diagnostics report on the `diagnostics` step, otherwise `null` |
| `formData` | Always `[]` |
| `isInstalled` | Always `false`: an installed site never reaches the template |
| `dbExists` | `InstallerService::databaseFileExists()`, or `false` while `db.cms.database` is not configured |

On the `diagnostics` step the diagnostics run again for the page, and their
result replaces the success or error message.

The final step renders `setup::complete` in its own response, with `title`
and `errors` (the list from `InstallerService::validate()`).
`Handler\CompleteHandler` is kept for compatibility: it redirects to
`/setup` before installation and answers 404 after.

## Templates

`ConfigProvider` maps the `setup` template namespace to the package's
`templates/setup` directory. Override a template by adding your own path for
the `setup` namespace before it. The shipped templates use the laminas-view
`escapeHtml` and `escapeHtmlAttr` helpers.

## After installation

Once `InstallerService::isInstalled()` is true, both handlers answer every
request, GET or POST, with an empty 404 and call nothing else: no config
write, cache clear, migration, connection test or user creation. `?force=1`
no longer reopens the wizard. 404 rather than 403, so an installed site does
not confirm that a setup endpoint exists.

To change the database settings of an installed site, edit
`config/autoload/db.local.php`. `InstallerService::repair()` has no route;
call it from code you control, such as a CLI command.

Before installation the wizard is open to anyone who can reach it. Install
promptly, or restrict `/setup` at the web server until you have.
