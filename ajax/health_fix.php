<?php
/**
 * Run an autofix for a single health check. Dev-only, CSRF protected. The
 * actual fix logic lives next to the check declaration in includes/health.php
 * (or in plugin code that contributed the check), so this endpoint is a
 * thin dispatcher.
 *
 *   POST id=<check_id>
 *   200 {"ok":bool, "message":string}
 *   401 not signed in
 *   403 not dev / csrf
 *   400 missing id
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

$result = pl_health_run_autofix($id);
echo json_encode([
    'ok'      => !empty($result['ok']),
    'message' => $result['message'] ?? '',
    'id'      => $id,
]);
