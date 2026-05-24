<?php
require_once __DIR__ . '/_lang.php';
require_once __DIR__ . '/_state.php';
require_once __DIR__ . '/_debug.php';
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/_config.php';

if (file_exists(__DIR__ . '/../.installed') && !PL_INSTALLER_DEBUG) {
    http_response_code(403);
    pl_install_shell_open('welcome');
    ?>
    <section class="install-card">
        <h2><?= tie('lock.heading') ?></h2>
        <p class="lead"><?= ti('lock.body') ?></p>
        <p class="lead"><?= ti('lock.debug_hint') ?></p>
    </section>
    <?php
    pl_install_shell_close();
    exit;
}

/**
 * www-data permissions probe. Returns 'ok'|'fail'|'unknown'.
 */
function pl_install_dir_check(string $directory): string {
    if (!function_exists('posix_getpwnam') || !function_exists('posix_getgrnam')) {
        return 'unknown';
    }
    $userInfo  = @posix_getpwnam('www-data');
    $groupInfo = @posix_getgrnam('www-data');
    if ($userInfo === false || $groupInfo === false) return 'unknown';

    $ownerUid = @fileowner($directory);
    $groupGid = @filegroup($directory);
    $perms    = @fileperms($directory);
    if ($ownerUid === false || $perms === false) return 'unknown';

    $ownerRWX = ($perms & 0700) === 0700;
    $groupRWX = ($perms & 0070) === 0070;
    $otherRWX = ($perms & 0007) === 0007;

    if ($userInfo['uid']  === $ownerUid) return $ownerRWX ? 'ok' : 'fail';
    if ($groupInfo['gid'] === $groupGid) return $groupRWX ? 'ok' : 'fail';
    return $otherRWX ? 'ok' : 'fail';
}

function pl_install_dir_chip(string $status): string {
    $map = [
        'ok'      => '<span style="color:#4ade80;">✓ ' . tie('welcome.req.ok') . '</span>',
        'fail'    => '<span style="color:#e17777;">✗ ' . tie('welcome.req.fail') . '</span>',
        'unknown' => '<span style="color:var(--text-muted);">? ' . tie('welcome.req.unknown') . '</span>',
    ];
    return $map[$status] ?? $map['unknown'];
}

/**
 * Free-walk navigation: any step is reachable. Only sanitize unknown step
 * names. Required-step gating lives on the exit step + finish.php.
 */
$step = pl_install_resolve_step($_GET['step'] ?? 'welcome');

// CSRF check on every POST. Only fails if the token field is missing or stale —
// the clear-log button (handled in _debug.php earlier) is also a POST but
// short-circuits before this check.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !pl_install_csrf_check()) {
    pl_install_shell_open($step);
    ?>
    <section class="install-card">
        <div class="install-result err">
            <span class="iconify-inline" data-icon="ic:round-error"></span>
            <?= tie('error.csrf') ?>
        </div>
    </section>
    <?php
    pl_install_shell_close();
    exit;
}

/**
 * Per-step POST handlers — process *before* rendering. Storing data in
 * $_SESSION instead of writing config.default.php incrementally means the
 * user can refresh / back-button without leaving half-written state.
 */
$flash = null; // [class, key, args]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 'database':
            $db = [
                'host'    => trim($_POST['db_host'] ?? ''),
                'user'    => trim($_POST['db_user'] ?? ''),
                'pass'    => $_POST['db_pass'] ?? '',
                'name'    => trim($_POST['db_name'] ?? ''),
                'collate' => $_POST['db_collate'] ?? 'cz',
                'drop'    => !empty($_POST['db_drop']),
            ];
            if ($db['host'] === '' || $db['user'] === '' || $db['name'] === '') {
                $flash = ['err', 'db.fail'];
                break;
            }
            try {
                $conn = @mysqli_connect($db['host'], $db['user'], $db['pass'], $db['name']);
                if (!$conn) {
                    $flash = ['err', 'db.fail'];
                    break;
                }
                $conn->close();
            } catch (Throwable $e) {
                $flash = ['err', 'db.fail'];
                break;
            }
            // Persist to session (not to disk — that happens at "Create schema").
            pl_install_complete('database', $db);
            $flash = ['ok', 'db.ok'];
            break;

        case 'info':
            $info = [
                'name'        => trim($_POST['name'] ?? ''),
                'url'         => trim($_POST['url'] ?? ''),
                'frontend'    => trim($_POST['frontend'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'keywords'    => trim($_POST['keywords'] ?? ''),
                'language'    => trim($_POST['language'] ?? 'cs'),
            ];
            pl_install_complete('info', $info);
            header('Location: install.php?step=img');
            exit;

        case 'img':
            $img = [
                'icon'            => trim($_POST['icon'] ?? 'favicon.ico'),
                'icon_full'       => trim($_POST['icon_full'] ?? 'media/img/logo-full.png'),
                'icon_text_light' => trim($_POST['icon_text_light'] ?? ''),
                'icon_text_dark'  => trim($_POST['icon_text_dark'] ?? ''),
            ];
            pl_install_complete('img', $img);
            header('Location: install.php?step=smtp');
            exit;

        case 'smtp':
            // SMTP is optional — empty fields skip persistence.
            $smtp = [
                'from'      => trim($_POST['from'] ?? ''),
                'smtp_host' => trim($_POST['smtp_host'] ?? ''),
                'smtp_port' => trim($_POST['smtp_port'] ?? '465'),
                'smtp_pass' => $_POST['smtp_pass'] ?? '',
                'smtp_sec'  => $_POST['smtp_sec'] ?? 'ssl',
                'smtp_user' => trim($_POST['from'] ?? ''),
            ];
            pl_install_complete('smtp', $smtp);
            header('Location: install.php?step=webset');
            exit;
    }
}

/**
 * Render the current step.
 */
pl_install_shell_open($step);

switch ($step):
    case 'welcome': ?>
        <section class="install-card">
            <h2><?= tie('welcome.heading') ?></h2>
            <p class="lead"><?= tie('welcome.lead') ?></p>

            <fieldset class="form-section">
                <legend>
                    <span class="iconify-inline" data-icon="ic:round-checklist"></span>
                    <?= tie('welcome.requirements') ?>
                </legend>
                <ul style="padding-left:var(--space-5); margin:0; line-height:1.8;">
                    <li>
                        <?= ti('welcome.req.permissions') ?>
                    </li>
                    <li>
                        <?= ti('welcome.req.dir.install') ?>
                        — <?= pl_install_dir_chip(pl_install_dir_check(__DIR__)) ?>
                    </li>
                    <li>
                        <?= ti('welcome.req.dir.root') ?>
                        — <?= pl_install_dir_chip(pl_install_dir_check(__DIR__ . '/..')) ?>
                    </li>
                    <li><?= ti('welcome.req.mysql') ?></li>
                    <li><?= ti('welcome.req.smtp') ?></li>
                </ul>
            </fieldset>

            <?php pl_install_complete('welcome'); ?>
            <form method="get" action="install.php">
                <input type="hidden" name="step" value="database">
                <div class="install-actions">
                    <button type="submit" class="btn-green">
                        <span class="iconify-inline" data-icon="ic:round-arrow-forward"></span>
                        <?= tie('common.continue') ?>
                    </button>
                </div>
            </form>
        </section>
    <?php break;

    case 'database':
        $prev = pl_install_data('database');
        $isPending = empty($_SESSION['install_state']['completed']['database']);
        ?>
        <section class="install-card">
            <h2><?= tie('db.heading') ?> <span style="font-size:var(--text-xs); color:#e17777; vertical-align:middle;"><?= tie('required.badge') ?></span></h2>
            <p class="lead"><?= ti('db.lead') ?></p>
            <?php if ($isPending): ?>
                <div class="install-result info">
                    <span class="iconify-inline" data-icon="ic:round-info"></span>
                    <?= tie('required.missing') ?>
                </div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="install-result <?= $flash[0] ?>">
                    <span class="iconify-inline" data-icon="ic:round-<?= $flash[0] === 'ok' ? 'check-circle' : 'error' ?>"></span>
                    <?= tie($flash[1]) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?= pl_install_csrf_field() ?>
                <div class="form-section" style="border:0;padding:0;">
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="db_host"><?= tie('db.host') ?></label>
                            <input type="text" id="db_host" name="db_host" placeholder="localhost"
                                   value="<?= htmlspecialchars($prev['host'] ?? '', ENT_QUOTES) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="db_name"><?= tie('db.name') ?></label>
                            <input type="text" id="db_name" name="db_name" placeholder="pressline"
                                   value="<?= htmlspecialchars($prev['name'] ?? '', ENT_QUOTES) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="db_user"><?= tie('db.user') ?></label>
                            <input type="text" id="db_user" name="db_user" placeholder="root"
                                   value="<?= htmlspecialchars($prev['user'] ?? '', ENT_QUOTES) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="db_pass"><?= tie('db.pass') ?></label>
                            <input type="password" id="db_pass" name="db_pass"
                                   value="<?= htmlspecialchars($prev['pass'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field full-width">
                            <label for="db_collate"><?= tie('db.collate') ?></label>
                            <select id="db_collate" name="db_collate">
                                <option value="cz"        <?= ($prev['collate'] ?? 'cz') === 'cz' ? 'selected' : '' ?>><?= tie('db.collate.cz') ?></option>
                                <option value="universal" <?= ($prev['collate'] ?? '') === 'universal' ? 'selected' : '' ?>><?= tie('db.collate.universal') ?></option>
                            </select>
                        </div>
                        <div class="form-field full-width">
                            <label class="checkbox-row" style="display:flex;align-items:center;gap:var(--space-2);">
                                <input type="checkbox" name="db_drop" value="1" <?= !empty($prev['drop']) ? 'checked' : '' ?>>
                                <span><?= tie('db.drop') ?></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="install-actions">
                    <button type="submit" class="tool"><?= tie('db.test') ?></button>
                </div>
            </form>

            <?php if (!empty($_SESSION['install_state']['completed']['database'])): ?>
                <form method="post" action="db_install.php">
                    <?= pl_install_csrf_field() ?>
                    <div class="install-actions">
                        <button type="submit" class="btn-green">
                            <span class="iconify-inline" data-icon="ic:round-build"></span>
                            <?= tie('db.create') ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    <?php break;

    case 'info':
        $prev = pl_install_data('info');
        $isPending = empty($_SESSION['install_state']['completed']['info']);
        $defaults = [
            'name'        => 'PressLine',
            'url'         => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'frontend'    => 'https://web.' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'description' => 'Stránky poháněné PressLine',
            'keywords'    => 'blog, CMS, web',
            'language'    => $INSTALL_LANG,
        ];
        $v = $prev + $defaults;
        ?>
        <section class="install-card">
            <h2><?= tie('info.heading') ?> <span style="font-size:var(--text-xs); color:#e17777; vertical-align:middle;"><?= tie('required.badge') ?></span></h2>
            <p class="lead"><?= ti('info.lead') ?></p>
            <?php if ($isPending): ?>
                <div class="install-result info">
                    <span class="iconify-inline" data-icon="ic:round-info"></span>
                    <?= tie('required.missing') ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?= pl_install_csrf_field() ?>
                <div class="form-section" style="border:0;padding:0;">
                    <div class="form-grid">
                        <div class="form-field full-width">
                            <label for="name"><?= tie('info.name') ?></label>
                            <input type="text" id="name" name="name" required
                                   value="<?= htmlspecialchars($v['name'], ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label for="url"><?= tie('info.url') ?></label>
                            <input type="text" id="url" name="url" required
                                   value="<?= htmlspecialchars($v['url'], ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label for="frontend"><?= tie('info.frontend') ?></label>
                            <input type="text" id="frontend" name="frontend" required
                                   value="<?= htmlspecialchars($v['frontend'], ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field full-width">
                            <label for="description"><?= tie('info.description') ?></label>
                            <textarea id="description" name="description" rows="3" required><?= htmlspecialchars($v['description']) ?></textarea>
                        </div>
                        <div class="form-field">
                            <label for="keywords"><?= tie('info.keywords') ?></label>
                            <input type="text" id="keywords" name="keywords" required
                                   value="<?= htmlspecialchars($v['keywords'], ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label for="language"><?= tie('info.language') ?></label>
                            <input type="text" id="language" name="language" placeholder="cs" required
                                   value="<?= htmlspecialchars($v['language'], ENT_QUOTES) ?>">
                        </div>
                    </div>
                </div>
                <div class="install-actions">
                    <button type="submit" class="btn-green">
                        <span class="iconify-inline" data-icon="ic:round-arrow-forward"></span>
                        <?= tie('common.continue') ?>
                    </button>
                </div>
            </form>
        </section>
    <?php break;

    case 'img':
        $prev = pl_install_data('img');
        $defaults = [
            'icon'            => 'favicon.ico',
            'icon_full'       => 'media/img/logo-full.png',
            'icon_text_light' => '',
            'icon_text_dark'  => '',
        ];
        $v = $prev + $defaults;
        ?>
        <section class="install-card">
            <h2><?= tie('img.heading') ?></h2>
            <p class="lead"><?= ti('img.lead') ?></p>

            <form method="post">
                <?= pl_install_csrf_field() ?>
                <div class="form-section" style="border:0;padding:0;">
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="icon"><?= tie('img.icon') ?></label>
                            <input type="text" id="icon" name="icon" required
                                   value="<?= htmlspecialchars($v['icon'], ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label for="icon_full"><?= tie('img.icon_full') ?></label>
                            <input type="text" id="icon_full" name="icon_full" required
                                   value="<?= htmlspecialchars($v['icon_full'], ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label for="icon_text_light"><?= tie('img.icon_text') ?> · <?= tie('img.light') ?></label>
                            <input type="text" id="icon_text_light" name="icon_text_light"
                                   value="<?= htmlspecialchars($v['icon_text_light'], ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label for="icon_text_dark"><?= tie('img.icon_text') ?> · <?= tie('img.dark') ?></label>
                            <input type="text" id="icon_text_dark" name="icon_text_dark"
                                   value="<?= htmlspecialchars($v['icon_text_dark'], ENT_QUOTES) ?>">
                        </div>
                    </div>
                </div>
                <div class="install-actions">
                    <a href="?step=info" class="skip">‹ <?= tie('common.back') ?></a>
                    <button type="submit" class="btn-green">
                        <span class="iconify-inline" data-icon="ic:round-arrow-forward"></span>
                        <?= tie('common.continue') ?>
                    </button>
                </div>
            </form>
        </section>
    <?php break;

    case 'smtp':
        $prev = pl_install_data('smtp');
        $defaults = [
            'from'      => '',
            'smtp_host' => '',
            'smtp_port' => '465',
            'smtp_pass' => '',
            'smtp_sec'  => 'ssl',
        ];
        $v = $prev + $defaults;
        ?>
        <section class="install-card">
            <h2><?= tie('smtp.heading') ?></h2>
            <p class="lead"><?= tie('smtp.lead') ?></p>

            <form method="post">
                <?= pl_install_csrf_field() ?>
                <div class="form-section" style="border:0;padding:0;">
                    <div class="form-grid">
                        <div class="form-field full-width">
                            <label for="from"><?= tie('smtp.user') ?></label>
                            <input type="email" id="from" name="from"
                                   value="<?= htmlspecialchars($v['from'], ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label for="smtp_pass"><?= tie('smtp.pass') ?></label>
                            <input type="password" id="smtp_pass" name="smtp_pass"
                                   value="<?= htmlspecialchars($v['smtp_pass'], ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label for="smtp_host"><?= tie('smtp.host') ?></label>
                            <input type="text" id="smtp_host" name="smtp_host" placeholder="smtp.example.com"
                                   value="<?= htmlspecialchars($v['smtp_host'], ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label for="smtp_port"><?= tie('smtp.port') ?></label>
                            <input type="number" id="smtp_port" name="smtp_port" min="1" max="65535"
                                   value="<?= htmlspecialchars($v['smtp_port'], ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label for="smtp_sec"><?= tie('smtp.sec') ?></label>
                            <select id="smtp_sec" name="smtp_sec">
                                <option value="ssl" <?= $v['smtp_sec'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="tls" <?= $v['smtp_sec'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="install-actions">
                    <a href="?step=img" class="skip">‹ <?= tie('common.back') ?></a>
                    <button type="submit" class="btn-green">
                        <span class="iconify-inline" data-icon="ic:round-arrow-forward"></span>
                        <?= tie('common.continue') ?>
                    </button>
                </div>
            </form>
        </section>
    <?php break;

    case 'webset':
        // Render is handled by webset.php which acts as a step body — it
        // performs the inserts (or simulates them) and renders a status
        // panel. install.php just provides the shell + the final form.
        require __DIR__ . '/webset.php';
        break;

    case 'exit':
        $pending = pl_install_required_pending();
        $canFinish = empty($pending);
        ?>
        <section class="install-card">
            <h2><?= tie('exit.heading') ?></h2>
            <p class="lead"><?= ti('exit.lead') ?></p>

            <?php if (!$canFinish): ?>
                <div class="install-result err">
                    <span class="iconify-inline" data-icon="ic:round-error"></span>
                    <span>
                        <?= tie('exit.blocked') ?>
                        <?php foreach ($pending as $i => $s): ?>
                            <?= $i > 0 ? ', ' : ' ' ?>
                            <a href="?step=<?= urlencode($s) ?>" style="color:inherit; text-decoration:underline;">
                                <?= tie('step.' . $s) ?>
                            </a>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="install-creds">
                <strong><?= tie('exit.creds') ?></strong>
                <dl>
                    <dt><?= tie('exit.username') ?></dt>
                    <dd>pressline</dd>
                    <dt><?= tie('exit.password') ?></dt>
                    <dd>pressline24</dd>
                </dl>
                <div class="install-result err" style="margin-top:var(--space-3);">
                    <span class="iconify-inline" data-icon="ic:round-warning"></span>
                    <?= tie('exit.warn') ?>
                </div>
            </div>

            <p class="lead"><?= ti('exit.cleanup_intro') ?></p>

            <form method="post" action="finish.php">
                <?= pl_install_csrf_field() ?>
                <div class="install-actions">
                    <button type="submit" class="btn-green" <?= $canFinish ? '' : 'disabled' ?>>
                        <span class="iconify-inline" data-icon="ic:round-check"></span>
                        <?= tie('exit.finish') ?>
                    </button>
                </div>
            </form>
        </section>
    <?php break;
endswitch;

pl_install_shell_close();
