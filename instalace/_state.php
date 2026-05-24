<?php
/**
 * Installer state machine + CSRF.
 *
 * State lives entirely in $_SESSION['install_state'] — the user can refresh
 * any step without losing prior input. Forward navigation requires the
 * previous step to have been completed; backward navigation is always free.
 *
 * The state shape:
 *   [
 *     'step'      => 'welcome' | 'database' | 'info' | 'img' | 'smtp' | 'webset' | 'exit',
 *     'completed' => ['welcome' => true, 'database' => true, …],
 *     'data' => [
 *       'db'   => ['host' => …, 'user' => …, 'pass' => …, 'name' => …, 'collate' => …, 'drop' => bool],
 *       'info' => [ … ],
 *       'img'  => [ … ],
 *       'smtp' => [ … ],
 *     ],
 *   ]
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

const PL_INSTALL_STEPS = ['welcome', 'database', 'info', 'img', 'smtp', 'webset', 'exit'];

if (!isset($_SESSION['install_state']) || !is_array($_SESSION['install_state'])) {
    $_SESSION['install_state'] = [
        'step'      => 'welcome',
        'completed' => [],
        'data'      => [],
    ];
}

/** Mark a step as completed and store its data payload. */
function pl_install_complete(string $step, array $data = []): void {
    $_SESSION['install_state']['completed'][$step] = true;
    if (!empty($data)) {
        $_SESSION['install_state']['data'][$step] = $data;
    }
}

/** Pull stored data for a step (or empty array). */
function pl_install_data(string $step): array {
    return $_SESSION['install_state']['data'][$step] ?? [];
}

/** Index of a step in the canonical order, or false. */
function pl_install_step_index(string $step) {
    return array_search($step, PL_INSTALL_STEPS, true);
}

/**
 * Steps the installer requires before .installed can be created. Other
 * steps (img, smtp) are optional and freely skippable.
 */
const PL_INSTALL_REQUIRED = ['database', 'info'];

/**
 * Resolve a requested step. The user can navigate freely between steps —
 * we only sanitize unknown step names back to 'welcome'. Required-step
 * gating happens at finish.php / the Continue→exit button, not via redirect.
 */
function pl_install_resolve_step(string $requested): string {
    $idx = pl_install_step_index($requested);
    return $idx === false ? 'welcome' : $requested;
}

/**
 * Whether every required step has been completed. Used to gate the
 * "Finish installation" button and finish.php itself.
 */
function pl_install_required_done(): bool {
    $completed = $_SESSION['install_state']['completed'] ?? [];
    foreach (PL_INSTALL_REQUIRED as $step) {
        if (empty($completed[$step])) return false;
    }
    return true;
}

/**
 * List of required steps not yet completed (in canonical order). Used to
 * show the user what's still missing.
 */
function pl_install_required_pending(): array {
    $completed = $_SESSION['install_state']['completed'] ?? [];
    $pending = [];
    foreach (PL_INSTALL_REQUIRED as $step) {
        if (empty($completed[$step])) $pending[] = $step;
    }
    return $pending;
}

/* ───── CSRF ───── */

function pl_install_csrf_token(): string {
    if (empty($_SESSION['install_csrf'])) {
        $_SESSION['install_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['install_csrf'];
}

function pl_install_csrf_field(): string {
    $t = pl_install_csrf_token();
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($t, ENT_QUOTES) . '">';
}

function pl_install_csrf_check(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $sent = $_POST['_csrf'] ?? '';
    $expected = $_SESSION['install_csrf'] ?? '';
    return $sent !== '' && hash_equals($expected, $sent);
}

/** Reset everything (used for restart links). */
function pl_install_reset(): void {
    unset($_SESSION['install_state'], $_SESSION['install_csrf'], $_SESSION['pl_install_log']);
}
