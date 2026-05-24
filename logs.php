<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

if ($_SESSION['user_level'] < $admin_level) {
    header("Location: chyba.php?err=403&msg=" . rawurlencode(t('error.no_permission_view')));
    exit();
}

// Detect which columns the activity_log table actually has (older installs may have a different shape).
$logColumns = [];
if ($res = $connection->query('SHOW COLUMNS FROM activity_log')) {
    while ($r = $res->fetch_assoc()) $logColumns[$r['Field']] = true;
}
$hasType = isset($logColumns['type']);
$hasTargetId = isset($logColumns['target_id']);
$hasUsername = isset($logColumns['username']);
$hasUserId = isset($logColumns['user_id']);
$hasIp = isset($logColumns['ip']);
$hasPayload = isset($logColumns['payload']);
$hasCreated = isset($logColumns['created_at']) ? 'created_at'
            : (isset($logColumns['time']) ? 'time'
            : (isset($logColumns['timestamp']) ? 'timestamp' : null));

// Pagination
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$recordsPerPage = isset($_GET['per_page']) ? max(10, min(200, (int)$_GET['per_page'])) : 50;
$offset = ($page - 1) * $recordsPerPage;

// Filters
$filterAction = $_GET['action_filter'] ?? '';
$filterType = $_GET['type_filter'] ?? '';
$filterUser = $_GET['user_filter'] ?? '';

$where = [];
$params = [];
$types = '';
if ($filterAction) {
    $where[] = 'action = ?';
    $params[] = $filterAction;
    $types .= 's';
}
if ($filterType && $hasType) {
    $where[] = 'type = ?';
    $params[] = $filterType;
    $types .= 's';
}
if ($filterUser && $hasUsername) {
    $where[] = 'username LIKE ?';
    $params[] = '%' . $filterUser . '%';
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total count
$countSql = "SELECT COUNT(*) AS c FROM activity_log $whereSql";
$totalRecords = 0;
if ($stmt = $connection->prepare($countSql)) {
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $totalRecords = (int)$row['c'];
}
$totalPages = max(1, (int)ceil($totalRecords / $recordsPerPage));

// Fetch records
$logs = [];
$orderCol = $hasCreated ?: 'id';
$listSql = "SELECT * FROM activity_log $whereSql ORDER BY $orderCol DESC, id DESC LIMIT ? OFFSET ?";
if ($stmt = $connection->prepare($listSql)) {
    $listParams = $params;
    $listTypes = $types . 'ii';
    $listParams[] = $recordsPerPage;
    $listParams[] = $offset;
    $stmt->bind_param($listTypes, ...$listParams);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $logs[] = $row;
}

$sitename = t('logs.title');
$thisURL = basename($_SERVER['PHP_SELF']);

function actionLabel($action) {
    return match (strtolower($action)) {
        'add', 'insert' => [t('logs.action.created'), 'success', 'ic:round-add-circle'],
        'edit', 'update' => [t('logs.action.updated'), 'info', 'ic:round-edit'],
        'delete', 'remove' => [t('logs.action.deleted'), 'error', 'ic:round-delete'],
        default => [$action, 'muted', 'ic:round-bolt'],
    };
}
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php"; ?>
</head>
<body>
    <?php include __DIR__ . "/includes/sidebar.php"; ?>
    <main>
        <header class="page-header">
            <div class="page-header-text">
                <h1><?= $sitename ?></h1>
                <p><?= te('logs.records_n', number_format($totalRecords, 0, ',', ' ')) ?></p>
            </div>
            <div class="page-header-actions">
                <a href="<?= $thisURL ?>" class="tool">
                    <span class="iconify-inline" data-icon="ic:round-refresh"></span>
                    <span><?= te('logs.refresh') ?></span>
                </a>
            </div>
        </header>

        <div class="table-card">
            <div class="table-toolbar">
                <form method="get" action="<?= $thisURL ?>" class="filter-form" style="flex:1;flex-wrap:wrap;">
                    <select name="action_filter" onchange="this.form.submit()" class="filter-select">
                        <option value=""><?= te('logs.filter.all_actions') ?></option>
                        <option value="add" <?= $filterAction === 'add' ? 'selected' : '' ?>><?= te('logs.filter.action.add') ?></option>
                        <option value="edit" <?= $filterAction === 'edit' ? 'selected' : '' ?>><?= te('logs.filter.action.edit') ?></option>
                        <option value="delete" <?= $filterAction === 'delete' ? 'selected' : '' ?>><?= te('logs.filter.action.delete') ?></option>
                    </select>
                    <select name="type_filter" onchange="this.form.submit()" class="filter-select">
                        <option value=""><?= te('logs.filter.all_types') ?></option>
                        <option value="article" <?= $filterType === 'article' ? 'selected' : '' ?>><?= te('logs.filter.type.article') ?></option>
                        <option value="category" <?= $filterType === 'category' ? 'selected' : '' ?>><?= te('logs.filter.type.category') ?></option>
                        <option value="media" <?= $filterType === 'media' ? 'selected' : '' ?>><?= te('logs.filter.type.media') ?></option>
                        <option value="user" <?= $filterType === 'user' ? 'selected' : '' ?>><?= te('logs.filter.type.user') ?></option>
                        <option value="webset" <?= $filterType === 'webset' ? 'selected' : '' ?>><?= te('logs.filter.type.webset') ?></option>
                        <option value="preference" <?= $filterType === 'preference' ? 'selected' : '' ?>><?= te('logs.filter.type.preference') ?></option>
                        <option value="role" <?= $filterType === 'role' ? 'selected' : '' ?>><?= te('logs.filter.type.role') ?></option>
                    </select>
                    <input type="text" name="user_filter" placeholder="<?= te('logs.filter.user_ph') ?>" value="<?= esc($filterUser) ?>"
                           class="filter-select" style="width:auto;min-width:180px;">
                    <?php if ($filterAction || $filterType || $filterUser): ?>
                    <a href="<?= $thisURL ?>" class="tool">
                        <span class="iconify-inline" data-icon="ic:round-search-off"></span>
                        <span><?= te('logs.filter.clear') ?></span>
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!empty($logs)): ?>
            <table id="itemList">
                <thead>
                    <tr>
                        <th><?= te('logs.col.action') ?></th>
                        <th><?= te('logs.col.type') ?></th>
                        <th><?= te('logs.col.target') ?></th>
                        <th><?= te('logs.col.user') ?></th>
                        <th class="no-display"><?= te('logs.col.ip') ?></th>
                        <th><?= te('logs.col.when') ?></th>
                        <th class="no-display"><?= te('logs.col.detail') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        [$actLabel, $actClass, $actIcon] = actionLabel($log['action'] ?? '');
                        $logType = $log['type'] ?? '';
                        $logTargetId = $log['target_id'] ?? null;
                        $logUsername = $log['username'] ?? null;
                        $logUserId = $log['user_id'] ?? 0;
                        $logIp = $log['ip'] ?? '';
                        $logPayload = $log['payload'] ?? ($log['data'] ?? '');
                        $logCreated = $log[$hasCreated ?? 'created_at'] ?? null;
                    ?>
                    <tr>
                        <td>
                            <span class="badge badge-<?= $actClass ?>">
                                <span class="iconify-inline" data-icon="<?= $actIcon ?>"></span>
                                <?= $actLabel ?>
                            </span>
                        </td>
                        <td><?= $logType !== '' ? esc($logType) : '<span class="sub">—</span>' ?></td>
                        <td>
                            <?php if (!empty($logTargetId)): ?>
                                <code style="font-family:ui-monospace,Menlo,monospace;font-size:var(--text-xs);color:var(--text-muted);">#<?= (int)$logTargetId ?></code>
                            <?php else: ?>
                                <span class="sub">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($logUsername)): ?>
                                <a href="uzivatel.php?id=<?= (int)$logUserId ?>"><?= esc($logUsername) ?></a>
                            <?php else: ?>
                                <span class="sub"><?= te('logs.system') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="sub no-display"><?= esc($logIp) ?></td>
                        <td class="sub" title="<?= esc($logCreated ?? '') ?>">
                            <?= $logCreated ? formatDate($logCreated) : '—' ?>
                        </td>
                        <td class="no-display">
                            <?php if (!empty($logPayload) && $logPayload !== '[]' && $logPayload !== '{}'): ?>
                                <a href="javascript:void(0);"
                                   class="log-show-btn"
                                   data-action="<?= esc($actLabel) ?>"
                                   data-type="<?= esc($logType) ?>"
                                   data-target="<?= esc($logTargetId ?? '') ?>"
                                   data-user="<?= esc($logUsername ?? t('logs.system')) ?>"
                                   data-ip="<?= esc($logIp) ?>"
                                   data-when="<?= esc($logCreated ?? '') ?>"
                                   data-payload="<?= esc($logPayload) ?>">
                                    <span class="iconify-inline" data-icon="ic:round-visibility"></span>
                                    <span><?= te('logs.show') ?></span>
                                </a>
                            <?php else: ?>
                                <span class="sub">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <span class="iconify-inline" data-icon="ic:round-history" style="width:48px;height:48px;color:var(--text-disabled);"></span>
                <h3><?= te('logs.empty.title') ?></h3>
                <p><?= te(($filterAction || $filterType || $filterUser) ? 'logs.empty.filtered' : 'logs.empty.none') ?></p>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1):
            $query = array_merge($_GET, ['per_page' => $recordsPerPage, 'p' => $page]);
            include __DIR__ . "/includes/pagination.php";
        endif; ?>
    </main>

    <!-- Log entry detail modal -->
    <div id="log-detail-modal" class="modal">
        <div class="modal-content log-detail-content">
            <header class="plugin-modal-header">
                <h2><?= te('logs.modal.title') ?></h2>
                <span class="close" onclick="closeLogModal()">
                    <span class="iconify-inline" data-icon="ic:round-close"></span>
                </span>
            </header>
            <div class="log-detail-body">
                <dl class="log-detail-list">
                    <dt><?= te('logs.modal.action') ?></dt>
                    <dd id="logd-action">—</dd>
                    <dt><?= te('logs.modal.type') ?></dt>
                    <dd id="logd-type">—</dd>
                    <dt><?= te('logs.modal.target') ?></dt>
                    <dd id="logd-target">—</dd>
                    <dt><?= te('logs.modal.user') ?></dt>
                    <dd id="logd-user">—</dd>
                    <dt><?= te('logs.modal.ip') ?></dt>
                    <dd id="logd-ip">—</dd>
                    <dt><?= te('logs.modal.when') ?></dt>
                    <dd id="logd-when">—</dd>
                </dl>
                <div class="log-detail-payload">
                    <label><?= te('logs.modal.payload') ?></label>
                    <pre id="logd-payload">—</pre>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/includes/footer.php"; ?>

    <script>
    function closeLogModal() {
        document.getElementById('log-detail-modal').style.display = 'none';
    }

    function tryPrettyJSON(s) {
        try { return JSON.stringify(JSON.parse(s), null, 2); }
        catch (e) { return s; }
    }

    document.querySelectorAll('.log-show-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('logd-action').textContent = this.dataset.action || '—';
            document.getElementById('logd-type').textContent = this.dataset.type || '—';
            document.getElementById('logd-target').textContent = this.dataset.target ? '#' + this.dataset.target : '—';
            document.getElementById('logd-user').textContent = this.dataset.user || '—';
            document.getElementById('logd-ip').textContent = this.dataset.ip || '—';
            document.getElementById('logd-when').textContent = this.dataset.when || '—';
            document.getElementById('logd-payload').textContent = tryPrettyJSON(this.dataset.payload || '');
            document.getElementById('log-detail-modal').style.display = 'block';
        });
    });

    // Close on backdrop click or Escape
    document.getElementById('log-detail-modal').addEventListener('click', function (e) {
        if (e.target === this) closeLogModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLogModal();
    });
    </script>
</body>
</html>
