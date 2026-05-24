<?php
require_once __DIR__ . "/pl-load.php";
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $user = $dbFetcher->user('reset_token', $token);

    if ($user) {
        // Check if the token is still valid
        if ($user['reset_token_expires'] > time()) {
            // Valid token, allow the user to reset the password
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                csrf_verify();
                $newPassword = $_POST['new_password'];
                $update = $dbSetter->user([
                    'action' => 'edit',
                    'id' => $user['id'],
                    'password' => $newPassword,
                    'reset_token' => '',
                    'reset_token_expires' => ''
                ]);
                $success = t('reset.change.flash.success');
            }
        } else {
            $error = t('reset.change.error.invalid');
        }
    } else {
        $error = t('reset.change.error.invalid');
    }
} else {
    $error = t('reset.change.error.invalid');
}
?>

<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <!-- Metatags -->
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
    <!-- CSSs -->
    <link rel="stylesheet" href="./res/css/admin.css">
    <title><?= te('reset.change.title') ?> | <?= esc($name);?></title>
    <style>
        #password-progress {
            height: 100%;
            border-radius: 5px;
        }

        #password-progress-bar {
            width: 100%;
            height: 0.5rem;
            background-color: var(--primary-0t5);
            border-radius: 5px;
            overflow: hidden;
            transition: all 2s;
        }

        .disclaimer-password-strength {
            margin-top: 5px;
            color:var(--secondary);
            text-shadow: 0 0 5px var(--accent);
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script>
        const I18N = {
            mismatch: <?= json_encode(t('reset.change.mismatch')) ?>,
            tooShort: <?= json_encode(t('reset.change.too_short')) ?>,
        };
        $(document).ready(function() {
            $("input[name='new_password'], input[name='enter_new_password']").on("click", function () {
                $("#password-req").show();
            });
            var submitButton = $("input[type='submit']");

            function checkPasswordStrength(password) {
                var uppercaseRegex = /[A-Z]/;
                var lowercaseRegex = /[a-z]/;
                var numberRegex = /\d/;
                var specialCharRegex = /[!@#$%^&*(),.?":{}|<>]/;

                var hasUppercase = uppercaseRegex.test(password);
                var hasLowercase = lowercaseRegex.test(password);
                var hasNumber = numberRegex.test(password);
                var hasSpecialChar = specialCharRegex.test(password);

                if (hasUppercase && hasNumber && hasSpecialChar) {
                    return 2; // Strong
                } else if (hasLowercase && (hasUppercase || hasSpecialChar || hasNumber)) {
                    return 1; // Medium
                } else if (password === "") {
                    return -1; // Empty
                } else {
                    return 0; // Weak
                }
            }

            function checkPasswordMatch() {
                var newPassword = $("input[name='new_password']");
                var confirmNewPassword = $("input[name='enter_new_password']").val();
                var disclaimerPassword = $(".disclaimer-password");

                if (newPassword.val() !== confirmNewPassword) {
                    newPassword.addClass("password-mismatch");
                    disclaimerPassword.html(I18N.mismatch);
                    return false;
                } else {
                    newPassword.removeClass("password-mismatch");
                    disclaimerPassword.html("");
                    return true;
                }
            }

            function updatePasswordStrengthIndicator(password) {
                var strength = checkPasswordStrength(password);
                var progressBar = $("#password-progress");
                var disclaimerPasswordReq = $(".disclaimer-password-req");
                switch (strength) {
                    case 0:
                        progressBar.css("width", "33%");
                        progressBar.css("background-color", "var(--error)");
                        break;
                    case 1:
                        progressBar.css("width", "66%");
                        progressBar.css("background-color", "var(--warn)");
                        break;
                    case 2:
                        progressBar.css("width", "100%");
                        progressBar.css("background-color", "var(--success)");
                        break;
                    default:
                        progressBar.css("width", "0%");
                        break;
                    case -1:
                        disclaimerPasswordStrength.html("");
                        progressBar.css("width", "0%");
                        break;
                }

                if (password.length >= 6) {
                    disclaimerPasswordReq.html("");
                } else {
                    disclaimerPasswordReq.html(I18N.tooShort);
                }
            }

            $("input[name='new_password']").on("keyup", function() {
                checkPasswordMatch();
                updatePasswordStrengthIndicator($("input[name='new_password']").val());
                updateSubmitButtonStatus();
            });

            $("input[name='enter_new_password']").on("keyup", function() {
                checkPasswordMatch();
                updatePasswordStrengthIndicator($("input[name='new_password']").val());
                updateSubmitButtonStatus();
            });

            function updateSubmitButtonStatus(value) {
                var isPasswordMatch = checkPasswordMatch();
                var passwordStrength = checkPasswordStrength($("input[name='new_password']").val());

                if (isPasswordMatch && passwordStrength >= 1 && (value === true || value === undefined)) {
                    submitButton.prop("disabled", false);
                } else {
                    submitButton.prop("disabled", true);
                }
            }
        });

        function togglePasswordVisibility() {
            var passwordInput = document.getElementById("new_password");
            var passwordInputEnter = document.getElementById("enter_new_password");
            var showPasswordCheckbox = document.getElementById("show-password");
            passwordInput.type = showPasswordCheckbox.checked ? "text" : "password";
            passwordInputEnter.type = showPasswordCheckbox.checked ? "text" : "password";
        }
    </script>
</head>
<body>
    <main>
        <div class="img" style="flex-direction:column;gap:var(--space-3);">
            <div style="display:flex;align-items:center;justify-content:center;width:64px;height:64px;background:var(--bg-tertiary);border-radius:var(--radius-lg);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 375 374.999991" height="40px">
                    <path fill="var(--text-primary)" d="M 363.316406 292.121094 C 363.324219 293.40625 363.519531 317.816406 363.507812 330.214844 C 363.503906 334.757812 360.949219 336.199219 356.402344 336.199219 L 192.28125 336.238281 C 190.09375 336.238281 187.996094 335.367188 186.453125 333.824219 C 184.90625 332.277344 184.039062 330.179688 184.039062 327.996094 L 184.15625 126.699219 L 232.617188 134.265625 L 232.628906 292.035156 C 232.628906 292.035156 313.894531 292.09375 363.316406 292.121094 Z"/>
                    <path stroke="var(--accent)" stroke-linecap="round" transform="matrix(0.817312, 0, 0, 0.907278, -30.561292, -6.42769)" fill="none" stroke-linejoin="round" d="M 92.723417 239.123147 C 93.516796 236.746532 142.596325 239.476195 186.083971 236.836947 C 210.243774 235.368784 227.664641 234.240753 246.046165 225.780521 C 303.231536 199.465538 290.71432 147.481392 291.674977 146.10795" stroke-width="56.439999"/>
                    <path fill="var(--text-primary)" d="M 62.726562 84.292969 L 62.648438 332.820312 C 62.648438 334.207031 61.765625 335.441406 60.453125 335.890625 C 59.140625 336.339844 57.6875 335.902344 56.835938 334.808594 C 49.769531 325.6875 38.773438 311.496094 38.765625 311.5 C 38.671875 311.621094 28.28125 325.578125 21.46875 334.734375 C 20.628906 335.851562 19.171875 336.304688 17.847656 335.867188 C 16.523438 335.425781 15.632812 334.191406 15.625 332.796875 C 15.496094 285.976562 14.949219 84.902344 14.835938 45.046875 C 14.832031 43.222656 15.554688 41.472656 16.839844 40.179688 C 18.128906 38.890625 19.875 38.164062 21.699219 38.164062 L 141.230469 38.164062 C 157.78125 38.164062 173.007812 42.390625 186.898438 50.824219 C 200.800781 59.246094 211.898438 70.636719 220.175781 84.976562 C 228.445312 99.304688 232.589844 115.496094 232.589844 133.542969 C 232.589844 134.128906 232.746094 138.164062 232.589844 142.675781 C 232.378906 148.644531 231.785156 155.449219 231.210938 156.222656 C 222.980469 167.296875 184.410156 163.355469 170.90625 169.921875 C 170.785156 169.980469 170.613281 169.960938 170.464844 169.902344 C 170.265625 169.820312 170.117188 169.664062 170.222656 169.542969 C 173.335938 165.960938 178.066406 160.015625 180.203125 155.242188 C 184.511719 145.621094 183.855469 134.605469 183.808594 133.542969 C 183.71875 131.585938 183.40625 127.636719 183.296875 126.355469 C 182.300781 114.820312 178.367188 106.683594 170.929688 98.484375 C 162.355469 89.023438 152.15625 84.292969 140.328125 84.292969 Z"/>
                </svg>
            </div>
            <h1><?= te('reset.change.title') ?></h1>
        </div>
        <div class="form">
            <div id="info">
                <?php if (isset($error)) echo esc($error); ?>
                <?php if (isset($success)) echo esc($success); ?>
            </div>
            <?php if (!isset($success) & !isset($error)) : ?>
                <form action="reset-hesla.php?token=<?= esc($token) ?>" method="post">
                    <?= csrf_field() ?>
                    <label for="new_password"><?= te('reset.change.new') ?></label>
                    <br>
                    <input placeholder="<?= te('reset.change.new_placeholder') ?>" type="password" autocomplete="new-password" name="enter_new_password" id="enter_new_password">
                    <br>
                    <label for="new_password"><?= te('reset.change.confirm') ?></label>
                    <br>
                    <input placeholder="<?= te('reset.change.confirm_placeholder') ?>" type="password" autocomplete="new-password" name="new_password" id="new_password">
                    <br>
                    <div id="password-progress-bar">
                        <div id="password-progress"></div>
                    </div>
                    <div class="disclaimer-password" style="color: var(--warn); margin-bottom: 10px;"></div>
                    <div id="password-req" style="display:none;">
                        <details>
                            <summary><?= te('reset.change.requirements') ?></summary>
                            <ul>
                                <li><?= te('reset.change.req.min6') ?></li>
                                <li><?= te('reset.change.req.lower') ?></li>
                                <li><?= te('reset.change.req.upper_or_special') ?></li>
                            </ul>
                        </details>
                        <details>
                            <summary><?= te('reset.change.recommended') ?></summary>
                            <ul>
                                <li><?= te('reset.change.req.min6') ?></li>
                                <li><?= te('reset.change.req.lower') ?></li>
                                <li><?= te('reset.change.req.upper') ?></li>
                                <li><?= te('reset.change.req.number') ?></li>
                                <li><?= te('reset.change.req.special') ?></li>
                            </ul>
                        </details>
                    </div>
                    <div id="showPass">
                        <label for="show_password"><?= te('reset.change.show_password') ?></label>
                        <input type="checkbox" id="show-password" onclick="togglePasswordVisibility()">
                    </div>
                    <input class="submit btn-green" type="submit" value="<?= te('reset.change.submit') ?>" disabled>
                </form>
            <?php endif; ?>
            <div class="other-options">
                <a href="<?= esc($frontend); ?>">
                    <span class="iconify-inline" data-icon="ic:round-home"></span><?= te('login.back_home') ?></a>
                <a href="login.php">
                    <span class="iconify-inline" data-icon="ic:round-log-in"></span><?= te('reset.change.login') ?></a>
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
    </script>
</body>
</html>
