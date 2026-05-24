<?php
require_once dirname(__DIR__) . '/pl-load.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

function deleteFile($filePath) {
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

if (isset($_POST['action']) && isset($_POST['id']) && $_POST['action'] == 'delete') {
    $id = (int)$_POST['id'];
    $media = $dbFetcher->media('id', $id);
    $filename = $media['filename'];
    if ($filename) {
        $filePathToDelete = PL_ROOT . '/media/uploads/' . $filename;
        $thumbPathToDelete = PL_ROOT . '/media/uploads/thumbnails/' . $filename;
        deleteFile($filePathToDelete);
        deleteFile($thumbPathToDelete);
    }
}
