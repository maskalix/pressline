<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

$currentFile = basename($_SERVER['PHP_SELF']);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$type = isset($_GET['type']) ? $_GET['type'] : NULL;
$message = isset($_GET['message']) ? $_GET['message'] : NULL;

if ($type && $message) {
    echo "<script>showAlert(" . json_encode($type) . "," . json_encode($message) . ")</script>";
}

$id = $_SESSION['user_id'];
$level = $_SESSION['user_level'];
$user = $dbFetcher->user('id', $id);
?>

<nav>
    <button id="collapseButton" type="button" aria-label="<?= te('nav.toggle_menu') ?>" title="<?= te('nav.toggle_menu') ?>">
        <span class="iconify-inline icon-menu" data-icon="mdi:menu"></span>
        <span class="iconify-inline icon-menu-open" data-icon="mdi:menu-open"></span>
    </button>
    <a href="<?= pl_url('/index.php') ?>" class="logo">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 375 374.999991" width="28" height="28" preserveAspectRatio="xMidYMid meet">
            <path fill="var(--text-primary)" d="M 363.316406 292.121094 C 363.324219 293.40625 363.519531 317.816406 363.507812 330.214844 C 363.503906 334.757812 360.949219 336.199219 356.402344 336.199219 L 192.28125 336.238281 C 190.09375 336.238281 187.996094 335.367188 186.453125 333.824219 C 184.90625 332.277344 184.039062 330.179688 184.039062 327.996094 L 184.15625 126.699219 L 232.617188 134.265625 L 232.628906 292.035156 C 232.628906 292.035156 313.894531 292.09375 363.316406 292.121094 Z M 363.316406 292.121094 " fill-opacity="1" fill-rule="evenodd"/>
            <path stroke-linecap="round" transform="matrix(0.817312, 0, 0, 0.907278, -30.561292, -6.42769)" fill="none" stroke-linejoin="round" d="M 92.723417 239.123147 C 93.516796 236.746532 142.596325 239.476195 186.083971 236.836947 C 210.243774 235.368784 227.664641 234.240753 246.046165 225.780521 C 303.231536 199.465538 290.71432 147.481392 291.674977 146.10795 " stroke="var(--accent)" stroke-width="56.439999" stroke-opacity="1" stroke-miterlimit="1.5"/>
            <path fill="var(--text-primary)" d="M 62.726562 84.292969 L 62.648438 332.820312 C 62.648438 334.207031 61.765625 335.441406 60.453125 335.890625 C 59.140625 336.339844 57.6875 335.902344 56.835938 334.808594 C 49.769531 325.6875 38.773438 311.496094 38.765625 311.5 C 38.671875 311.621094 28.28125 325.578125 21.46875 334.734375 C 20.628906 335.851562 19.171875 336.304688 17.847656 335.867188 C 16.523438 335.425781 15.632812 334.191406 15.625 332.796875 C 15.496094 285.976562 14.949219 84.902344 14.835938 45.046875 C 14.832031 43.222656 15.554688 41.472656 16.839844 40.179688 C 18.128906 38.890625 19.875 38.164062 21.699219 38.164062 L 141.230469 38.164062 C 157.78125 38.164062 173.007812 42.390625 186.898438 50.824219 C 200.800781 59.246094 211.898438 70.636719 220.175781 84.976562 C 228.445312 99.304688 232.589844 115.496094 232.589844 133.542969 C 232.589844 134.128906 232.746094 138.164062 232.589844 142.675781 C 232.378906 148.644531 231.785156 155.449219 231.210938 156.222656 C 222.980469 167.296875 184.410156 163.355469 170.90625 169.921875 C 170.785156 169.980469 170.613281 169.960938 170.464844 169.902344 C 170.265625 169.820312 170.117188 169.664062 170.222656 169.542969 C 173.335938 165.960938 178.066406 160.015625 180.203125 155.242188 C 184.511719 145.621094 183.855469 134.605469 183.808594 133.542969 C 183.71875 131.585938 183.40625 127.636719 183.296875 126.355469 C 182.300781 114.820312 178.367188 106.683594 170.929688 98.484375 C 162.355469 89.023438 152.15625 84.292969 140.328125 84.292969 Z M 62.726562 84.292969 " fill-opacity="1" fill-rule="evenodd"/>
        </svg>
        <span class="logo-name"><?= esc($name) ?></span>
    </a>
    <div class="nav-actions">
        <a href="<?= pl_url('/uzivatel.php') ?>?id=<?= (int)$id ?>" class="nav-user-link" title="<?= te('nav.profile') ?>">
            <img src="<?= icon('user', $user) ?>" alt="">
        </a>
        <a href="<?= pl_url('/nastaveni.php') ?>" class="nav-action" title="<?= te('nav.settings') ?>">
            <span class="iconify-inline" data-icon="ic:baseline-settings"></span>
        </a>
        <a href="<?= pl_url('/logout.php') ?>" class="nav-action" title="<?= te('nav.logout') ?>">
            <span class="iconify-inline" data-icon="ic:round-logout"></span>
        </a>
    </div>
</nav>
<aside>
    <div id="collapsibleDiv">
        <ul id="menu-list">
            <li>
                <a href="<?= pl_url('/index.php') ?>">
                    <span class="iconify-inline" data-icon="ic:round-dashboard"></span>
                    <span><?= te('nav.dashboard') ?></span>
                </a>
            </li>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                    <span class="iconify-inline" data-icon="ic:baseline-article"></span>
                    <span><?= te('nav.articles') ?></span>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a href="<?= pl_url('/clanky.php') ?>">
                            <span class="iconify-inline" data-icon="ic:round-format-list-bulleted"></span>
                            <span><?= te('nav.articles_list') ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= pl_url('/edit.php') ?>">
                            <span class="iconify-inline" data-icon="ic:round-add"></span>
                            <span><?= te('nav.article_add') ?></span>
                        </a>
                    </li>
                </ul>
            </li>
            <li>
                <a href="<?= pl_url('/rubriky.php') ?>">
                    <span class="iconify-inline" data-icon="ic:round-category"></span>
                    <span><?= te('nav.categories') ?></span>
                </a>
            </li>
            <li>
                <a href="<?= pl_url('/media.php') ?>">
                    <span class="iconify-inline" data-icon="ic:round-perm-media"></span>
                    <span><?= te('nav.media') ?></span>
                </a>
            </li>

            <?php if ($level >= $admin_level): ?>
            <hr>
            <li>
                <a href="<?= pl_url('/uzivatele.php') ?>">
                    <span class='iconify-inline' data-icon='ic:round-people'></span>
                    <span><?= te('nav.users') ?></span>
                </a>
            </li>
            <li>
                <a href="<?= pl_url('/logs.php') ?>">
                    <span class='iconify-inline' data-icon='ic:round-history'></span>
                    <span><?= te('nav.activity') ?></span>
                </a>
            </li>
            <li>
                <a href="<?= pl_url('/rozsireni.php') ?>">
                    <span class='iconify-inline' data-icon='ic:round-extension'></span>
                    <span><?= te('nav.extensions') ?></span>
                </a>
            </li>
            <li>
                <a href="<?= pl_url('/zdravi.php') ?>">
                    <span class='iconify-inline' data-icon='ic:round-monitor-heart'></span>
                    <span><?= te('nav.health') ?></span>
                </a>
            </li>
            <?php
            // Pull update status without forcing a refresh — dashboard.php
            // (or this sidebar render) takes care of the hourly refresh.
            $updStatus = function_exists('pl_updates_status') ? pl_updates_status() : ['has_update' => false];
            ?>
            <li>
                <a href="<?= pl_url('/aktualizace.php') ?>">
                    <span class='iconify-inline' data-icon='ic:round-system-update'></span>
                    <span><?= te('nav.updates') ?></span>
                    <?php if (!empty($updStatus['has_update'])): ?>
                        <span style="margin-left:auto; width:8px; height:8px; border-radius:50%; background:#fbbf24; display:inline-block;" aria-hidden="true"></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <?php
            // Plugin-contributed menu items
            $pluginMenuItems = function_exists('apply_filters') ? apply_filters('admin.menu', []) : [];
            if (!empty($pluginMenuItems)):
                $shownAny = false;
                foreach ($pluginMenuItems as $item):
                    $itemMin = $item['min_level'] ?? 0;
                    if ($level < $itemMin) continue;
                    if (!$shownAny) { echo "<hr>"; $shownAny = true; }
            ?>
                <li>
                    <a href="<?= esc($item['href'] ?? '#') ?>">
                        <span class="iconify-inline" data-icon="<?= esc($item['icon'] ?? 'ic:round-extension') ?>"></span>
                        <span><?= esc($item['label'] ?? t('nav.plugin_default')) ?></span>
                    </a>
                </li>
            <?php endforeach; endif; ?>
        </ul>
    </div>
</aside>
