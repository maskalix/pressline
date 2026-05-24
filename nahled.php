<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

if (isset($_GET['id'])) {
    $articleId = $_GET['id'];
    $article = $dbFetcher->article('id', $articleId, $_SESSION['user_level']);
    $category = $dbFetcher->category('id', $article['category']);
    $author = $dbFetcher->user('username', $article['author']);
} else {
    header("Location: clanky.php");
    exit();
}
$sitename = t('preview.title');

$image = null;
if (!empty($article['picture']) && is_string($article['picture']) && !strpos($article['picture'], '://')) {
    $filename = basename($article['picture']);
    $imgRow = $dbFetcher->media('filename', $filename);
    if (!empty($imgRow)) $image = $imgRow;
}
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php";?>
</head>
<body>
    <?php include __DIR__ . "/includes/sidebar.php";?>
    <main class="article-preview">
        <div class="tools-list">
            <a href="<?= esc(($_GET['origin'] ?? '') == 1 ? 'clanky.php' : 'tagItems.php?tag=' . urlencode($_GET['origin'] ?? '')) ?>" class="tool">
                <span class="iconify-inline" data-icon="ic:round-arrow-back"></span>
                <span><?= te('preview.back') ?></span>
            </a>
            <a href="edit.php?id=<?= (int)$article['id'] ?>" class="tool btn-green">
                <span class="iconify-inline" data-icon="ic:round-edit"></span>
                <span><?= te('preview.edit') ?></span>
            </a>
            <?php if ($article['public'] == 0): ?>
                <span class="badge badge-warn"><?= te('preview.draft_badge') ?></span>
            <?php endif; ?>
        </div>

        <article class="preview-article">
            <nav class="breadcrumbs">
                <a href="clanky.php"><?= te('preview.breadcrumb_articles') ?></a>
                <span class="iconify-inline" data-icon="ic:round-chevron-right"></span>
                <?php if ($category): ?>
                    <a href="clanky.php?search=<?= (int)$category['id'] ?>&amp;search_by=category"><?= esc($category['name']) ?></a>
                <?php endif; ?>
            </nav>

            <header class="preview-header">
                <h1><?= esc($article['name']) ?></h1>

                <div class="preview-meta">
                    <a href="uzivatel.php?id=<?= (int)$author['id'] ?>" class="preview-author">
                        <img src="<?= icon('user', $author) ?>" alt="" class="circle">
                        <span><?= esc($author['name']) . ' ' . esc($author['surname']) ?></span>
                    </a>
                    <span class="dot">•</span>
                    <span class="preview-meta-item">
                        <span class="iconify-inline" data-icon="ic:round-calendar-today"></span>
                        <?= formatDate($article['time']) ?>
                    </span>
                    <span class="dot">•</span>
                    <span class="preview-meta-item">
                        <span class="iconify-inline" data-icon="ic:round-schedule"></span>
                        <?= calculateReadingTime(countWords($article['content'])) ?>
                    </span>
                </div>
            </header>

            <?php if (!empty($article['picture'])): ?>
            <figure class="preview-figure">
                <img src="<?= esc(icon('img', $article['picture'])) ?>" alt="<?= esc($article['name']) ?>">
                <?php if ($image): ?>
                <figcaption>
                    <span class="figcaption-meta">
                        <?php if (!empty($image['author'])): ?>
                            <span><?= esc($image['author']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($image['name'])): ?>
                            <span class="dot">•</span>
                            <span><?= esc($image['name']) ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if (!empty($image['description'])): ?>
                    <span class="figcaption-desc">
                        <span class="iconify-inline" data-icon="ic:round-info"></span>
                        <?= esc($image['description']) ?>
                    </span>
                    <?php endif; ?>
                </figcaption>
                <?php endif; ?>
            </figure>
            <?php endif; ?>

            <section class="preview-body">
                <?= strip_tags($article['content'], '<p><br><h1><h2><h3><h4><h5><h6><a><img><ul><ol><li><strong><em><b><i><u><s><blockquote><pre><code><table><thead><tbody><tr><th><td><figure><figcaption><div><span><hr><sup><sub><dl><dt><dd><video><audio><source>') ?>
            </section>

            <?php if (!empty($article['tags'])): ?>
            <footer class="preview-footer">
                <div class="tags">
                    <?= createClickableTags($article['tags']) ?>
                </div>
            </footer>
            <?php endif; ?>
        </article>
    </main>
    <?php include __DIR__ . "/includes/footer.php";?>
</body>
</html>
