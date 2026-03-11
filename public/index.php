<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$pageTitle = '儀表板';
$currentPage = 'dashboard';
$user = Auth::user();
$db = Database::getInstance();
$branchIds = Auth::getAccessibleBranchIds();
$placeholders = implode(',', array_fill(0, count($branchIds), '?'));

// 統計資料
$stats = [];

// 案件統計
$stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM cases WHERE branch_id IN ($placeholders) GROUP BY status");
$stmt->execute($branchIds);
$caseStats = [];
foreach ($stmt->fetchAll() as $row) {
    $caseStats[$row['status']] = $row['cnt'];
}
$stats['total_cases'] = array_sum($caseStats);
$stats['pending_cases'] = $caseStats['pending'] ?? 0;
$stats['in_progress_cases'] = $caseStats['in_progress'] ?? 0;

// 今日排工數
$stmt = $db->prepare("SELECT COUNT(*) FROM schedules s JOIN cases c ON s.case_id = c.id WHERE c.branch_id IN ($placeholders) AND s.schedule_date = CURDATE()");
$stmt->execute($branchIds);
$stats['today_schedules'] = $stmt->fetchColumn();

// 即將到期證照 (30天內)
$stmt = $db->prepare("
    SELECT uc.expiry_date, u.real_name, cert.name as cert_name
    FROM user_certifications uc
    JOIN users u ON uc.user_id = u.id
    JOIN certifications cert ON uc.certification_id = cert.id
    WHERE u.branch_id IN ($placeholders)
      AND uc.expiry_date IS NOT NULL
      AND uc.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY uc.expiry_date ASC
    LIMIT 10
");
$stmt->execute($branchIds);
$expiringCerts = $stmt->fetchAll();

// 今日排工清單
$stmt = $db->prepare("
    SELECT s.*, c.title as case_title, c.address, c.case_number,
           v.plate_number, v.vehicle_type
    FROM schedules s
    JOIN cases c ON s.case_id = c.id
    LEFT JOIN vehicles v ON s.vehicle_id = v.id
    WHERE c.branch_id IN ($placeholders) AND s.schedule_date = CURDATE()
    ORDER BY s.created_at ASC
    LIMIT 20
");
$stmt->execute($branchIds);
$todaySchedules = $stmt->fetchAll();

require __DIR__ . '/../templates/layouts/header.php';
?>

<h2 class="mb-2">儀表板</h2>

<div class="stats-grid">
    <div class="card stat-card">
        <div class="stat-number"><?= $stats['total_cases'] ?></div>
        <div class="stat-label">總案件數</div>
    </div>
    <div class="card stat-card">
        <div class="stat-number"><?= $stats['pending_cases'] ?></div>
        <div class="stat-label">待排工</div>
    </div>
    <div class="card stat-card">
        <div class="stat-number"><?= $stats['in_progress_cases'] ?></div>
        <div class="stat-label">施工中</div>
    </div>
    <div class="card stat-card">
        <div class="stat-number"><?= $stats['today_schedules'] ?></div>
        <div class="stat-label">今日排工</div>
    </div>
</div>

<?php if (!empty($expiringCerts)): ?>
<div class="card">
    <div class="card-header">證照即將到期 (30天內)</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>人員</th>
                    <th>證照</th>
                    <th>到期日</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expiringCerts as $cert): ?>
                <tr>
                    <td><?= e($cert['real_name']) ?></td>
                    <td><?= e($cert['cert_name']) ?></td>
                    <td class="text-danger"><?= format_date($cert['expiry_date']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">今日排工</div>
    <?php if (empty($todaySchedules)): ?>
        <p class="text-muted">今日無排工</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>案件編號</th>
                    <th>案件名稱</th>
                    <th>地址</th>
                    <th>車輛</th>
                    <th>第幾次</th>
                    <th>狀態</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todaySchedules as $sch): ?>
                <tr>
                    <td><?= e($sch['case_number']) ?></td>
                    <td><?= e($sch['case_title']) ?></td>
                    <td><?= e($sch['address'] ?? '') ?></td>
                    <td><?= e($sch['plate_number'] ?? '-') ?></td>
                    <td><?= $sch['visit_number'] ?></td>
                    <td>
                        <span class="badge badge-<?= $sch['status'] === 'completed' ? 'success' : ($sch['status'] === 'in_progress' ? 'warning' : 'primary') ?>">
                            <?= e($sch['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
.stat-card { text-align: center; padding: 20px 12px; }
.stat-number { font-size: 2rem; font-weight: 700; color: var(--primary); }
.stat-label { font-size: .85rem; color: var(--gray-500); margin-top: 4px; }
@media (min-width: 768px) {
    .stats-grid { grid-template-columns: repeat(4, 1fr); }
}
</style>

<?php require __DIR__ . '/../templates/layouts/footer.php'; ?>
