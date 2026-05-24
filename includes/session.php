<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
} else {
    $level = $_SESSION['user_level'];
    // Refresh active language from this user's preference (set on login + on save).
    if (!isset($_SESSION['lang']) && isset($dbFetcher)) {
        $pref = $dbFetcher->preference('user_id', $_SESSION['user_id']);
        if (!empty($pref['language'])) {
            $_SESSION['lang'] = $pref['language'];
        }
    }
}
$GLOBALS['__pl_active_lang'] = pl_resolve_lang();
