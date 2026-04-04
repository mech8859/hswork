<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') {
    Session::flash('error', '需要管理員權限');
    redirect('/');
}
header('Content-Type: text/html; charset=utf-8');

$db = Database::getInstance();

// 確保表存在
try {
    $db->query("SELECT 1 FROM login_logs LIMIT 1");
} catch (PDOException $e) {
    $sqlFile = __DIR__ . '/../database/migration_043_login_logs.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $db->exec($sql);
    }
}

// 篩選
$filterUser = !empty($_GET['user']) ? $_GET['user'] : '';
$filterStatus = !empty($_GET['status']) ? $_GET['status'] : '';
$filterDateFrom = !empty($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = !empty($_GET['date_to']) ? $_GET['date_to'] : '';

$where = '1=1';
$params = array();
if ($filterUser) {
    $where .= ' AND (ll.username LIKE ? OR ll.ip_address LIKE ?)';
    $kw = '%' . $filterUser . '%';
    $params[] = $kw;
    $params[] = $kw;
}
if ($filterStatus) {
    $where .= ' AND ll.status = ?';
    $params[] = $filterStatus;
}
if ($filterDateFrom) {
    $where .= ' AND ll.created_at >= ?';
    $params[] = $filterDateFrom . ' 00:00:00';
}
if ($filterDateTo) {
    $where .= ' AND ll.created_at <= ?';
    $params[] = $filterDateTo . ' 23:59:59';
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$countStmt = $db->prepare("SELECT COUNT(*) FROM login_logs ll WHERE {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT ll.*, u.real_name
    FROM login_logs ll
    LEFT JOIN users u ON ll.user_id = u.id
    WHERE {$where}
    ORDER BY ll.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lastPage = max(1, (int)ceil($total / $perPage));

$pageTitle = '登入日誌';
$currentPage = 'login_logs';
require __DIR__ . '/../templates/layouts/header.php';
?>
<div class="d-flex justify-between align-center mb-2">
    <h2>登入日誌</h2>
    <span class="text-muted"><?= number_format($total) ?> 筆</span>
</div>

<div class="card mb-2">
    <form method="GET" action="/login_logs.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>帳號/IP</label>
                <input type="text" name="user" class="form-control" value="<?= htmlspecialchars($filterUser) ?>" placeholder="帳號或IP" autocomplete="off">
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <option value="success" <?= $filterStatus === 'success' ? 'selected' : '' ?>>成功</option>
                    <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>失敗</option>
                    <option value="locked" <?= $filterStatus === 'locked' ? 'selected' : '' ?>>鎖定</option>
                </select>
            </div>
            <div class="form-group">
                <label>起始日期</label>
                <input type="date" max="2099-12-31" name="date_from" class="form-control" value="<?= htmlspecialchars($filterDateFrom) ?>">
            </div>
            <div class="form-group">
                <label>結束日期</label>
                <input type="date" max="2099-12-31" name="date_to" class="form-control" value="<?= htmlspecialchars($filterDateTo) ?>">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/login_logs.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($logs)): ?>
    <p class="text-muted text-center" style="padding:20px">無登入紀錄</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table" style="font-size:.85rem">
            <thead>
                <tr>
                    <th>時間</th>
                    <th>帳號</th>
                    <th>姓名</th>
                    <th>狀態</th>
                    <th>IP</th>
                    <th>裝置</th>
                    <th>瀏覽器</th>
                    <th>系統</th>
                    <th>失敗原因</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr style="<?= $log['status'] !== 'success' ? 'background:#fff5f5;' : '' ?>">
                    <td style="white-space:nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
                    <td><?= htmlspecialchars($log['username']) ?></td>
                    <td><?= htmlspecialchars($log['real_name'] ? $log['real_name'] : '-') ?></td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                            <span style="color:green;font-weight:bold">成功</span>
                        <?php elseif ($log['status'] === 'locked'): ?>
                            <span style="color:#e67e22;font-weight:bold">鎖定</span>
                        <?php else: ?>
                            <span style="color:red;font-weight:bold">失敗</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                    <td><?= htmlspecialchars($log['device_type']) ?></td>
                    <td><?= htmlspecialchars($log['browser']) ?></td>
                    <td><?= htmlspecialchars($log['os']) ?></td>
                    <td style="color:#999"><?= htmlspecialchars($log['fail_reason'] ? $log['fail_reason'] : '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($lastPage > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&user=<?= urlencode($filterUser) ?>&status=<?= urlencode($filterStatus) ?>&date_from=<?= urlencode($filterDateFrom) ?>&date_to=<?= urlencode($filterDateTo) ?>" class="btn btn-outline btn-sm">← 上一頁</a><?php endif; ?>
        <span class="text-muted">第 <?= $page ?> / <?= $lastPage ?> 頁</span>
        <?php if ($page < $lastPage): ?><a href="?page=<?= $page + 1 ?>&user=<?= urlencode($filterUser) ?>&status=<?= urlencode($filterStatus) ?>&date_from=<?= urlencode($filterDateFrom) ?>&date_to=<?= urlencode($filterDateTo) ?>" class="btn btn-outline btn-sm">下一頁 →</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../templates/layouts/footer.php'; ?>
