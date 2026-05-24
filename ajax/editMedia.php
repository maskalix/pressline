<?php
require_once dirname(__DIR__) . '/pl-load.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $description = isset($_POST['edit-description']) ? $_POST['edit-description'] : '';
    $name = isset($_POST['edit-name']) ? $_POST['edit-name'] : '';
    $author = isset($_POST['edit-author']) ? $_POST['edit-author'] : '';

    $media = $dbFetcher->media('id', $id);

    if ($media) {
        $dbSetter->media([
            'action' => 'edit',
            'id' => $id,
            'description' => $description,
            'name' => $name,
            'author' => $author
        ]);

        $Parameters = $_POST;
        $Parameters['edit'] = 'success';
        $Parameters['id'] = $id;
        unset($Parameters['csrf_token']);
        header("Location: ../media.php?" . http_build_query($Parameters));
    } else {
        $Parameters = $_POST;
        $Parameters['edit'] = 'error';
        $Parameters['id'] = $id;
        unset($Parameters['csrf_token']);
        header("Location: ../media.php?" . http_build_query($Parameters));
    }
}
