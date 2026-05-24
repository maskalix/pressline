<?php
// Define default sorting
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : $defaultSortColumn;
$sortOrder = isset($_GET['order']) ? $_GET['order'] : $defaultSortOrder;
$oppositeOrder = ($sortOrder == 'ASC') ? 'DESC' : 'ASC';

// Pagination settings
$recordsPerPage = isset($_GET['per_page']) ? $_GET['per_page'] : 10;
$page = isset($_GET['p']) ? $_GET['p'] : 1;

// Check for search query
$searchBy = isset($_GET['search_by']) ? $_GET['search_by'] : $defaultSearchBy;
$search = isset($_GET['search']) ? $_GET['search'] : null;

// Start from $_GET so URL params like ?id=N (used by uzivatel.php) are preserved across pagination/sort links.
$query = array_merge($_GET, [
    'order' => $sortOrder,
    'per_page' => $recordsPerPage,
    'p' => $page,
    'sort' => $sortColumn,
    'search' => $search,
    'search_by' => $searchBy
]);
?>