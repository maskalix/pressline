<?php
require_once __DIR__ . '/_marketplace_bootstrap.php';

if ($_SESSION['user_level'] < $admin_level) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

try {
    echo json_encode(PL_Marketplace::categories());
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => 'marketplace_unavailable', 'detail' => $e->getMessage()]);
}
