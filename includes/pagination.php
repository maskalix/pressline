<div class="tools-list" id="pagination">
    <?php
    if ($page > 2) pageLink(1, '&lt;&lt;');
    if ($page > 1) pageLink($page - 1, '&lt;');
    
    for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++) {
        pageLink($i, $i, $i == $page);
    }
    
    if ($page < $totalPages) pageLink($page + 1, '&gt;');
    if ($page < $totalPages - 1) pageLink($totalPages, '&gt;&gt;');
    ?>
    <select id="perPageSelect" onchange="changePerPage()">
        <option value="10" <?= ($recordsPerPage == 10) ? 'selected' : ''; ?>>10</option>
        <option value="25" <?= ($recordsPerPage == 25) ? 'selected' : ''; ?>>25</option>
        <option value="50" <?= ($recordsPerPage == 50) ? 'selected' : ''; ?>>50</option>
        <option value="100" <?= ($recordsPerPage == 100) ? 'selected' : ''; ?>>100</option>
    </select>
</div>