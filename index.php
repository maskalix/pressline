<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

function getMediaImageCount($result, $imageFormats) {
    $imageCount = 0;
    foreach ($result as $row) {
        $ext = pathinfo($row['filename'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), $imageFormats)) {
            $imageCount++;
        }
    }
    return $imageCount;
}

$userId = $_SESSION['user_id'];
$currentUser = $dbFetcher->user('id', $userId);
$totalImageCount = getMediaImageCount($dbFetcher->allMedia(1, 9999), $imageFormats);
$totalArticles = $dbFetcher->countArticles();
$articlesToday = $dbFetcher->countArticles(['DATE(time)' => 'CURDATE()']);
$totalUsers = $dbFetcher->countUsers();
$totalAdmins = $dbFetcher->countUsers(['role' => '2']);
$totalCategories = max(0, $dbFetcher->countCategories() - 1);
$articlesNoCategory = $dbFetcher->countArticles(['category' => '1']);
$totalMedia = $dbFetcher->countMedia();
$recentArticles = $dbFetcher->allArticles(1, 5, 'time', 'DESC', $level);

$hour = (int) date('G');
$greeting = $hour < 6  ? t('dash.greeting.night')
          : ($hour < 12 ? t('dash.greeting.morning')
          : ($hour < 18 ? t('dash.greeting.afternoon') : t('dash.greeting.evening')));

$sitename = t('dash.title');
?>

<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php";?>
</head>
<body>
<?php include __DIR__ . "/includes/sidebar.php";?>
    <main class="dashboard">

        <?php
        // Show the update banner from cache only (no synchronous GitHub
        // call). A small async fetch below refreshes the cache when stale,
        // so the next dashboard render reflects the new state.
        if ($_SESSION['user_level'] >= $admin_level):
            $updStatus = pl_updates_status();
            $isStale   = pl_updates_is_stale();
            if (!empty($updStatus['has_update'])): ?>
            <a href="<?= pl_url('/aktualizace.php') ?>"
               style="display:flex; align-items:center; gap:var(--space-2);
                      padding:var(--space-3) var(--space-4); border-radius:var(--radius-sm);
                      background:rgba(251,191,36,0.12); color:#fbbf24; text-decoration:none;
                      margin-bottom:var(--space-4);">
                <span class="iconify-inline" data-icon="ic:round-system-update"></span>
                <span style="flex:1;"><?= t('updates.dashboard.banner', esc($updStatus['latest_tag'])) ?></span>
                <span style="font-weight:var(--font-medium);"><?= te('updates.dashboard.cta') ?> →</span>
            </a>
            <?php endif; ?>
            <?php if ($isStale): ?>
            <script>
                // Fire-and-forget refresh of the GitHub cache. If a new release
                // is available, the next dashboard render will show the banner.
                fetch('./ajax/updates_check.php', { method: 'POST',
                    headers: { 'X-Requested-With': 'fetch' },
                    body: new URLSearchParams({ csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || '' })
                }).catch(() => {});
            </script>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Hero / Welcome -->
        <section class="dashboard-hero">
            <div class="dashboard-hero-text">
                <p class="dashboard-greeting"><?= esc($greeting) ?>,</p>
                <h1><?= esc(($currentUser['name'] ?? '') . ' ' . ($currentUser['surname'] ?? '')) ?></h1>
                <p class="dashboard-sub"><?= t('dash.welcome_back', esc($name)) ?></p>
            </div>
            <div class="dashboard-hero-actions">
                <a href="./edit.php" class="btn-green">
                    <span class="iconify-inline" data-icon="ic:round-edit-note"></span>
                    <span><?= te('dash.new_article') ?></span>
                </a>
                <a href="./media.php" class="tool">
                    <span class="iconify-inline" data-icon="ic:round-upload"></span>
                    <span><?= te('dash.upload_media') ?></span>
                </a>
            </div>
        </section>

        <!-- Stats grid -->
        <section class="dashboard-stats">
            <a href="./clanky.php" class="stat-card">
                <div class="stat-card-icon">
                    <span class="iconify-inline" data-icon="ic:baseline-article"></span>
                </div>
                <div class="stat-card-body">
                    <p class="stat-card-label"><?= te('dash.label.articles') ?></p>
                    <p class="stat-card-value"><?= $totalArticles ?></p>
                    <?php if ($articlesToday > 0): ?>
                        <p class="stat-card-meta positive"><?= te('dash.meta.added_today', $articlesToday) ?></p>
                    <?php else: ?>
                        <p class="stat-card-meta"><?= te('dash.meta.none_today') ?></p>
                    <?php endif; ?>
                </div>
            </a>

            <a href="./uzivatele.php" class="stat-card">
                <div class="stat-card-icon">
                    <span class="iconify-inline" data-icon="ic:round-people"></span>
                </div>
                <div class="stat-card-body">
                    <p class="stat-card-label"><?= te('dash.label.users') ?></p>
                    <p class="stat-card-value"><?= $totalUsers ?></p>
                    <p class="stat-card-meta"><?= te('dash.meta.admins_n', $totalAdmins) ?></p>
                </div>
            </a>

            <a href="./rubriky.php" class="stat-card">
                <div class="stat-card-icon">
                    <span class="iconify-inline" data-icon="ic:round-category"></span>
                </div>
                <div class="stat-card-body">
                    <p class="stat-card-label"><?= te('dash.label.categories') ?></p>
                    <p class="stat-card-value"><?= $totalCategories ?></p>
                    <p class="stat-card-meta"><?= te('dash.meta.no_category_n', $articlesNoCategory) ?></p>
                </div>
            </a>

            <a href="./media.php" class="stat-card">
                <div class="stat-card-icon">
                    <span class="iconify-inline" data-icon="ic:round-perm-media"></span>
                </div>
                <div class="stat-card-body">
                    <p class="stat-card-label"><?= te('dash.label.media') ?></p>
                    <p class="stat-card-value"><?= $totalMedia ?></p>
                    <p class="stat-card-meta"><?= te('dash.meta.images_n', $totalImageCount) ?></p>
                </div>
            </a>
        </section>

        <!-- Two-column: recent + quick actions -->
        <div class="dashboard-grid">
            <section class="dashboard-block">
                <header class="dashboard-block-header">
                    <h2>
                        <span class="iconify-inline" data-icon="ic:round-history"></span>
                        <?= te('dash.recent_articles') ?>
                    </h2>
                    <a href="./clanky.php" class="dashboard-link">
                        <?= te('dash.show_all') ?>
                        <span class="iconify-inline" data-icon="ic:round-arrow-forward"></span>
                    </a>
                </header>
                <?php if (!empty($recentArticles)): ?>
                <ul class="recent-list">
                    <?php foreach ($recentArticles as $article):
                        $cat = $dbFetcher->category('id', $article['category'], $level); ?>
                    <li>
                        <a href="./nahled.php?id=<?= (int)$article['id'] ?>&amp;origin=1" class="recent-item">
                            <div class="recent-item-main">
                                <span class="recent-item-title"><?= esc($article['name']) ?></span>
                                <div class="recent-item-meta">
                                    <?php if ($cat && $cat['id'] != 1): ?>
                                        <span class="badge"><?= esc($cat['name']) ?></span>
                                    <?php endif; ?>
                                    <span><?= esc($article['author']) ?></span>
                                    <span class="dot">•</span>
                                    <span><?= formatDate($article['time'], false) ?></span>
                                    <?php if ($article['public'] == 0): ?>
                                        <span class="badge badge-warn"><?= te('dash.draft_badge') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="iconify-inline recent-item-arrow" data-icon="ic:round-chevron-right"></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state">
                    <span class="iconify-inline" data-icon="ic:round-article" style="width:40px;height:40px;color:var(--text-disabled);"></span>
                    <p><?= te('dash.empty.no_articles') ?></p>
                    <a href="./edit.php" class="btn-green"><?= te('dash.empty.create_first') ?></a>
                </div>
                <?php endif; ?>
            </section>

            <section class="dashboard-block">
                <header class="dashboard-block-header">
                    <h2>
                        <span class="iconify-inline" data-icon="ic:round-bolt"></span>
                        <?= te('dash.quick_actions') ?>
                    </h2>
                </header>
                <div class="quick-actions">
                    <a href="./edit.php" class="quick-action">
                        <span class="iconify-inline" data-icon="ic:round-edit-note"></span>
                        <span><?= te('dash.action.new_article') ?></span>
                    </a>
                    <a href="./rubriky.php" class="quick-action">
                        <span class="iconify-inline" data-icon="ic:round-add-circle"></span>
                        <span><?= te('dash.action.new_category') ?></span>
                    </a>
                    <a href="./media.php" class="quick-action">
                        <span class="iconify-inline" data-icon="ic:round-cloud-upload"></span>
                        <span><?= te('dash.action.upload_file') ?></span>
                    </a>
                    <?php if ($level >= $admin_level): ?>
                    <a href="./edit_uzivatel.php" class="quick-action">
                        <span class="iconify-inline" data-icon="ic:round-person-add"></span>
                        <span><?= te('dash.action.new_user') ?></span>
                    </a>
                    <?php endif; ?>
                    <a href="./nastaveni.php" class="quick-action">
                        <span class="iconify-inline" data-icon="ic:baseline-settings"></span>
                        <span><?= te('dash.action.settings') ?></span>
                    </a>
                    <a href="<?= esc($frontend) ?>" target="_blank" class="quick-action">
                        <span class="iconify-inline" data-icon="ic:round-language"></span>
                        <span><?= te('dash.action.view_site') ?></span>
                    </a>
                </div>
            </section>
        </div>

        <?php
        // Plugin-contributed dashboard cards
        $extraCards = function_exists('apply_filters') ? apply_filters('admin.dashboardCards', []) : [];
        if (!empty($extraCards)):
        ?>
        <div class="dashboard-grid plugin-cards">
            <?php foreach ($extraCards as $renderer):
                if (is_callable($renderer)) {
                    try { $renderer(); } catch (Throwable $e) { error_log('Dashboard card failed: ' . $e->getMessage()); }
                }
            endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . "/includes/footer.php";?>
</body>
</html>
