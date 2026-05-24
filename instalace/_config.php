<?php
/**
 * Shared helper for writing key/value pairs into config.default.php.
 * Lives in its own file so step pages and db_install.php can both pull it
 * without dragging the whole installer state machine along.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/_debug.php';

function pl_install_config_set(array $kv): void {
    $path = __DIR__ . '/config.default.php';
    if (PL_INSTALLER_DEBUG) {
        foreach ($kv as $k => $v) {
            pl_install_debug('write', 'config.default.php',
                '$' . $k . ' = ' . var_export((string)$v, true) . ';');
        }
        return;
    }
    if (!file_exists($path)) {
        file_put_contents($path, "<?php\n");
    }
    $content = file_get_contents($path);
    foreach ($kv as $k => $v) {
        $pattern = '/\$' . preg_quote($k, '/') . '\s*=\s*[\'"][^\'"]*[\'"];/';
        $replacement = '$' . $k . " = '" . addslashes((string)$v) . "';";
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $replacement, $content);
        } else {
            $content = rtrim($content, "\n ") . "\n" . $replacement . "\n";
        }
    }
    file_put_contents($path, $content);
}
