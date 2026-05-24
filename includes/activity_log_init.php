<?php
/**
 * Ensures the activity_log table exists with the schema PressLine expects.
 * If a legacy/incompatible activity_log already exists, it is renamed to
 * activity_log_legacy and a fresh one is created.
 *
 * Idempotent — safe to run on every page load.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

if (!isset($connection) || !$connection) return;

$expectedSchema = "CREATE TABLE activity_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    username VARCHAR(64) NULL,
    action VARCHAR(32) NOT NULL,
    type VARCHAR(32) NULL,
    target_id INT UNSIGNED NULL,
    payload TEXT NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_user (user_id),
    INDEX idx_type_action (type, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Check if activity_log exists
$exists = false;
if ($res = @$connection->query("SHOW TABLES LIKE 'activity_log'")) {
    $exists = $res->num_rows > 0;
}

if (!$exists) {
    // Fresh install — create the proper table
    @$connection->query($expectedSchema);
    return;
}

// Inspect columns to see if it matches expected schema
$cols = [];
if ($res = @$connection->query("SHOW COLUMNS FROM activity_log")) {
    while ($r = $res->fetch_assoc()) $cols[$r['Field']] = true;
}

$required = ['id', 'user_id', 'username', 'action', 'type', 'target_id', 'payload', 'ip', 'created_at'];
$missing = array_diff($required, array_keys($cols));

if (!empty($missing)) {
    // Legacy table — preserve it under a different name and create the proper one.
    // Use a fresh suffix if activity_log_legacy already exists too.
    $legacyName = 'activity_log_legacy';
    $suffix = '';
    $i = 0;
    while (true) {
        $check = $connection->query("SHOW TABLES LIKE '" . $legacyName . $suffix . "'");
        if (!$check || $check->num_rows === 0) break;
        $i++;
        $suffix = '_' . $i;
        if ($i > 20) break; // give up after 20 tries
    }
    @$connection->query("RENAME TABLE activity_log TO " . $legacyName . $suffix);
    @$connection->query($expectedSchema);
}
