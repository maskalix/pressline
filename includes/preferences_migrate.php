<?php
/**
 * Adds the `language` column to `preferences` so each user can pick their
 * own admin UI language (cs/en/de). Idempotent — safe on every page load.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

if (!isset($connection) || !$connection) return;

$hasLang = false;
if ($res = @$connection->query("SHOW COLUMNS FROM preferences LIKE 'language'")) {
    $hasLang = $res->num_rows > 0;
}
if (!$hasLang) {
    @$connection->query("ALTER TABLE preferences ADD COLUMN language VARCHAR(8) NOT NULL DEFAULT 'cs'");
}
