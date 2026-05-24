<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    exit;
}
include PL_ROOT . "/version.php"; ?>
<footer class="row">
    <span class="footer-segment">
        <a href="/about.php">PressLine<sup style="font-size: 60%;">v<?= esc($version) ?></sup></a>
        <span class="iconify-inline" data-icon="ic:round-favorite" style="color: var(--accent); width: 12px; height: 12px;"></span>
        <span><?= date("Y") ?></span>
    </span>
    <span class="footer-sep">·</span>
    <span class="footer-segment">
        cooked by
        <a href="https://lnln.eu" target="_blank" rel="noopener" class="footer-brand">LNLN.eu</a>
    </span>
</footer>