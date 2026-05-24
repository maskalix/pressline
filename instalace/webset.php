<?php
/**
 * Webset application step. Pulls every collected key/value pair from
 * $_SESSION and writes them to the `webset` table — or, in debug mode,
 * records each intended INSERT.
 *
 * This file is included by install.php case 'webset', so it only renders
 * the step body (the shell is already open). Performs the work on each
 * page-load (idempotent: ON DUPLICATE KEY UPDATE) so refresh is safe.
 *
 * After applying, it shows a status panel and a "continue" form that
 * advances to the exit step.
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/_lang.php';
require_once __DIR__ . '/_state.php';
require_once __DIR__ . '/_debug.php';
require_once __DIR__ . '/_config.php';

// Aggregate everything previous steps put into the session into one flat
// key/value map keyed by webset.key. Keys here mirror what pl-load.php
// reads via $websets[…].
$info = pl_install_data('info');
$img  = pl_install_data('img');
$smtp = pl_install_data('smtp');

$websets = array_filter([
    // info
    'name'            => $info['name']        ?? null,
    'url'             => $info['url']         ?? null,
    'frontend'        => $info['frontend']    ?? null,
    'description'     => $info['description'] ?? null,
    'keywords'        => $info['keywords']    ?? null,
    'language'        => $info['language']    ?? null,
    // img
    'icon'            => $img['icon']            ?? null,
    'icon_full'       => $img['icon_full']       ?? null,
    'icon_text_light' => $img['icon_text_light'] ?? null,
    'icon_text_dark'  => $img['icon_text_dark']  ?? null,
    // smtp
    'from'      => $smtp['from']      ?? null,
    'smtp_host' => $smtp['smtp_host'] ?? null,
    'smtp_port' => $smtp['smtp_port'] ?? null,
    'smtp_user' => $smtp['smtp_user'] ?? null,
    'smtp_pass' => $smtp['smtp_pass'] ?? null,
    'smtp_sec'  => $smtp['smtp_sec']  ?? null,
], fn($v) => $v !== null && $v !== '');

// Apply.
$applied = [];
$errors  = [];

if (PL_INSTALLER_DEBUG) {
    foreach ($websets as $k => $v) {
        pl_install_debug('sql', "webset[$k]",
            "INSERT INTO webset (`key`, `value`) VALUES (" .
            var_export($k, true) . ', ' . var_export((string)$v, true) . ")\n" .
            "ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $applied[] = $k;
    }
} else {
    // Open the connection from the credentials we just persisted.
    $db = pl_install_data('database');
    $conn = @mysqli_connect($db['host'] ?? '', $db['user'] ?? '', $db['pass'] ?? '', $db['name'] ?? '');
    if (!$conn) {
        $errors[] = mysqli_connect_error() ?: ti('error.connection');
    } else {
        $stmt = $conn->prepare("INSERT INTO webset (`key`, `value`) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        if (!$stmt) {
            $errors[] = $conn->error;
        } else {
            foreach ($websets as $k => $v) {
                $vs = (string)$v;
                $stmt->bind_param('ss', $k, $vs);
                if ($stmt->execute()) {
                    $applied[] = $k;
                } else {
                    $errors[] = "$k: " . $stmt->error;
                }
            }
            $stmt->close();
        }
        $conn->close();
    }
}

// Mark step as completed only if we got through without errors. The
// continue button is gated on completion.
if (empty($errors)) {
    pl_install_complete('webset', ['applied' => $applied]);
}
?>

<section class="install-card">
    <h2><?= tie('webset.heading') ?></h2>
    <p class="lead"><?= ti('webset.lead') ?></p>

    <?php if (!empty($errors)): ?>
        <div class="install-result err">
            <span class="iconify-inline" data-icon="ic:round-error"></span>
            <?= tie('error.create_tables') ?>
        </div>
        <ul class="schema-log">
            <?php foreach ($errors as $err): ?>
                <li class="err"><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="install-result ok">
            <span class="iconify-inline" data-icon="ic:round-check-circle"></span>
            <?= tie('webset.applied') ?> · <?= count($applied) ?>
        </div>
        <ul class="schema-log">
            <?php foreach ($applied as $k): ?>
                <li><?= htmlspecialchars($k) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="get">
        <input type="hidden" name="step" value="exit">
        <div class="install-actions">
            <button type="submit" class="btn-green" <?= empty($errors) ? '' : 'disabled' ?>>
                <span class="iconify-inline" data-icon="ic:round-arrow-forward"></span>
                <?= tie('common.continue') ?>
            </button>
        </div>
    </form>
</section>
