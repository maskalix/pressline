<?php
/**
 * PressLine self-update support.
 *
 * Layer 1 — discovery: pl_updates_status() returns a cached snapshot of
 * the latest GitHub release. Refreshed on demand or via the 1h timer.
 *
 * Layer 2 — apply:    pl_updates_apply($tag) downloads the zipball,
 * verifies it, extracts to a staging dir, backs up the live tree, and
 * swaps in the new code. Files in PL_UPDATE_PRESERVE survive the swap.
 *
 * Storage:
 *   webset['update_repo']     — "user/repo" on GitHub. Default below.
 *   webset['update_cache']    — JSON snapshot of last GitHub fetch.
 *   webset['update_history']  — JSON list of past update events (ring of 10).
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

const PL_UPDATE_DEFAULT_REPO = 'maskalix/PressLine';
const PL_UPDATE_CACHE_TTL    = 3600;

// Paths preserved during update — relative to PL_ROOT.
// Anything matching one of these prefixes is left untouched.
const PL_UPDATE_PRESERVE = [
    'pl-config.php',
    'media',          // user uploads
    'plugins',        // installed plugins (dist/ is built separately)
    '.installed',
    '.htaccess',
    'PHPMailer',      // user may have updated it independently
    'instalace',      // installer dir (post-install removable, but if present, leave it)
];

/**
 * Resolve the configured GitHub repo (or the default).
 */
function pl_updates_repo(): string {
    global $websets;
    $r = trim((string)($websets['update_repo'] ?? ''));
    return $r !== '' ? $r : PL_UPDATE_DEFAULT_REPO;
}

/**
 * Read $version without re-including version.php (which echoes nothing).
 */
function pl_updates_current_version(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
    $version = '0.0.0';
    @include __DIR__ . '/../version.php';
    return $cached = (string)($version ?? '0.0.0');
}

/**
 * Strip any leading "v" / "V" + whitespace from a release tag so version_compare
 * can apply semver-ish ordering.
 */
function pl_updates_normalize_tag(string $tag): string {
    return ltrim(trim($tag), 'vV');
}

/**
 * The cached status snapshot. Returns:
 *   [
 *     'checked_at'  => ISO 8601 string or null,
 *     'latest_tag'  => 'v1.2.3' or null,
 *     'latest_name' => 'Release name' or null,
 *     'latest_body' => 'Markdown body' or '',
 *     'published_at'=> 'ISO date' or null,
 *     'zipball_url' => 'https://…' or null,
 *     'error'       => string|null,
 *     'has_update'  => bool,
 *   ]
 */
function pl_updates_status(): array {
    global $websets;
    $raw = $websets['update_cache'] ?? '';
    $base = [
        'checked_at'   => null,
        'latest_tag'   => null,
        'latest_name'  => null,
        'latest_body'  => '',
        'published_at' => null,
        'zipball_url'  => null,
        'error'        => null,
        'has_update'   => false,
    ];
    if ($raw === '') return $base;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return $base;
    $merged = array_merge($base, $decoded);
    // Recompute has_update against the running version (which may have just
    // changed if a successful update bumped version.php in this same request).
    $current = pl_updates_normalize_tag(pl_updates_current_version());
    $latest  = $merged['latest_tag'] ? pl_updates_normalize_tag($merged['latest_tag']) : null;
    $merged['has_update'] = $latest && version_compare($latest, $current, '>');
    return $merged;
}

/**
 * Whether the cache is older than PL_UPDATE_CACHE_TTL.
 */
function pl_updates_is_stale(): bool {
    $s = pl_updates_status();
    if (!$s['checked_at']) return true;
    return (time() - strtotime($s['checked_at'])) > PL_UPDATE_CACHE_TTL;
}

/**
 * Persist a cache row. No-ops if the DB connection is unavailable.
 */
function pl_updates_cache_write(array $snapshot): void {
    global $connection, $websets;
    if (!$connection instanceof mysqli) return;
    $json = json_encode($snapshot);
    $stmt = $connection->prepare(
        "INSERT INTO webset (`key`, `value`) VALUES ('update_cache', ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    if (!$stmt) return;
    $stmt->bind_param('s', $json);
    $stmt->execute();
    $stmt->close();
    $websets['update_cache'] = $json;
}

/**
 * Hit the GitHub releases API for the configured repo. Refreshes the
 * cache. Returns the new snapshot (same shape as pl_updates_status()).
 *
 * Network errors are caught and stored as cache['error'] so the page can
 * report them rather than re-trying forever.
 */
function pl_updates_refresh(): array {
    $repo = pl_updates_repo();
    $url  = "https://api.github.com/repos/$repo/releases/latest";

    $snapshot = pl_updates_status();
    $snapshot['checked_at'] = gmdate('c');

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: PressLine-Updater\r\nAccept: application/vnd.github+json\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        $snapshot['error'] = 'network error';
        pl_updates_cache_write($snapshot);
        return $snapshot;
    }

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }
    if ($status !== 200) {
        $snapshot['error'] = "github http $status";
        pl_updates_cache_write($snapshot);
        return $snapshot;
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || empty($decoded['tag_name'])) {
        $snapshot['error'] = 'no releases';
        pl_updates_cache_write($snapshot);
        return $snapshot;
    }

    $snapshot['error']        = null;
    $snapshot['latest_tag']   = (string)$decoded['tag_name'];
    $snapshot['latest_name']  = (string)($decoded['name'] ?? $decoded['tag_name']);
    $snapshot['latest_body']  = (string)($decoded['body'] ?? '');
    $snapshot['published_at'] = (string)($decoded['published_at'] ?? '');
    $snapshot['zipball_url']  = (string)($decoded['zipball_url'] ?? '');

    $current = pl_updates_normalize_tag(pl_updates_current_version());
    $latest  = pl_updates_normalize_tag($snapshot['latest_tag']);
    $snapshot['has_update'] = version_compare($latest, $current, '>');

    pl_updates_cache_write($snapshot);
    return $snapshot;
}

/**
 * Refresh if stale (no-op otherwise). Cheap to call from the dashboard.
 */
function pl_updates_refresh_if_stale(): array {
    if (pl_updates_is_stale()) return pl_updates_refresh();
    return pl_updates_status();
}

/* ─── Apply ─────────────────────────────────────────────────────── */

/**
 * Run the full update sequence: download → verify → extract → backup → swap
 * → bump version. Returns ['ok' => bool, 'message' => string,
 * 'version' => string].
 *
 * Steps that fail trigger best-effort rollback from the backup snapshot.
 */
function pl_updates_apply(string $tag): array {
    $tag = trim($tag);
    if ($tag === '') return ['ok' => false, 'message' => 'empty tag'];

    $repo   = pl_updates_repo();
    $stamp  = date('Ymd-His') . '-' . substr(md5($tag . microtime()), 0, 6);
    $tmp    = sys_get_temp_dir();
    $zip    = $tmp . "/pl-update-$stamp.zip";
    $extract= $tmp . "/pl-update-$stamp";
    $backup = $tmp . "/pl-update-backup-$stamp";

    // 1. Download
    $url = "https://github.com/$repo/archive/refs/tags/" . rawurlencode($tag) . ".zip";
    $ok = pl_updates_download($url, $zip);
    if (!$ok['ok']) return $ok;

    // 2. Verify it's a real zip
    if (!class_exists('ZipArchive')) {
        @unlink($zip);
        return ['ok' => false, 'message' => 'ZipArchive PHP extension not available'];
    }
    $zipObj = new ZipArchive();
    if ($zipObj->open($zip) !== true) {
        @unlink($zip);
        return ['ok' => false, 'message' => 'invalid zip file'];
    }

    // 3. Extract
    if (!@mkdir($extract, 0755, true) && !is_dir($extract)) {
        $zipObj->close();
        @unlink($zip);
        return ['ok' => false, 'message' => 'cannot create extraction dir'];
    }
    if (!$zipObj->extractTo($extract)) {
        $zipObj->close();
        pl_updates_rm_recursive($extract);
        @unlink($zip);
        return ['ok' => false, 'message' => 'extraction failed'];
    }
    $zipObj->close();
    @unlink($zip);

    // GitHub wraps the contents in a single top-level dir named like
    // <user>-<repo>-<sha>. Find it.
    $top = pl_updates_find_top_dir($extract);
    if ($top === null) {
        pl_updates_rm_recursive($extract);
        return ['ok' => false, 'message' => 'unexpected zip layout'];
    }

    // 4. Backup the live tree (only the files that *would* be replaced;
    //    full backup of PL_ROOT is too big and would copy media/ etc.).
    if (!@mkdir($backup, 0755, true) && !is_dir($backup)) {
        pl_updates_rm_recursive($extract);
        return ['ok' => false, 'message' => 'cannot create backup dir'];
    }

    $swappable = pl_updates_collect_swappable($top);
    foreach ($swappable as $rel) {
        $live = PL_ROOT . '/' . $rel;
        $dest = $backup . '/' . $rel;
        if (!is_file($live)) continue;
        @mkdir(dirname($dest), 0755, true);
        if (!@copy($live, $dest)) {
            // Best-effort: don't abort on backup-of-missing-file.
            // But abort on any *real* copy failure.
            if (file_exists($live)) {
                pl_updates_rm_recursive($extract);
                pl_updates_rm_recursive($backup);
                return ['ok' => false, 'message' => "backup failed: $rel"];
            }
        }
    }

    // 5. Swap files in
    $swapErrors = [];
    foreach ($swappable as $rel) {
        $src  = $top . '/' . $rel;
        $dest = PL_ROOT . '/' . $rel;
        if (!is_file($src)) continue;
        @mkdir(dirname($dest), 0755, true);
        if (!@copy($src, $dest)) {
            $swapErrors[] = $rel;
        }
    }

    if ($swapErrors) {
        // Roll back from backup.
        foreach ($swappable as $rel) {
            $b = $backup . '/' . $rel;
            $d = PL_ROOT . '/' . $rel;
            if (is_file($b)) @copy($b, $d);
        }
        pl_updates_rm_recursive($extract);
        pl_updates_rm_recursive($backup);
        return [
            'ok' => false,
            'message' => 'swap failed for: ' . implode(', ', array_slice($swapErrors, 0, 5))
                       . (count($swapErrors) > 5 ? ' (+' . (count($swapErrors) - 5) . ' more)' : '')
                       . ' — rolled back',
        ];
    }

    // 6. Bump version.php — pull from version.php inside the new tree.
    $newVersion = pl_updates_normalize_tag($tag);
    $newVersionFile = $top . '/version.php';
    if (is_file($newVersionFile)) {
        @copy($newVersionFile, PL_ROOT . '/version.php');
    } else {
        // Fallback: write a minimal version.php with the tag.
        @file_put_contents(PL_ROOT . '/version.php',
            "<?php\n\$version = '" . addslashes($newVersion) . "';\n");
    }

    // 7. Re-run the schema in case the new code added tables/columns.
    if (file_exists(PL_ROOT . '/includes/schema.php')) {
        require_once PL_ROOT . '/includes/schema.php';
        global $connection;
        if ($connection instanceof mysqli) {
            $collation = 'utf8mb4_czech_ci';
            if ($res = @$connection->query(
                "SELECT TABLE_COLLATION FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() LIMIT 1"
            )) {
                if ($r = $res->fetch_assoc()) $collation = $r['TABLE_COLLATION'] ?: $collation;
            }
            pl_schema_apply($connection, pl_schema($collation, 'uncategorized'));
        }
    }

    // 8. Cleanup staging; keep backup until next update for diagnostics.
    pl_updates_rm_recursive($extract);
    pl_updates_history_record($tag, true, "swap ok ($stamp)");

    // 9. Refresh the cache so the page reflects post-update state.
    pl_updates_refresh();

    return [
        'ok'      => true,
        'message' => "updated to $tag (backup at $backup)",
        'version' => $newVersion,
    ];
}

/* ─── Helpers ───────────────────────────────────────────────────── */

function pl_updates_download(string $url, string $dest): array {
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: PressLine-Updater\r\n",
            'timeout' => 60,
            'follow_location' => 1,
        ],
    ]);
    $bytes = @file_get_contents($url, false, $ctx);
    if ($bytes === false) return ['ok' => false, 'message' => 'download failed'];
    if (strlen($bytes) < 1024) return ['ok' => false, 'message' => 'download too small'];
    if (@file_put_contents($dest, $bytes) === false) {
        return ['ok' => false, 'message' => 'cannot write download'];
    }
    return ['ok' => true, 'message' => 'downloaded ' . strlen($bytes) . ' bytes'];
}

/**
 * GitHub zipballs contain one wrapper directory. Return its full path,
 * or null if the layout is unexpected (multiple/no top-level dirs).
 */
function pl_updates_find_top_dir(string $extract): ?string {
    $entries = @scandir($extract);
    if (!$entries) return null;
    $dirs = [];
    foreach ($entries as $e) {
        if ($e === '.' || $e === '..') continue;
        if (is_dir($extract . '/' . $e)) $dirs[] = $extract . '/' . $e;
    }
    return count($dirs) === 1 ? $dirs[0] : null;
}

/**
 * List every relative path under $top that is *not* in PL_UPDATE_PRESERVE.
 * Returns paths relative to $top (and to PL_ROOT, which is the destination).
 */
function pl_updates_collect_swappable(string $top): array {
    $list = [];
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($top, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    $topLen = strlen($top) + 1;
    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        $rel = substr($file->getPathname(), $topLen);
        if (pl_updates_is_preserved($rel)) continue;
        $list[] = $rel;
    }
    return $list;
}

function pl_updates_is_preserved(string $rel): bool {
    foreach (PL_UPDATE_PRESERVE as $p) {
        if ($rel === $p) return true;
        if (str_starts_with($rel, $p . '/')) return true;
    }
    return false;
}

function pl_updates_rm_recursive(string $path): void {
    if (!file_exists($path)) return;
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($path);
}

function pl_updates_history_record(string $tag, bool $ok, string $message): void {
    global $connection, $websets;
    if (!$connection instanceof mysqli) return;
    $rawHistory = $websets['update_history'] ?? '[]';
    $h = json_decode($rawHistory, true);
    if (!is_array($h)) $h = [];
    $h[] = [
        'tag'     => $tag,
        'ok'      => $ok,
        'message' => $message,
        'when'    => gmdate('c'),
    ];
    if (count($h) > 10) $h = array_slice($h, -10);
    $json = json_encode($h);
    $stmt = $connection->prepare(
        "INSERT INTO webset (`key`, `value`) VALUES ('update_history', ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    if (!$stmt) return;
    $stmt->bind_param('s', $json);
    $stmt->execute();
    $stmt->close();
    $websets['update_history'] = $json;
}
