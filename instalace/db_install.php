<?php
/**
 * Apply the canonical schema to the database the user just verified on
 * the database step. Then write the DB credentials to config.default.php
 * (in normal mode) so the rest of the wizard can re-open the connection
 * without having to retype them.
 *
 * On success: persist outcome to session, redirect to next step.
 * On debug:   record SQL statements + redirect.
 * On failure: show the schema log inline so the user can see which table
 *             complained and why.
 */
require_once __DIR__ . '/_lang.php';
require_once __DIR__ . '/_state.php';
require_once __DIR__ . '/_debug.php';
require_once __DIR__ . '/_shell.php';
require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/../includes/schema.php';

if (file_exists(__DIR__ . '/../.installed') && !PL_INSTALLER_DEBUG) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit(strip_tags(ti('lock.heading') . ' — ' . ti('lock.body')));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !pl_install_csrf_check()) {
    header('Location: install.php?step=database');
    exit;
}

$db = pl_install_data('database');
if (empty($db['host']) || empty($db['name'])) {
    header('Location: install.php?step=database');
    exit;
}

$collation = $db['collate'] === 'cz' ? 'utf8mb4_czech_ci' : 'utf8mb4_0900_ai_ci';
$schema = pl_schema($collation, ti('category.default'));

if (PL_INSTALLER_DEBUG) {
    // Don't actually connect — just record what we'd do.
    if (!empty($db['drop'])) {
        pl_install_debug('sql', 'wipe existing tables',
            "SET foreign_key_checks = 0;\n" .
            "DROP TABLE IF EXISTS …; -- all tables in DATABASE()\n" .
            "SET foreign_key_checks = 1;");
    }
    foreach ($schema['tables'] as $name => $sql) {
        pl_install_debug('sql', "ensure table `$name`", $sql);
    }
    foreach ($schema['seeds'] as $table => $rows) {
        foreach ($rows as $row) {
            pl_install_debug('sql', "seed `$table`", $row);
        }
    }
    foreach ($schema['constraints'] as $sql) {
        pl_install_debug('sql', 'foreign key', $sql);
    }
    pl_install_debug('write', 'config.default.php',
        "set \$db_server, \$db_user, \$db_password, \$db_name (from session)");

    // Mark schema as completed so the next step is reachable in debug mode.
    $_SESSION['install_state']['data']['_schema_log'] = ['simulated; see debug banner'];
    pl_install_complete('database', $db); // already complete, but refresh data
    header('Location: install.php?step=info');
    exit;
}

// Real apply.
$conn = @mysqli_connect($db['host'], $db['user'], $db['pass'], $db['name']);
if (!$conn) {
    pl_install_shell_open('database');
    ?>
    <section class="install-card">
        <h2><?= tie('error.connection') ?></h2>
        <div class="install-result err">
            <span class="iconify-inline" data-icon="ic:round-error"></span>
            <?= htmlspecialchars(mysqli_connect_error() ?: '') ?>
        </div>
        <div class="install-actions">
            <a href="install.php?step=database" class="tool"><?= tie('common.retry') ?></a>
        </div>
    </section>
    <?php
    pl_install_shell_close();
    exit;
}

// Optional wipe.
if (!empty($db['drop'])) {
    @$conn->query("SET foreign_key_checks = 0");
    if ($res = @$conn->query(
        "SELECT GROUP_CONCAT('`', table_name, '`') AS t
         FROM information_schema.tables WHERE table_schema = DATABASE()"
    )) {
        $row = $res->fetch_assoc();
        if (!empty($row['t'])) {
            @$conn->query("DROP TABLE IF EXISTS " . $row['t']);
        }
    }
    @$conn->query("SET foreign_key_checks = 1");
}

$result = pl_schema_apply($conn, $schema);
$conn->close();

// Persist DB creds to config.default.php so webset.php can open its own
// connection later without re-asking the user.
pl_install_config_set([
    'db_server'   => $db['host'],
    'db_user'     => $db['user'],
    'db_password' => $db['pass'],
    'db_name'     => $db['name'],
]);

$_SESSION['install_state']['data']['_schema_log'] = $result['log'];
$_SESSION['install_state']['data']['_schema_errors'] = $result['errors'];

if (!$result['ok']) {
    pl_install_shell_open('database');
    ?>
    <section class="install-card">
        <h2><?= tie('error.create_tables') ?></h2>
        <ul class="schema-log">
            <?php foreach ($result['log'] as $line): ?>
                <li><?= htmlspecialchars($line) ?></li>
            <?php endforeach; ?>
            <?php foreach ($result['errors'] as $err): ?>
                <li class="err"><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="install-actions">
            <a href="install.php?step=database" class="tool"><?= tie('common.retry') ?></a>
        </div>
    </section>
    <?php
    pl_install_shell_close();
    exit;
}

header('Location: install.php?step=info');
exit;
