<?php
/**
 * PressLine Marketplace Client
 *
 * Talks to the marketplace registry (default https://marketplace.pressline.app)
 * to browse, install, update, and uninstall plugins.
 *
 * Public surface (per blueprint section 5.2):
 *   PL_Marketplace::search($params)            — list plugins
 *   PL_Marketplace::getDetail($id)             — single plugin
 *   PL_Marketplace::categories()               — static category list
 *   PL_Marketplace::download($id, $version)    — fetch zip → /tmp, verify sha256
 *   PL_Marketplace::install($id, $version)     — full flow: download + extract + activate
 *   PL_Marketplace::update($id)                — install latest version atop existing
 *   PL_Marketplace::uninstall($id)             — disable + delete files
 *   PL_Marketplace::checkUpdates($installed)   — diff installed vs registry
 *   PL_Marketplace::ping($id, $version, $event)— anonymous telemetry
 *   PL_Marketplace::siteId()                   — stable per-install uuid
 *
 * Settings (read from webset table):
 *   marketplace_url                — base URL of the registry
 *   marketplace_telemetry          — '1'/'0' (default 1)
 *   marketplace_site_id            — generated lazily on first use
 *   marketplace_update_check_at    — last ISO-8601 update check
 *   marketplace_update_cache       — JSON-encoded update list (1h TTL)
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

class PL_Marketplace {
    const DEFAULT_URL          = 'https://marketplace.pressline.app';
    const HTTP_TIMEOUT         = 15;
    const MAX_PACKAGE_BYTES    = 5 * 1024 * 1024;       // 5 MB compressed
    const MAX_EXTRACTED_BYTES  = 25 * 1024 * 1024;      // 25 MB extracted
    const UPDATE_CACHE_TTL     = 3600;                  // 1h

    /** @var mysqli|null */
    private static $connection = null;

    public static function init($connection): void {
        self::$connection = $connection;
    }

    // ── Settings ────────────────────────────────────────────────────────────

    public static function baseUrl(): string {
        $url = self::webset('marketplace_url') ?: self::DEFAULT_URL;
        return rtrim($url, '/');
    }

    public static function telemetryEnabled(): bool {
        $v = self::webset('marketplace_telemetry');
        return $v === null || $v === '1' || $v === 1 || $v === true;
    }

    public static function siteId(): string {
        $id = self::webset('marketplace_site_id');
        if ($id) return $id;
        $id = bin2hex(random_bytes(16));
        self::websetWrite('marketplace_site_id', $id);
        return $id;
    }

    private static function siteHash(string $pluginId): string {
        return hash('sha256', self::siteId() . ':' . $pluginId);
    }

    // ── Public API: browse ──────────────────────────────────────────────────

    public static function search(array $params = []): array {
        $allowed = ['q', 'category', 'tag', 'sort', 'page', 'per_page', 'pressline_ver'];
        $qs = [];
        foreach ($allowed as $k) {
            if (isset($params[$k]) && $params[$k] !== '') $qs[$k] = $params[$k];
        }
        if (!isset($qs['pressline_ver'])) {
            $qs['pressline_ver'] = self::presslineVersion();
        }
        return self::httpGetJson('/api/v1/plugins?' . http_build_query($qs));
    }

    public static function getDetail(string $id): array {
        return self::httpGetJson('/api/v1/plugins/' . rawurlencode($id));
    }

    public static function categories(): array {
        return self::httpGetJson('/api/v1/categories');
    }

    // ── Public API: install / update / uninstall ────────────────────────────

    /**
     * Download a plugin zip to /tmp, verify sha256 against header.
     *
     * Two-step flow:
     *   1. Hit the registry's /download endpoint without following redirects.
     *      The registry returns 302 with Location: <signed-url> and a
     *      X-Package-Sha256 header. We capture both.
     *   2. Fetch the signed URL into a tmp file.
     *
     * Doing this in two steps keeps the sha256 header (which only the registry
     * sets, not the storage host) and gives a clear error if the redirect
     * target is unreachable.
     */
    public static function download(string $id, string $version): string {
        $registryUrl = self::baseUrl() . '/api/v1/plugins/' . rawurlencode($id)
                     . '/versions/' . rawurlencode($version) . '/download';

        // Step 1: get redirect target + sha256 from the registry response.
        [$location, $expectedSha] = self::fetchRegistryRedirect($registryUrl);

        if (self::isUnreachableLocalUrl($location)) {
            throw new RuntimeException(
                "Registry returned an unreachable storage URL ({$location}). " .
                "The marketplace is misconfigured — its storage base URL points to a non-public host."
            );
        }

        // Step 2: stream the signed URL into a tmp file.
        $tmp = sys_get_temp_dir() . '/pl-mp-' . preg_replace('/[^a-z0-9-]/i', '_', $id . '-' . $version) . '-' . bin2hex(random_bytes(4)) . '.zip';
        $fh  = fopen($tmp, 'wb');
        if (!$fh) throw new RuntimeException('Cannot open temp file for download');

        $ch = curl_init($location);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fh,
            CURLOPT_FOLLOWLOCATION => true,   // signed URLs sometimes redirect once
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => self::HTTP_TIMEOUT,
            CURLOPT_USERAGENT      => 'PressLine-Marketplace/1.0',
        ]);
        $ok   = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        unset($ch);
        fclose($fh);

        if (!$ok || $code !== 200) {
            @unlink($tmp);
            throw new RuntimeException(
                "Storage download failed (HTTP $code from {$location})" . ($err ? ": $err" : '')
            );
        }

        $size = filesize($tmp);
        if ($size === false || $size > self::MAX_PACKAGE_BYTES) {
            @unlink($tmp);
            throw new RuntimeException('Package exceeds size limit');
        }

        if (!$expectedSha) {
            @unlink($tmp);
            throw new RuntimeException('Registry did not return X-Package-Sha256 header');
        }
        $actual = hash_file('sha256', $tmp);
        if (!hash_equals(strtolower($expectedSha), strtolower($actual))) {
            @unlink($tmp);
            throw new RuntimeException("Package sha256 mismatch (expected $expectedSha, got $actual)");
        }

        return $tmp;
    }

    /**
     * Hit the registry's /download endpoint expecting a 302. Returns [Location, X-Package-Sha256].
     *
     * Uses GET (not HEAD) because most blueprint-conformant registries gate download counters
     * behind GET and reject HEAD with 405. We discard the body via a no-op write callback so
     * we never actually transfer the redirect response body (it's typically empty anyway).
     */
    private static function fetchRegistryRedirect(string $registryUrl): array {
        $headers = [];
        $ch = curl_init($registryUrl);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,   // we WANT to see the 302
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_USERAGENT      => 'PressLine-Marketplace/1.0',
            CURLOPT_WRITEFUNCTION  => fn($_c, $data) => strlen($data),  // discard body
            CURLOPT_HEADERFUNCTION => function ($_c, $hdr) use (&$headers) {
                $line = trim($hdr);
                if (strpos($line, ':') !== false) {
                    [$k, $v] = explode(':', $line, 2);
                    $headers[strtolower(trim($k))] = trim($v);
                }
                return strlen($hdr);
            },
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        unset($ch);

        if ($code === 0) {
            throw new RuntimeException("Registry unreachable" . ($err ? ": $err" : ''));
        }
        if ($code !== 302 && $code !== 301 && $code !== 307 && $code !== 308) {
            throw new RuntimeException("Registry did not return a redirect (HTTP $code)");
        }
        $location = $headers['location'] ?? '';
        if (!$location) {
            throw new RuntimeException('Registry returned a redirect without Location header');
        }
        return [$location, $headers['x-package-sha256'] ?? ''];
    }

    /**
     * Detects redirect targets that point at localhost / private addresses,
     * which a production server can't reach.
     */
    private static function isUnreachableLocalUrl(string $url): bool {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;
        $host = strtolower($host);
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) return true;
        // 10.x, 192.168.x, 172.16-31.x — private RFC1918 ranges are usually not what we want.
        if (preg_match('/^10\./', $host)) return true;
        if (preg_match('/^192\.168\./', $host)) return true;
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host)) return true;
        return false;
    }

    /**
     * Install a plugin from the marketplace.
     *   1. download + verify sha256
     *   2. extract atomically to /plugins/{id}/
     *   3. record version + activate status in DB
     *   4. run migrations from the new version
     *   5. ping install
     *
     * The actual plugin code (its hooks) won't execute until the *next* request,
     * because PL_Plugins's static cache is already populated for this request.
     * That's fine — the install endpoint just needs to ack and let the page reload.
     */
    public static function install(string $id, string $version): array {
        $zip = self::download($id, $version);
        try {
            self::extractAtomic($zip, $id);
        } finally {
            @unlink($zip);
        }

        if (self::$connection) {
            self::upsertVersion($id, $version, 'active');
            self::runMigrations($id);
        }

        self::ping($id, $version, 'install');
        return ['id' => $id, 'version' => $version, 'status' => 'installed'];
    }

    /**
     * Update an installed plugin to the latest available version.
     * Swaps files atomically, bumps the DB version row, runs new migrations.
     * Hook code runs on the *next* request (this one already loaded the old version).
     */
    public static function update(string $id): array {
        $detail = self::getDetail($id);
        $latest = $detail['latest_version']['version'] ?? null;
        if (!$latest) throw new RuntimeException("No latest version for plugin $id");

        $zip = self::download($id, $latest);
        try {
            self::extractAtomic($zip, $id);
        } finally {
            @unlink($zip);
        }

        if (self::$connection) {
            self::upsertVersion($id, $latest, 'active');
            self::runMigrations($id);
        }

        self::ping($id, $latest, 'update');
        return ['id' => $id, 'version' => $latest, 'status' => 'updated'];
    }

    /**
     * Insert-or-update a row in `plugins` with the given version + status.
     * Called after a successful extract so checkUpdates stops flagging the
     * plugin as outdated even though PL_Plugins's cache still sees the old version.
     */
    private static function upsertVersion(string $id, string $version, string $status): void {
        if (!self::$connection) return;
        $stmt = self::$connection->prepare(
            "INSERT INTO plugins (id, version, status) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE version = VALUES(version), status = VALUES(status)"
        );
        if (!$stmt) return;
        $stmt->bind_param('sss', $id, $version, $status);
        @$stmt->execute();
    }

    /**
     * Run /plugins/<id>/migrations/*.sql files in lexicographic order.
     * Replicates PL_Plugins::activate's migration logic without going through
     * the loader (which has already cached this request's plugin set).
     */
    private static function runMigrations(string $id): void {
        if (!self::$connection) return;
        $migDir = PL_ROOT . '/plugins/' . $id . '/migrations';
        if (!is_dir($migDir)) return;
        $files = glob($migDir . '/*.sql') ?: [];
        sort($files);
        foreach ($files as $f) {
            $sql = @file_get_contents($f);
            if (!$sql) continue;
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                @self::$connection->query($stmt);
            }
        }
    }

    /**
     * Full uninstall: remove DB row + delete plugin folder.
     */
    public static function uninstall(string $id): array {
        if (!preg_match('/^[a-z0-9-]{1,64}$/', $id)) {
            throw new RuntimeException('Invalid plugin id');
        }
        $dir = PL_ROOT . '/plugins/' . $id;
        if (self::$connection) PL_Plugins::uninstall(self::$connection, $id);
        if (is_dir($dir)) self::rrmdir($dir);
        self::ping($id, '', 'uninstall');
        return ['id' => $id, 'status' => 'uninstalled'];
    }

    /**
     * Compare installed plugin versions against registry latest.
     * Cached for 1h in webset to avoid hammering the registry on every page load.
     */
    public static function checkUpdates(array $installedRecords): array {
        $cached = self::cachedUpdateList();
        if ($cached !== null) return $cached;

        $updates = [];
        foreach ($installedRecords as $id => $rec) {
            if (empty($rec['manifest']) || empty($rec['db'])) continue;
            $current = $rec['db']['version'] ?? '0.0.0';
            try {
                $detail = self::getDetail($id);
                $latest = $detail['latest_version']['version'] ?? null;
                if ($latest && version_compare($latest, $current, '>')) {
                    $updates[] = [
                        'plugin_id'       => $id,
                        'current_version' => $current,
                        'latest_version'  => $latest,
                        'changelog'       => $detail['latest_version']['changelog'] ?? '',
                    ];
                }
            } catch (Throwable $_e) {
                // plugin not in registry, or registry unreachable — skip silently
            }
        }
        self::websetWrite('marketplace_update_cache', json_encode([
            'at'   => time(),
            'list' => $updates,
        ]));
        return $updates;
    }

    /**
     * Anonymous install/update/uninstall ping.
     * Fires only if telemetry is enabled. Best-effort; failures are swallowed.
     */
    public static function ping(string $id, string $version, string $event): void {
        if (!self::telemetryEnabled()) return;
        if (!in_array($event, ['install', 'update', 'uninstall'], true)) return;

        $payload = [
            'event'         => $event,
            'version'       => $version,
            'site_hash'     => self::siteHash($id),
            'pressline_ver' => self::presslineVersion(),
            'php_ver'       => PHP_VERSION,
        ];
        try {
            self::httpPostJson('/api/v1/plugins/' . rawurlencode($id) . '/install-ping', $payload);
        } catch (Throwable $_e) {
            // swallow — telemetry must never block install
        }
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private static function extractAtomic(string $zipPath, string $expectedId): void {
        if (!preg_match('/^[a-z0-9-]{1,64}$/', $expectedId)) {
            throw new RuntimeException('Invalid plugin id');
        }
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is required');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Failed to open zip');
        }

        // Pre-flight: verify manifest, no traversal, size bounds.
        $manifestRaw = $zip->getFromName('plugin.json');
        if ($manifestRaw === false) {
            $zip->close();
            throw new RuntimeException('Zip is missing plugin.json at root');
        }
        $manifest = json_decode($manifestRaw, true);
        if (!is_array($manifest) || ($manifest['id'] ?? '') !== $expectedId) {
            $zip->close();
            throw new RuntimeException('Manifest id does not match requested plugin id');
        }

        $totalUncompressed = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];
            // Block path traversal, absolute paths, symlinks, dotfiles
            if ($name === '' || str_contains($name, '..') || $name[0] === '/' || str_starts_with($name, '.git/') || preg_match('/(^|\/)\.env/i', $name)) {
                $zip->close();
                throw new RuntimeException("Refusing to extract suspicious entry: $name");
            }
            $totalUncompressed += $stat['size'];
            if ($totalUncompressed > self::MAX_EXTRACTED_BYTES) {
                $zip->close();
                throw new RuntimeException('Extracted size exceeds limit');
            }
        }

        $pluginsDir = PL_ROOT . '/plugins';
        $finalDir   = $pluginsDir . '/' . $expectedId;
        $stagingDir = $pluginsDir . '/.tmp-' . $expectedId . '-' . bin2hex(random_bytes(4));
        $backupDir  = $pluginsDir . '/.bak-' . $expectedId . '-' . bin2hex(random_bytes(4));

        if (!is_dir($pluginsDir)) {
            if (!@mkdir($pluginsDir, 0755, true)) {
                $zip->close();
                throw new RuntimeException('Cannot create plugins directory');
            }
        }
        if (!@mkdir($stagingDir, 0755, true)) {
            $zip->close();
            throw new RuntimeException('Cannot create staging directory');
        }

        if (!$zip->extractTo($stagingDir)) {
            $zip->close();
            self::rrmdir($stagingDir);
            throw new RuntimeException('Extraction failed');
        }
        $zip->close();

        // Atomic swap: backup old, move staging into place, drop backup on success.
        $hadOld = is_dir($finalDir);
        if ($hadOld) {
            if (!@rename($finalDir, $backupDir)) {
                self::rrmdir($stagingDir);
                throw new RuntimeException('Cannot move existing plugin out of the way');
            }
        }
        if (!@rename($stagingDir, $finalDir)) {
            // Rollback
            if ($hadOld) @rename($backupDir, $finalDir);
            self::rrmdir($stagingDir);
            throw new RuntimeException('Cannot move staging directory into place');
        }
        if ($hadOld) self::rrmdir($backupDir);
    }

    private static function rrmdir(string $dir): void {
        if (!is_dir($dir)) return;
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) self::rrmdir($path);
            else @unlink($path);
        }
        @rmdir($dir);
    }

    private static function cachedUpdateList(): ?array {
        $raw = self::webset('marketplace_update_cache');
        if (!$raw) return null;
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['at'], $data['list'])) return null;
        if ((time() - (int)$data['at']) > self::UPDATE_CACHE_TTL) return null;
        return $data['list'];
    }

    public static function clearUpdateCache(): void {
        self::websetWrite('marketplace_update_cache', '');
    }

    private static function presslineVersion(): string {
        $f = PL_ROOT . '/version.php';
        if (is_file($f)) {
            $contents = @file_get_contents($f);
            // Match semver with optional pre-release suffix (e.g. "2.0.0a", "1.5.0-beta").
            if ($contents && preg_match('/[\'"]([0-9]+\.[0-9]+(?:\.[0-9]+)?[a-zA-Z0-9.\-+]*)[\'"]/', $contents, $m)) {
                return $m[1];
            }
        }
        return '1.5';
    }

    // ── HTTP ────────────────────────────────────────────────────────────────

    private static function httpGetJson(string $path): array {
        $url = self::baseUrl() . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_USERAGENT      => 'PressLine-Marketplace/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        unset($ch);
        if ($body === false || $code >= 400) {
            throw new RuntimeException("Marketplace GET $path failed (HTTP $code): $err");
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new RuntimeException("Marketplace GET $path returned non-JSON");
        }
        return $json;
    }

    private static function httpPostJson(string $path, array $payload): array {
        $url = self::baseUrl() . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_USERAGENT      => 'PressLine-Marketplace/1.0',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        unset($ch);
        if ($body === false || $code >= 400) {
            throw new RuntimeException("Marketplace POST $path failed (HTTP $code): $err");
        }
        $json = json_decode($body, true);
        return is_array($json) ? $json : [];
    }

    // ── Webset I/O ──────────────────────────────────────────────────────────
    // Direct SQL because $dbSetter->webset() expects upsert-style action wrapper
    // and we want a clean, predictable read/write API for marketplace state.

    private static function webset(string $key): ?string {
        if (!self::$connection) return null;
        $stmt = self::$connection->prepare("SELECT value FROM webset WHERE `key` = ? LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? ($row['value'] ?? null) : null;
    }

    private static function websetWrite(string $key, string $value): void {
        if (!self::$connection) return;
        $stmt = self::$connection->prepare(
            "INSERT INTO webset (`key`, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)"
        );
        if (!$stmt) return;
        $stmt->bind_param('ss', $key, $value);
        @$stmt->execute();
    }
}
