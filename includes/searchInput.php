<?php if ($search): ?>
    <a href="<?= $thisURL?>" class="tool btn-yellow"><span class="iconify-inline" data-icon="ic:round-search-off"></span><span> Vymazat vyhledavani</span></a>
<?php endif; ?>
<div id="searchInputContainer">
    <input type="text" id="searchInput" name="search" placeholder="hledat">
    <span id="searchIcon" onclick="search()">
        <span class="iconify-inline" data-icon="ic:round-search"></span>
    </span>
</div>