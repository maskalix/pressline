<?php
require_once dirname(__DIR__) . '/pl-load.php';
require_once dirname(__DIR__) . '/includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

function getUniqueFilename($filename, $uploadDir){
    $counter = 1;
    $originalFilename = $filename;

    while (file_exists($uploadDir . $filename)) {
        $filename = pathinfo($originalFilename, PATHINFO_FILENAME) . '_' . $counter . '.' . pathinfo($originalFilename, PATHINFO_EXTENSION);
        $counter++;
    }

    return $filename;
}

function createThumbnail($sourcePath, $thumbnailPath, $imageFormats) {
    $fileExtension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

    if (!in_array($fileExtension, $imageFormats)) {
        return false;
    }

    if ($fileExtension === 'jpg') {
        $createImage = 'imagecreatefromjpeg';
    } else {
        $createImage = 'imagecreatefrom' . $fileExtension;
    }

    if (!function_exists($createImage)) {
        return false;
    }

    $sourceImage = $createImage($sourcePath);

    if (!$sourceImage) {
        return false;
    }

    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);

    $thumbnailWidth = 50;
    $thumbnailHeight = intval($thumbnailWidth * ($sourceHeight / $sourceWidth));

    $thumbnailImage = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);

    imagecopyresampled($thumbnailImage, $sourceImage, 0, 0, 0, 0, $thumbnailWidth, $thumbnailHeight, $sourceWidth, $sourceHeight);

    if ($fileExtension === 'jpg') {
        $saveImage = 'imagejpeg';
    } else {
        $saveImage = 'image' . $fileExtension;
    }
    $saveImage($thumbnailImage, $thumbnailPath);

    imagedestroy($sourceImage);
    imagedestroy($thumbnailImage);

    return true;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    ini_set('upload_max_filesize', '20M');
    ini_set('post_max_size', '20M');

    $originalFilename = $_FILES['file']['name'];
    $temp_filename = $_FILES['file']['tmp_name'];

    if (!validateUploadedFile($temp_filename, $originalFilename)) {
        $query['state'] = 'negative';
        $query['message'] = 'Nepodporovaný typ souboru';
        header("Location: ../media.php?" . http_build_query($query));
        exit;
    }

    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $author = isset($_POST['author']) ? $_POST['author'] : '';
    $filename = getUniqueFilename($originalFilename, PL_ROOT . '/media/uploads/');
    $target_dir = PL_ROOT . "/media/uploads/";
    $target_path = $target_dir . $filename;
    $thumbnail_dir = PL_ROOT . "/media/uploads/thumbnails/";
    $thumbnail_path = $thumbnail_dir . $filename;

    $query = [];
    if (move_uploaded_file($temp_filename, $target_path)) {
        createThumbnail($target_path, $thumbnail_path, $imageFormats);
        $media = $dbSetter->media([
            'action' => 'add',
            'filename' => $filename,
            'description' => $description,
            'name' => $name,
            'author' => $author
        ]);

        if ($media) {
            if (function_exists('do_action')) {
                do_action('media.afterUpload', [
                    'filename' => $filename,
                    'name' => $name,
                    'author' => $author,
                    'description' => $description,
                    'path' => $target_path,
                ]);
            }
            $query['state'] = 'positive';
            $query['message'] = 'Soubor byl úspěšně nahrán';
            header("Location: ../media.php?" . http_build_query($query));
        } else {
            $query['state'] = 'negative';
            $query['message'] = 'Soubor se nepodařilo nahrát';
            header("Location: ../media.php?" . http_build_query($query));
        }
    } else {
        $query['state'] = 'negative';
        $query['message'] = 'Soubor se nepodařilo nahrát';
        header("Location: ../media.php?" . http_build_query($query));
    }
}
