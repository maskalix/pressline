<?php
require_once dirname(__DIR__) . '/pl-load.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $Color = isset($_POST['color']) && in_array($_POST['color'], $colors) ? $_POST['color'] : 'def';
    $Mode = isset($_POST['theme']) && in_array($_POST['theme'], array_keys($themeOptions)) ? $_POST['theme'] : 'def';
    $Lang = isset($_POST['language']) && isset($LANG_AVAILABLE[$_POST['language']])
        ? $_POST['language']
        : ($_SESSION['lang'] ?? 'cs');

    $existingPref = $dbFetcher->preference('user_id', $userId);
    $dbSetter->preference([
        'action' => $existingPref ? 'edit' : 'add',
        'id' => $existingPref ? $existingPref['id'] : null,
        'user_id' => $userId,
        'mode' => $Mode,
        'color' => $Color,
        'language' => $Lang,
    ]);

    $_SESSION['lang'] = $Lang;
    setcookie('selectedLanguage', $Lang, [
        'expires'  => time() + 60 * 60 * 24 * 365,
        'path'     => '/',
        'samesite' => 'Lax',
        'httponly' => false, // read by client-side date formatters too
    ]);

    header("Location: ../nastaveni.php");
    exit();
} else {
    header("Location: ../nastaveni.php");
    exit();
}
