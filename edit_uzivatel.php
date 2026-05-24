<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

$userId = isset($_GET['id']) ? $_GET['id'] : 0;
$sitename = ($userId > 0) ? t('user_editor.title.edit') : t('user_editor.title.new');
$allowEdit = true;

if ($userId > 0) {
    $userDetails = $dbFetcher->user('id', $userId);
    $allowEdit = $_SESSION['user_level'] > $dbFetcher->role('id', $userDetails['role'])['level'];
}

if ($_SESSION['user_level'] < $admin_level || $allowEdit === false) {
    if (intval($_SESSION['user_id']) !== intval($_GET['id'] ?? 0)) {
        header("Location: chyba.php?err=403&msg=" . rawurlencode(t('error.no_permission_edit_user')));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php";?>
</head>
<body>
    <?php include __DIR__ . "/includes/sidebar.php";?>
    <main>
        <h1><?= $sitename ?></h1>

        <form class="user-form" method="post" action="setter-proxy.php">
            <?= csrf_field() ?>
            <?php if ($userId > 0): ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $userId ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>
            <input type="hidden" name="type" value="user">
            <input type="hidden" name="origin" value="<?= esc($url) ?>">

            <!-- Section: Account -->
            <fieldset class="form-section">
                <legend>
                    <span class="iconify-inline" data-icon="ic:round-account-circle"></span>
                    <?= te('user_editor.section.account') ?>
                </legend>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="username"><?= te('user_editor.username') ?></label>
                        <input id="username" autocomplete="off" placeholder="<?= te('user_editor.username_ph') ?>"
                               type="text" name="username"
                               value="<?= esc($userDetails['username'] ?? '') ?>">
                        <span class="disclaimer-username field-error"></span>
                    </div>
                    <?php if ($_SESSION['user_level'] >= $admin_level): ?>
                    <div class="form-field">
                        <label for="role"><?= te('user_editor.role') ?></label>
                        <select id="role" name="role">
                            <?php $roles = $dbFetcher->allRoles(); foreach ($roles as $role): ?>
                                <option value="<?= (int)$role['id'] ?>"
                                    <?= isset($userDetails['role']) && $userDetails['role'] == $role['id'] ? 'selected' : '' ?>>
                                    <?= esc($role['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <!-- Section: Personal info -->
            <fieldset class="form-section">
                <legend>
                    <span class="iconify-inline" data-icon="ic:round-badge"></span>
                    <?= te('user_editor.section.personal') ?>
                </legend>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="name"><?= te('user_editor.name') ?> <span class="required">*</span></label>
                        <input id="name" type="text" name="name" placeholder="<?= te('user_editor.name_ph') ?>"
                               value="<?= esc($userDetails['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-field">
                        <label for="surname"><?= te('user_editor.surname') ?> <span class="required">*</span></label>
                        <input id="surname" type="text" name="surname" placeholder="<?= te('user_editor.surname_ph') ?>"
                               value="<?= esc($userDetails['surname'] ?? '') ?>" required>
                    </div>
                    <div class="form-field">
                        <label for="mail"><?= te('user_editor.email') ?></label>
                        <input id="mail" type="email" name="mail" placeholder="mail@example.com"
                               value="<?= esc($userDetails['mail'] ?? '') ?>">
                    </div>
                    <div class="form-field">
                        <label for="img"><?= te('user_editor.profile_img') ?></label>
                        <input id="img" type="text" name="img" placeholder="https://…"
                               value="<?= esc($userDetails['img'] ?? '') ?>">
                    </div>
                    <div class="form-field full-width">
                        <label for="story"><?= te('user_editor.story') ?></label>
                        <input id="story" type="text" name="story" placeholder="<?= te('user_editor.story_ph') ?>"
                               value="<?= esc($userDetails['story'] ?? '') ?>">
                    </div>
                </div>
            </fieldset>

            <!-- Section: Social -->
            <fieldset class="form-section">
                <legend>
                    <span class="iconify-inline" data-icon="ic:round-share"></span>
                    <?= te('user_editor.section.social') ?>
                </legend>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="social_ig">
                            <span class="iconify-inline" data-icon="ri:instagram-line"></span> Instagram
                        </label>
                        <input id="social_ig" type="text" name="social_ig" placeholder="username"
                               value="<?= esc($userDetails['social_ig'] ?? '') ?>">
                    </div>
                    <div class="form-field">
                        <label for="social_fb">
                            <span class="iconify-inline" data-icon="ri:facebook-circle-fill"></span> Facebook
                        </label>
                        <input id="social_fb" type="text" name="social_fb" placeholder="username"
                               value="<?= esc($userDetails['social_fb'] ?? '') ?>">
                    </div>
                    <div class="form-field">
                        <label for="social_x">
                            <span class="iconify-inline" data-icon="simple-icons:x"></span> X
                        </label>
                        <input id="social_x" type="text" name="social_x" placeholder="handle"
                               value="<?= esc($userDetails['social_x'] ?? '') ?>">
                    </div>
                </div>
            </fieldset>

            <!-- Section: Password -->
            <fieldset class="form-section">
                <legend>
                    <span class="iconify-inline" data-icon="ic:round-lock"></span>
                    <?= te($userId > 0 ? 'user_editor.section.password.change' : 'user_editor.section.password.set') ?>
                    <?= $userId > 0 ? '' : '<span class="required">*</span>' ?>
                </legend>

                <?php if ($userId > 0): ?>
                <label class="checkbox-row">
                    <input type="checkbox" id="no-change">
                    <span><?= te('user_editor.no_change') ?></span>
                </label>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-field">
                        <label for="password"><?= te('user_editor.new_password') ?></label>
                        <input id="password" type="password" autocomplete="new-password"
                               name="password" placeholder="<?= te('user_editor.new_password_ph') ?>">
                    </div>
                    <div class="form-field">
                        <label for="enter_password"><?= te('user_editor.confirm_password') ?></label>
                        <input id="enter_password" type="password" autocomplete="new-password"
                               name="enter_password" placeholder="<?= te('user_editor.confirm_password_ph') ?>">
                    </div>
                </div>

                <div id="password-progress-bar"><div id="password-progress"></div></div>
                <div class="disclaimer-row">
                    <span class="disclaimer-password-strength field-info"></span>
                    <span class="disclaimer-password field-error"></span>
                    <span class="disclaimer-password-req field-error"></span>
                </div>

                <details class="password-help" id="password-req">
                    <summary><?= te('user_editor.req.title') ?></summary>
                    <div class="password-help-content">
                        <div>
                            <h4><?= te('user_editor.req.minimum') ?></h4>
                            <ul>
                                <li><?= te('user_editor.req.min6') ?></li>
                                <li><?= te('user_editor.req.lower') ?></li>
                                <li><?= te('user_editor.req.upper_or') ?></li>
                            </ul>
                        </div>
                        <div>
                            <h4><?= te('user_editor.req.recommended') ?></h4>
                            <ul>
                                <li><?= te('user_editor.req.min6') ?></li>
                                <li><?= te('user_editor.req.lower') ?></li>
                                <li><?= te('user_editor.req.upper') ?></li>
                                <li><?= te('user_editor.req.number') ?></li>
                                <li><?= te('user_editor.req.special') ?></li>
                            </ul>
                        </div>
                    </div>
                </details>

                <label class="checkbox-row">
                    <input type="checkbox" id="show-password">
                    <span><?= te('user_editor.show_password') ?></span>
                </label>
            </fieldset>

            <div class="form-actions">
                <a href="<?= $userId > 0 ? './uzivatel.php?id=' . (int)$userId : './uzivatele.php' ?>" class="tool">
                    <span class="iconify-inline" data-icon="ic:round-arrow-back"></span>
                    <?= te('user_editor.cancel') ?>
                </a>
                <input class="btn-green" type="submit"
                       value="<?= te($userId > 0 ? 'user_editor.save' : 'user_editor.create') ?>" disabled>
            </div>
        </form>
    </main>
    <?php include __DIR__ . "/includes/footer.php";?>

    <script>
    const I18N = {
        mismatch: <?= json_encode(t('user_editor.js.mismatch')) ?>,
        weak:     <?= json_encode(t('user_editor.js.weak')) ?>,
        medium:   <?= json_encode(t('user_editor.js.medium')) ?>,
        strong:   <?= json_encode(t('user_editor.js.strong')) ?>,
        tooShort: <?= json_encode(t('user_editor.js.too_short')) ?>,
        taken:    <?= json_encode(t('user_editor.js.taken')) ?>,
    };
    document.addEventListener("DOMContentLoaded", function () {
        const passwordInput = document.querySelector("input[name='password']");
        const confirmPasswordInput = document.querySelector("input[name='enter_password']");
        const usernameInput = document.querySelector("input[name='username']");
        const submitButton = document.querySelector("input[type='submit']");
        const progressBar = document.getElementById("password-progress");
        const disclaimerPassword = document.querySelector(".disclaimer-password");
        const disclaimerPasswordStrength = document.querySelector(".disclaimer-password-strength");
        const disclaimerPasswordReq = document.querySelector(".disclaimer-password-req");
        const disclaimerUsername = document.querySelector(".disclaimer-username");
        const checkbox = document.getElementById("no-change");
        const showPasswordCheckbox = document.getElementById("show-password");

        function checkPasswordStrength(password) {
            const uppercase = /[A-Z]/.test(password);
            const number = /\d/.test(password);
            const special = /[!@#$%^&*(),.?":{}|<>]/.test(password);

            if (uppercase && number && special) return 2;
            if (uppercase || number || special) return 1;
            if (password === "") return -1;
            return 0;
        }

        function checkPasswordMatch() {
            const a = passwordInput.value;
            const b = confirmPasswordInput.value;
            if (a !== b) {
                passwordInput.classList.add("password-mismatch");
                disclaimerPassword.textContent = I18N.mismatch;
                return false;
            } else {
                passwordInput.classList.remove("password-mismatch");
                disclaimerPassword.textContent = "";
                return true;
            }
        }

        function updatePasswordStrengthIndicator(password) {
            const strength = checkPasswordStrength(password);
            const map = {
                0: { width: "33%", color: "var(--error)", text: I18N.weak },
                1: { width: "66%", color: "var(--warn)", text: I18N.medium },
                2: { width: "100%", color: "var(--success)", text: I18N.strong }
            };
            const info = map[strength];
            if (info) {
                progressBar.style.width = info.width;
                progressBar.style.backgroundColor = info.color;
                disclaimerPasswordStrength.textContent = info.text;
                disclaimerPasswordStrength.style.color = info.color;
            } else {
                progressBar.style.width = "0%";
                disclaimerPasswordStrength.textContent = "";
            }

            disclaimerPasswordReq.textContent = (password.length >= 6 || password.length === 0)
                ? "" : I18N.tooShort;
        }

        const isEditMode = <?= $userId > 0 ? 'true' : 'false' ?>;

        function updateSubmitButtonStatus() {
            const match = checkPasswordMatch();
            const strength = checkPasswordStrength(passwordInput.value);
            const noChange = checkbox && checkbox.checked;
            const passwordEmpty = passwordInput.value === "" && confirmPasswordInput.value === "";
            const usernameTaken = usernameInput.classList.contains("taken");

            if (usernameTaken || !usernameInput.value) {
                submitButton.disabled = true;
                return;
            }

            // In edit mode: empty password fields means "don't change password"
            if (isEditMode && passwordEmpty) {
                submitButton.disabled = false;
                return;
            }

            if (match && strength >= 1) {
                submitButton.disabled = false;
            } else if (noChange) {
                submitButton.disabled = false;
            } else {
                submitButton.disabled = true;
            }
        }

        function togglePasswordVisibility() {
            const t = showPasswordCheckbox.checked ? "text" : "password";
            passwordInput.type = t;
            confirmPasswordInput.type = t;
        }

        function checkUsernameAvailability(callback) {
            const username = usernameInput.value;
            if (!username) { if (callback) callback(true); return; }

            fetch("./ajax/check-username.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    username: username,
                    userId: <?= json_encode($userId) ?>
                }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === "unauthorized") return;
                if (data.status === "taken") {
                    usernameInput.classList.add("taken");
                    disclaimerUsername.textContent = I18N.taken;
                    if (callback) callback(false);
                } else {
                    usernameInput.classList.remove("taken");
                    disclaimerUsername.textContent = "";
                    if (callback) callback(true);
                }
            })
            .catch(err => console.error("Error:", err));
        }

        passwordInput.addEventListener("input", () => {
            updatePasswordStrengthIndicator(passwordInput.value);
            updateSubmitButtonStatus();
        });

        confirmPasswordInput.addEventListener("input", () => {
            updatePasswordStrengthIndicator(passwordInput.value);
            updateSubmitButtonStatus();
        });

        usernameInput.addEventListener("input", () => {
            checkUsernameAvailability(() => updateSubmitButtonStatus());
        });

        if (checkbox) {
            checkbox.addEventListener("change", () => {
                updateSubmitButtonStatus();
            });
        }

        showPasswordCheckbox.addEventListener("change", togglePasswordVisibility);

        // Initial check on page load (validates username, enables submit if no changes needed)
        checkUsernameAvailability(() => updateSubmitButtonStatus());
    });
    </script>
</body>
</html>
