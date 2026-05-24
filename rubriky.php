<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

$defaultSortColumn = 'id';
$defaultSortOrder = 'ASC';
$defaultSearchBy = 'name';
require_once __DIR__ . "/includes/search.php";

if ($search && $searchBy) {
    $categories = $dbFetcher->categories($searchBy, $search, $page, $recordsPerPage, $sortColumn, $sortOrder);
    $totalRecords = $dbFetcher->countCategories([$searchBy => $search]);
} else {
    $categories = $dbFetcher->allCategories($page, $recordsPerPage, $sortColumn, $sortOrder);
    $totalRecords = $dbFetcher->countCategories();
}

$totalPages = ceil($totalRecords / $recordsPerPage);

if ($search) {
    $sitename = t('articles.search_results');
    $subtitle = t('articles.search_for', esc($search));
} else {
    $count = max(0, $totalRecords - 1);
    $sitename = t('categories.title');
    $subtitle = tn((int)$count, 'categories.subtitle');
}
$thisURL = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php"; ?>
    <script>
        function openForm(val, id, name) {
            document.getElementById(val).style.display = 'block';
            if (id) {
                document.getElementById("id").value = id;
                document.getElementById("name").value = name;
            }
        }
        function closeForm(val) {
            document.getElementById(val).style.display = 'none';
        }
    </script>
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
                <a href="javascript:void(0);" onclick="openForm('createCategoryModal')" class="btn-green">
                    <span class="iconify-inline" data-icon="ic:round-add"></span>
                    <span><?= te('categories.new') ?></span>
                </a>
            </div>
        </header>

        <?php if (!empty($categories)): ?>
        <div class="category-grid">
            <?php foreach ($categories as $category):
                $articleCount = $dbFetcher->countArticles(['category' => $category['id']]);
                $isDefault = $category['id'] == 1;
            ?>
            <article class="category-card">
                <div class="category-card-header">
                    <div class="category-card-icon">
                        <span class="iconify-inline" data-icon="<?= $isDefault ? 'ic:round-inbox' : 'ic:round-folder' ?>"></span>
                    </div>
                    <?php if (!$isDefault): ?>
                    <input type="checkbox" class="delete-checkbox" value="<?= (int)$category['id'] ?>" title="<?= te('categories.bulk_select') ?>">
                    <?php else: ?>
                    <span class="iconify-inline neutral" data-icon="ic:round-lock" title="<?= te('categories.default_tooltip') ?>"></span>
                    <?php endif; ?>
                </div>

                <h3 class="category-card-title">
                    <a href="clanky.php?search=<?= (int)$category['id'] ?>&amp;search_by=category"><?= esc($category['name']) ?></a>
                </h3>

                <div class="category-card-meta">
                    <span><?= esc(tn((int)$articleCount, 'categories.articles')) ?></span>
                </div>

                <div class="category-card-actions">
                    <?php if (!$isDefault): ?>
                    <a href="javascript:void(0);" onclick="openForm('editCategoryModal', <?= (int)$category['id'] ?>, <?= htmlspecialchars(json_encode($category['name']), ENT_QUOTES, 'UTF-8') ?>)" class="positive-hover" title="<?= te('categories.row.edit') ?>">
                        <span class="iconify-inline" data-icon="ic:round-edit"></span>
                    </a>
                    <a href="javascript:void(0);" onclick="deletePrompt(<?= (int)$category['id'] ?>, 'category')" class="negative-hover" title="<?= te('categories.row.delete') ?>">
                        <span class="iconify-inline" data-icon="ic:round-delete"></span>
                    </a>
                    <?php else: ?>
                    <span class="muted-label"><?= te('categories.default_label') ?></span>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="bulk-actions">
            <?php if ($search): ?>
            <a href="<?= $thisURL ?>" class="tool">
                <span class="iconify-inline" data-icon="ic:round-search-off"></span>
                <span><?= te('categories.clear_filter') ?></span>
            </a>
            <?php endif; ?>
            <a href="javascript:void(0);" onclick="deleteSelectedPrompt('category')" class="tool">
                <span class="iconify-inline" data-icon="ic:round-delete"></span>
                <span><?= te('articles.bulk.delete') ?></span>
            </a>
            <div class="bulk-search">
                <?php include __DIR__ . "/includes/searchInput.php"; ?>
            </div>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <span class="iconify-inline" data-icon="ic:round-folder-off" style="width:48px;height:48px;color:var(--text-disabled);"></span>
            <h3><?= te('categories.empty.title') ?></h3>
            <p><?= te('categories.empty.body') ?></p>
            <a href="javascript:void(0);" onclick="openForm('createCategoryModal')" class="btn-green"><?= te('categories.empty.create') ?></a>
        </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): include __DIR__ . "/includes/pagination.php"; endif; ?>

        <!-- Create modal -->
        <form id="createCategoryForm" action="./setter-proxy.php" method="POST">
            <?= csrf_field() ?>
            <div id="createCategoryModal" class="modal">
                <div id="createCategoryModalContent" class="modal-content">
                    <span class="close" onclick="closeForm('createCategoryModal')">
                        <span class="iconify-inline" data-icon="ic:round-close"></span>
                    </span>
                    <h2><?= te('categories.modal.create') ?></h2>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="type" value="category">
                    <div class="form-field" style="display:flex;flex-direction:column;gap:var(--space-1);">
                        <label for="newCategoryName"><?= te('categories.modal.name') ?></label>
                        <input id="newCategoryName" type="text" name="name" placeholder="<?= te('categories.modal.placeholder') ?>" required autofocus>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="tool" onclick="closeForm('createCategoryModal')"><?= te('categories.modal.cancel') ?></button>
                        <button type="submit" class="btn-green"><?= te('categories.modal.create_btn') ?></button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Edit modal -->
        <form id="editCategoryForm" action="./setter-proxy.php" method="POST">
            <?= csrf_field() ?>
            <div id="editCategoryModal" class="modal">
                <div id="editCategoryModalContent" class="modal-content">
                    <span class="close" onclick="closeForm('editCategoryModal')">
                        <span class="iconify-inline" data-icon="ic:round-close"></span>
                    </span>
                    <h2><?= te('categories.modal.edit') ?></h2>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="type" value="category">
                    <input type="hidden" id="id" name="id">
                    <div class="form-field" style="display:flex;flex-direction:column;gap:var(--space-1);">
                        <label for="name"><?= te('categories.modal.name') ?></label>
                        <input type="text" id="name" name="name" placeholder="<?= te('categories.modal.placeholder') ?>" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="tool" onclick="closeForm('editCategoryModal')"><?= te('categories.modal.cancel') ?></button>
                        <button type="submit" class="btn-green"><?= te('categories.modal.save') ?></button>
                    </div>
                </div>
            </div>
        </form>
    </main>
    <?php include __DIR__ . "/includes/footer.php"; ?>
</body>
</html>
