<?php
require_once __DIR__ . "/pl-load.php";
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify();
    $username = $_POST['username'];
    $password = $_POST['password'];

    $user = $dbFetcher->user('username', $username);

    if (isset($user['id']) && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $user_role = $dbFetcher->user('id', $user['id'])['role'];
        $_SESSION['user_role'] = $user_role;
        $_SESSION['user_level'] = $dbFetcher->role('id', $user_role)['level'];
        $_SESSION['user_logged_in'] = true;
        $pref = $dbFetcher->preference('user_id', $user['id']);
        if (!empty($pref['language']) && isset($LANG_AVAILABLE[$pref['language']])) {
            $_SESSION['lang'] = $pref['language'];
            setcookie('selectedLanguage', $pref['language'], [
                'expires'  => time() + 60 * 60 * 24 * 365,
                'path'     => '/',
                'samesite' => 'Lax',
                'httponly' => false,
            ]);
        }
        if (function_exists('do_action')) do_action('user.afterLogin', $user);
        header("Location: index.php?type=positive&message=" . rawurlencode(t('login.flash.success')));
        exit();
    } else {
        $error = t('login.error.invalid');
    }
}
$sitename = t('login.title');
?>

<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-language" content="<?= esc($GLOBALS['__pl_active_lang']);?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= te('meta.description') ?>">
    <meta name="keywords" content="<?= esc($keywords);?>">
    <meta name="author" content="PressLine by Martin Skalicky">

    <meta property="og:title" content="<?= esc($name);?>">
    <meta property="og:type" content="pressline.app">
    <meta property="og:url" content="<?= esc($url);?>">
    <meta property="og:image" content="<?= esc($url);?>/<?= esc($icon_full);?>">

    <meta name="twitter:title" content="<?= esc($name);?>">
    <meta name="twitter:description" content="<?= esc($description);?>">
    <meta name="twitter:image" content="<?= esc($url);?>/<?= esc($icon_full);?>">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="image_src" href="<?= esc($url);?>/<?= esc($icon_full);?>">

    <script src="./res/js/iconify.min.js"></script>
    <link rel="stylesheet" href="./res/css/admin.css">
    <title><?= esc($sitename) . " | " . esc($name) ?></title>
</head>
<body>
    <main>
        <div class="img" style="flex-direction:column;gap:var(--space-3);">
            <div style="display:flex;align-items:center;justify-content:center;width:64px;height:64px;background:var(--bg-tertiary);border-radius:var(--radius-lg);">
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" zoomAndPan="magnify" viewBox="0 0 375 374.999991" height="40px" preserveAspectRatio="xMidYMid meet" version="1.0">
                    <path fill="var(--text-primary)" d="M 363.316406 292.121094 C 363.324219 293.40625 363.519531 317.816406 363.507812 330.214844 C 363.503906 334.757812 360.949219 336.199219 356.402344 336.199219 L 192.28125 336.238281 C 190.09375 336.238281 187.996094 335.367188 186.453125 333.824219 C 184.90625 332.277344 184.039062 330.179688 184.039062 327.996094 L 184.15625 126.699219 L 232.617188 134.265625 L 232.628906 292.035156 C 232.628906 292.035156 313.894531 292.09375 363.316406 292.121094 Z M 363.316406 292.121094 " fill-opacity="1" fill-rule="evenodd"/>
                    <path stroke="var(--accent)" stroke-linecap="round" transform="matrix(0.817312, 0, 0, 0.907278, -30.561292, -6.42769)" fill="none" stroke-linejoin="round" d="M 92.723417 239.123147 C 93.516796 236.746532 142.596325 239.476195 186.083971 236.836947 C 210.243774 235.368784 227.664641 234.240753 246.046165 225.780521 C 303.231536 199.465538 290.71432 147.481392 291.674977 146.10795 " stroke-width="56.439999" stroke-opacity="1" stroke-miterlimit="1.5"/>
                    <path fill="var(--text-primary)" d="M 62.726562 84.292969 L 62.648438 332.820312 C 62.648438 334.207031 61.765625 335.441406 60.453125 335.890625 C 59.140625 336.339844 57.6875 335.902344 56.835938 334.808594 C 49.769531 325.6875 38.773438 311.496094 38.765625 311.5 C 38.671875 311.621094 28.28125 325.578125 21.46875 334.734375 C 20.628906 335.851562 19.171875 336.304688 17.847656 335.867188 C 16.523438 335.425781 15.632812 334.191406 15.625 332.796875 C 15.496094 285.976562 14.949219 84.902344 14.835938 45.046875 C 14.832031 43.222656 15.554688 41.472656 16.839844 40.179688 C 18.128906 38.890625 19.875 38.164062 21.699219 38.164062 L 141.230469 38.164062 C 157.78125 38.164062 173.007812 42.390625 186.898438 50.824219 C 200.800781 59.246094 211.898438 70.636719 220.175781 84.976562 C 228.445312 99.304688 232.589844 115.496094 232.589844 133.542969 C 232.589844 134.128906 232.746094 138.164062 232.589844 142.675781 C 232.378906 148.644531 231.785156 155.449219 231.210938 156.222656 C 222.980469 167.296875 184.410156 163.355469 170.90625 169.921875 C 170.785156 169.980469 170.613281 169.960938 170.464844 169.902344 C 170.265625 169.820312 170.117188 169.664062 170.222656 169.542969 C 173.335938 165.960938 178.066406 160.015625 180.203125 155.242188 C 184.511719 145.621094 183.855469 134.605469 183.808594 133.542969 C 183.71875 131.585938 183.40625 127.636719 183.296875 126.355469 C 182.300781 114.820312 178.367188 106.683594 170.929688 98.484375 C 162.355469 89.023438 152.15625 84.292969 140.328125 84.292969 Z M 62.726562 84.292969 " fill-opacity="1" fill-rule="evenodd"/>
                </svg>
            </div>
            <h1><?= esc($sitename);?></h1>
        </div>
        <div class="form">
            <div id="info">
                <?php if (isset($error)) echo esc($error); ?>
            </div>
            <form action="login.php" method="post">
                <?= csrf_field() ?>
                <label for="username"><?= te('login.username') ?></label>
                <br>
                <input type="text" name="username" autocomplete="username" placeholder="<?= te('login.username_placeholder') ?>" required>
                <br><br>
                <label for="password"><?= te('login.password') ?></label>
                <br>
                <input type="password" name="password" autocomplete="current-password" placeholder="<?= te('login.password_placeholder') ?>" required>
                <br>
                <input class="submit btn-green" type="submit" value="<?= te('login.submit') ?>">
            </form>
            <div class="other-options">
                <a href="<?= esc($frontend); ?>">
                    <span class="iconify-inline" data-icon="ic:round-home"></span><?= te('login.back_home') ?></a>
                <a href="obnovit-heslo.php">
                    <span class="iconify-inline" data-icon="ic:round-key"></span><?= te('login.forgot_password') ?></a>
            </div>
        </div>
    </main>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var info = document.getElementById('info');
            if (info && info.innerHTML.trim() === '') {
                info.style.display = 'none';
            }
        })
        setTimeout(function() {
            var info = document.getElementById('info');
            if (info && info.innerHTML.trim() !== '') {
                info.style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>
