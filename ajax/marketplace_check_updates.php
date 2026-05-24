<?php
require_once __DIR__ . '/_marketplace_bootstrap.php';

if ($_SESSION['user_level'] < $admin_level) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}
csrf_verify();

try {
    PL_Marketplace::clearUpdateCache();
    $records = PL_Plugins::records($connection);
    $updates = PL_Marketplace::checkUpdates($records);
    echo json_encode(['updates' => $updates, 'count' => count($updates)]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => 'check_failed', 'detail' => $e->getMessage()]);
}
