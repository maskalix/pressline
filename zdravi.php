<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";
require_once __DIR__ . "/includes/health.php";

if ($_SESSION['user_level'] < $admin_level) {
    header("Location: chyba.php?err=403&msg=" . rawurlencode(t('error.no_permission_view')));
    exit();
}

$checks = pl_health_run();
$checks = pl_health_apply_suppressions($checks);
$summary = pl_health_summary($checks);
$grouped = pl_health_group($checks);
$canManage = $_SESSION['user_level'] >= $dev_level;

$sitename = t('health.title');

// Order groups so core stuff comes first; plugin-contributed and unknown
// groups land at the bottom in alphabetical order.
$groupOrder = ['environment', 'database', 'filesystem', 'config', 'security', 'mail', 'plugins'];
$ordered = [];
foreach ($groupOrder as $g) {
    if (isset($grouped[$g])) {
        $ordered[$g] = $grouped[$g];
        unset($grouped[$g]);
    }
}
ksort($grouped);
$ordered = array_merge($ordered, $grouped);
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php"; ?>
    <style>
        .health-summary {
            display: flex;
            gap: var(--space-3);
            flex-wrap: wrap;
            margin-bottom: var(--space-4);
        }
        .health-pill {
            padding: 6px 14px;
            border-radius: var(--radius-full);
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .health-pill .iconify-inline { width: 16px; height: 16px; }
        .health-pill.ok   { background: rgba(74,222,128,0.12); color: #4ade80; }
        .health-pill.warn { background: rgba(251,191,36,0.12); color: #fbbf24; }
        .health-pill.fail { background: rgba(225,119,119,0.14); color: #e17777; }

        .health-group {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-4);
            overflow: hidden;
        }
        .health-group-header {
            padding: var(--space-3) var(--space-5);
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border);
            font-weight: var(--font-medium);
            font-size: var(--text-sm);
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .health-group-body { padding: 0; }

        .health-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-5);
            border-top: 1px solid var(--border);
        }
        .health-item:first-child { border-top: 0; }
        .health-icon {
            width: 28px;
            height: 28px;
            border-radius: var(--radius-full);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .health-icon.ok    { background: rgba(74,222,128,0.15); color: #4ade80; }
        .health-icon.warn  { background: rgba(251,191,36,0.15); color: #fbbf24; }
        .health-icon.fail  { background: rgba(225,119,119,0.15); color: #e17777; }
        .health-icon.unknown { background: var(--bg-tertiary); color: var(--text-muted); }
        .health-icon .iconify-inline { width: 16px; height: 16px; }

        .health-text { display: flex; flex-direction: column; min-width: 0; }
        .health-label { font-weight: var(--font-medium); }
        .health-detail {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-top: 2px;
            word-break: break-word;
        }
        .health-action,
        .health-btn {
            font-size: var(--text-sm);
            color: var(--accent);
            text-decoration: none;
            white-space: nowrap;
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: transparent;
            font-family: inherit;
            cursor: pointer;
        }
        .health-action:hover,
        .health-btn:hover {
            background: var(--bg-tertiary);
        }
        .health-btn[disabled] {
            opacity: 0.5;
            cursor: progress;
        }
        .health-btn.health-suppress {
            border: 0;
            background: transparent;
            color: var(--text-muted);
            padding: 4px 6px;
        }
        .health-btn.health-suppress:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        .health-btn.health-suppress .iconify-inline {
            display: block;
            width: 18px;
            height: 18px;
        }
        .health-actions-cell {
            display: flex;
            gap: 6px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .health-item.is-suppressed {
            opacity: 0.55;
        }
        .health-item.is-suppressed .health-icon {
            background: var(--bg-tertiary) !important;
            color: var(--text-muted) !important;
        }
        .health-suppressed-badge {
            display: inline-block;
            padding: 1px 8px;
            margin-left: 8px;
            border-radius: var(--radius-full);
            background: var(--bg-tertiary);
            color: var(--text-muted);
            font-size: var(--text-xs);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            vertical-align: middle;
        }
        .health-pill.suppressed { background: var(--bg-tertiary); color: var(--text-muted); }
        .health-fix-flash {
            font-size: var(--text-xs);
            margin-left: 8px;
        }
        .health-fix-flash.ok { color: #4ade80; }
        .health-fix-flash.err { color: #e17777; }
    </style>
</head>
<body>
    <?php include __DIR__ . "/includes/sidebar.php"; ?>
    <main>
        <header class="page-header">
            <div class="page-header-text">
                <h1><?= esc($sitename) ?></h1>
                <p><?= te('health.subtitle') ?></p>
            </div>
            <div class="page-header-actions">
                <a href="zdravi.php" class="tool">
                    <span class="iconify-inline" data-icon="ic:round-refresh"></span>
                    <span><?= te('health.refresh') ?></span>
                </a>
            </div>
        </header>

        <div class="health-summary">
            <span class="health-pill ok">
                <span class="iconify-inline" data-icon="ic:round-check-circle"></span>
                <?= te('health.summary.ok_n', $summary['ok']) ?>
            </span>
            <?php if ($summary['warn'] > 0): ?>
            <span class="health-pill warn">
                <span class="iconify-inline" data-icon="ic:round-warning"></span>
                <?= te('health.summary.warn_n', $summary['warn']) ?>
            </span>
            <?php endif; ?>
            <?php if ($summary['fail'] > 0): ?>
            <span class="health-pill fail">
                <span class="iconify-inline" data-icon="ic:round-error"></span>
                <?= te('health.summary.fail_n', $summary['fail']) ?>
            </span>
            <?php endif; ?>
            <?php if ($summary['suppressed'] > 0): ?>
            <span class="health-pill suppressed">
                <span class="iconify-inline" data-icon="ic:round-visibility-off"></span>
                <?= te('health.suppressed_n', $summary['suppressed']) ?>
            </span>
            <?php endif; ?>
        </div>

        <?php foreach ($ordered as $groupKey => $groupChecks):
            $groupLabel = t('health.group.' . $groupKey);
            // If a plugin contributes a group like 'seo-helper', the t() call
            // returns the key itself — fall back to a humanized version.
            if ($groupLabel === 'health.group.' . $groupKey) {
                $groupLabel = ucfirst(str_replace(['-', '_'], ' ', $groupKey));
            }
            ?>
            <section class="health-group">
                <header class="health-group-header"><?= esc($groupLabel) ?></header>
                <div class="health-group-body">
                    <?php foreach ($groupChecks as $check):
                        $status = $check['status'] ?? 'unknown';
                        $isSuppressed = !empty($check['suppressed']);
                        $iconName = [
                            'ok'      => 'ic:round-check',
                            'warn'    => 'ic:round-warning',
                            'fail'    => 'ic:round-close',
                            'unknown' => 'ic:round-help-outline',
                        ][$status] ?? 'ic:round-help-outline';
                        $checkId = $check['id'] ?? '';
                        $hasAutofix = !empty($check['autofix']);
                        // Autofix only shown for non-OK, non-suppressed rows.
                        $showFix = $hasAutofix && $status !== 'ok' && !$isSuppressed && $canManage;
                        ?>
                        <div class="health-item <?= $isSuppressed ? 'is-suppressed' : '' ?>" data-id="<?= esc($checkId) ?>">
                            <span class="health-icon <?= esc($status) ?>" title="<?= te('health.status.' . $status) ?>">
                                <span class="iconify-inline" data-icon="<?= $iconName ?>"></span>
                            </span>
                            <div class="health-text">
                                <span class="health-label">
                                    <?= esc($check['label'] ?? '') ?>
                                    <?php if ($isSuppressed): ?>
                                        <span class="health-suppressed-badge"><?= te('health.suppressed') ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($check['detail'])): ?>
                                    <span class="health-detail"><?= esc($check['detail']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="health-actions-cell">
                                <?php if (!empty($check['action_href'])): ?>
                                    <a href="<?= esc($check['action_href']) ?>" class="health-action">
                                        <?= esc($check['action_label'] ?? '→') ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($showFix): ?>
                                    <button type="button" class="health-btn health-fix"
                                            data-id="<?= esc($checkId) ?>"
                                            data-label="<?= esc($check['label'] ?? '') ?>">
                                        <span class="iconify-inline" data-icon="ic:round-build"></span>
                                        <?= te('health.fix') ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($canManage && $checkId !== ''): ?>
                                    <button type="button" class="health-btn health-suppress"
                                            data-id="<?= esc($checkId) ?>"
                                            title="<?= te($isSuppressed ? 'health.unsuppress' : 'health.suppress') ?>"
                                            aria-label="<?= te($isSuppressed ? 'health.unsuppress' : 'health.suppress') ?>">
                                        <span class="iconify-inline" data-icon="<?= $isSuppressed ? 'ic:round-visibility' : 'ic:round-visibility-off' ?>"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($groupKey === 'plugins' && empty($groupChecks)): ?>
                        <div class="health-item">
                            <span></span>
                            <div class="health-text">
                                <span class="health-detail"><?= te('health.plugins.empty') ?></span>
                            </div>
                            <span></span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
    <?php include __DIR__ . "/includes/footer.php"; ?>

    <?php if ($canManage): ?>
    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const I18N = {
            fixBusy:    <?= json_encode(t('health.fix.busy')) ?>,
            fixOk:      <?= json_encode(t('health.fix.ok')) ?>,
            fixFail:    <?= json_encode(t('health.fix.fail')) ?>,
            fixConfirm: <?= json_encode(t('health.fix.confirm')) ?>,
        };

        // Toggle suppression. Reload after success so the row's class,
        // summary count, and button label all repaint correctly.
        document.querySelectorAll('.health-suppress').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                if (!id) return;
                btn.disabled = true;
                try {
                    const fd = new FormData();
                    fd.set('id', id);
                    fd.set('csrf_token', csrf);
                    const r = await fetch('./ajax/health_suppress.php', { method: 'POST', body: fd });
                    const d = await r.json();
                    if (!r.ok || !d.ok) throw new Error(d.error || 'failed');
                    location.reload();
                } catch (e) {
                    btn.disabled = false;
                    alert(e.message);
                }
            });
        });

        // Run autofix. Same reload-on-success pattern.
        document.querySelectorAll('.health-fix').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const label = btn.dataset.label || id;
                if (!id) return;
                if (!confirm(I18N.fixConfirm.replace('%s', label))) return;
                const original = btn.innerHTML;
                btn.disabled = true;
                btn.textContent = I18N.fixBusy;
                try {
                    const fd = new FormData();
                    fd.set('id', id);
                    fd.set('csrf_token', csrf);
                    const r = await fetch('./ajax/health_fix.php', { method: 'POST', body: fd });
                    const d = await r.json();
                    if (!r.ok || !d.ok) throw new Error(d.error || d.message || 'failed');
                    location.reload();
                } catch (e) {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    const flash = document.createElement('span');
                    flash.className = 'health-fix-flash err';
                    flash.textContent = I18N.fixFail.replace('%s', e.message);
                    btn.parentNode.appendChild(flash);
                    setTimeout(() => flash.remove(), 5000);
                }
            });
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
