<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

function esc($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function validateTable($table) {
    $allowed = ['media', 'articles', 'users', 'categories', 'tags', 'roles', 'webset', 'preferences'];
    if (!in_array($table, $allowed, true)) {
        throw new Exception("Invalid table name.");
    }
    return $table;
}

function validateWhereColumn($where) {
    $allowed = ['id', 'key', 'category', 'user_id', 'username'];
    $where = str_replace('`', '', $where);
    $where = preg_replace('/[^a-zA-Z_]/', '', $where);
    if (!in_array($where, $allowed, true)) {
        throw new Exception("Invalid where column.");
    }
    return $where;
}

function validateUploadedFile($tmpPath, $filename) {
    $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'shtml', 'shtm', 'htaccess', 'cgi', 'pl', 'py', 'jsp', 'asp', 'aspx', 'exe', 'bat', 'sh'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, $dangerousExtensions, true)) {
        return false;
    }

    if (strpos($filename, "\0") !== false || strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    $dangerousMimes = ['application/x-httpd-php', 'application/x-php', 'text/x-php', 'application/x-executable', 'application/x-sharedlib'];
    if (in_array($mimeType, $dangerousMimes, true)) {
        return false;
    }

    return true;
}

function configureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}
