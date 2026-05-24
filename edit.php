<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

$editMode = false;
$articleId = null;

if (isset($_GET['id'])) {
    $editMode = true;
    $articleId = (int)$_GET['id'];

    $article = $dbFetcher->article('id', $articleId, $level);
    if (!$article) {
        header("Location: ./clanky.php?type=negative&message=" . rawurlencode(t('error.article_not_found')));
        exit();
    }
} else {
    $user_id = $_SESSION['user_id'];
    $editingUser = $dbFetcher->user('id', $user_id);
    $article = [
        'id' => null,
        'name' => '',
        'author' => $editingUser['username'] ?? '',
        'category' => 1,
        'picture' => '',
        'time' => '',
        'content' => '',
        'url' => '',
        'tags' => '',
        'deletable' => 1,
        'public' => 1,
        'level' => 0,
    ];
}

// Build unique tag list (lowercase, sorted)
$tags = $dbFetcher->tags('tag', '');
$uniqueTags = [];
if ($tags !== null) {
    $seen = [];
    foreach ($tags as $tag) {
        $key = mb_strtolower($dbFetcher->removeAccents($tag));
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $uniqueTags[] = mb_strtolower($tag);
        }
    }
    sort($uniqueTags);
    if (isset($_GET['tag_order']) && $_GET['tag_order'] === 'DESC') rsort($uniqueTags);
}

$categories = $dbFetcher->categories('name', false);
$authors = $dbFetcher->allUsers();
$sitename = $editMode ? t('editor.title.edit') : t('editor.title.new');

// Datetime-local needs format: YYYY-MM-DDTHH:MM
$articleTimeForInput = '';
if (!empty($article['time'])) {
    $t = $article['time'];
    $dt = strtotime($t);
    if ($dt) $articleTimeForInput = date('Y-m-d\TH:i', $dt);
}
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php"; ?>
    <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.min.js"></script>
    <link rel="stylesheet" href="./res/css/insert-media.css">
</head>
<body>
    <?php include __DIR__ . "/includes/sidebar.php"; ?>

    <main class="article-editor-page">
        <header class="page-header">
            <div class="page-header-text">
                <h1><?= $sitename ?></h1>
                <div class="editor-meta-line">
                    <?php if ($editMode): ?>
                        <span class="editor-meta-item">
                            <span class="iconify-inline" data-icon="ic:round-tag"></span>
                            <?= (int)$articleId ?>
                        </span>
                        <span class="editor-meta-sep">·</span>
                    <?php endif; ?>
                    <span class="editor-meta-item" title="<?= te('editor.meta.words_label') ?>">
                        <span class="iconify-inline" data-icon="ic:round-text-fields"></span>
                        <span id="statWords">0</span>
                    </span>
                    <span class="editor-meta-sep">·</span>
                    <span class="editor-meta-item" title="<?= te('editor.meta.time_label') ?>">
                        <span class="iconify-inline" data-icon="ic:round-schedule"></span>
                        <span id="statTime">—</span>
                    </span>
                    <span class="editor-meta-sep">·</span>
                    <span class="editor-meta-item" title="<?= te('editor.meta.chars_label') ?>">
                        <span class="iconify-inline" data-icon="ic:round-text-snippet"></span>
                        <span id="statChars">0</span>
                    </span>
                </div>
            </div>
            <div class="page-header-actions">
                <a href="./clanky.php" class="tool">
                    <span class="iconify-inline" data-icon="ic:round-arrow-back"></span>
                    <span><?= te('editor.back') ?></span>
                </a>
                <?php if ($editMode): ?>
                    <a href="./nahled.php?id=<?= (int)$articleId ?>&amp;origin=1" class="tool" target="_blank">
                        <span class="iconify-inline" data-icon="ic:round-visibility"></span>
                        <span><?= te('editor.preview') ?></span>
                    </a>
                <?php endif; ?>
                <button form="addArticle" type="submit" class="btn-green">
                    <span class="iconify-inline" data-icon="<?= $editMode ? 'ic:round-save' : 'ic:round-add' ?>"></span>
                    <span><?= te($editMode ? 'editor.save' : 'editor.create') ?></span>
                </button>
            </div>
        </header>

        <form id="addArticle" method="POST" action="setter-proxy.php" class="article-editor">
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="article">
            <input type="hidden" name="origin" value="<?= esc($url) ?>">
            <?php if ($editMode): ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= (int)$articleId ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif; ?>

            <div class="article-editor-grid">

                <!-- ============ LEFT COLUMN: title + content ============ -->
                <div class="article-editor-content">
                    <div class="content-card">
                        <input type="text" id="itemName" name="name"
                               class="article-title-input"
                               placeholder="<?= te('editor.title_placeholder') ?>"
                               autocomplete="off"
                               value="<?= htmlspecialchars(trim($article['name'] ?? '')) ?>" required>
                        <div class="content-card-tabs">
                            <button type="button" class="editor-tab active" data-tab="edit">
                                <span class="iconify-inline" data-icon="ic:round-edit"></span>
                                <span><?= te('editor.tab.edit') ?></span>
                            </button>
                            <button type="button" class="editor-tab" data-tab="code">
                                <span class="iconify-inline" data-icon="ic:round-code"></span>
                                <span><?= te('editor.tab.code') ?></span>
                            </button>
                        </div>
                        <div id="quillHost"></div>
                        <textarea id="itemContent" name="content" class="code-editor-textarea" hidden><?= htmlspecialchars($article['content'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- ============ RIGHT COLUMN: flat settings list ============ -->
                <aside class="article-editor-aside">
                    <!-- Status (pill toggle) -->
                    <div class="aside-row">
                        <label class="aside-label"><?= te('editor.status') ?></label>
                        <div class="status-toggle" role="radiogroup">
                            <input type="radio" id="status-pub" name="public" value="1" <?= $article['public'] == 1 ? 'checked' : '' ?>>
                            <label for="status-pub" class="status-pill status-pub">
                                <span class="iconify-inline" data-icon="ic:round-check-circle"></span>
                                <?= te('editor.status.published') ?>
                            </label>
                            <input type="radio" id="status-draft" name="public" value="0" <?= $article['public'] == 0 ? 'checked' : '' ?>>
                            <label for="status-draft" class="status-pill status-draft">
                                <span class="iconify-inline" data-icon="ic:round-edit-note"></span>
                                <?= te('editor.status.draft') ?>
                            </label>
                        </div>
                    </div>

                    <!-- Author -->
                    <div class="aside-row">
                        <label for="author" class="aside-label"><?= te('editor.author') ?></label>
                        <select id="author" name="author" required>
                            <?php foreach ($authors as $a):
                                $display = trim(($a['name'] ?? '') . ' ' . ($a['surname'] ?? '')) ?: $a['username']; ?>
                                <option value="<?= htmlspecialchars($a['username']) ?>"
                                    <?= ($article['author'] == $a['username']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($display) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Category -->
                    <div class="aside-row">
                        <label for="itemCategory" class="aside-label"><?= te('editor.category') ?></label>
                        <select id="itemCategory" name="category">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"
                                    <?= ($article['category'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Publish date -->
                    <div class="aside-row">
                        <label for="native_input" class="aside-label"><?= te('editor.publish_date') ?></label>
                        <input type="datetime-local" id="native_input" name="time" value="<?= $articleTimeForInput ?>">
                    </div>

                    <!-- Featured image -->
                    <div class="aside-row">
                        <label class="aside-label"><?= te('editor.featured_image') ?></label>
                        <button type="button" class="featured-image-picker"
                                onclick="openMediaModalFeatured()"
                                id="featuredImagePicker"
                                data-empty="<?= empty($article['picture']) ? '1' : '0' ?>">
                            <img id="picturePreviewImg" src="<?= esc(icon('img', $article['picture'])) ?>" alt=""
                                 <?= empty($article['picture']) ? 'style="display:none;"' : '' ?>>
                            <div class="featured-image-empty" <?= !empty($article['picture']) ? 'style="display:none;"' : '' ?>>
                                <span class="iconify-inline" data-icon="ic:round-add-photo-alternate"></span>
                                <span><?= te('editor.featured.choose') ?></span>
                            </div>
                        </button>
                        <button type="button" class="featured-image-remove" id="featuredImageRemove"
                                <?= empty($article['picture']) ? 'style="display:none;"' : '' ?>>
                            <span class="iconify-inline" data-icon="ic:round-delete"></span>
                            <span><?= te('editor.featured.remove') ?></span>
                        </button>
                        <input type="hidden" id="itemPicture" name="picture"
                               value="<?= htmlspecialchars($article['picture'] ?? '') ?>">
                    </div>

                    <!-- Tags -->
                    <div class="aside-row">
                        <label for="itemTagsInput" class="aside-label"><?= te('editor.tags') ?></label>
                        <div class="tag-container" id="tagContainer"></div>
                        <div id="tagInput">
                            <input type="hidden" id="itemTagsHidden" name="tags" value="">
                            <input type="text" id="itemTagsInput" list="list-tags"
                                   name="tagsInput" placeholder="<?= te('editor.tag_placeholder') ?>"
                                   onkeydown="handleTagInput(event)">
                        </div>
                        <datalist id="list-tags">
                            <?php foreach ($uniqueTags as $tag): ?>
                                <option value="<?= esc($tag) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <?php if ($level >= $admin_level): ?>
                    <!-- Advanced (collapsible, segmented buttons) -->
                    <details class="aside-row aside-row-collapsible">
                        <summary>
                            <span class="aside-label"><?= te('editor.advanced') ?></span>
                            <span class="iconify-inline collapse-arrow" data-icon="ic:round-expand-more"></span>
                        </summary>
                        <div class="aside-row-body">
                            <div class="form-field">
                                <label class="aside-sublabel"><?= te('editor.delete_lock') ?></label>
                                <div class="seg-toggle">
                                    <input type="radio" id="lock-open" name="deletable" value="1" <?= $article['deletable'] == 1 ? 'checked' : '' ?>>
                                    <label for="lock-open" class="seg-pill">
                                        <span class="iconify-inline" data-icon="ic:round-lock-open"></span>
                                        <?= te('editor.lock.open') ?>
                                    </label>
                                    <input type="radio" id="lock-closed" name="deletable" value="0" <?= $article['deletable'] == 0 ? 'checked' : '' ?>>
                                    <label for="lock-closed" class="seg-pill">
                                        <span class="iconify-inline" data-icon="ic:round-lock"></span>
                                        <?= te('editor.lock.closed') ?>
                                    </label>
                                </div>
                            </div>
                            <div class="form-field">
                                <label class="aside-sublabel"><?= te('editor.min_role') ?></label>
                                <div class="seg-toggle seg-toggle-many">
                                    <input type="radio" id="lvl-0" name="level" value="0" <?= ($article['level'] ?? 0) == 0 ? 'checked' : '' ?>>
                                    <label for="lvl-0" class="seg-pill">0</label>
                                    <?php $levels = $dbFetcher->allLevels(); foreach ($levels as $lvlOpt):
                                        $lvlVal = (int)$lvlOpt['level']; ?>
                                        <input type="radio" id="lvl-<?= $lvlVal ?>" name="level" value="<?= $lvlVal ?>" <?= ($article['level'] ?? 0) == $lvlVal ? 'checked' : '' ?>>
                                        <label for="lvl-<?= $lvlVal ?>" class="seg-pill"><?= $lvlVal ?></label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="aside-hint"><?= te('editor.role_hint') ?></p>
                            </div>
                        </div>
                    </details>
                    <?php endif; ?>
                </aside>
            </div>
        </form>
    </main>

    <!-- ============ MEDIA SELECTION MODAL ============ -->
    <div id="media-selection-modal" class="modal">
        <?php $mediaItems = $dbFetcher->medias('name', ''); ?>
        <div class="plugin-modal-content">
            <header class="plugin-modal-header">
                <h2><?= te('editor.modal.choose_image') ?></h2>
                <span class="close" onclick="closeModal('media-selection-modal')">
                    <span class="iconify-inline" data-icon="ic:round-close"></span>
                </span>
            </header>
            <div id="media-container" class="grid">
                <div id="media-list">
                <?php if ($mediaItems): ?>
                    <?php foreach ($mediaItems as $row):
                        $filename = $row['filename'];
                        $name = esc($row['name'] ?? t('media.no_name'));
                        $description = esc($row['description'] ?? '');
                        $author = esc($row['author'] ?? '');
                        $filePath = 'media/uploads/' . esc($filename);
                        $thumbPath = 'media/uploads/thumbnails/' . esc($filename);
                        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                        if (!in_array($fileExtension, $imageFormats)) continue;
                    ?>
                        <div class="media-item">
                            <div class="img-container" id="<?= (int)$row['id'] ?>">
                                <div class="file-name"><abbr title="<?= $name ?>"><?= $name ?></abbr></div>
                                <div class="file-info"
                                    data-id="<?= (int)$row['id'] ?>"
                                    data-description="<?= $description ?>"
                                    data-name="<?= $name ?>"
                                    data-author="<?= $author ?>"
                                    data-url="<?= $filePath ?>"
                                    data-fileurl="<?= $filePath ?>">
                                    <?= $fileExtension ?>
                                </div>
                                <img loading="lazy" class="thumbnail"
                                     src="<?= $thumbPath ?>" data-src="<?= $filePath ?>" alt="">
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="iconify-inline" data-icon="ic:round-image-not-supported" style="width:40px;height:40px;color:var(--text-disabled);"></span>
                        <p><?= te('editor.empty.no_files') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============ IMAGE OPTIONS MODAL ============ -->
    <div id="image-options-modal" class="modal">
        <div class="modal-content image-options-content">
            <header class="plugin-modal-header">
                <h2><?= te('editor.modal.image_options') ?></h2>
                <span class="close" onclick="closeModal('image-options-modal')">
                    <span class="iconify-inline" data-icon="ic:round-close"></span>
                </span>
            </header>
            <div class="image-options-body">
                <div class="form-field">
                    <label><?= te('editor.modal.size') ?></label>
                    <div class="seg-toggle seg-toggle-many">
                        <input type="radio" id="img-size-small" name="img-size" value="small">
                        <label for="img-size-small" class="seg-pill">
                            <span class="iconify-inline" data-icon="ic:round-format-size"></span>
                            <?= te('editor.modal.size.small') ?>
                        </label>
                        <input type="radio" id="img-size-medium" name="img-size" value="medium">
                        <label for="img-size-medium" class="seg-pill"><?= te('editor.modal.size.medium') ?></label>
                        <input type="radio" id="img-size-large" name="img-size" value="large">
                        <label for="img-size-large" class="seg-pill"><?= te('editor.modal.size.large') ?></label>
                        <input type="radio" id="img-size-full" name="img-size" value="full" checked>
                        <label for="img-size-full" class="seg-pill"><?= te('editor.modal.size.full') ?></label>
                    </div>
                </div>

                <div class="form-field">
                    <label for="img-alt"><?= te('editor.modal.alt') ?></label>
                    <input type="text" name="alt" id="img-alt" placeholder="<?= te('editor.modal.alt_placeholder') ?>">
                </div>

                <div class="form-field">
                    <label for="img-title"><?= te('editor.modal.title_field') ?></label>
                    <input type="text" name="title" id="img-title" placeholder="<?= te('editor.modal.title_placeholder') ?>">
                </div>

                <div class="form-field">
                    <label for="img-author"><?= te('editor.modal.author') ?></label>
                    <input type="text" name="author" id="img-author" placeholder="<?= te('editor.modal.author_placeholder') ?>">
                </div>

                <div class="form-field">
                    <label for="img-caption"><?= te('editor.modal.caption') ?></label>
                    <input type="text" name="caption" id="img-caption" placeholder="<?= te('editor.modal.caption_placeholder') ?>">
                </div>
            </div>
            <footer class="image-options-footer">
                <button type="button" class="btn-red" onclick="removeEditorImage()">
                    <span class="iconify-inline" data-icon="ic:round-delete"></span>
                    <span><?= te('editor.modal.delete') ?></span>
                </button>
                <div style="display:flex;gap:var(--space-2);margin-left:auto;">
                    <button type="button" class="tool" onclick="closeModal('image-options-modal')"><?= te('editor.modal.cancel') ?></button>
                    <button type="button" class="btn-green" onclick="applyImageOptions()">
                        <span class="iconify-inline" data-icon="ic:round-check"></span>
                        <span><?= te('editor.modal.apply') ?></span>
                    </button>
                </div>
            </footer>
        </div>
    </div>

    <?php include __DIR__ . "/includes/footer.php"; ?>

    <script src="./res/js/tags-style.js"></script>
    <script>
    const I18N = {
        startTyping: <?= json_encode(t('editor.quill.start_typing')) ?>,
        bold:        <?= json_encode(t('editor.quill.bold')) ?>,
        italic:      <?= json_encode(t('editor.quill.italic')) ?>,
        underline:   <?= json_encode(t('editor.quill.underline')) ?>,
        strike:      <?= json_encode(t('editor.quill.strike')) ?>,
        listOrdered: <?= json_encode(t('editor.quill.list_ordered')) ?>,
        listBullet:  <?= json_encode(t('editor.quill.list_bullet')) ?>,
        indentDec:   <?= json_encode(t('editor.quill.indent_dec')) ?>,
        indentInc:   <?= json_encode(t('editor.quill.indent_inc')) ?>,
        scriptSub:   <?= json_encode(t('editor.quill.script_sub')) ?>,
        scriptSuper: <?= json_encode(t('editor.quill.script_super')) ?>,
        blockquote:  <?= json_encode(t('editor.quill.blockquote')) ?>,
        codeBlock:   <?= json_encode(t('editor.quill.code_block')) ?>,
        link:        <?= json_encode(t('editor.quill.link')) ?>,
        image:       <?= json_encode(t('editor.quill.image')) ?>,
        video:       <?= json_encode(t('editor.quill.video')) ?>,
        clean:       <?= json_encode(t('editor.quill.clean')) ?>,
        align:       <?= json_encode(t('editor.quill.align')) ?>,
        color:       <?= json_encode(t('editor.quill.color')) ?>,
        background:  <?= json_encode(t('editor.quill.background')) ?>,
        header:      <?= json_encode(t('editor.quill.header')) ?>,
        paragraph:   <?= json_encode(t('editor.quill.paragraph')) ?>,
        bgShort:     <?= json_encode(t('editor.quill.background_short')) ?>,
        wordsFmt:    <?= json_encode(t('editor.meta.words_n')) ?>,
        charsFmt:    <?= json_encode(t('editor.meta.chars_n')) ?>,
        lessThanMin: <?= json_encode(t('editor.reading.less_than_min')) ?>,
        minShort:    <?= json_encode(t('editor.reading.min_short')) ?>,
        secShort:    <?= json_encode(t('editor.reading.sec_short')) ?>,
    };
    function fmt(s, ...args) { let i = 0; return s.replace(/%[sd]/g, () => String(args[i++])); }
    function openModal(id) { document.getElementById(id).style.display = 'block'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    function openMediaModal(forFeatured = false) {
        window._mediaPickFeatured = !forFeatured ? !!window._quillInsertRange ? false : true : true;
        // simpler: distinguish via a flag set by callers
        openModal('media-selection-modal');
    }
    // Featured-image picker uses this entry point (no Quill range stashed)
    function openMediaModalFeatured() {
        window._quillInsertRange = null;
        window._mediaPickFeatured = true;
        openModal('media-selection-modal');
    }

    // ====== Quill rich text editor ======
    let quillEditor = null;
    document.addEventListener("DOMContentLoaded", function () {
        const textarea = document.getElementById('itemContent');
        const host = document.getElementById('quillHost');
        if (!textarea || !host) return;

        host.innerHTML = textarea.value;

        quillEditor = new Quill(host, {
            theme: 'snow',
            placeholder: I18N.startTyping,
            modules: {
                toolbar: {
                    container: [
                        [{ header: [1, 2, 3, 4, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ color: [] }, { background: [] }],
                        [{ script: 'sub' }, { script: 'super' }],
                        [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
                        [{ align: [] }],
                        ['blockquote', 'code-block'],
                        ['link', 'image', 'video'],
                        ['clean']
                    ],
                    handlers: {
                        image: imageHandler
                    }
                }
            }
        });

        // Add native tooltips to every Quill toolbar control
        const tooltips = {
            'ql-bold': I18N.bold,
            'ql-italic': I18N.italic,
            'ql-underline': I18N.underline,
            'ql-strike': I18N.strike,
            'ql-list[value="ordered"]': I18N.listOrdered,
            'ql-list[value="bullet"]': I18N.listBullet,
            'ql-indent[value="-1"]': I18N.indentDec,
            'ql-indent[value="+1"]': I18N.indentInc,
            'ql-script[value="sub"]': I18N.scriptSub,
            'ql-script[value="super"]': I18N.scriptSuper,
            'ql-blockquote': I18N.blockquote,
            'ql-code-block': I18N.codeBlock,
            'ql-link': I18N.link,
            'ql-image': I18N.image,
            'ql-video': I18N.video,
            'ql-clean': I18N.clean,
            'ql-align': I18N.align,
            'ql-color': I18N.color,
            'ql-background': I18N.background,
            'ql-header': I18N.header,
        };
        for (const [sel, title] of Object.entries(tooltips)) {
            host.parentElement.querySelectorAll('.' + sel.replace(/\[.*\]/, '') + (sel.match(/\[.*\]/) || [''])[0])
                .forEach(btn => {
                    btn.setAttribute('title', title);
                    btn.setAttribute('aria-label', title);
                });
        }
        // Also tooltip on the picker labels (header dropdown shows "Normal/Heading 1...")
        host.parentElement.querySelectorAll('.ql-header .ql-picker-label')
            .forEach(el => el.setAttribute('title', I18N.paragraph));
        host.parentElement.querySelectorAll('.ql-align .ql-picker-label')
            .forEach(el => el.setAttribute('title', I18N.align));
        host.parentElement.querySelectorAll('.ql-color .ql-picker-label')
            .forEach(el => el.setAttribute('title', I18N.color));
        host.parentElement.querySelectorAll('.ql-background .ql-picker-label')
            .forEach(el => el.setAttribute('title', I18N.bgShort));

        // Don't autofocus Quill — let user click the title first
        document.activeElement && document.activeElement.blur && document.activeElement.blur();

        // Image click → open the image options modal
        quillEditor.root.addEventListener('click', function (e) {
            if (e.target.tagName === 'IMG') {
                e.preventDefault();
                openImageOptions(e.target);
            }
        });

        document.getElementById('addArticle').addEventListener('submit', function () {
            textarea.value = quillEditor.root.innerHTML;
        });

        updateReadingInfo(quillEditor.root.innerHTML);
        quillEditor.on('text-change', () => updateReadingInfo(quillEditor.root.innerHTML));
    });

    // ====== Custom image handler — opens our gallery picker ======
    function imageHandler() {
        // Stash the current selection so we know where to insert
        window._quillInsertRange = quillEditor.getSelection(true);
        openMediaModal();
    }

    function insertImageFromGallery(url, meta) {
        const range = window._quillInsertRange || quillEditor.getSelection(true) || { index: quillEditor.getLength() };
        // Insert image
        quillEditor.insertEmbed(range.index, 'image', url, 'user');
        quillEditor.setSelection(range.index + 1);
        // Set extra attributes (alt, title) on the inserted img
        setTimeout(() => {
            const imgs = quillEditor.root.querySelectorAll('img[src="' + CSS.escape(url) + '"]');
            const last = imgs[imgs.length - 1];
            if (last && meta) {
                if (meta.name) { last.alt = meta.name; last.title = meta.name; }
                if (meta.author) last.dataset.author = meta.author;
                if (meta.description) last.dataset.description = meta.description;
            }
        }, 0);
    }

    // ====== Image options modal (alt, title, author, size, caption) ======
    let activeEditorImage = null;
    function openImageOptions(img) {
        activeEditorImage = img;
        const modal = document.getElementById('image-options-modal');
        modal.querySelector('[name="alt"]').value = img.alt || '';
        modal.querySelector('[name="title"]').value = img.title || '';
        modal.querySelector('[name="author"]').value = img.dataset.author || '';
        modal.querySelector('[name="caption"]').value = img.dataset.caption || '';
        const sizeRadios = modal.querySelectorAll('input[name="img-size"]');
        const cur = img.dataset.size || 'full';
        sizeRadios.forEach(r => r.checked = r.value === cur);
        openModal('image-options-modal');
    }

    function applyImageOptions() {
        if (!activeEditorImage) return;
        const modal = document.getElementById('image-options-modal');
        const alt = modal.querySelector('[name="alt"]').value.trim();
        const title = modal.querySelector('[name="title"]').value.trim();
        const author = modal.querySelector('[name="author"]').value.trim();
        const caption = modal.querySelector('[name="caption"]').value.trim();
        const size = modal.querySelector('input[name="img-size"]:checked')?.value || 'full';

        activeEditorImage.alt = alt;
        activeEditorImage.title = title;
        if (author) activeEditorImage.dataset.author = author; else delete activeEditorImage.dataset.author;
        if (caption) activeEditorImage.dataset.caption = caption; else delete activeEditorImage.dataset.caption;

        // Apply size class
        activeEditorImage.classList.remove('img-small', 'img-medium', 'img-large', 'img-full');
        activeEditorImage.classList.add('img-' + size);
        activeEditorImage.dataset.size = size;

        closeModal('image-options-modal');
        // Trigger Quill change so our reading-info / saving logic picks it up
        quillEditor.update();
    }

    function removeEditorImage() {
        if (!activeEditorImage) return;
        activeEditorImage.parentNode.removeChild(activeEditorImage);
        closeModal('image-options-modal');
        quillEditor.update();
    }

    // ====== Reading info ======
    function countWords(text) {
        const t = String(text).trim().replace(/[.,\/#!$%\^&\*;:{}=\-_`~()]/g, '');
        if (!t) return 0;
        return t.split(/\s+/).filter(w => w.length > 0).length;
    }

    function calculateReadingTime(words) {
        const wpm = 175;
        const seconds = Math.round((words / wpm) * 60);
        if (seconds < 60) return I18N.lessThanMin;
        const m = Math.floor(seconds / 60);
        const s = Math.round((seconds % 60) / 15) * 15;
        if (s === 60 || s === 0) return m + ' ' + I18N.minShort;
        return m + ' ' + I18N.minShort + ' ' + s + ' ' + I18N.secShort;
    }

    function updateReadingInfo(html) {
        const text = (new DOMParser()).parseFromString(html, 'text/html').body.textContent || '';
        const words = countWords(text);
        const eta = calculateReadingTime(words);
        const w = document.getElementById('statWords');
        const tEl = document.getElementById('statTime');
        const c = document.getElementById('statChars');
        if (w) w.textContent = fmt(I18N.wordsFmt, words);
        if (tEl) tEl.textContent = eta;
        if (c) c.textContent = fmt(I18N.charsFmt, text.length);
    }

    // ====== Featured image picker ======
    function updateFeaturedImageUI() {
        const input = document.getElementById('itemPicture');
        const picker = document.getElementById('featuredImagePicker');
        const img = document.getElementById('picturePreviewImg');
        const empty = picker.querySelector('.featured-image-empty');
        const remove = document.getElementById('featuredImageRemove');
        const url = (input.value || '').trim();
        if (url) {
            img.src = url;
            img.style.display = 'block';
            empty.style.display = 'none';
            remove.style.display = 'inline-flex';
            picker.dataset.empty = '0';
        } else {
            img.style.display = 'none';
            empty.style.display = 'flex';
            remove.style.display = 'none';
            picker.dataset.empty = '1';
        }
    }

    document.getElementById('featuredImageRemove')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('itemPicture').value = '';
        updateFeaturedImageUI();
    });

    // ====== Editor tabs (Edit/Code) ======
    document.querySelectorAll('.editor-tab[data-tab]').forEach(tab => {
        tab.addEventListener('click', function () {
            const which = this.dataset.tab;
            if (!which) return;
            document.querySelectorAll('.editor-tab[data-tab]').forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            const ta = document.getElementById('itemContent');
            const host = document.getElementById('quillHost');

            if (which === 'code') {
                // Sync Quill HTML → textarea, then show textarea, hide Quill
                if (quillEditor) ta.value = quillEditor.root.innerHTML;
                ta.removeAttribute('hidden');
                ta.classList.add('visible');
                host.classList.add('hidden-tab');
            } else {
                // Sync textarea → Quill, then show Quill, hide textarea
                if (quillEditor) quillEditor.root.innerHTML = ta.value;
                ta.classList.remove('visible');
                ta.setAttribute('hidden', '');
                host.classList.remove('hidden-tab');
            }
        });
    });

    // ====== Media modal selection ======
    document.querySelectorAll('#media-selection-modal .media-item').forEach(item => {
        item.addEventListener('click', function () {
            const fi = this.querySelector('.file-info');
            const url = fi.dataset.url;
            const meta = {
                name: fi.dataset.name,
                author: fi.dataset.author,
                description: fi.dataset.description,
            };

            if (window._mediaPickFeatured) {
                // Picking featured image
                document.getElementById('itemPicture').value = url;
                updateFeaturedImageUI();
            } else {
                // Inserting into Quill content
                insertImageFromGallery(url, meta);
            }
            window._mediaPickFeatured = false;
            window._quillInsertRange = null;
            closeModal('media-selection-modal');
        });
    });

    // Initial sync
    updateFeaturedImageUI();

    // ====== Lazy load thumbnails in media modal ======
    function lazyLoadImages() {
        document.querySelectorAll('#media-selection-modal img[data-src]:not([data-loaded])').forEach(img => {
            const rect = img.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom >= 0) {
                const src = img.dataset.src;
                const full = new Image();
                full.src = src;
                full.onload = () => {
                    img.src = src;
                    img.style.filter = 'blur(0)';
                    img.setAttribute('data-loaded', 'true');
                };
            }
        });
    }
    window.addEventListener('scroll', lazyLoadImages);
    window.addEventListener('resize', lazyLoadImages);
    window.addEventListener('DOMContentLoaded', lazyLoadImages);

    // ====== Prevent Enter from submitting form (except in textareas) ======
    document.getElementById('addArticle').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && !e.target.closest('.ql-editor')) {
            e.preventDefault();
        }
    });

    // ====== Tag removal ======
    function removeTag(btn) {
        const tag = btn.parentNode;
        const tagName = tag.lastChild.nodeType === 3 ? tag.lastChild.nodeValue.trim() : '';
        tag.remove();
        const hidden = document.getElementById('itemTagsHidden');
        const arr = hidden.value.split(', ').filter(t => t !== tagName);
        hidden.value = arr.join(', ');
    }

    // ====== Pre-load tags in edit mode ======
    <?php if ($editMode && !empty($article['tags'])):
        $tagsArr = explode(', ', $article['tags']);
        foreach ($tagsArr as $t) {
            if ($t !== '') echo "addTag(" . json_encode($t) . ");\n";
        }
    endif; ?>
    </script>
</body>
</html>
