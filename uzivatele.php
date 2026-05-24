<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

if ($_SESSION['user_level'] < $admin_level) {
    header("Location: chyba.php?err=403&msg=" . rawurlencode(t('error.no_permission_view')));
    exit();
}

$defaultSortColumn = 'username';
$defaultSortOrder = 'ASC';
$defaultSearchBy = 'username';
require_once __DIR__ . "/includes/search.php";

if ($search && $searchBy) {
    $users = $dbFetcher->users($searchBy, $search, $page, $recordsPerPage, $sortColumn, $sortOrder);
    $totalRecords = $dbFetcher->countUsers([$searchBy => $search]);
} else {
    $users = $dbFetcher->allUsers($page, $recordsPerPage, $sortColumn, $sortOrder);
    $totalRecords = $dbFetcher->countUsers();
}

$totalPages = ceil($totalRecords / $recordsPerPage);
$sitename = t('users.title');
$thisURL = basename($_SERVER['PHP_SELF']);
$subtitle = tn((int)$totalRecords, 'users.subtitle');
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php"; ?>
</head>
<body>
    <?php include __DIR__ . "/includes/sidebar.php"; ?>
    <main>
        <header class="page-header">
            <div class="page-header-text">
                <h1><?= $sitename ?></h1>
                <p><?= $subtitle ?></p>
            </div>
            <div class="page-header-actions">
                <a href="edit_uzivatel.php" class="btn-green">
                    <span class="iconify-inline" data-icon="ic:round-person-add"></span>
                    <span><?= te('users.new') ?></span>
                </a>
            </div>
        </header>

        <div class="user-toolbar">
            <a href="javascript:void(0);" class="tool" onclick="deleteSelectedPrompt('user')">
                <span class="iconify-inline" data-icon="ic:round-delete"></span>
                <span><?= te('users.bulk.delete') ?></span>
            </a>
            <?php if ($search): ?>
            <a href="<?= $thisURL ?>" class="tool">
                <span class="iconify-inline" data-icon="ic:round-search-off"></span>
                <span><?= te('users.clear_filter') ?></span>
            </a>
            <?php endif; ?>
            <div class="user-toolbar-search">
                <?php include __DIR__ . "/includes/searchInput.php"; ?>
            </div>
        </div>

        <?php if (!empty($users)): ?>
        <div class="user-grid">
            <?php foreach ($users as $u):
                $userRole = $dbFetcher->role('id', $u['role']);
                $isAdmin = $u['role'] >= $admin_level;
                $articleCount = $dbFetcher->countArticles(['author' => $u['username']]);
            ?>
            <article class="user-card">
                <input type="checkbox" class="delete-checkbox user-card-check" value="<?= (int)$u['id'] ?>" title="<?= te('users.card.select') ?>">

                <a href="uzivatel.php?id=<?= (int)$u['id'] ?>" class="user-card-main">
                    <img src="<?= icon('user', $u) ?>" alt="" class="user-card-avatar">
                    <div class="user-card-info">
                        <h3>
                            <?= esc(($u['name'] ?? '') . ' ' . ($u['surname'] ?? '')) ?>
                        </h3>
                        <p class="user-card-username">@<?= esc($u['username'] ?? '') ?></p>
                    </div>
                </a>

                <div class="user-card-meta">
                    <span class="badge<?= $isAdmin ? ' badge-accent' : '' ?>">
                        <span class="iconify-inline" data-icon="<?= $isAdmin ? 'ic:round-admin-panel-settings' : 'ic:round-person' ?>"></span>
                        <?= esc($userRole['name'] ?? '?') ?>
                    </span>
                    <span class="badge">
                        <span class="iconify-inline" data-icon="ic:round-article"></span>
                        <?= $articleCount ?>
                    </span>
                </div>

                <div class="user-card-actions">
                    <a href="edit_uzivatel.php?id=<?= (int)$u['id'] ?>" class="positive-hover" title="<?= te('users.row.edit') ?>">
                        <span class="iconify-inline" data-icon="ic:round-edit"></span>
                    </a>
                    <a href="javascript:void(0);" onclick="deletePrompt(<?= (int)$u['id'] ?>, 'user')" class="negative-hover" title="<?= te('users.row.delete') ?>">
                        <span class="iconify-inline" data-icon="ic:round-delete"></span>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <span class="iconify-inline" data-icon="ic:round-people" style="width:48px;height:48px;color:var(--text-disabled);"></span>
            <h3><?= te('users.empty.title') ?></h3>
            <p><?= te($search ? 'users.empty.filtered' : 'users.empty.create_first') ?></p>
            <?php if (!$search): ?>
            <a href="edit_uzivatel.php" class="btn-green"><?= te('users.empty.create_btn') ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): include __DIR__ . "/includes/pagination.php"; endif; ?>
    </main>
    <?php include __DIR__ . "/includes/footer.php"; ?>
</body>
</html>
