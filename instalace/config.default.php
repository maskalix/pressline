<?php
/**
 * Template config — install.php replaces the empty strings with the values
 * the user enters. After installation finishes, this file should be moved
 * to the project root as `pl-config.php` (or its values copied across).
 *
 * Modern PressLine reads runtime settings from the `webset` database table,
 * not from this file. The only thing pl-config.php still owns is database
 * credentials and (optionally) the DEBUG flag.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

// Database
$db_server   = '';
$db_user     = '';
$db_password = '';
$db_name     = '';

// Set DEBUG = true for installer simulation mode (see pl-config.php docs).
if (!defined('DEBUG')) {
    define('DEBUG', false);
}
