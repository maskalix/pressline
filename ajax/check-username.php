<?php
require_once dirname(__DIR__) . '/pl-load.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "unauthorized"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    $username = isset($input['username']) ? $input['username'] : '';
    $userId = isset($input['userId']) ? $input['userId'] : 0;

    $user = $dbFetcher->user('username', $username);

    if ($user && (int)$user['id'] !== (int)$userId) {
        echo json_encode(["status" => "taken"]);
    } else {
        echo json_encode(["status" => "available"]);
    }
}
