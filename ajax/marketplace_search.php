<?php
require_once __DIR__ . '/_marketplace_bootstrap.php';

if ($_SESSION['user_level'] < $admin_level) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

try {
    $params = [
        'q'        => $_GET['q']        ?? '',
        'category' => $_GET['category'] ?? '',
        'tag'      => $_GET['tag']      ?? '',
        'sort'     => $_GET['sort']     ?? 'popular',
        'page'     => (int)($_GET['page'] ?? 1),
        'per_page' => min(100, max(1, (int)($_GET['per_page'] ?? 20))),
    ];
    echo json_encode(PL_Marketplace::search($params));
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => 'marketplace_unavailable', 'detail' => $e->getMessage()]);
}
