<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

$currentFile = basename($_SERVER['PHP_SELF']);

$sessionId = $_SESSION['user_id'];

$preferences = $dbFetcher->preference('user_id', $sessionId);

$mode = isset($preferences['mode']) && in_array($preferences['mode'], array_keys($themeOptions)) ? $preferences['mode'] : 'dark';
$color = isset($preferences['color']) && in_array($preferences['color'], $colors) ? $preferences['color'] : 'def';
$quotedColors = array_map(function($color) { return "\"$color\"";}, $colors);
$stringifiedColors = implode(', ', $quotedColors);

echo "<script>";
echo "var mode = " . json_encode($mode) . ";";
echo "var color = " . json_encode($color) . ";";
echo "var colors = " . json_encode($stringifiedColors) . ";";
echo "</script>";
?>

<!-- Metatags -->
<meta charset="UTF-8">
<meta http-equiv="Content-language" content="<?= esc($GLOBALS['__pl_active_lang']);?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= te('meta.description') ?>">
<meta name="keywords" content="<?= esc($keywords);?>">
<meta name="author" content="PressLine by Martin Skalicky">
<?= csrf_meta() ?>

<meta property="og:site_name" content="<?= esc($name);?>">
<meta property="og:title" content="<?= esc($name);?>">
<meta property="og:type" content="<?= esc($name);?>p">
<meta property="og:url" content="<?= esc($url);?>">
<meta property="og:image" content="<?= esc($url);?>/<?= esc($icon_full);?>">

<meta name="twitter:title" content="<?= esc($name);?>">
<meta name="twitter:description" content="<?= esc($description);?>">
<meta name="twitter:image" content="<?= esc($url);?>/<?= esc($icon_full);?>">
<meta name="twitter:card" content="summary_large_image">

<link rel="image_src" href="<?= esc($url);?>/<?= esc($icon_full);?>">
<link rel="icon" type="image/x-icon" href="<?= esc($url); ?>/favicon.ico" style="scale:0.8">
<link rel="canonical" href="<?= esc($url); ?>">
<meta name="robots" content="index, follow">

<!-- CSSs -->
<link rel="stylesheet" href="/res/css/style.css">
<link rel="stylesheet" href="/res/css/media.css">

<!-- Scripts -->
<script src="/res/js/mode.js"></script>
<script src="/res/js/script.js"></script>
<script src="/res/js/iconify.min.js"></script>
<script src="/res/js/aside.js" defer></script>

<!-- Title -->
<title><?= esc($sitename);?> | <?= esc($name);?></title>