<?php
/**
 * Health-check engine. The page calls pl_health_run() to gather a flat list
 * of check results, then renders them grouped by `group`. Plugins extend
 * the list via the `admin.healthChecks` filter:
 *
 *   add_filter('admin.healthChecks', function ($checks) {
 *       $checks[] = [
 *           'id'           => 'my-plugin.thing',
 *           'group'        => 'plugins',
 *           'status'       => 'ok' | 'warn' | 'fail' | 'unknown',
 *           'label'        => 'Plugin: short title',
 *           'detail'       => 'Free-text detail (already translated)',
 *           'action_href'  => pl_url('/plugins/my-plugin/page.php'),  // optional, root-absolute
 *           'action_label' => 'Open',                            // optional
 *       ];
 *       return $checks;
 *   });
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

const PL_HEALTH_REQUIRED_EXTENSIONS = ['mysqli', 'mbstring', 'gd', 'curl', 'zip'];
const PL_HEALTH_EXPECTED_TABLES = [
    'articles', 'categories', 'media', 'preferences', 'users',
    'roles', 'webset', 'activity_log', 'plugins',
];

function pl_health_run(): array {
    global $connection, $websets;
    $checks = [];

    // ── Environment ────────────────────────────────────────────────
    $phpVer = PHP_VERSION;
    $phpOk  = version_compare($phpVer, '8.0', '>=');
    $checks[] = [
        'id'     => 'env.php_version',
        'group'  => 'environment',
        'status' => $phpOk ? 'ok' : 'fail',
        'label'  => t('health.check.php_version'),
        'detail' => t($phpOk ? 'health.check.php_version.ok' : 'health.check.php_version.fail', $phpVer),
    ];

    $missing = [];
    foreach (PL_HEALTH_REQUIRED_EXTENSIONS as $ext) {
        if (!extension_loaded($ext)) $missing[] = $ext;
    }
    $checks[] = [
        'id'     => 'env.php_extensions',
        'group'  => 'environment',
        'status' => $missing ? 'fail' : 'ok',
        'label'  => t('health.check.php_ext'),
        'detail' => $missing
            ? t('health.check.php_ext.fail', implode(', ', $missing))
            : t('health.check.php_ext.ok'),
    ];

    // ── Database ───────────────────────────────────────────────────
    if ($connection instanceof mysqli && !$connection->connect_errno) {
        $checks[] = [
            'id'     => 'db.connection',
            'group'  => 'database',
            'status' => 'ok',
            'label'  => t('health.check.db_conn'),
            'detail' => t('health.check.db_conn.ok'),
        ];

        // Tables
        $present = [];
        if ($res = @$connection->query("SHOW TABLES")) {
            while ($r = $res->fetch_array()) $present[] = $r[0];
        }
        $missingTables = array_values(array_diff(PL_HEALTH_EXPECTED_TABLES, $present));
        $checks[] = [
            'id'      => 'db.schema',
            'group'   => 'database',
            'status'  => $missingTables ? 'fail' : 'ok',
            'label'   => t('health.check.db_schema'),
            'detail'  => $missingTables
                ? t('health.check.db_schema.fail', implode(', ', $missingTables))
                : t('health.check.db_schema.ok'),
            'autofix' => $missingTables ? 'pl_health_fix_schema' : null,
        ];

        // Specific column we rely on for per-user UI language
        $hasLang = false;
        if ($res = @$connection->query("SHOW COLUMNS FROM preferences LIKE 'language'")) {
            $hasLang = $res->num_rows > 0;
        }
        $checks[] = [
            'id'      => 'db.preferences_language',
            'group'   => 'database',
            'status'  => $hasLang ? 'ok' : 'fail',
            'label'   => t('health.check.db_pref_lang'),
            'detail'  => t($hasLang ? 'health.check.db_pref_lang.ok' : 'health.check.db_pref_lang.fail'),
            'autofix' => $hasLang ? null : 'pl_health_fix_pref_language',
        ];

        // Activity (recent)
        $recent = null;
        if ($res = @$connection->query("SELECT created_at FROM activity_log ORDER BY id DESC LIMIT 1")) {
            if ($r = $res->fetch_assoc()) $recent = $r['created_at'];
        }
        $isRecent = $recent && (time() - strtotime($recent) < 86400);
        $checks[] = [
            'id'     => 'db.activity_recent',
            'group'  => 'database',
            'status' => $isRecent ? 'ok' : 'warn',
            'label'  => t('health.check.activity_recent'),
            'detail' => $isRecent
                ? t('health.check.activity_recent.ok', $recent)
                : t('health.check.activity_recent.warn'),
        ];
    } else {
        $checks[] = [
            'id'     => 'db.connection',
            'group'  => 'database',
            'status' => 'fail',
            'label'  => t('health.check.db_conn'),
            'detail' => t('health.check.db_conn.fail',
                $connection instanceof mysqli ? $connection->connect_error : 'no connection'),
        ];
    }

    // ── Filesystem ─────────────────────────────────────────────────
    $dirsToCheck = [
        'plugins'        => PL_ROOT . '/plugins',
        'media/uploads'  => PL_ROOT . '/media/uploads',
    ];
    foreach ($dirsToCheck as $label => $path) {
        $checks[] = pl_health_dir_check($label, $path);
    }

    // ── Configuration ──────────────────────────────────────────────
    $installed = file_exists(PL_ROOT . '/.installed');
    $checks[] = [
        'id'      => 'config.installed_lock',
        'group'   => 'config',
        'status'  => $installed ? 'ok' : 'warn',
        'label'   => t('health.check.installed_lock'),
        'detail'  => t($installed ? 'health.check.installed_lock.ok' : 'health.check.installed_lock.warn'),
        'action_href'  => $installed ? null : './instalace/install.php',
        'action_label' => $installed ? null : t('nav.settings'),
        'autofix' => $installed ? null : 'pl_health_fix_installed_lock',
    ];

    $debugOn = defined('DEBUG') && DEBUG === true;
    $checks[] = [
        'id'     => 'config.debug',
        'group'  => 'config',
        'status' => $debugOn ? 'warn' : 'ok',
        'label'  => t('health.check.debug_off'),
        'detail' => t($debugOn ? 'health.check.debug_off.warn' : 'health.check.debug_off.ok'),
    ];

    // ── Mail ───────────────────────────────────────────────────────
    $smtpHost = $websets['smtp_host'] ?? '';
    $smtpFrom = $websets['from'] ?? '';
    $smtpOk = $smtpHost !== '' && $smtpFrom !== '';
    $checks[] = [
        'id'     => 'mail.smtp_set',
        'group'  => 'mail',
        'status' => $smtpOk ? 'ok' : 'warn',
        'label'  => t('health.check.smtp_set'),
        'detail' => t($smtpOk ? 'health.check.smtp_set.ok' : 'health.check.smtp_set.warn'),
        'action_href'  => $smtpOk ? null : './nastaveni.php',
        'action_label' => $smtpOk ? null : t('nav.settings'),
    ];

    // ── Security ───────────────────────────────────────────────────
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $checks[] = [
        'id'     => 'security.https',
        'group'  => 'security',
        'status' => $isHttps ? 'ok' : 'warn',
        'label'  => t('health.check.https'),
        'detail' => t($isHttps ? 'health.check.https.ok' : 'health.check.https.warn'),
    ];

    // ── Environment / server type ──────────────────────────────────
    $serverType = pl_server_type();
    $checks[] = [
        'id'     => 'env.server',
        'group'  => 'environment',
        'status' => $serverType === 'unknown' ? 'warn' : 'ok',
        'label'  => t('health.check.server'),
        'detail' => $serverType === 'unknown'
            ? t('health.check.server.unknown')
            : t('health.check.server.ok', $serverType),
    ];

    // ── Config / hide_php_ext consistency ──────────────────────────
    $hidePhp = pl_hide_php_enabled();
    if (!$hidePhp) {
        $checks[] = [
            'id'     => 'config.hide_php',
            'group'  => 'config',
            'status' => 'ok',
            'label'  => t('health.check.hide_php_consistent'),
            'detail' => t('health.check.hide_php_consistent.off'),
        ];
    } else {
        // The toggle ships .htaccess. That works on Apache, not on nginx.
        // If we detect a non-Apache server, warn and point at Settings.
        $compatible = $serverType === 'apache' || $serverType === 'unknown';
        $checks[] = [
            'id'     => 'config.hide_php',
            'group'  => 'config',
            'status' => $compatible ? 'ok' : 'warn',
            'label'  => t('health.check.hide_php_consistent'),
            'detail' => $compatible
                ? t('health.check.hide_php_consistent.ok', $serverType)
                : t('health.check.hide_php_consistent.warn', $serverType),
            'action_href'  => $compatible ? null : './nastaveni.php#urls',
            'action_label' => $compatible ? null : t('nav.settings'),
        ];
    }

    // ── Plugins extend via filter ──────────────────────────────────
    if (function_exists('apply_filters')) {
        $checks = apply_filters('admin.healthChecks', $checks);
    }

    return $checks;
}

/**
 * Single dir → health-check-shaped result. Encapsulates the posix-based
 * write check used in a couple of places.
 */
function pl_health_dir_check(string $label, string $path): array {
    $status = 'unknown';
    if (function_exists('posix_getpwnam') && function_exists('posix_getgrnam')) {
        $userInfo  = @posix_getpwnam('www-data');
        $groupInfo = @posix_getgrnam('www-data');
        $ownerUid  = @fileowner($path);
        $groupGid  = @filegroup($path);
        $perms     = @fileperms($path);
        if ($userInfo !== false && $groupInfo !== false && $ownerUid !== false && $perms !== false) {
            $writable = false;
            if ($userInfo['uid']  === $ownerUid) $writable = (bool)($perms & 0200);
            elseif ($groupInfo['gid'] === $groupGid) $writable = (bool)($perms & 0020);
            else                                     $writable = (bool)($perms & 0002);
            $status = $writable ? 'ok' : 'fail';
        }
    } else {
        // Fallback: just is_writable. Less precise but always answers.
        $status = is_writable($path) ? 'ok' : 'fail';
    }
    $id = 'fs.' . str_replace('/', '.', $label);
    return [
        'id'      => $id,
        'group'   => 'filesystem',
        'status'  => $status,
        'label'   => t('health.check.dir_perms', $label),
        'detail'  => t('health.check.dir_perms.' . $status),
        // Path needed by the autofix dispatcher; carried out-of-band.
        '_path'   => $path,
        'autofix' => $status === 'fail' ? 'pl_health_fix_dir_perms' : null,
    ];
}

/**
 * Aggregate counts by status, excluding suppressed checks (which get their
 * own summary slot).
 */
function pl_health_summary(array $checks): array {
    $summary = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'unknown' => 0, 'suppressed' => 0];
    foreach ($checks as $c) {
        if (!empty($c['suppressed'])) {
            $summary['suppressed']++;
            continue;
        }
        $s = $c['status'] ?? 'unknown';
        if (!isset($summary[$s])) $summary[$s] = 0;
        $summary[$s]++;
    }
    return $summary;
}

/**
 * Per-install set of suppressed check IDs. Stored as JSON in webset.
 * Loaded once per request.
 */
function pl_health_suppressed_set(bool $refresh = false): array {
    static $set = null;
    if ($set !== null && !$refresh) return $set;
    global $websets;
    $raw = $websets['health_suppressed'] ?? '';
    if ($raw === '') return $set = [];
    $decoded = json_decode($raw, true);
    return $set = (is_array($decoded) ? array_flip($decoded) : []);
}

function pl_health_is_suppressed(string $id): bool {
    $set = pl_health_suppressed_set();
    return isset($set[$id]);
}

/**
 * Persist a new suppression list. Caller passes the desired set as a flat
 * array of IDs. Updates webset row + the in-memory cache so the same
 * request sees the update.
 */
function pl_health_set_suppressions(array $ids): bool {
    global $connection, $websets;
    if (!$connection instanceof mysqli) return false;

    $ids = array_values(array_unique(array_filter($ids, 'is_string')));
    $json = $ids ? json_encode($ids) : '';

    $stmt = $connection->prepare(
        "INSERT INTO webset (`key`, `value`) VALUES ('health_suppressed', ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    if (!$stmt) return false;
    $stmt->bind_param('s', $json);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $websets['health_suppressed'] = $json;
        pl_health_suppressed_set(true); // bust static cache
    }
    return $ok;
}

/**
 * Annotate every check with a `suppressed` flag based on the persisted set.
 */
function pl_health_apply_suppressions(array $checks): array {
    $set = pl_health_suppressed_set();
    foreach ($checks as &$c) {
        $c['suppressed'] = isset($set[$c['id'] ?? '']);
    }
    return $checks;
}

/**
 * Group checks by their `group` key, preserving insertion order within
 * each group.
 */
function pl_health_group(array $checks): array {
    $grouped = [];
    foreach ($checks as $c) {
        $g = $c['group'] ?? 'plugins';
        $grouped[$g][] = $c;
    }
    return $grouped;
}

/* ─── Autofix dispatcher and core fixes ───────────────────────────── */

/**
 * Look up the autofix callable for a given check id, by re-running the
 * checks and finding the matching row. Returns the [callable, payload]
 * pair, or null if the id has no fix attached (e.g. user is trying to fix
 * a check whose plugin no longer ships an autofix).
 *
 * Returns ['cb' => callable, 'check' => array] or null.
 */
function pl_health_find_autofix(string $id): ?array {
    foreach (pl_health_run() as $check) {
        if (($check['id'] ?? null) !== $id) continue;
        if (empty($check['autofix']) || !is_callable($check['autofix'])) return null;
        return ['cb' => $check['autofix'], 'check' => $check];
    }
    return null;
}

/**
 * Run an autofix by id. Returns ['ok' => bool, 'message' => string]. The
 * callable receives the full check array so plugin fixes can access their
 * own context (e.g. the `_path` for filesystem checks).
 */
function pl_health_run_autofix(string $id): array {
    $found = pl_health_find_autofix($id);
    if ($found === null) {
        return ['ok' => false, 'message' => 'no autofix for ' . $id];
    }
    try {
        return ($found['cb'])($found['check']);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

/* ─── Core autofix implementations ────────────────────────────────── */

function pl_health_fix_pref_language(array $check): array {
    global $connection;
    if (!$connection instanceof mysqli) {
        return ['ok' => false, 'message' => 'no DB connection'];
    }
    require_once __DIR__ . '/preferences_migrate.php'; // idempotent ALTER
    return ['ok' => true, 'message' => 'preferences.language ensured'];
}

function pl_health_fix_schema(array $check): array {
    global $connection, $websets;
    if (!$connection instanceof mysqli) {
        return ['ok' => false, 'message' => 'no DB connection'];
    }
    require_once __DIR__ . '/schema.php';
    // Guess at the existing collation by looking at any existing table; default
    // to utf8mb4_czech_ci (the historical PressLine default).
    $collation = 'utf8mb4_czech_ci';
    if ($res = @$connection->query(
        "SELECT TABLE_COLLATION FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() LIMIT 1"
    )) {
        if ($r = $res->fetch_assoc()) {
            $collation = $r['TABLE_COLLATION'] ?: $collation;
        }
    }
    $defaultCat = function_exists('t') ? t('category.default') : 'uncategorized';
    if ($defaultCat === 'category.default') $defaultCat = 'uncategorized';
    $result = pl_schema_apply($connection, pl_schema($collation, $defaultCat));
    return [
        'ok'      => $result['ok'],
        'message' => $result['ok']
            ? 'schema applied (' . count($result['log']) . ' ops)'
            : implode('; ', $result['errors']),
    ];
}

function pl_health_fix_installed_lock(array $check): array {
    $path = PL_ROOT . '/.installed';
    if (file_exists($path)) {
        return ['ok' => true, 'message' => '.installed already present'];
    }
    if (@file_put_contents($path, date('Y-m-d H:i:s')) === false) {
        return ['ok' => false, 'message' => 'could not create .installed'];
    }
    return ['ok' => true, 'message' => '.installed created'];
}

function pl_health_fix_dir_perms(array $check): array {
    $path = $check['_path'] ?? null;
    if (!$path || !is_dir($path)) {
        return ['ok' => false, 'message' => 'directory not found'];
    }
    // Best-effort: chmod to add group write. If the script doesn't own the
    // directory this will fail silently and the user has to chown manually.
    $perms = @fileperms($path);
    if ($perms === false) {
        return ['ok' => false, 'message' => 'cannot read perms'];
    }
    $newPerms = $perms | 0775; // u=rwx,g=rwx,o=rx
    if (!@chmod($path, $newPerms)) {
        return [
            'ok' => false,
            'message' => 'chmod failed — change owner to www-data manually',
        ];
    }
    return ['ok' => true, 'message' => "chmod {$path} → 0" . substr(sprintf('%o', $newPerms), -4)];
}
