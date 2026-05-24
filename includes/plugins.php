<?php
/**
 * PressLine Plugin Loader
 *
 * Scans /plugins/ folder, reads plugin.json manifests, loads active plugins.
 * Active state is stored in the `plugins` DB table.
 *
 * Plugin folder layout:
 *   /plugins/<plugin-id>/
 *     plugin.json    — required manifest
 *     plugin.php     — entry point (or whatever 'entry' points to)
 *     readme.md      — optional
 *     assets/        — optional CSS/JS
 *     migrations/    — optional SQL files (run on install)
 *     templates/     — optional template overrides
 *
 * plugin.json fields:
 *   id, name, version, author, description, homepage,
 *   min_pressline, permissions, hooks, entry
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

class PL_Plugins {
    private static $loaded = [];
    private static $manifests = [];
    private static $activeIds = [];
    private static $rootDir = null;

    public static function init($connection): void {
        self::$rootDir = PL_ROOT . '/plugins';
        self::ensureSchema($connection);
        self::loadActiveSet($connection);
        self::scanAndLoad($connection);
    }

    /**
     * Create the plugins table if it doesn't exist.
     */
    private static function ensureSchema($connection): void {
        $sql = "CREATE TABLE IF NOT EXISTS plugins (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            version VARCHAR(32) NOT NULL,
            status ENUM('installed', 'active', 'disabled') NOT NULL DEFAULT 'installed',
            settings TEXT NULL,
            installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        @$connection->query($sql);
    }

    /**
     * Load the set of currently-active plugin ids from the DB.
     */
    private static function loadActiveSet($connection): void {
        self::$activeIds = [];
        $res = @$connection->query("SELECT id FROM plugins WHERE status = 'active'");
        if (!$res) return;
        while ($r = $res->fetch_assoc()) self::$activeIds[$r['id']] = true;
    }

    /**
     * Scan /plugins/ for manifests, load active ones.
     */
    private static function scanAndLoad($connection): void {
        if (!is_dir(self::$rootDir)) return;
        $items = @scandir(self::$rootDir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = self::$rootDir . '/' . $item;
            if (!is_dir($path)) continue;

            $manifestPath = $path . '/plugin.json';
            if (!is_file($manifestPath)) continue;

            $raw = @file_get_contents($manifestPath);
            $manifest = $raw ? json_decode($raw, true) : null;
            if (!is_array($manifest) || empty($manifest['id'])) continue;

            $manifest['_path'] = $path;
            self::$manifests[$manifest['id']] = $manifest;

            // Auto-register newly-found plugins as 'installed'
            self::registerIfNew($connection, $manifest);

            if (!empty(self::$activeIds[$manifest['id']])) {
                self::loadPlugin($manifest);
            }
        }
    }

    /**
     * Insert a row for a plugin we've never seen before. Default status: installed.
     */
    private static function registerIfNew($connection, array $manifest): void {
        $id = $manifest['id'];
        $version = $manifest['version'] ?? '0.0.0';
        $stmt = $connection->prepare("SELECT id FROM plugins WHERE id = ?");
        if (!$stmt) return;
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        if (!$exists) {
            $ins = $connection->prepare("INSERT INTO plugins (id, version, status) VALUES (?, ?, 'installed')");
            if ($ins) {
                $ins->bind_param('ss', $id, $version);
                @$ins->execute();
            }
        }
    }

    /**
     * Require the plugin's entry file. Plugin then registers its hooks.
     */
    private static function loadPlugin(array $manifest): void {
        $id = $manifest['id'];
        if (isset(self::$loaded[$id])) return;
        $entry = $manifest['entry'] ?? 'plugin.php';
        $entryPath = $manifest['_path'] . '/' . $entry;
        if (!is_file($entryPath)) return;
        try {
            require_once $entryPath;
            self::$loaded[$id] = true;
        } catch (Throwable $e) {
            error_log("Plugin '$id' failed to load: " . $e->getMessage());
        }
    }

    /**
     * Get all known plugin manifests (active + installed + disabled).
     */
    public static function all(): array {
        return self::$manifests;
    }

    /**
     * Get loaded (active and successfully required) plugin ids.
     */
    public static function loaded(): array {
        return array_keys(self::$loaded);
    }

    /**
     * Read full plugin records (manifest + DB row) for the admin UI.
     */
    public static function records($connection): array {
        $rows = [];
        $res = @$connection->query("SELECT * FROM plugins");
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[$r['id']] = $r;
        }
        $records = [];
        foreach (self::$manifests as $id => $m) {
            $records[$id] = [
                'manifest' => $m,
                'db' => $rows[$id] ?? null,
                'loaded' => isset(self::$loaded[$id]),
            ];
        }
        // Include orphan DB rows (plugin folder removed)
        foreach ($rows as $id => $row) {
            if (!isset($records[$id])) {
                $records[$id] = [
                    'manifest' => null,
                    'db' => $row,
                    'loaded' => false,
                    'orphan' => true,
                ];
            }
        }
        return $records;
    }

    /**
     * Activate a plugin (admin action). Runs migrations on first activation.
     */
    public static function activate($connection, string $id): bool {
        $manifest = self::$manifests[$id] ?? null;
        if (!$manifest) return false;
        // Run migrations
        $migDir = $manifest['_path'] . '/migrations';
        if (is_dir($migDir)) {
            $files = glob($migDir . '/*.sql') ?: [];
            sort($files);
            foreach ($files as $f) {
                $sql = @file_get_contents($f);
                if ($sql) {
                    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                        @$connection->query($stmt);
                    }
                }
            }
        }
        $stmt = $connection->prepare("UPDATE plugins SET status = 'active' WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('s', $id);
        return @$stmt->execute();
    }

    public static function disable($connection, string $id): bool {
        $stmt = $connection->prepare("UPDATE plugins SET status = 'disabled' WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('s', $id);
        return @$stmt->execute();
    }

    public static function uninstall($connection, string $id): bool {
        $stmt = $connection->prepare("DELETE FROM plugins WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('s', $id);
        return @$stmt->execute();
    }

    /**
     * Get a plugin's stored settings (JSON column).
     */
    public static function getSettings($connection, string $id): array {
        $stmt = $connection->prepare("SELECT settings FROM plugins WHERE id = ?");
        if (!$stmt) return [];
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || !$row['settings']) return [];
        return json_decode($row['settings'], true) ?: [];
    }

    public static function setSettings($connection, string $id, array $settings): bool {
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE);
        $stmt = $connection->prepare("UPDATE plugins SET settings = ? WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('ss', $json, $id);
        return @$stmt->execute();
    }
}

/**
 * Plugin SDK — fluent API for plugins to register themselves.
 *
 * Usage in plugin.php:
 *   PL_Plugin::register('my-plugin')
 *       ->onArticleSave(function($article) { ... })
 *       ->addAdminMenuItem('Můj plugin', pl_url('/plugins/my-plugin/page.php'), 'ic:round-extension');
 */
class PL_Plugin {
    private $id;

    public static function register(string $id): self {
        return new self($id);
    }

    public function __construct(string $id) {
        $this->id = $id;
    }

    public function id(): string { return $this->id; }

    public function on(string $hook, callable $cb, int $priority = 10): self {
        PL_Hooks::addAction($hook, $cb, $priority);
        return $this;
    }

    public function filter(string $hook, callable $cb, int $priority = 10): self {
        PL_Hooks::addFilter($hook, $cb, $priority);
        return $this;
    }

    // Convenience hook helpers
    public function onArticleSave(callable $cb): self { return $this->on('article.afterSave', $cb); }
    public function onArticleBeforeSave(callable $cb): self { return $this->on('article.beforeSave', $cb); }
    public function onArticleDelete(callable $cb): self { return $this->on('article.beforeDelete', $cb); }
    public function onMediaUpload(callable $cb): self { return $this->on('media.afterUpload', $cb); }
    public function onLogin(callable $cb): self { return $this->on('user.afterLogin', $cb); }

    public function addAdminMenuItem(string $label, string $href, string $icon = 'ic:round-extension', int $minLevel = 0): self {
        PL_Hooks::addFilter('admin.menu', function($items) use ($label, $href, $icon, $minLevel) {
            $items[] = ['label' => $label, 'href' => $href, 'icon' => $icon, 'min_level' => $minLevel];
            return $items;
        });
        return $this;
    }

    public function addDashboardCard(callable $renderer): self {
        PL_Hooks::addFilter('admin.dashboardCards', function($cards) use ($renderer) {
            $cards[] = $renderer;
            return $cards;
        });
        return $this;
    }

    public function addEditorButton(string $label, string $icon, string $jsHandler): self {
        PL_Hooks::addFilter('editor.toolbarButtons', function($buttons) use ($label, $icon, $jsHandler) {
            $buttons[] = ['label' => $label, 'icon' => $icon, 'handler' => $jsHandler];
            return $buttons;
        });
        return $this;
    }
}
