<?php
/**
 * Shared bootstrap for marketplace AJAX endpoints.
 *
 * Goals:
 *   - Always emit JSON, even when something upstream warns/notices/throws.
 *   - Capture stray output from pl-load.php so it doesn't corrupt the body.
 *   - Convert PHP errors and uncaught exceptions into a structured JSON error.
 */

// Buffer immediately so any stray warnings from required files don't pollute output.
ob_start();

// Disable HTML error rendering — we render errors as JSON ourselves.
ini_set('display_errors', '0');
ini_set('html_errors', '0');

header('Content-Type: application/json; charset=utf-8');

set_error_handler(function (int $errno, string $msg, string $file = '', int $line = 0) {
    // Honour @-suppression and the configured error_reporting level.
    if (!(error_reporting() & $errno)) return false;
    throw new ErrorException($msg, 0, $errno, $file, $line);
});

set_exception_handler(function (Throwable $e) {
    // Discard any buffered output before sending the error response.
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error'  => 'internal_error',
        'detail' => $e->getMessage(),
        'where'  => basename($e->getFile()) . ':' . $e->getLine(),
    ]);
    exit;
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error'  => 'fatal',
            'detail' => $err['message'],
            'where'  => basename($err['file']) . ':' . $err['line'],
        ]);
    }
});

require_once dirname(__DIR__) . '/pl-load.php';
require_once dirname(__DIR__) . '/includes/session.php';

// Drop anything pl-load / session emitted into the buffer.
if (ob_get_length() > 0) ob_clean();
