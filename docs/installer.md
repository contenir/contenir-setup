# Installer service

`Service\InstallerService` installs and checks the CMS database through the
host application's services:

| Dependency | From | Used for |
| --- | --- | --- |
| `Laminas\Db\Adapter\Adapter` | `AdapterManager::getAdapter('cms')` in the factory | `validate()` queries |
| `Contenir\Service\Migration\MigrationService` | Contenir CMS | `migrate()`, `getStatus()` |
| `User\Manager\UserManager` | Contenir CMS | `createUser()` |
| `array $config` | the `config` service | `db.cms.database` |

## Methods

| Method | Behaviour |
| --- | --- |
| `getDatabasePath()` | `db.cms.database`; throws `RuntimeException('CMS database path not configured')` when missing or empty |
| `databaseFileExists()` | The database file exists and is not empty |
| `isInstalled()` | The file exists and the migration status has `is_up_to_date: true` and an integer `current_version` above 0. Any exception counts as not installed |
| `install(?array $admin)` | Creates the database directory, checks it is writable, runs all migrations, creates the administrator when given, and returns `isInstalled()` |
| `createAdminUser(array $data)` | `UserManager::createUser()` with `role_id: administrator` and `active: active` added |
| `validate()` | `[]` when valid, otherwise problems: `Database is not installed`, `No administrator user found`, `Missing default roles` (fewer than 3), or a failed query |
| `repair()` | Copies a non-empty database to `<file>.backup.<Y-m-d-His>`, deletes it, and runs `install()` without an administrator |

`install()` and `repair()` wrap every failure in a `RuntimeException`
(`Database installation failed: ...`, `Database repair failed: ...`) with
the original as the previous exception. `repair()` stops before deleting
anything if the backup cannot be written.
