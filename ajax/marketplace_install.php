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

$id      = $_POST['id']      ?? '';
$version = $_POST['version'] ?? '';
if (!preg_match('/^[a-z0-9-]{1,64}$/', $id)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}
if (!preg_match('/^[0-9a-zA-Z.\-+]{1,32}$/', $version)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_version']);
    exit;
}

try {
    PL_Marketplace::clearUpdateCache();
    echo json_encode(PL_Marketplace::install($id, $version));
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'install_failed', 'detail' => $e->getMessage()]);
}
