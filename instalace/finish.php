<?php
require_once __DIR__ . '/_lang.php';
require_once __DIR__ . '/_state.php';
require_once __DIR__ . '/_debug.php';

if (file_exists(__DIR__ . '/../.installed') && !PL_INSTALLER_DEBUG) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo strip_tags(ti('lock.heading') . ' — ' . ti('lock.body'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !pl_install_csrf_check()) {
    header('Location: install.php?step=exit');
    exit;
}

if (!pl_install_required_done()) {
    header('Location: install.php?step=exit');
    exit;
}

if (PL_INSTALLER_DEBUG) {
    pl_install_debug('lock', '.installed',
        'create lock file at ' . date('Y-m-d H:i:s'));
    header('Location: install.php?step=exit');
    exit;
}

file_put_contents(__DIR__ . '/../.installed', date('Y-m-d H:i:s'));

// Clean up installer session state on the way out.
pl_install_reset();

header('Location: ../index.php');
exit;
