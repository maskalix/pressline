<?php
/**
 * Toggle a single health-check ID's suppressed state. Dev-only, CSRF
 * protected. Response is JSON; the caller is the Health page's inline JS.
 *
 *   POST id=<check_id>   → adds / removes from the suppressed set
 *   200 {"ok":true,"suppressed":bool}   on success
 *   403                                  if not dev
 *   400                                  if id missing
 */
require_once dirname(__DIR__) . '/pl-load.php';
require_once dirname(__DIR__) . '/includes/health.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$level = (int)($_SESSION['user_level'] ?? 0);
if ($level < $dev_level) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'dev_only']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

try {
    csrf_verify();
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$id = trim((string)($_POST['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_id']);
    exit;
}

$set = pl_health_suppressed_set();
if (isset($set[$id])) {
    unset($set[$id]);
    $isSuppressed = false;
} else {
    $set[$id] = true;
    $isSuppressed = true;
}

$ok = pl_health_set_suppressions(array_keys($set));
echo json_encode([
    'ok' => $ok,
    'suppressed' => $isSuppressed,
    'id' => $id,
]);
