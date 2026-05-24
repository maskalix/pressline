<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

$userId = $_SESSION['user_id'];
$user = $dbFetcher->user('id', $userId);
$preferences = $dbFetcher->preference('user_id', $userId);
$sitename = t('nav.settings');
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php"; ?>
    <script src="./res/js/settings.js"></script>
    <script>
        const webSettings = <?php
            $ws = $dbFetcher->allWebsets(1, 100);
            $ws = array_map(function ($w) {
                if ($w['key'] === 'smtp_pass') $w['value'] = '********';
                return $w;
            }, $ws);
            echo json_encode($ws);
        ?>;
    </script>
</head>
<body onload="ThemeChange()">
    <?php include __DIR__ . "/includes/sidebar.php"; ?>
    <main>
        <header class="page-header">
            <div class="page-header-text">
                <h1><?= $sitename ?></h1>
                <p><?= te('settings.subtitle') ?></p>
            </div>
            <div class="page-header-actions">
                <a href="uzivatel.php?id=<?= (int)$userId ?>" class="tool">
                    <span class="iconify-inline" data-icon="ic:round-account-circle"></span>
                    <span><?= te('settings.my_profile') ?></span>
                </a>
            </div>
        </header>

        <div class="settings-stack">
            <!-- User preferences -->
            <form action="./setter-proxy.php" method="post" class="settings-form">
                <?= csrf_field() ?>
                <fieldset class="form-section">
                    <legend>
                        <span class="iconify-inline" data-icon="ic:round-palette"></span>
                        <?= te('settings.appearance') ?>
                    </legend>
                    <?php if ($preferences !== null && $preferences !== ""): ?>
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= (int)$preferences['id'] ?>">
                    <?php else: ?>
                        <input type="hidden" name="action" value="add">
                    <?php endif; ?>
                    <input type="hidden" name="user_id" value="<?= (int)$userId ?>">
                    <input type="hidden" name="type" value="preference">

                    <div class="form-grid">
                        <div class="form-field">
                            <label for="mode"><?= te('settings.theme_mode') ?></label>
                            <select name="mode" id="mode" onchange="handleThemeChange(colors)">
                                <?php foreach ($themeOptions as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= (!isset($preferences['mode']) || $preferences['mode'] === $value) ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="color"><?= te('settings.color') ?></label>
                            <select name="color" id="color" onchange="handleThemeChange(colors)">
                                <?php foreach ($colorOptions as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= (isset($preferences['color']) && $preferences['color'] === $value) ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="language"><?= te('settings.language') ?></label>
                            <select name="language" id="language">
                                <?php
                                $currentLang = $preferences['language'] ?? ($GLOBALS['__pl_active_lang'] ?? 'cs');
                                foreach ($LANG_AVAILABLE as $code => $label): ?>
                                    <option value="<?= esc($code) ?>" <?= ($currentLang === $code) ? 'selected' : '' ?>>
                                        <?= esc($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn-green" type="submit">
                            <span class="iconify-inline" data-icon="ic:round-save"></span>
                            <span><?= te('settings.save_changes') ?></span>
                        </button>
                    </div>
                </fieldset>
            </form>

            <?php if ($_SESSION['user_level'] >= $admin_level): ?>
            <script src="./res/js/websets.js"></script>

            <div id="web-settings" class="settings-form">
                <fieldset class="form-section">
                    <legend>
                        <span class="iconify-inline" data-icon="ic:round-language"></span>
                        <?= te('settings.web_info') ?>
                    </legend>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="webset-name"><?= te('settings.web_name') ?></label>
                            <input type="text" name="value" id="webset-name" data-key="name">
                        </div>
                        <div class="form-field full-width">
                            <label for="webset-description"><?= te('settings.web_description') ?></label>
                            <input type="text" name="value" id="webset-description" data-key="description">
                        </div>
                        <div class="form-field">
                            <label for="webset-url"><?= te('settings.url_admin') ?></label>
                            <input type="text" name="value" id="webset-url" data-key="url">
                        </div>
                        <div class="form-field">
                            <label for="webset-frontend"><?= te('settings.url_frontend') ?></label>
                            <input type="text" name="value" id="webset-frontend" data-key="frontend">
                        </div>
                        <div class="form-field full-width">
                            <label for="webset-keywords"><?= te('settings.keywords') ?></label>
                            <input type="text" name="value" id="webset-keywords" data-key="keywords">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>
                        <span class="iconify-inline" data-icon="ic:round-image"></span>
                        <?= te('settings.logo_icons') ?>
                    </legend>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="webset-icon"><?= te('settings.icon') ?></label>
                            <input type="text" name="value" id="webset-icon" data-key="icon">
                        </div>
                        <div class="form-field">
                            <label for="webset-icon_full"><?= te('settings.logo_wide') ?></label>
                            <input type="text" name="value" id="webset-icon_full" data-key="icon_full">
                        </div>
                        <div class="form-field">
                            <label for="webset-icon_text_dark"><?= te('settings.logo_text_dark') ?></label>
                            <input type="text" name="value" id="webset-icon_text_dark" data-key="icon_text_dark">
                        </div>
                        <div class="form-field">
                            <label for="webset-icon_text_light"><?= te('settings.logo_text_light') ?></label>
                            <input type="text" name="value" id="webset-icon_text_light" data-key="icon_text_light">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>
                        <span class="iconify-inline" data-icon="ic:round-tune"></span>
                        <?= te('settings.defaults') ?>
                    </legend>
                    <div class="form-grid">
                        <div class="form-field full-width">
                            <label for="webset-default_article_img"><?= te('settings.default_article_img') ?></label>
                            <input type="text" name="value" id="webset-default_article_img" data-key="default_article_img">
                        </div>
                        <div class="form-field full-width">
                            <label for="webset-default_user_icon"><?= te('settings.default_user_icon') ?></label>
                            <input type="text" name="value" id="webset-default_user_icon" data-key="default_user_icon">
                        </div>
                        <div class="form-field">
                            <label for="webset-language"><?= te('settings.web_language') ?></label>
                            <input type="text" name="value" id="webset-language" data-key="language">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>
                        <span class="iconify-inline" data-icon="ic:round-mail"></span>
                        <?= te('settings.smtp') ?>
                    </legend>
                    <div class="form-grid">
                        <div class="form-field full-width">
                            <label for="webset-from"><?= te('settings.smtp_from') ?></label>
                            <input type="text" name="value" id="webset-from" data-key="from">
                        </div>
                        <div class="form-field">
                            <label for="webset-smtp_host"><?= te('settings.smtp_host') ?></label>
                            <input type="text" name="value" id="webset-smtp_host" data-key="smtp_host">
                        </div>
                        <div class="form-field">
                            <label for="webset-smtp_port"><?= te('settings.smtp_port') ?></label>
                            <input type="text" name="value" id="webset-smtp_port" data-key="smtp_port">
                        </div>
                        <div class="form-field">
                            <label for="webset-smtp_sec"><?= te('settings.smtp_sec') ?></label>
                            <input type="text" name="value" id="webset-smtp_sec" data-key="smtp_sec">
                        </div>
                        <div class="form-field">
                            <label for="webset-smtp_user"><?= te('settings.smtp_user') ?></label>
                            <input type="text" name="value" id="webset-smtp_user" data-key="smtp_user">
                        </div>
                        <div class="form-field full-width">
                            <label for="webset-smtp_pass"><?= te('settings.smtp_pass') ?></label>
                            <input type="password" name="value" id="webset-smtp_pass" data-key="smtp_pass">
                            <label class="checkbox-row" style="margin-top:var(--space-2);">
                                <input type="checkbox" id="show-password" onclick="showPassword(this, document.getElementById('webset-smtp_pass'))">
                                <span><?= te('settings.show_password') ?></span>
                            </label>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>
                        <span class="iconify-inline" data-icon="ic:round-storefront"></span>
                        <?= te('settings.marketplace') ?>
                    </legend>
                    <div class="form-grid">
                        <div class="form-field full-width">
                            <label for="webset-marketplace_url"><?= te('settings.marketplace_url') ?></label>
                            <input type="text" name="value" id="webset-marketplace_url" data-key="marketplace_url" placeholder="https://marketplace.pressline.app">
                        </div>
                        <div class="form-field">
                            <label class="checkbox-row">
                                <input type="checkbox" id="webset-marketplace_telemetry" data-key="marketplace_telemetry" data-bool="1">
                                <span><?= te('settings.marketplace_telemetry') ?></span>
                            </label>
                            <p style="font-size:.8rem; color:var(--text-muted); margin-top:4px;">
                                <?= te('settings.marketplace_telemetry_help') ?>
                            </p>
                        </div>
                        <div class="form-field">
                            <label class="checkbox-row">
                                <input type="checkbox" id="webset-marketplace_auto_update" data-key="marketplace_auto_update" data-bool="1">
                                <span><?= te('settings.marketplace_auto_update') ?></span>
                            </label>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>
                        <span class="iconify-inline" data-icon="ic:round-link"></span>
                        <?= te('settings.urls') ?>
                    </legend>
                    <div class="form-grid">
                        <div class="form-field full-width">
                            <label class="checkbox-row">
                                <input type="checkbox" id="webset-hide_php_ext" data-key="hide_php_ext" data-bool="1">
                                <span><?= te('settings.hide_php') ?></span>
                            </label>
                            <p style="font-size:.8rem; color:var(--text-muted); margin-top:4px;">
                                <?= te('settings.hide_php.help') ?>
                            </p>
                            <p style="font-size:.8rem; color:var(--text-muted); margin-top:6px;">
                                <?= te('settings.hide_php.server') ?>:
                                <code><?= esc(pl_server_type()) ?></code>
                            </p>
                            <?php
                            $serverType = pl_server_type();
                            if ($serverType === 'apache' && pl_mod_rewrite_loaded() === false): ?>
                                <p style="font-size:.8rem; color:#e17777; margin-top:6px;">
                                    <?= te('settings.hide_php.unavailable') ?>
                                </p>
                            <?php elseif ($serverType === 'nginx'): ?>
                                <div style="margin-top:8px; font-size:.8rem;">
                                    <p style="color:var(--text-muted); margin:0 0 6px;">
                                        <?= te('settings.hide_php.nginx') ?>
                                    </p>
                                    <pre id="nginx-snippet"
                                         style="background:var(--bg-tertiary); padding:var(--space-3);
                                                border-radius:var(--radius-sm); overflow-x:auto; margin:0;
                                                font-size:var(--text-xs);"><?= esc(pl_hide_php_nginx_snippet()) ?></pre>
                                    <button type="button" class="tool" style="margin-top:6px;"
                                            onclick="(async()=>{await navigator.clipboard.writeText(document.getElementById('nginx-snippet').textContent); this.textContent=<?= htmlspecialchars(json_encode(t('settings.hide_php.nginx.copied')), ENT_QUOTES) ?>;})()">
                                        <?= te('settings.hide_php.nginx.copy') ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-field full-width">
                            <label for="webset-update_repo"><?= te('updates.repo') ?></label>
                            <input type="text" name="value" id="webset-update_repo" data-key="update_repo" placeholder="maskalix/PressLine">
                            <p style="font-size:.8rem; color:var(--text-muted); margin-top:4px;"><?= te('updates.repo.help') ?></p>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>
                        <span class="iconify-inline" data-icon="ic:round-shield"></span>
                        <?= te('settings.access_levels') ?>
                    </legend>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="webset-admin_level"><?= te('settings.admin_level') ?></label>
                            <input type="text" name="value" id="webset-admin_level" data-key="admin_level">
                        </div>
                        <div class="form-field">
                            <label for="webset-dev_level"><?= te('settings.dev_level') ?></label>
                            <input type="text" name="value" id="webset-dev_level" data-key="dev_level">
                        </div>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button class="btn-green" type="button" id="webset-save">
                        <span class="iconify-inline" data-icon="ic:round-save"></span>
                        <span><?= te('settings.save_web_changes') ?></span>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($_SESSION['user_level'] >= $dev_level): ?>
            <fieldset class="form-section">
                <legend>
                    <span class="iconify-inline" data-icon="ic:round-code"></span>
                    <?= te('settings.dev') ?>
                </legend>
                <p style="color:var(--text-muted); margin-bottom: var(--space-3);">
                    <?= te('settings.dev_backup_intro') ?>
                </p>
                <form action="download-db.php" method="post">
                    <button class="tool" type="submit">
                        <span class="iconify-inline" data-icon="ic:round-download"></span>
                        <span><?= te('settings.download_backup') ?></span>
                    </button>
                </form>
            </fieldset>
            <?php endif; ?>
        </div>
    </main>
    <?php include __DIR__ . "/includes/footer.php"; ?>
</body>
</html>
