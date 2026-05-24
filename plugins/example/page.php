<?php
require_once __DIR__ . "/../../pl-load.php";
require_once __DIR__ . "/../../includes/functions.php";
require_once __DIR__ . "/../../includes/session.php";

$sitename = t('plugin.example.page.title');
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/../../includes/head.php"; ?>
</head>
<body>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>
    <main>
        <header class="page-header">
            <div class="page-header-text">
                <h1><?= esc($sitename) ?></h1>
                <p><?= esc(t('plugin.example.page.subtitle')) ?></p>
            </div>
        </header>

        <div class="content-card" style="padding: var(--space-6); border-radius: var(--radius-lg) !important;">
            <h2><?= esc(t('plugin.example.page.greeting')) ?></h2>
            <p><?= t('plugin.example.page.body') ?></p>
            <p><?= t('plugin.example.page.path') ?></p>
        </div>
    </main>
    <?php include __DIR__ . "/../../includes/footer.php"; ?>
</body>
</html>
