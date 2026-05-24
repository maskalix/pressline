<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

if ($_SESSION['user_level'] < $admin_level) {
    header("Location: chyba.php?err=403&msg=" . rawurlencode(t('error.no_permission_view')));
    exit();
}

$canApply = $_SESSION['user_level'] >= $dev_level;

// Action: refresh from GitHub.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check') {
    csrf_verify();
    pl_updates_refresh();
    header('Location: ' . pl_url('/aktualizace.php'));
    exit;
}

// Action: apply.
$applyResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    csrf_verify();
    if ($canApply && !empty($_POST['ack']) && !empty($_POST['tag'])) {
        $applyResult = pl_updates_apply((string)$_POST['tag']);
        if ($applyResult['ok']) {
            // Clean redirect so a refresh doesn't re-attempt.
            $msg = t('updates.run.ok', $applyResult['version'] ?? '?');
            header('Location: ' . pl_url('/aktualizace.php') . '?type=positive&message=' . rawurlencode($msg));
            exit;
        }
    }
}

$status   = pl_updates_status();
$current  = pl_updates_current_version();
$repo     = pl_updates_repo();
$sitename = t('updates.title');
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php"; ?>
    <style>
        .updates-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); }
        @media (max-width: 720px) { .updates-grid { grid-template-columns: 1fr; } }
        .version-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
        }
        .version-card .label {
            font-size: var(--text-xs);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }
        .version-card .version {
            font-family: var(--font-mono, monospace);
            font-size: var(--text-2xl);
            font-weight: var(--font-medium);
            margin-top: var(--space-2);
        }
        .version-card .meta {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-top: var(--space-1);
        }
        .updates-status {
            margin: var(--space-4) 0;
        }
        .updates-status .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: var(--radius-full);
            font-size: var(--text-sm);
        }
        .updates-status .pill.ok    { background: rgba(74,222,128,0.12); color: #4ade80; }
        .updates-status .pill.upd   { background: rgba(251,191,36,0.12); color: #fbbf24; }
        .updates-status .pill.err   { background: rgba(225,119,119,0.14); color: #e17777; }
        .changelog-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            margin-top: var(--space-4);
        }
        .changelog-card h2 { margin-top: 0; }
        .changelog-body {
            font-family: var(--font-mono, monospace);
            font-size: var(--text-sm);
            background: var(--bg-tertiary);
            padding: var(--space-4);
            border-radius: var(--radius-sm);
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 400px;
            overflow-y: auto;
        }
        .updates-warning {
            border: 1px solid #fbbf24;
            background: rgba(251,191,36,0.06);
            color: var(--text-secondary);
            border-radius: var(--radius-sm);
            padding: var(--space-3) var(--space-4);
            font-size: var(--text-sm);
        }
        .ack-row {
            display: flex; align-items: center; gap: var(--space-2);
            font-size: var(--text-sm);
            margin: var(--space-3) 0;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . "/includes/sidebar.php"; ?>
    <main>
        <header class="page-header">
            <div class="page-header-text">
                <h1><?= esc($sitename) ?></h1>
                <p><?= te('updates.subtitle') ?></p>
            </div>
            <div class="page-header-actions">
                <form method="post" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="check">
                    <button type="submit" class="tool">
                        <span class="iconify-inline" data-icon="ic:round-refresh"></span>
                        <span><?= te('updates.check_now') ?></span>
                    </button>
                </form>
            </div>
        </header>

        <div class="updates-grid">
            <div class="version-card">
                <div class="label"><?= te('updates.current') ?></div>
                <div class="version"><?= esc($current) ?></div>
                <div class="meta"><?= te('updates.repo') ?>: <code><?= esc($repo) ?></code></div>
            </div>
            <div class="version-card">
                <div class="label"><?= te('updates.latest') ?></div>
                <div class="version"><?= esc($status['latest_tag'] ?? '—') ?></div>
                <div class="meta">
                    <?php if (!empty($status['published_at'])): ?>
                        <?= te('updates.published', formatDate($status['published_at'], false)) ?>
                    <?php elseif (!empty($status['checked_at'])): ?>
                        <?= te('updates.last_checked', formatDate($status['checked_at'])) ?>
                    <?php else: ?>
                        <?= te('updates.never_checked') ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="updates-status">
            <?php if (!empty($status['error'])): ?>
                <span class="pill err">
                    <span class="iconify-inline" data-icon="ic:round-error"></span>
                    <?= te('updates.error', $status['error']) ?>
                </span>
            <?php elseif ($status['has_update']): ?>
                <span class="pill upd">
                    <span class="iconify-inline" data-icon="ic:round-system-update"></span>
                    <?= te('updates.available') ?>
                </span>
            <?php elseif (!empty($status['latest_tag'])): ?>
                <span class="pill ok">
                    <span class="iconify-inline" data-icon="ic:round-check-circle"></span>
                    <?= te('updates.up_to_date') ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($applyResult && !$applyResult['ok']): ?>
            <div class="updates-warning" style="border-color:#e17777; color:#e17777;">
                <strong><?= te('updates.run.fail', $applyResult['message']) ?></strong>
            </div>
        <?php endif; ?>

        <?php if (!empty($status['latest_body'])): ?>
            <section class="changelog-card">
                <h2>
                    <span class="iconify-inline" data-icon="ic:round-list-alt"></span>
                    <?= te('updates.changelog') ?>
                </h2>
                <div class="changelog-body"><?= esc($status['latest_body']) ?></div>
            </section>
        <?php endif; ?>

        <?php if ($status['has_update'] && $canApply): ?>
            <section class="changelog-card">
                <h2>
                    <span class="iconify-inline" data-icon="ic:round-system-update"></span>
                    <?= te('updates.run', $status['latest_tag']) ?>
                </h2>
                <div class="updates-warning">
                    <span class="iconify-inline" data-icon="ic:round-warning"></span>
                    <?= te('updates.run.warning') ?>
                </div>
                <form method="post" id="apply-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply">
                    <input type="hidden" name="tag" value="<?= esc($status['latest_tag']) ?>">
                    <label class="ack-row">
                        <input type="checkbox" name="ack" value="1" required>
                        <span><?= te('updates.run.ack') ?></span>
                    </label>
                    <div style="display:flex; justify-content:flex-end;">
                        <button type="submit" class="btn-green" id="apply-btn"
                                onclick="return confirm(<?= htmlspecialchars(json_encode(t('updates.run.confirm', $status['latest_tag'])), ENT_QUOTES) ?>);">
                            <span class="iconify-inline" data-icon="ic:round-system-update"></span>
                            <span><?= te('updates.run', $status['latest_tag']) ?></span>
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . "/includes/footer.php"; ?>
</body>
</html>
