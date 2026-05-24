<?php
/**
 * Shared installer chrome: HTML head + topbar + step rail + footer.
 *
 * Pulls in the same admin CSS bundle (variables.css + style.css) so the
 * installer reuses the design tokens and form classes the rest of the app
 * uses. Iconify is loaded for the icons sprinkled through the steps.
 *
 * Pages call:
 *   pl_install_shell_open();
 *   …content…
 *   pl_install_shell_close();
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/_lang.php';
require_once __DIR__ . '/_state.php';
require_once __DIR__ . '/_debug.php';

function pl_install_shell_open(string $currentStep): void {
    global $INSTALL_LANG, $INSTALL_LANGS;
    ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($INSTALL_LANG, ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= tie('page.title') ?> · PressLine</title>
    <link rel="icon" href="../favicon.ico">
    <link rel="stylesheet" href="../res/css/variables.css">
    <link rel="stylesheet" href="../res/css/style.css">
    <script>
        // Defaults the admin's mode.js expects when there's no logged-in user.
        var mode = 'dark';
        var color = 'def';
        var colors = '"def","gray","orange","green","blue","red","lila"';
    </script>
    <script src="../res/js/iconify.min.js"></script>
    <script src="../res/js/mode.js"></script>
    <style>
        /* Installer-only layout overrides — chrome is bespoke (no logged-in
           sidebar), but the inner forms reuse the admin's .form-section /
           .form-field / .btn-green / .tool classes. */
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            padding: var(--space-6) var(--space-6);
            margin: 0;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        .install-shell {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: var(--space-5);
        }
        .install-card .form-grid {
            /* Roomier two-column form on wider viewports. */
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .install-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-3);
        }
        .install-brand {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--text-secondary);
            font-weight: var(--font-medium);
        }
        .install-brand .iconify-inline { color: var(--accent); }
        .install-langs { display: flex; gap: 4px; }
        .install-langs a {
            padding: 4px 10px;
            border-radius: var(--radius-full);
            color: var(--text-muted);
            text-decoration: none;
            font-size: var(--text-xs);
            font-weight: var(--font-medium);
        }
        .install-langs a.is-active {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        .install-rail {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .install-rail .pip {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            background: var(--bg-tertiary);
            color: var(--text-muted);
            font-size: var(--text-xs);
            font-weight: var(--font-medium);
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
        }
        .install-rail .pip:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        .install-rail .pip.is-done {
            color: var(--success, #4ade80);
        }
        .install-rail .pip.is-done::before {
            content: '✓ ';
            color: var(--success, #4ade80);
        }
        .install-rail .pip.is-required:not(.is-done) {
            box-shadow: inset 0 0 0 1px rgba(225,119,119,0.45);
        }
        .install-rail .pip.is-required:not(.is-done)::before {
            content: '*';
            color: #e17777;
            font-weight: bold;
        }
        .install-rail .pip.is-active {
            background: var(--accent);
            color: var(--bg-primary);
        }
        .install-rail .pip.is-active:hover {
            background: var(--accent);
            color: var(--bg-primary);
            filter: brightness(1.05);
        }

        .install-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
        }
        .install-card > h2 {
            margin: 0;
            font-size: var(--text-2xl);
        }
        .install-card .lead {
            color: var(--text-muted);
            font-size: var(--text-sm);
            margin: 0;
        }

        .install-result {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-4);
            border-radius: var(--radius-sm);
            font-size: var(--text-sm);
        }
        .install-result.ok  { background: rgba(74,222,128,0.1); color: #4ade80; }
        .install-result.err { background: rgba(225,119,119,0.1); color: #e17777; }
        .install-result.info { background: var(--bg-tertiary); color: var(--text-secondary); }

        .install-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: var(--space-2);
            margin-top: var(--space-2);
        }
        .install-actions .skip {
            margin-right: auto;
            color: var(--text-muted);
            font-size: var(--text-sm);
            text-decoration: none;
        }
        .install-actions .skip:hover { color: var(--text-primary); }

        .install-creds {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: var(--space-4);
            background: var(--bg-tertiary);
        }
        .install-creds dl { margin: 0; }
        .install-creds dt {
            font-size: var(--text-xs);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-top: var(--space-2);
        }
        .install-creds dt:first-child { margin-top: 0; }
        .install-creds dd { margin: 4px 0 0; font-family: var(--font-mono, monospace); }

        .schema-log {
            list-style: none;
            padding: 0;
            margin: 0;
            font-family: var(--font-mono, monospace);
            font-size: var(--text-xs);
            max-height: 240px;
            overflow-y: auto;
            background: var(--bg-tertiary);
            border-radius: var(--radius-sm);
            padding: var(--space-2) var(--space-3);
        }
        .schema-log li { padding: 2px 0; color: var(--text-secondary); }
        .schema-log li.err { color: #e17777; }

        /* Debug banner */
        .debug-banner {
            margin-top: var(--space-3);
            border: 1px dashed var(--accent);
            border-radius: var(--radius);
            background: rgba(255, 165, 0, 0.04);
            padding: var(--space-3) var(--space-4);
            font-size: var(--text-xs);
        }
        .debug-banner-head {
            display: flex;
            gap: var(--space-2);
            align-items: center;
            color: var(--accent);
        }
        .debug-clear {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            border-radius: var(--radius-sm);
            padding: 2px 10px;
            cursor: pointer;
            font-size: var(--text-xs);
            font-family: inherit;
        }
        .debug-log {
            list-style: none;
            padding: 0;
            margin: var(--space-3) 0 0;
            display: flex;
            flex-direction: column;
            gap: var(--space-2);
        }
        .debug-log li {
            padding: var(--space-2);
            border-left: 2px solid var(--accent);
            background: var(--bg-tertiary);
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
        }
        .debug-action {
            display: inline-block;
            padding: 1px 6px;
            margin: 0 6px;
            border-radius: 3px;
            font-weight: var(--font-bold);
            background: var(--bg-secondary);
        }
        .debug-action-write { color: #4ade80; }
        .debug-action-sql   { color: #f59e0b; }
        .debug-action-lock  { color: #e17777; }
        .debug-action-skip  { color: var(--text-muted); }
        .debug-target { color: var(--text-secondary); }
        .debug-detail {
            margin: var(--space-2) 0 0;
            padding: var(--space-2);
            background: var(--bg-primary);
            border-radius: var(--radius-sm);
            font-size: var(--text-xs);
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 200px;
            overflow-y: auto;
        }
        .debug-when { color: var(--text-muted); margin-right: 4px; }
        .debug-empty { color: var(--text-muted); margin: var(--space-2) 0 0; }
    </style>
</head>
<body>
<main class="install-shell">

    <header class="install-topbar">
        <span class="install-brand">
            <span class="iconify-inline" data-icon="ic:round-build"></span>
            <?= tie('page.brand') ?>
        </span>
        <nav class="install-langs" aria-label="<?= tie('lang.label') ?>">
            <?php foreach ($INSTALL_LANGS as $code => $label):
                $href = '?step=' . urlencode($currentStep) . '&lang=' . urlencode($code); ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"
                   class="<?= $code === $INSTALL_LANG ? 'is-active' : '' ?>">
                    <?= htmlspecialchars(strtoupper($code)) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <div class="install-rail" role="navigation" aria-label="installation progress">
        <?php
        $completed = $_SESSION['install_state']['completed'] ?? [];
        foreach (PL_INSTALL_STEPS as $s):
            $isDone     = !empty($completed[$s]);
            $isActive   = $s === $currentStep;
            $isRequired = in_array($s, PL_INSTALL_REQUIRED, true);
            $cls = 'pip';
            if ($isDone)   $cls .= ' is-done';
            if ($isActive) $cls .= ' is-active';
            if ($isRequired && !$isDone) $cls .= ' is-required';
            $href = '?step=' . urlencode($s);
            ?>
            <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" class="<?= $cls ?>"
               <?php if ($isRequired): ?>title="<?= tie('required.badge') ?>"<?php endif; ?>>
                <?= tie('step.' . $s) ?><?php if ($isRequired && !$isDone): ?> *<?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php }

function pl_install_shell_close(): void {
    pl_install_debug_banner();
    ?>
</main>
</body>
</html>
<?php
}
