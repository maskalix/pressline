<?php
require_once __DIR__ . '/pl-load.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$validTypes = ['article', 'category', 'media', 'preference', 'role', 'user', 'webset'];

try {
    global $connection;
    $setter = new DatabaseSetter($connection);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $type = $_POST['type'] ?? null;
        $data = $_POST;
        unset($data['type']);
        unset($data['csrf_token']);

        if (!$type || !in_array($type, $validTypes, true)) {
            throw new Exception("Missing or invalid 'type' parameter.");
        }

        $success = $setter->$type($data);
        $message = $success ? t('flash.done') : t('flash.error');
        $state = $success ? "positive" : "negative";

        // Sync session lang if a user just updated their own language preference.
        if ($success
            && $type === 'preference'
            && (int)($data['user_id'] ?? 0) === (int)$_SESSION['user_id']
            && !empty($data['language'])
            && isset($LANG_AVAILABLE[$data['language']])) {
            $_SESSION['lang'] = $data['language'];
            setcookie('selectedLanguage', $data['language'], [
                'expires'  => time() + 60 * 60 * 24 * 365,
                'path'     => '/',
                'samesite' => 'Lax',
                'httponly' => false,
            ]);
        }

        // Apply hide_php_ext to .htaccess when the toggle changes.
        if ($success && $type === 'webset' && ($data['key'] ?? '') === 'hide_php_ext') {
            $enable = ($data['value'] ?? '0') === '1';
            $apply = pl_apply_hide_php($enable);
            if (!$apply['ok']) {
                // Don't fail the whole request — surface a warning instead.
                $message = $apply['message'];
                $state   = 'negative';
            }
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {
            header('Content-Type: application/json');
            echo json_encode(["state" => $state, "message" => $message]);
        } else {
            $origin = $_POST['origin'] ?? $_SERVER['HTTP_REFERER'] ?? '/';
            $redirectUrl = buildUrlWithStateAndMessage($origin, $state, $message);
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
} catch (Exception $e) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch') {
        header('Content-Type: application/json');
        echo json_encode(["state" => "negative", "message" => $e->getMessage()]);
    } else {
        $origin = $_POST['origin'] ?? $_SERVER['HTTP_REFERER'] ?? '/';
        $redirectUrl = buildUrlWithStateAndMessage($origin, 'negative', $e->getMessage());
        header('Location: ' . $redirectUrl);
        exit;
    }
}

function buildUrlWithStateAndMessage(string $url, string $state, string $message): string {
    $parsedUrl = parse_url($url);

    $baseUrl = '';
    if (isset($parsedUrl['path'])) {
        $baseUrl = basename($parsedUrl['path']);
        if ($baseUrl === '') $baseUrl = 'index.php';
    } else {
        $baseUrl = 'index.php';
    }

    $queryParams = [];
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $queryParams);
    }

    unset($queryParams['state'], $queryParams['message']);
    $queryParams['state'] = $state;
    $queryParams['message'] = $message;

    $newQuery = http_build_query($queryParams);
    $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

    return $baseUrl . ($newQuery ? '?' . $newQuery : '') . $fragment;
}
