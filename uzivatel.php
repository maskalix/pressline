<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

$displayUserId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$displayUser = [];

if ($displayUserId > 0) {
    $displayUser = $dbFetcher->user('id', $displayUserId);
}

$defaultSortColumn = 'time';
$defaultSortOrder = 'DESC';
$defaultSearchBy = 'author';
require_once __DIR__ . "/includes/search.php";

if ($displayUserId <= 0 || empty($displayUser)) {
    $sitename = t('error.user_not_found.title');
    ?>
    <!DOCTYPE html>
    <html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
    <head>
        <?php include __DIR__ . "/includes/head.php"; ?>
    </head>
    <body>
        <?php include __DIR__ . "/includes/sidebar.php"; ?>
        <main>
            <div class="empty-state" style="margin-top:var(--space-12);">
                <span class="iconify-inline" data-icon="ic:round-person-off" style="width:48px;height:48px;color:var(--text-disabled);"></span>
                <h3><?= te('error.user_not_found.title') ?></h3>
                <p><?= te('error.user_not_found.body') ?></p>
                <a href="./uzivatele.php" class="btn-green"><?= te('error.user_not_found.back') ?></a>
            </div>
        </main>
        <script src="./res/js/script.js"></script>
        <?php include __DIR__ . "/includes/footer.php"; ?>
    </body>
    </html>
    <?php
    exit();
}

$author = $displayUser['username'];
$searchBy = 'author';
$search = $author;
$articles = $dbFetcher->articles($searchBy, $search, $page, $recordsPerPage, $sortColumn, $sortOrder, $level);
$totalRecords = $dbFetcher->countArticles([$searchBy => $search, 'level' => "<=" . $level]);
$totalPages = ceil($totalRecords / $recordsPerPage);
$role = $dbFetcher->role('id', $displayUser['role']);
$sitename = t('profile.title');
$thisURL = basename($_SERVER['PHP_SELF']);

$canEdit = $_SESSION['user_level'] >= $admin_level
    || (int)$_SESSION['user_id'] === (int)$displayUser['id'];
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php"; ?>
</head>
<body>
    <?php include __DIR__ . "/includes/sidebar.php"; ?>
    <main>
        <div class="tools-list">
            <a href="uzivatele.php" class="tool">
                <span class="iconify-inline" data-icon="ic:round-arrow-back"></span>
                <span><?= te('profile.back') ?></span>
            </a>
            <?php if ($canEdit): ?>
            <a href="edit_uzivatel.php?id=<?= (int)$displayUser['id'] ?>" class="tool btn-green">
                <span class="iconify-inline" data-icon="ic:round-edit"></span>
                <span><?= te('profile.edit') ?></span>
            </a>
            <?php endif; ?>
        </div>

        <section class="profile-card">
            <div class="profile-cover"></div>
            <div class="profile-main">
                <img src="<?= icon('user', $displayUser) ?>" alt="<?= esc($displayUser['name'] ?? '') ?>" class="profile-avatar">
                <div class="profile-info">
                    <h1>
                        <?= esc(($displayUser['name'] ?? '') . ' ' . ($displayUser['surname'] ?? '')) ?>
                    </h1>
                    <p class="profile-username">@<?= esc($displayUser['username'] ?? '') ?></p>
                    <div class="profile-badges">
                        <span class="badge">
                            <span class="iconify-inline" data-icon="ic:round-workspace-premium"></span>
                            <?= esc($role['name']) ?> · lvl <?= (int)$role['level'] ?>
                        </span>
                        <span class="badge">
                            <span class="iconify-inline" data-icon="ic:round-article"></span>
                            <?= te('profile.articles_n', (int)$totalRecords) ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php if (!empty($displayUser['story'])): ?>
            <p class="profile-story"><?= esc($displayUser['story']) ?></p>
            <?php endif; ?>

            <div class="profile-links">
                <?php if (!empty($displayUser['mail'])): ?>
                <a href="mailto:<?= esc($displayUser['mail']) ?>" class="profile-link">
                    <span class="iconify-inline" data-icon="ic:round-email"></span>
                    <span><?= esc($displayUser['mail']) ?></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($displayUser['social_ig'])): ?>
                <a href="https://instagram.com/<?= esc($displayUser['social_ig']) ?>" target="_blank" rel="noopener" class="profile-link">
                    <span class="iconify-inline" data-icon="ri:instagram-line"></span>
                    <span><?= esc($displayUser['social_ig']) ?></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($displayUser['social_fb'])): ?>
                <a href="https://facebook.com/<?= esc($displayUser['social_fb']) ?>" target="_blank" rel="noopener" class="profile-link">
                    <span class="iconify-inline" data-icon="ri:facebook-circle-fill"></span>
                    <span><?= esc($displayUser['social_fb']) ?></span>
                </a>
                <?php endif; ?>
                <?php if (!empty($displayUser['social_x'])): ?>
                <a href="https://x.com/<?= esc($displayUser['social_x']) ?>" target="_blank" rel="noopener" class="profile-link">
                    <span class="iconify-inline" data-icon="ri:twitter-x-line"></span>
                    <span><?= esc($displayUser['social_x']) ?></span>
                </a>
                <?php endif; ?>
            </div>
        </section>

        <section class="profile-articles">
            <h2><?= te('profile.user_articles') ?></h2>
            <?php if (!empty($articles)): ?>
            <table id="itemList" class="clanky">
                <thead>
                    <tr>
                        <th><a href="?<?= http_build_query(editQuery(['sort', 'order'], ['name', $oppositeOrder])) ?>"><?= te('profile.col.name') ?></a></th>
                        <th class="no-display"><a href="?<?= http_build_query(editQuery(['sort', 'order'], ['time', $oppositeOrder])) ?>"><?= te('profile.col.date') ?></a></th>
                        <th class="no-display"><a href="?<?= http_build_query(editQuery(['sort', 'order'], ['category', $oppositeOrder])) ?>"><?= te('profile.col.category') ?></a></th>
                        <th class="tools"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article):
                        $cat = $article['category'];
                        $category = $dbFetcher->category('id', $cat, $level); ?>
                    <tr>
                        <td>
                            <a href="nahled.php?id=<?= (int)$article['id'] ?>&amp;origin=1"><?= esc($article['name']) ?></a>
                            <?php if ($article['public'] == 0): ?>
                                <span class="badge badge-warn" style="margin-left:8px;"><?= te('dash.draft_badge') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="sub no-display"><?= formatDate($article['time'], false) ?></td>
                        <td class="sub no-display">
                            <?php if ($category): ?>
                                <a href="clanky.php?search=<?= (int)$cat ?>&amp;search_by=category"><?= esc($category['name']) ?></a>
                            <?php endif; ?>
                        </td>
                        <td class="tools">
                            <a href="edit.php?id=<?= (int)$article['id'] ?>">
                                <span style="cursor: pointer;" class="positive-hover">
                                    <span class="iconify-inline" data-icon="ic:round-edit"></span>
                                </span>
                            </a>
                            <?php if ($article['deletable'] == 1): ?>
                                <span style="cursor: pointer;" class="negative-hover" onclick="deletePrompt(<?= (int)$article['id'] ?>, 'article')">
                                    <span class="iconify-inline" data-icon="ic:round-delete"></span>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php include __DIR__ . "/includes/pagination.php"; ?>
            <?php else: ?>
            <div class="empty-state">
                <span class="iconify-inline" data-icon="ic:round-article" style="width:40px;height:40px;color:var(--text-disabled);"></span>
                <p><?= te('profile.empty.no_articles') ?></p>
            </div>
            <?php endif; ?>
        </section>
    </main>
    <?php include __DIR__ . "/includes/footer.php"; ?>
</body>
</html>
