<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

// Get URL query
$displayLayout = isset($_GET["layout"]) ? $_GET["layout"] : "list";
$displayImage = isset($_GET['img']) ? $_GET['img'] : 'true';
$oppositeDisplayImage = ($displayImage == 'true') ? 'false' : 'true'; 

$defaultSortColumn = 'upload_date';
$defaultSortOrder = 'DESC';
$defaultSearchBy = 'username';
require_once __DIR__ . "/includes/search.php";

$totalRecords = $dbFetcher->countMedia();
$totalPages = ceil($totalRecords / $recordsPerPage);

$query = editQuery(['layout', 'img'],[$displayLayout, $displayImage]);
$medias = $dbFetcher->allMedia($page, $recordsPerPage, $sortColumn, $sortOrder);
$sitename = t('media.title');
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php";?>
    <script>
        const uploadMaxFilesize = <?= json_encode(ini_get('upload_max_filesize'));?>;
        const postMaxSize = <?= json_encode(ini_get('post_max_size'));?>;
    </script>
    <script src="./res/js/uploadSizeCheck.js"></script>
</head>
<body>
<?php include __DIR__ . "/includes/sidebar.php";?>
<main>
    <header class="page-header">
        <div class="page-header-text">
            <h1><?= $sitename ?></h1>
            <p><?= te('media.subtitle.files_n', (int)$totalRecords) ?></p>
        </div>
        <div class="page-header-actions">
            <a href="#" id="upload-btn" class="btn-green">
                <span class="iconify-inline" data-icon="ic:round-upload-file"></span>
                <span><?= te('media.upload_file') ?></span>
            </a>
        </div>
    </header>

    <?php if (isset($_GET['upload']) && ($_GET['upload'] === 'error' || $_GET['upload'] === 'success')): ?>
        <div id="upload-status" class="status-banner status-<?= $_GET['upload'] === 'error' ? 'error' : 'success' ?>">
            <span class="iconify-inline" data-icon="<?= $_GET['upload'] === 'error' ? 'ic:round-error' : 'ic:round-check-circle' ?>"></span>
            <span><?= te($_GET['upload'] === 'error' ? 'media.upload.failed' : 'media.upload.success') ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['edit']) && ($_GET['edit'] === 'error' || $_GET['edit'] === 'success')): ?>
        <div id="edit-status" class="status-banner status-<?= $_GET['edit'] === 'error' ? 'error' : 'success' ?>">
            <span class="iconify-inline" data-icon="<?= $_GET['edit'] === 'error' ? 'ic:round-error' : 'ic:round-check-circle' ?>"></span>
            <span><?= te($_GET['edit'] === 'error' ? 'media.edit.failed' : 'media.edit.success') ?></span>
        </div>
    <?php endif; ?>

    <div class="media-toolbar">
        <div id="media-tools" class="layout-toggle">
            <a href="?<?= http_build_query(editQuery(['layout'],['grid'])) ?>" class="<?= $displayLayout === 'grid' ? 'active' : '' ?>" title="<?= te('media.layout.grid') ?>">
                <span class="iconify-inline" data-icon="ic:round-grid-view"></span>
                <span><?= te('media.layout.grid') ?></span>
            </a>
            <a href="?<?= http_build_query(editQuery(['layout'],['list'])) ?>" class="<?= $displayLayout === 'list' ? 'active' : '' ?>" title="<?= te('media.layout.list') ?>">
                <span class="iconify-inline" data-icon="ic:round-list"></span>
                <span><?= te('media.layout.list') ?></span>
            </a>
        </div>
        <div class="media-toolbar-right">
            <a href="?<?= http_build_query(editQuery(['img'],[$oppositeDisplayImage])) ?>" class="tool" title="<?= te($displayImage === 'true' ? 'media.thumbs.hide' : 'media.thumbs.show') ?>">
                <span class="iconify-inline" data-icon="<?= $displayImage === 'true' ? 'ic:round-visibility-off' : 'ic:round-visibility' ?>"></span>
                <span><?= te($displayImage === 'true' ? 'media.thumbs.hide' : 'media.thumbs.show') ?></span>
            </a>
        </div>
    </div>

    <div id="media-container" class="<?= htmlspecialchars($displayLayout) ?>">
        <?php if (count($medias) > 0): ?>
            <?php foreach ($medias as $media): ?>
                <?php
                $filename = $media['filename'];
                $name = esc(isset($media['name']) ? $media['name'] : t('media.no_name'));
                $description = esc(isset($media['description']) ? $media['description'] : t('media.no_description'));
                $author = esc(isset($media['author']) ? $media['author'] : t('media.no_author'));

                $filePath = 'media/uploads/' . esc($media['filename']);
                $thumbPath = 'media/uploads/thumbnails/' . esc($media['filename']);
                $fileExtension = strtolower(pathinfo($media['filename'], PATHINFO_EXTENSION));

                $isImage = in_array($fileExtension, $imageFormats);
                $isVideo = in_array($fileExtension, $videoFormats);
                $isAudio = in_array($fileExtension, $audioFormats);
                $showThumbnail = $isImage && $displayImage === 'true';

                // Monochrome iconify icon per type
                if ($isImage) {
                    $monoIcon = 'ic:round-image';
                } elseif ($isVideo) {
                    $monoIcon = 'ic:round-movie';
                } elseif ($isAudio) {
                    $monoIcon = 'ic:round-music-note';
                } else {
                    $monoIcon = 'ic:round-description';
                }
                ?>

                <div class="media-item<?= $showThumbnail ? '' : ' media-item-icon' ?>">
                    <div class="img-container" id="<?= (int)$media['id'] ?>">
                        <div class="file-name"><abbr title="<?= $name ?>"><?= $name ?></abbr></div>
                        <div
                            class="file-info"
                            data-id="<?= (int)$media['id'] ?>"
                            data-description="<?= $description ?>"
                            data-name="<?= $name ?>"
                            data-author="<?= $author ?>"
                            data-url="<?= $filePath ?>"
                            data-fileurl="<?= $filePath ?>"
                        >
                            <?= $fileExtension ?>
                        </div>

                        <?php if ($showThumbnail): ?>
                        <img
                            loading="lazy"
                            class="thumbnail"
                            src="<?= $thumbPath ?>"
                            data-src="<?= $filePath ?>"
                            alt="thumbnail">
                        <?php else: ?>
                        <div class="thumbnail thumbnail-icon" data-loaded="true">
                            <span class="iconify-inline" data-icon="<?= $monoIcon ?>"></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="content-container">
                        <p><?= $name ?></p>
                        <div class="media-actions">
                            <a href="javascript:void(0);" class="positive-hover edit-media-btn"
                               data-id="<?= (int)$media['id'] ?>"
                               data-name="<?= $name ?>"
                               data-author="<?= $author ?>"
                               data-description="<?= $description ?>"
                               title="<?= te('media.row.edit') ?>">
                                <span class="iconify-inline" data-icon="ic:round-edit"></span>
                            </a>
                            <a href="javascript:void(0);" class="negative-hover" onclick="deletePrompt(<?= (int)$media['id'] ?>, 'media')" title="<?= te('media.row.delete') ?>">
                                <span class="iconify-inline" data-icon="ic:round-delete"></span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p><?= te('media.empty') ?></p>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . "/includes/pagination.php";?>
</main>
<!-- Modals -->
<!-- Image Modal -->
<div id="display-modal">
    <div id="display-modal-content">
        <span class="close btn-red" data-modal="display-modal">
            <span class="iconify-inline" data-icon="ic:round-close" style="cursor: pointer;"></span>
        </span>
        <div id="modal-image-container">
            <a href="" target="_blank">
                <img id="modal-image" src="" alt="image">   
            </a>
        </div>
        <div id="modal-content-container">
            <h1></h1>
            <h2></h2>
            <p></p>
            <br>
            <div class="edit-buttons">
                <button class="btn-red delete-btn" data-id="">
                    <span class="iconify-inline" data-icon="ic:round-delete" style="cursor: pointer;"></span>
                    <span> <?= te('media.modal.delete_file') ?></span>
                </button>
                <button class="btn-yellow edit-btn" data-id="">
                    <span class="iconify-inline" data-icon="ic:round-edit" style="cursor: pointer;"></span>
                    <span> <?= te('media.modal.edit_file') ?></span>
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Upload Modal -->
<div id="upload-modal" class="modal">
    <div class="modal-content">
        <span class="close btn-red" data-modal="upload-modal">
            <span class="iconify-inline" data-icon="ic:round-close" style="cursor: pointer;"></span>
        </span>
        <h2 style="margin-top: 40px;"><?= te('media.modal.upload_title') ?></h2>
        <form action="./ajax/uploadMedia.php" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="ad">
            <input type="hidden" name="type" value="media">
            <input type="file" id="fileInput" name="file" required onchange="validateFile(this.files[0]);displayImage()">
            <img id="previewImage" src="#" alt="Preview" style="display: none; max-width: 100%; height: 150px; object-fit: cover;">
            <br>
            <label for="name"><?= te('media.modal.name') ?></label>
            <br>
            <input list="filenameList" type="text" id="name" name="name" required>
            <br>
            <label for="author"><?= te('media.modal.author') ?></label>
            <br>
            <input type="text" name="author" id="author" required>
            <br>
            <label for="description"><?= te('media.modal.description') ?></label>
            <br>
            <input type="text" name="description" id="description" placeholder="<?= te('media.modal.description_ph') ?>" style="margin-bottom: 20px">
            <br><br>
            <div class="edit-buttons">
                <input type="submit" class="btn-green" value="<?= te('media.modal.upload_btn') ?>">
            </div>
            <datalist id="filenameList"></datalist>
        </form>
    </div>
</div>
<!-- Edit Modal -->
<div id="edit-modal" class="modal">
    <div id="edit-modal-content" class="modal-content">
        <span class="close btn-red" data-modal="edit-modal">
            <span class="iconify-inline" data-icon="ic:round-close" style="cursor: pointer;"></span>
        </span>
        <h2 style="margin-top: 40px;"><?= te('media.modal.edit_file') ?></h2>
        <form id="edit-form" action="./ajax/editMedia.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit-file-id" value="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="type" value="media">
            <label for="edit-name"><?= te('media.modal.name') ?></label>
            <br>
            <input type="text" name="edit-name" id="edit-name" required>
            <br>
            <label for="edit-author"><?= te('media.modal.author') ?></label>
            <br>
            <input type="text" name="edit-author" id="edit-author" required>
            <br>
            <label for="edit-description"><?= te('media.modal.description') ?></label>
            <input type="hidden" name="img" value="<?= esc($displayImage) ?>">
            <input type="hidden" name="layout" value="<?= esc($displayLayout) ?>">
            <br>
            <input type="text" name="edit-description" id="edit-description" placeholder="<?= te('media.modal.description_ph') ?>">
            <br><br>
            <div class="edit-buttons">
                <button type="submit" class="btn-green">
                    <span class="iconify-inline" data-icon="ic:round-save"></span>
                    <span><?= te('media.modal.save') ?></span>
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    function displayImage() {
        var input = document.getElementById('fileInput');
        var preview = document.getElementById('previewImage');
        var datalist = document.getElementById('filenameList');

        if (input.files && input.files[0]) {
            var file = input.files[0];
            var allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'ico', 'bmp', 'webp'];
            var audioFormats = ['mp3', 'wav', 'ogg', 'flac', 'aac', 'wma'];
            var videoFormats = ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm'];
            var fileExtension = file.name.split('.').pop().toLowerCase();

            if (allowedExtensions.includes(fileExtension)) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }

                reader.readAsDataURL(file);
            } else if (audioFormats.includes(fileExtension)) {
                preview.src = "./media/img/audio.webp";
                preview.style.display = 'block';
            } else if (videoFormats.includes(fileExtension)) {
                preview.src = "./media/img/video.webp";
                preview.style.display = 'block';
            } else {
                preview.src = "./media/img/document.webp";
                preview.style.display = 'block';
            }

            // Update datalist with filenames (without file type suffix)
            var filenamesWithoutSuffix = Array.from(input.files).map(file => file.name.replace(/\.[^/.]+$/, ''));
            datalist.innerHTML = ''; // Clear existing options

            filenamesWithoutSuffix.forEach(function (filename) {
                var option = document.createElement('option');
                option.value = filename;
                datalist.appendChild(option);
            });
        }
    };

    // Lazy-load images
    function lazyLoadImages() {
        const images = document.querySelectorAll('img[data-src]:not([data-loaded])');

        const totalImages = images.length;
        let loadedImages = 0;
        images.forEach(img => {
            const rect = img.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom >= 0) {
                const fullResURL = img.getAttribute('data-src');
                const fullResImage = new Image();
                fullResImage.src = fullResURL;

                // Once the full-resolution image is loaded, replace the blurred thumbnail
                fullResImage.onload = function () {
                    img.src = fullResURL;
                    img.style.filter = "blur(0px)";
                    img.setAttribute('data-loaded', true); // Mark the image as loaded
                };              
            }
        });
    }

    // Attach lazy-load function to scroll and resize events
    window.addEventListener('scroll', lazyLoadImages);
    window.addEventListener('resize', lazyLoadImages);

    // Initial lazy load on page load
    window.addEventListener('DOMContentLoaded', lazyLoadImages);
</script>
<script>
    // MODALS! 
    // Function to open the edit or upload modal
    function openModal(modal) {
        const Modal = document.getElementById(modal);
        Modal.style.display = 'block';
    }

    function closeModal(modal) {
        const Modal = document.getElementById(modal);
        Modal.style.display = 'none';
    }

    const closeButtons = document.querySelectorAll('.close');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const modalId = this.getAttribute('data-modal');
            closeModal(modalId);
        });
    });

    window.addEventListener('click', function (event) {
        closeButtons.forEach(btn => {
            const modalId = btn.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal && event.target === modal) {
                closeModal(modalId);
            }
        });
    });

    // Attach click event to the display buttons
    const displayButtons = document.querySelectorAll('.display-btn');
    displayButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            var img = this.parentNode.parentNode.parentNode.querySelector('.img-container');
            var id = img.querySelector('.file-info').dataset.id;
            var description = img.querySelector('.file-info').dataset.description;
            var name = img.querySelector('.file-info').dataset.name;
            var URL = img.querySelector('.file-info').dataset.url;
            var author = img.querySelector('.file-info').dataset.author;
            openImageModal(name, author, description, URL, id);
        });
    });

    // Attach click event to the upload button
    const uploadBtn = document.getElementById('upload-btn');
    uploadBtn.addEventListener('click', function () {
        openModal('upload-modal');
    });

    // Attach click event to the image container
    function openImageModal(name, author, description, URL, fileURL, id) {
        const modal = document.getElementById('display-modal');
        const img = document.getElementById('modal-image');
        const imgName = modal.querySelector('h1');
        const imgAuthor = modal.querySelector('h2');
        const imgDescription = modal.querySelector('p');
        const imgURL = modal.querySelector('a');

        modal.querySelectorAll('[data-id]').forEach(el => {
            el.dataset.id = id;
        });
        img.src = URL;
        imgName.textContent = name;
        imgAuthor.textContent = author;
        imgDescription.textContent = description;
        imgName.setAttribute('data-id', id);
        imgURL.setAttribute('href', fileURL);

        openModal('display-modal');
    }

    // Attach click event to the image container
    const imageContainers = document.querySelectorAll('.img-container');
    imageContainers.forEach(container => {
        container.addEventListener('click', function () {
            var id = this.querySelector('.file-info').dataset.id;
            var description = this.querySelector('.file-info').dataset.description;
            var name = this.querySelector('.file-info').dataset.name;
            var URL = this.querySelector('.file-info').dataset.url;
            var fileURL = this.querySelector('.file-info').dataset.fileurl;
            var author = this.querySelector('.file-info').dataset.author;
            openImageModal(name, author, description, URL, fileURL, id);
        });
    });

    // Inline edit button on each media card
    document.querySelectorAll('.edit-media-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const id = this.dataset.id;
            const name = this.dataset.name;
            const author = this.dataset.author;
            const description = this.dataset.description;
            openEditModal(id, name, author, description);
        });
    });

    // Stop delete-button click from triggering the card open
    document.querySelectorAll('.media-actions .negative-hover').forEach(btn => {
        btn.addEventListener('click', function (e) { e.stopPropagation(); });
    });


    const editForm = document.getElementById('edit-form');
    const editModal = document.getElementById('edit-modal');

    const openEditModal = (id, name, author, description) => {
        document.getElementById('edit-file-id').value = id;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-author').value = author;
        document.getElementById('edit-description').value = description;
        openModal('edit-modal');
    };

    // Attach click event to the edit buttons
    const editButtons = document.querySelectorAll('.edit-btn');
    const modal = document.getElementById('display-modal');
    editButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const imgName = modal.querySelector('h1').textContent;
            const imgAuthor = modal.querySelector('h2').textContent;
            const imgDescription = modal.querySelector('p').textContent;
            const imgURL = modal.querySelector('a').textContent;
            const imgID = modal.querySelector('h1').getAttribute('data-id');
    
            openEditModal(imgID, imgName, imgAuthor, imgDescription);
        });
    });
</script>
    <?php include __DIR__ . "/includes/footer.php";?>
</body>
</html>