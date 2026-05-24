<?php
/**
 * Fire-and-forget GitHub release cache refresh. Called from the dashboard
 * after first paint when the cache is stale, so users don't wait on a
 * synchronous GitHub call.
 *
 * Admin-level (matches who can see the updates page in the first place).
 * Returns a tiny JSON ack — the dashboard ignores the response.
 */
require_once dirname(__DIR__) . '/pl-load.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$level = (int)($_SESSION['user_level'] ?? 0);
if ($level < $admin_level) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'admin_only']);
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

$snapshot = pl_updates_refresh_if_stale();
echo json_encode([
    'ok' => true,
    'has_update' => !empty($snapshot['has_update']),
    'latest' => $snapshot['latest_tag'] ?? null,
]);
