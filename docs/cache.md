# Cache clearing

`Service\CacheService::clearAll()` deletes every file below the cache
directory: the top-level `cache_dir` config value, or `data/cache` relative
to the working directory. Directories are kept, so cache adapters find their
layout intact.

```php
(new CacheService(['cache_dir' => 'data/cache']))->clearAll();
// ['success' => true, 'message' => 'Successfully cleared 12 cache file(s)', 'deleted_count' => 12]
```

| Situation | Result |
| --- | --- |
| No cache directory | `success: true`, `Cache directory does not exist - nothing to clear` |
| Directory not writable | `success: false`, `Failed to clear cache: Directory is not writable: <dir>`, `deleted_count: 0` |
| Some files cannot be deleted | Skipped; `deleted_count` counts the files removed |

The wizard clears the cache before diagnostics, after writing the database
config and after installing, so the merged-config cache picks up the new
`db.local.php`.
