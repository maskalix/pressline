<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

define('PL_ROOT', __DIR__);

require_once PL_ROOT . '/pl-config.php';
require_once PL_ROOT . '/includes/security.php';
require_once PL_ROOT . '/includes/csrf.php';

configureSession();

require_once PL_ROOT . '/includes/hooks.php';
require_once PL_ROOT . '/includes/fetcher.php';
require_once PL_ROOT . '/includes/setter.php';
require_once PL_ROOT . '/includes/plugins.php';
require_once PL_ROOT . '/includes/marketplace.php';

$connection = mysqli_connect($db_server, $db_user, $db_password, $db_name);
if (!$connection || $connection->connect_error) {
    http_response_code(503);
    exit('Database connection failed.');
}

$dbFetcher = new DatabaseFetcher($connection);
$dbSetter = new DatabaseSetter($connection);

// Ensure activity log table exists (idempotent — runs CREATE TABLE IF NOT EXISTS)
require_once PL_ROOT . '/includes/activity_log_init.php';

// Ensure preferences.language column exists
require_once PL_ROOT . '/includes/preferences_migrate.php';

// Translation strings + t() helper (must come before $GLOBALS lookup below)
require_once PL_ROOT . '/includes/lang.php';

// URL helpers (pl_url, hide_php_ext setting)
require_once PL_ROOT . '/includes/urls.php';

// Self-update support (pl_updates_status, pl_updates_apply)
require_once PL_ROOT . '/includes/updates.php';

// Discover plugins, load active ones (registers their hooks)
PL_Plugins::init($connection);
PL_Marketplace::init($connection);

$websetsArray = $dbFetcher->allWebsets(1, $dbFetcher->countWebsets());
$websets = [];
foreach ($websetsArray as $webset) {
    $websets[$webset['key']] = $webset['value'];
}

$from = $websets['from'] ?? "your@mail.address";
$smtp_host = $websets['smtp_host'] ?? "smtp.mail.address";
$smtp_port = $websets['smtp_port'] ?? 465;
$smtp_user = $websets['smtp_user'] ?? $from;
$smtp_pass = $websets['smtp_pass'] ?? "";
$smtp_sec = $websets['smtp_sec'] ?? "ssl";

$url = $websets['url'] ?? "https://admin.pressline.app";
$frontend = $websets['frontend'] ?? "https://pressline.app";
$name = $websets['name'] ?? "PressLine";
$description = $websets['description'] ?? "PressLine je revoluční CMS pro snadné a rychlé publikování obsahu. Vytvořte si vlastní blog nebo webové stránky během několika minut.";
$keywords = $websets['keywords'] ?? "CMS, content creation, blog";
$icon = $websets['icon'] ?? "favicon.ico";
$icon_full = $websets['icon_full'] ?? "media/img/logo-full.png";
$icon_text_light = $websets['icon_text_light'] ?? "";
$icon_text_dark = $websets['icon_text_dark'] ?? "";
$default_user_icon = $websets['default_user_icon'] ?? 'https://gravatar.com/avatar/9f670c649d9280251fc38f52d5d607fe?s=200&d=mp&r=g';
$default_article_img = $websets['default_article_img'] ?? 'https://placehold.co/600x400/EEE/31343C?text=PressLine&font=raleway';

$admin_level = $websets['admin_level'] ?? 2;
$dev_level = $websets['dev_level'] ?? 3;

$language = $websets['language'] ?? "cs";

$colorOptions = [
    'gray'   => 'šedá',
    'orange' => 'oranžová',
    'green'  => 'zelená',
    'blue'   => 'modrá',
    'red'    => 'červená',
    'lila'   => 'fialová',
];
$colors = array_keys($colorOptions);

$themeOptions = [
    'light'  => 'světlý',
    'dark'   => 'tmavý',
    'amoled' => 'AMOLED',
];

$imageFormats = ['png', 'jpg', 'jpeg', 'gif', 'ico', 'bmp', 'webp'];
$audioFormats = ['mp3', 'wav', 'ogg', 'flac', 'aac', 'wma'];
$videoFormats = ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm'];

$since = "2023-10-01 00:00:00";

// Active UI language for this request. session.php overrides this once the
// user is authenticated; here we cover pre-auth pages (login, password reset).
$GLOBALS['__pl_active_lang'] = pl_resolve_lang();
