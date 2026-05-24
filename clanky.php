<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

$defaultSortColumn = 'time';
$defaultSortOrder = 'DESC';
$defaultSearchBy = 'name';
require_once __DIR__ . "/includes/search.php";

// Pull category list for filter dropdown
$allCategoriesForFilter = $dbFetcher->allCategories(1, 9999, 'name', 'ASC');
// Pull tag list for filter dropdown
$tagListForFilter = $dbFetcher->tags('tag', '');
$uniqueTagsForFilter = [];
if (is_array($tagListForFilter)) {
    foreach ($tagListForFilter as $t) {
        $key = mb_strtolower($t);
        if (!isset($uniqueTagsForFilter[$key])) $uniqueTagsForFilter[$key] = $t;
    }
    ksort($uniqueTagsForFilter);
}

if ($search && $searchBy) {
    $articles = $dbFetcher->articles($searchBy, $search, $page, $recordsPerPage, $sortColumn, $sortOrder, $level);
    $totalRecords = $dbFetcher->countArticles([$searchBy => $search, 'level' => "<=" . $level]);
} else {
    $articles = $dbFetcher->allArticles($page, $recordsPerPage, $sortColumn, $sortOrder, $level);
    $totalRecords = $dbFetcher->countArticles(['level' => "<=" . $level]);
}

$totalPages = ceil($totalRecords / $recordsPerPage);

if ($search) {
    if ($searchBy == 'category') {
        $catName = esc($dbFetcher->category('id', $search)['name'] ?? '');
        $sitename = t('articles.title_in_category');
        $subtitle = $catName;
    } else {
        $sitename = t('articles.search_results');
        $subtitle = t('articles.search_for', esc($search));
    }
} else {
    $sitename = t('articles.title');
    $subtitle = tn((int)$totalRecords, 'articles.subtitle');
}
$thisURL = basename($_SERVER['PHP_SELF']);
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
                <a href="edit.php" class="btn-green">
                    <span class="iconify-inline" data-icon="ic:round-edit-note"></span>
                    <span><?= te('articles.new') ?></span>
                </a>
            </div>
        </header>

        <div class="table-card">
            <div class="table-toolbar">
                <div class="table-toolbar-left">
                    <form method="get" action="<?= $thisURL ?>" class="filter-form" id="filterForm">
                        <select name="search_by" onchange="this.form.submit()" class="filter-select">
                            <option value="name" <?= $searchBy === 'name' ? 'selected' : '' ?>><?= te('articles.filter.all') ?></option>
                            <option value="category" <?= $searchBy === 'category' ? 'selected' : '' ?>><?= te('articles.filter.category') ?></option>
                            <option value="tag" <?= $searchBy === 'tag' ? 'selected' : '' ?>><?= te('articles.filter.tag') ?></option>
                            <option value="author" <?= $searchBy === 'author' ? 'selected' : '' ?>><?= te('articles.filter.author') ?></option>
                        </select>
                        <?php if ($searchBy === 'category'): ?>
                            <select name="search" onchange="this.form.submit()" class="filter-select">
                                <option value=""><?= te('articles.filter.choose_cat') ?></option>
                                <?php foreach ($allCategoriesForFilter as $cat): if ($cat['id'] == 1) continue; ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= ((string)$search === (string)$cat['id']) ? 'selected' : '' ?>>
                                        <?= esc($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($searchBy === 'tag'): ?>
                            <select name="search" onchange="this.form.submit()" class="filter-select">
                                <option value=""><?= te('articles.filter.choose_tag') ?></option>
                                <?php foreach ($uniqueTagsForFilter as $tg): ?>
                                    <option value="<?= esc($tg) ?>" <?= ((string)$search === (string)$tg) ? 'selected' : '' ?>>
                                        <?= esc($tg) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <?php if ($search): ?>
                        <a href="<?= $thisURL ?>" class="tool">
                            <span class="iconify-inline" data-icon="ic:round-search-off"></span>
                            <span><?= te('articles.filter.clear') ?></span>
                        </a>
                        <?php endif; ?>
                    </form>
                    <a href="javascript:void(0);" class="tool" onclick="deleteSelectedPrompt('article')">
                        <span class="iconify-inline" data-icon="ic:round-delete"></span>
                        <span><?= te('articles.bulk.delete') ?></span>
                    </a>
                </div>
                <div class="table-toolbar-right">
                    <?php if (!in_array($searchBy, ['category', 'tag'])): ?>
                        <?php include __DIR__ . "/includes/searchInput.php"; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($articles)): ?>
            <table id="itemList" class="clanky">
                <thead>
                    <tr>
                        <th class="check"><input type="checkbox" id="selectAll" onclick="toggleCheckboxes()"></th>
                        <th><a href="?<?= http_build_query(editQuery(['sort', 'order'], ['name', $oppositeOrder])) ?>"><?= te('articles.col.name') ?></a></th>
                        <th class="no-display"><a href="?<?= http_build_query(editQuery(['sort', 'order'], ['author', $oppositeOrder])) ?>"><?= te('articles.col.author') ?></a></th>
                        <th class="no-display"><a href="?<?= http_build_query(editQuery(['sort', 'order'], ['time', $oppositeOrder])) ?>"><?= te('articles.col.date') ?></a></th>
                        <th class="no-display"><a href="?<?= http_build_query(editQuery(['sort', 'order'], ['category', $oppositeOrder])) ?>"><?= te('articles.col.category') ?></a></th>
                        <th class="tools"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article):
                        $cat = $article['category'];
                        $category = $dbFetcher->category('id', $cat, $level);
                        $articleAuthor = $dbFetcher->user('username', $article['author']);
                    ?>
                    <tr>
                        <td class="check">
                            <?php if ($article['deletable'] == 0): ?>
                                <span class="iconify-inline neutral" data-icon="ic:round-lock"></span>
                            <?php else: ?>
                                <input type="checkbox" class="delete-checkbox" value="<?= (int)$article['id'] ?>">
                            <?php endif; ?>
                        </td>
                        <td class="cell-primary">
                            <a href="nahled.php?id=<?= (int)$article['id'] ?>&amp;origin=1"><?= esc($article['name']) ?></a>
                            <?php if ($article['public'] == 0): ?>
                                <span class="badge badge-warn" style="margin-left:6px;"><?= te('dash.draft_badge') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="no-display">
                            <a href="uzivatel.php?id=<?= (int)($articleAuthor['id'] ?? 0) ?>" class="cell-with-avatar" style="font-weight:var(--font-medium);">
                                <img src="<?= icon('user', $articleAuthor) ?>" alt="">
                                <span><?= esc($article['author']) ?></span>
                            </a>
                        </td>
                        <td class="sub no-display">
                            <?= formatDate($article['time'], false) ?>
                        </td>
                        <td class="no-display">
                            <?php if ($category): ?>
                                <a href="?search=<?= (int)$cat ?>&amp;search_by=category" class="badge"><?= esc($category['name']) ?></a>
                            <?php endif; ?>
                        </td>
                        <td class="tools">
                            <a href="edit.php?id=<?= (int)$article['id'] ?>" class="positive-hover" title="<?= te('articles.row.edit') ?>">
                                <span class="iconify-inline" data-icon="ic:round-edit"></span>
                            </a>
                            <?php if ($article['deletable'] == 1): ?>
                                <span class="negative-hover" style="cursor:pointer;" onclick="deletePrompt(<?= (int)$article['id'] ?>, 'article')" title="<?= te('articles.row.delete') ?>">
                                    <span class="iconify-inline" data-icon="ic:round-delete"></span>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <span class="iconify-inline" data-icon="ic:round-article" style="width:48px;height:48px;color:var(--text-disabled);"></span>
                <h3><?= te('articles.empty.title') ?></h3>
                <p>
                    <?= te($search ? 'articles.empty.filtered' : 'articles.empty.create') ?>
                </p>
                <?php if (!$search): ?>
                <a href="edit.php" class="btn-green">
                    <span class="iconify-inline" data-icon="ic:round-add"></span>
                    <?= te('articles.new') ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <?php include __DIR__ . "/includes/pagination.php"; ?>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . "/includes/footer.php"; ?>
</body>
</html>
