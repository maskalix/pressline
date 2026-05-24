<?php
/**
 * Installer debug-mode helpers. When DEBUG is true (set in pl-config.php),
 * the installer records intended file/SQL writes into $_SESSION instead of
 * performing them, and a banner at the bottom of every step shows what was
 * intercepted.
 *
 * The four action types:
 *   - write : would write to a config file
 *   - sql   : would execute SQL against the database
 *   - lock  : would create the .installed lock file
 *   - skip  : decided to skip (e.g. seeds because the table already had rows)
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

// Bring DEBUG in without booting the full app — pl-config is just credentials
// + the DEBUG constant; loading it has no side effects.
if (!defined('PL_INSTALLER_DEBUG')) {
    $cfg = __DIR__ . '/../pl-config.php';
    if (file_exists($cfg)) {
        @include_once $cfg;
    }
    define('PL_INSTALLER_DEBUG', defined('DEBUG') && DEBUG === true);
}

if (!isset($_SESSION['pl_install_log']) || !is_array($_SESSION['pl_install_log'])) {
    $_SESSION['pl_install_log'] = [];
}

/**
 * Record an intended action for the simulation banner.
 *
 *   pl_install_debug('write', 'config.default.php', '$name = "foo"');
 *   pl_install_debug('sql',   'create tables',     $sqlSnippet);
 *   pl_install_debug('lock',  '.installed',        '2026-05-09 19:33');
 *   pl_install_debug('skip',  'seed roles',        'table already has 3 rows');
 */
function pl_install_debug(string $action, string $target, string $detail = ''): void {
    $_SESSION['pl_install_log'][] = [
        'action' => $action,
        'target' => $target,
        'detail' => $detail,
        'when'   => date('H:i:s'),
    ];
}

/**
 * Render the simulation banner. No-op when DEBUG is off.
 *
 * Form-clear handler runs early in install.php — by the time we render here
 * the log will already be reset if the user clicked "clear".
 */
function pl_install_debug_banner(): void {
    if (!PL_INSTALLER_DEBUG) return;
    $log = $_SESSION['pl_install_log'] ?? [];
    $step = $_GET['step'] ?? 'welcome';
    ?>
    <div class="debug-banner">
        <div class="debug-banner-head">
            <span class="iconify-inline" data-icon="ic:round-bug-report"></span>
            <strong><?= htmlspecialchars(ti('debug.banner')) ?></strong>
            <span><?= htmlspecialchars(ti('debug.lead')) ?></span>
            <?php if (!empty($log)): ?>
                <form method="post" action="?step=<?= htmlspecialchars($step, ENT_QUOTES) ?>" style="margin-left:auto;">
                    <button type="submit" name="pl_install_clear" value="1" class="debug-clear">
                        <?= htmlspecialchars(ti('debug.clear')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php if (!empty($log)): ?>
            <ol class="debug-log">
                <?php foreach ($log as $entry): ?>
                    <li>
                        <code class="debug-when"><?= htmlspecialchars($entry['when']) ?></code>
                        <span class="debug-action debug-action-<?= htmlspecialchars($entry['action']) ?>"><?= htmlspecialchars(strtoupper($entry['action'])) ?></span>
                        <code class="debug-target"><?= htmlspecialchars($entry['target']) ?></code>
                        <?php if (!empty($entry['detail'])): ?>
                            <pre class="debug-detail"><?= htmlspecialchars($entry['detail']) ?></pre>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="debug-empty"><?= htmlspecialchars(ti('debug.empty')) ?></p>
        <?php endif; ?>
    </div>
    <?php
}

// Process clear-log POST early so the rendered banner reflects the clear.
if (PL_INSTALLER_DEBUG && !empty($_POST['pl_install_clear'])) {
    $_SESSION['pl_install_log'] = [];
    $step = $_GET['step'] ?? 'welcome';
    header('Location: install.php?step=' . urlencode($step));
    exit;
}
