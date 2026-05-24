<?php
/**
 * PressLine configuration template.
 *
 * Copy this file to `pl-config.php` and fill in your own database credentials,
 * or set the corresponding `PL_DB_*` environment variables instead.
 *
 * Runtime settings (site name, theme, SMTP, marketplace URL, …) live in the
 * `webset` database table, not here. This file only owns the DB connection
 * and the DEBUG flag.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

$db_server   = getenv('PL_DB_HOST') ?: 'localhost:3306';
$db_user     = getenv('PL_DB_USER') ?: 'pressline';
$db_password = getenv('PL_DB_PASS') ?: '';
$db_name     = getenv('PL_DB_NAME') ?: 'pressline';

// Set DEBUG = true to enable installer simulation mode
// (no files written, no SQL executed, actions are recorded and shown
// at the end of each step). Leave at false for normal operation.
if (!defined('DEBUG')) {
    define('DEBUG', false);
}
