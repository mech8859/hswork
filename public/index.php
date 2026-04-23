<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

// AJAX actions for announcements
$action = isset($_GET['action']) ? $_GET['action'] : '';
if ($action === 'add_announcement' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!Auth::hasPermission('staff.manage')) {
        echo json_encode(array('error' => '權限不足'));
        exit;
    }
    $db = Database::getInstance();
    $stmt = $db->prepare('INSERT INTO announcements (title, content, is_pinned, created_by) VALUES (?, ?, ?, ?)');
    $stmt->execute(array(
        trim($_POST['title']),
        trim($_POST['content']),
        (int)$_POST['is_pinned'],
        Auth::id(),
    ));
    echo json_encode(array('success' => true));
    exit;
}
if ($action === 'delete_announcement' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!Auth::hasPermission('staff.manage')) {
        echo json_encode(array('error' => '權限不足'));
        exit;
    }
    $db = Database::getInstance();
    $db->prepare('DELETE FROM announcements WHERE id = ?')->execute(array((int)$_POST['id']));
    echo json_encode(array('success' => true));
    exit;
}

$pageTitle = '儀表板';
$currentPage = 'dashboard';
$user = Auth::user();
$db = Database::getInstance();
$branchIds = Auth::getAccessibleBranchIds();

// 每日自動備份到 Google Drive（boss 登入觸發，每天最多一次）
if ($user['role'] === 'boss') {
    $backupLock = __DIR__ . '/../data/backup_last_date.txt';
    $today = date('Y-m-d');
    $lastDate = file_exists($backupLock) ? trim(file_get_contents($backupLock)) : '';
    if ($lastDate !== $today) {
        file_put_contents($backupLock, $today);
        // 非同步觸發備份（不等待完成）
        $backupUrl = 'https://hswork.com.tw/cron_backup.php?key=hswork_backup_2026_secret';
        $ch = curl_init($backupUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_exec($ch);
        curl_close($ch);
    }
}
$placeholders = implode(',', array_fill(0, count($branchIds), '?'));
$branchCond = "(branch_id IN ($placeholders) OR id IN (SELECT case_id FROM case_branch_support WHERE branch_id IN ($placeholders)))";
$branchCondC = "(c.branch_id IN ($placeholders) OR c.id IN (SELECT case_id FROM case_branch_support WHERE branch_id IN ($placeholders)))";
$branchParams2 = array_merge($branchIds, $branchIds); // 兩倍 params

// 統計資料
$stats = array();

// 案件統計
$stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM cases WHERE $branchCond GROUP BY status");
$stmt->execute($branchParams2);
$caseStats = array();
foreach ($stmt->fetchAll() as $row) {
    $caseStats[$row['status']] = $row['cnt'];
}
$stats['total_cases'] = array_sum($caseStats);

// 待排工：status 為「未完工」或「待安排派工查修」，且沒有未來排程
$stmt = $db->prepare("
    SELECT COUNT(*) FROM cases c
    WHERE $branchCondC
      AND c.status IN ('incomplete', 'awaiting_dispatch')
      AND NOT EXISTS (
          SELECT 1 FROM schedules s
          WHERE s.case_id = c.id AND s.schedule_date >= CURDATE()
      )
");
$stmt->execute($branchParams2);
$stats['pending_cases'] = (int)$stmt->fetchColumn();

$stats['in_progress_cases'] = isset($caseStats['incomplete']) ? $caseStats['incomplete'] : 0;

// 未指派案件
$stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE $branchCond AND sub_status = '未指派'");
$stmt->execute($branchParams2);
$stats['unassigned'] = (int)$stmt->fetchColumn();

// 今日排工數
$stmt = $db->prepare("SELECT COUNT(*) FROM schedules s JOIN cases c ON s.case_id = c.id WHERE $branchCondC AND s.schedule_date = CURDATE()");
$stmt->execute($branchParams2);
$stats['today_schedules'] = $stmt->fetchColumn();

// 本月排程率 (有排工天數 / 工作天數)
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$stmt = $db->prepare("SELECT COUNT(DISTINCT schedule_date) FROM schedules s JOIN cases c ON s.case_id = c.id WHERE $branchCondC AND s.schedule_date BETWEEN ? AND ?");
$params = array_merge($branchParams2, array($monthStart, $monthEnd));
$stmt->execute($params);
$scheduledDays = (int)$stmt->fetchColumn();
// 計算本月工作天（排除週末）
$workDays = 0;
$current = $monthStart;
$todayStr = date('Y-m-d');
while ($current <= $monthEnd && $current <= $todayStr) {
    $dow = (int)date('w', strtotime($current));
    if ($dow > 0 && $dow < 6) $workDays++;
    $current = date('Y-m-d', strtotime($current . ' +1 day'));
}
$stats['schedule_rate'] = $workDays > 0 ? round($scheduledDays / $workDays * 100) : 0;

// 本月人力使用率 (有排工的工程師人次 / 總工程師 * 工作天)
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT CONCAT(se.user_id, '-', s.schedule_date))
    FROM schedule_engineers se
    JOIN schedules s ON se.schedule_id = s.id
    JOIN cases c ON s.case_id = c.id
    WHERE $branchCondC AND s.schedule_date BETWEEN ? AND ?
");
$stmt->execute($params);
$engUsage = (int)$stmt->fetchColumn();
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE branch_id IN ($placeholders) AND is_engineer = 1 AND is_active = 1");
$stmt->execute($branchIds);
$totalEngineers = (int)$stmt->fetchColumn();
$stats['utilization_rate'] = ($totalEngineers > 0 && $workDays > 0)
    ? round($engUsage / ($totalEngineers * $workDays) * 100)
    : 0;

// 全員佈告欄
$announcements = array();
try {
    $stmt = $db->prepare("
        SELECT a.*, u.real_name AS author_name
        FROM announcements a
        LEFT JOIN users u ON a.created_by = u.id
        ORDER BY a.is_pinned DESC, a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll();
} catch (Exception $e) {
    // table may not exist yet
}

// 個人提醒 — 今日我的排工（未回報）
$myReminders = array();
if ($user['is_engineer']) {
    $stmt = $db->prepare("
        SELECT s.schedule_date, c.title AS case_title, c.case_number, c.id AS case_id, s.id AS schedule_id
        FROM schedule_engineers se
        JOIN schedules s ON se.schedule_id = s.id
        JOIN cases c ON s.case_id = c.id
        WHERE se.user_id = ? AND s.schedule_date <= CURDATE() AND s.status != 'completed'
        ORDER BY s.schedule_date DESC
        LIMIT 10
    ");
    $stmt->execute(array($user['id']));
    $myReminders = $stmt->fetchAll();
}

// 業務提醒 — 新進件（指派給我的、狀態待聯絡）
$mySalesReminders = array();
$mySalesTodaySchedules = array();
$mySalesTodayEvents = array();
if (in_array($user['role'], array('sales', 'sales_manager', 'sales_assistant', 'boss'))) {
    $stmt = $db->prepare("
        SELECT c.id, c.case_number, c.title, c.customer_name, c.sub_status, c.created_at
        FROM cases c
        WHERE c.sales_id = ? AND c.sub_status IN ('待聯絡', '未指派')
        ORDER BY c.created_at DESC
        LIMIT 20
    ");
    $stmt->execute(array($user['id']));
    $mySalesReminders = $stmt->fetchAll();

    // 業務提醒 — 我的案件今日有施工
    $stmt = $db->prepare("
        SELECT DISTINCT c.id, c.case_number, c.title, c.customer_name, c.address,
               s.id AS schedule_id, s.schedule_date, s.visit_number
        FROM schedules s
        JOIN cases c ON s.case_id = c.id
        WHERE c.sales_id = ? AND s.schedule_date = CURDATE() AND s.status != 'cancelled'
        ORDER BY s.id
        LIMIT 20
    ");
    $stmt->execute(array($user['id']));
    $mySalesTodaySchedules = $stmt->fetchAll();

    // 業務提醒 — 我今日的業務行事曆行程
    $stmt = $db->prepare("
        SELECT bc.id, bc.customer_name, bc.activity_type, bc.start_time, bc.end_time,
               bc.address, bc.case_id, bc.status
        FROM business_calendar bc
        WHERE bc.staff_id = ? AND bc.event_date = CURDATE()
        ORDER BY bc.start_time, bc.id
        LIMIT 20
    ");
    $stmt->execute(array($user['id']));
    $mySalesTodayEvents = $stmt->fetchAll();
}

// 主管可看所有人提醒
$allReminders = array();
if (Auth::hasPermission('schedule.manage')) {
    $stmt = $db->prepare("
        SELECT s.schedule_date, c.title AS case_title, c.case_number, u.real_name,
               s.id AS schedule_id, c.id AS case_id
        FROM schedule_engineers se
        JOIN schedules s ON se.schedule_id = s.id
        JOIN cases c ON s.case_id = c.id
        JOIN users u ON se.user_id = u.id
        WHERE c.branch_id IN ($placeholders)
          AND s.schedule_date = CURDATE() AND s.status != 'completed'
        ORDER BY u.real_name, s.schedule_date
        LIMIT 20
    ");
    $stmt->execute($branchIds);
    $allReminders = $stmt->fetchAll();
}

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

// 師徒制到期提醒（超過90天）
$mentorshipExpiring = array();
if (in_array($user['role'], array('eng_manager', 'eng_deputy', 'boss'))) {
    $msStmt = $db->prepare("
        SELECT u.id, u.real_name, u.mentor_start_date, u.branch_id,
               m.real_name AS mentor_name,
               DATEDIFF(CURDATE(), u.mentor_start_date) AS days_elapsed
        FROM users u
        JOIN users m ON u.mentor_id = m.id
        WHERE u.branch_id IN ($placeholders)
          AND u.is_active = 1
          AND u.mentor_id IS NOT NULL
          AND u.mentor_start_date IS NOT NULL
          AND DATEDIFF(CURDATE(), u.mentor_start_date) >= 90
        ORDER BY u.mentor_start_date ASC
    ");
    $msStmt->execute($branchIds);
    $mentorshipExpiring = $msStmt->fetchAll();
}

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

// 已進場/需再安排 案件數 (eng_manager + boss)
$needsRescheduleCount = 0;
if (in_array($user['role'], array('eng_manager', 'eng_deputy', 'boss'))) {
    $nrStmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE branch_id IN ($placeholders) AND stage = 6");
    $nrStmt->execute($branchIds);
    $needsRescheduleCount = (int)$nrStmt->fetchColumn();
}

$canManageAnnouncements = Auth::hasPermission('staff.manage');

require __DIR__ . '/../templates/layouts/header.php';
?>

<h2 class="mb-2">儀表板</h2>

<!-- 統計卡片 -->
<div class="stats-grid">
    <a href="/cases.php" class="card stat-card stat-link">
        <div class="stat-number"><?= $stats['total_cases'] ?></div>
        <div class="stat-label">總案件數</div>
    </a>
    <a href="/cases.php?sub_status=未指派" class="card stat-card stat-link" <?= $stats['unassigned'] > 0 ? 'style="border-left:3px solid var(--danger)"' : '' ?>>
        <div class="stat-number" <?= $stats['unassigned'] > 0 ? 'style="color:var(--danger)"' : '' ?>><?= $stats['unassigned'] ?></div>
        <div class="stat-label">未指派</div>
    </a>
    <a href="/cases.php?status=incomplete,awaiting_dispatch" class="card stat-card stat-link">
        <div class="stat-number"><?= $stats['pending_cases'] ?></div>
        <div class="stat-label">待排工</div>
    </a>
    <a href="/schedule.php?year=<?= date('Y') ?>&month=<?= date('n') ?>" class="card stat-card stat-link">
        <div class="stat-number"><?= $stats['today_schedules'] ?></div>
        <div class="stat-label">今日排工</div>
    </a>
    <a href="/cases.php?status=incomplete" class="card stat-card stat-link">
        <div class="stat-number"><?= $stats['in_progress_cases'] ?></div>
        <div class="stat-label">施工中</div>
    </a>
    <div class="card stat-card">
        <div class="stat-number"><?= $stats['schedule_rate'] ?>%</div>
        <div class="stat-label">本月排程率</div>
    </div>
    <div class="card stat-card">
        <div class="stat-number"><?= $stats['utilization_rate'] ?>%</div>
        <div class="stat-label">人力使用率</div>
    </div>
<?php if (in_array($user['role'], array('eng_manager', 'eng_deputy', 'boss')) && $needsRescheduleCount > 0): ?>
    <a href="/engineering_tracking.php?view=list&stage=6&all=1" class="card stat-card stat-link" style="border-left:3px solid #F44336">
        <div class="stat-number" style="color:#F44336"><?= $needsRescheduleCount ?></div>
        <div class="stat-label">需再安排</div>
    </a>
<?php endif; ?>
</div>

<!-- 兩欄佈局：佈告欄 + 提醒 -->
<div class="dashboard-cols">
    <!-- 全員佈告欄 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>全員佈告欄</span>
            <?php if ($canManageAnnouncements): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="showAnnouncementForm()">+ 發布公告</button>
            <?php endif; ?>
        </div>
        <div id="announcementFormArea" style="display:none;padding:12px;border-bottom:1px solid var(--gray-200)">
            <div class="form-group">
                <input type="text" id="annTitle" class="form-control" placeholder="公告標題">
            </div>
            <div class="form-group">
                <textarea id="annContent" class="form-control" rows="3" placeholder="公告內容"></textarea>
            </div>
            <div class="d-flex gap-1">
                <label style="font-size:.85rem;display:flex;align-items:center;gap:4px">
                    <input type="checkbox" id="annPinned"> 置頂
                </label>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitAnnouncement()">發布</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="hideAnnouncementForm()">取消</button>
            </div>
        </div>
        <?php if (empty($announcements)): ?>
        <p class="text-muted text-center" style="padding:16px">暫無公告</p>
        <?php else: ?>
        <?php foreach ($announcements as $ann): ?>
        <div class="ann-item">
            <div class="d-flex justify-between align-center">
                <strong>
                    <?php if ($ann['is_pinned']): ?><span style="color:var(--danger)">[置頂]</span> <?php endif; ?>
                    <?= e($ann['title']) ?>
                </strong>
                <span class="text-muted" style="font-size:.75rem"><?= date('m/d', strtotime($ann['created_at'])) ?></span>
            </div>
            <div style="font-size:.85rem;color:var(--gray-600);margin-top:2px"><?= nl2br(e($ann['content'])) ?></div>
            <div class="text-muted" style="font-size:.7rem;margin-top:2px"><?= e($ann['author_name'] ?: '') ?></div>
            <?php if ($canManageAnnouncements): ?>
            <button type="button" class="ann-del" onclick="deleteAnnouncement(<?= $ann['id'] ?>)" title="刪除">&times;</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 個人提醒看板 -->
    <div class="card">
        <div class="card-header">個人提醒看板</div>
        <?php if (!empty($mySalesReminders)): ?>
        <div style="font-size:.8rem;font-weight:600;color:var(--danger);padding:8px 12px 4px">新進件案件</div>
        <?php foreach ($mySalesReminders as $r): ?>
        <div class="reminder-item">
            <div class="d-flex justify-between align-center">
                <a href="/cases.php?action=edit&id=<?= $r['id'] ?>" style="font-weight:500"><?= e($r['case_number']) ?> <?= e($r['customer_name'] ?: $r['title']) ?></a>
                <span class="badge" style="font-size:.65rem;background:#FF9800;color:#fff"><?= e($r['sub_status']) ?></span>
            </div>
            <div class="text-muted" style="font-size:.75rem"><?= date('m/d H:i', strtotime($r['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($mySalesTodaySchedules)): ?>
        <div style="font-size:.8rem;font-weight:600;color:#1565c0;padding:8px 12px 4px">今日我的案件施工</div>
        <?php foreach ($mySalesTodaySchedules as $r): ?>
        <div class="reminder-item">
            <div class="d-flex justify-between align-center">
                <a href="/cases.php?action=edit&id=<?= $r['id'] ?>" style="font-weight:500"><?= e($r['case_number']) ?> <?= e($r['customer_name'] ?: $r['title']) ?><?php if (!empty($r['visit_number']) && $r['visit_number'] > 1): ?> <small style="color:#888">第<?= (int)$r['visit_number'] ?>次</small><?php endif; ?></a>
                <span class="badge" style="font-size:.65rem;background:#1976D2;color:#fff">今日施工</span>
            </div>
            <?php if (!empty($r['address'])): ?>
            <div class="text-muted" style="font-size:.75rem"><?= e($r['address']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($mySalesTodayEvents)): ?>
        <?php
        $_actTypes = array('visit'=>'拜訪','survey'=>'場勘','follow_up'=>'跟催','quotation'=>'報價','signing'=>'簽約','other'=>'其他');
        ?>
        <div style="font-size:.8rem;font-weight:600;color:#2e7d32;padding:8px 12px 4px">今日業務行程</div>
        <?php foreach ($mySalesTodayEvents as $r):
            $_typeLabel = isset($_actTypes[$r['activity_type']]) ? $_actTypes[$r['activity_type']] : $r['activity_type'];
            $_time = !empty($r['start_time']) ? substr($r['start_time'], 0, 5) : '';
            $_link = !empty($r['case_id']) ? '/cases.php?action=edit&id=' . (int)$r['case_id'] : '/business_calendar.php?action=edit&id=' . (int)$r['id'];
        ?>
        <div class="reminder-item">
            <div class="d-flex justify-between align-center">
                <a href="<?= $_link ?>" style="font-weight:500"><?php if ($_time): ?><span style="color:#e65100;margin-right:4px"><?= $_time ?></span><?php endif; ?><?= e($r['customer_name'] ?: '(無客戶名稱)') ?></a>
                <span class="badge" style="font-size:.65rem;background:#2e7d32;color:#fff"><?= e($_typeLabel) ?></span>
            </div>
            <?php if (!empty($r['address'])): ?>
            <div class="text-muted" style="font-size:.75rem"><?= e($r['address']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($myReminders)): ?>
        <div style="font-size:.8rem;font-weight:600;color:var(--gray-500);padding:8px 12px 4px">排工待回報</div>
        <?php foreach ($myReminders as $r): ?>
        <div class="reminder-item">
            <div class="d-flex justify-between align-center">
                <a href="/schedule.php?action=view&id=<?= $r['schedule_id'] ?>" style="font-weight:500"><?= e($r['case_number']) ?> <?= e($r['case_title']) ?></a>
                <span class="badge badge-warning" style="font-size:.7rem">待回報</span>
            </div>
            <div class="text-muted" style="font-size:.8rem"><?= e($r['schedule_date']) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($mySalesReminders) && empty($myReminders) && empty($mySalesTodaySchedules) && empty($mySalesTodayEvents)): ?>
        <p class="text-muted text-center" style="padding:16px">目前無待辦提醒</p>
        <?php endif; ?>

        <?php if (!empty($allReminders)): ?>
        <div style="border-top:2px solid var(--gray-200);padding-top:8px;margin-top:8px">
            <div style="font-size:.8rem;font-weight:600;color:var(--gray-500);padding:0 0 6px">全員今日待回報</div>
            <?php foreach ($allReminders as $r): ?>
            <div class="reminder-item">
                <div class="d-flex justify-between align-center">
                    <span><strong><?= e($r['real_name']) ?></strong> — <?= e($r['case_number']) ?></span>
                    <span class="badge badge-warning" style="font-size:.65rem">未完成</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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

<?php if (!empty($mentorshipExpiring)): ?>
<div class="card" style="border-left:4px solid var(--warning)">
    <div class="card-header" style="color:var(--warning)">⚠ 師徒制到期提醒（已超過 3 個月）</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>學徒</th>
                    <th>師傅</th>
                    <th>開始日期</th>
                    <th>已持續</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mentorshipExpiring as $ms): ?>
                <tr>
                    <td><a href="/staff.php?action=edit&id=<?= $ms['id'] ?>"><?= e($ms['real_name']) ?></a></td>
                    <td><?= e($ms['mentor_name']) ?></td>
                    <td><?= format_date($ms['mentor_start_date']) ?></td>
                    <td class="text-warning"><strong><?= $ms['days_elapsed'] ?> 天</strong></td>
                    <td><a href="/staff.php?action=edit&id=<?= $ms['id'] ?>" class="btn btn-outline btn-sm">調整</a></td>
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
    <?php
    $scheduleStatusLabels = array(
        'planned'       => '已排',
        'confirmed'     => '已確認',
        'in_progress'   => '施工中',
        'checked_out'   => '已下工',
        'needs_revisit' => '需再施工',
        'no_report'     => '未回報',
        'completed'     => '已完工',
        'cancelled'     => '已取消',
    );
    ?>
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
                    <td><?= e($sch['address'] ?: '') ?></td>
                    <td><?= e($sch['plate_number'] ?: '-') ?></td>
                    <td><?= $sch['visit_number'] ?></td>
                    <td>
                        <span class="badge badge-<?= $sch['status'] === 'completed' ? 'success' : ($sch['status'] === 'in_progress' ? 'warning' : ($sch['status'] === 'cancelled' ? 'danger' : 'primary')) ?>">
                            <?= e(isset($scheduleStatusLabels[$sch['status']]) ? $scheduleStatusLabels[$sch['status']] : $sch['status']) ?>
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
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
.stat-card { text-align: center; padding: 16px 8px; }
.stat-link { text-decoration: none; cursor: pointer; transition: box-shadow .2s, transform .15s; display: block; }
.stat-link:hover { box-shadow: 0 4px 12px rgba(0,0,0,.12); transform: translateY(-2px); }
.stat-number { font-size: 1.8rem; font-weight: 700; color: var(--primary); }
.stat-label { font-size: .8rem; color: var(--gray-500); margin-top: 2px; }
.dashboard-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.ann-item { padding: 10px 12px; border-bottom: 1px solid var(--gray-100); position: relative; }
.ann-item:last-child { border-bottom: none; }
.ann-del { position: absolute; top: 8px; right: 8px; background: none; border: none; color: var(--danger); cursor: pointer; font-size: 1.1rem; display: none; }
.ann-item:hover .ann-del { display: block; }
.reminder-item { padding: 8px 12px; border-bottom: 1px solid var(--gray-100); }
.reminder-item:last-child { border-bottom: none; }
@media (max-width: 767px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .dashboard-cols { grid-template-columns: 1fr; }
}
@media (min-width: 768px) {
    .stats-grid { grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); }
}
</style>

<script>
function showAnnouncementForm() {
    document.getElementById('announcementFormArea').style.display = 'block';
}
function hideAnnouncementForm() {
    document.getElementById('announcementFormArea').style.display = 'none';
}
function submitAnnouncement() {
    var title = document.getElementById('annTitle').value.trim();
    var content = document.getElementById('annContent').value.trim();
    if (!title) { alert('請輸入標題'); return; }
    var fd = new FormData();
    fd.append('title', title);
    fd.append('content', content);
    fd.append('is_pinned', document.getElementById('annPinned').checked ? 1 : 0);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/index.php?action=add_announcement');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) location.reload();
                else alert(res.error || '發布失敗');
            } catch(e) { alert('發布失敗'); }
        }
    };
    xhr.send(fd);
}
function deleteAnnouncement(id) {
    if (!confirm('確定刪除此公告?')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/index.php?action=delete_announcement');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) location.reload();
            } catch(e) {}
        }
    };
    xhr.send('id=' + id);
}
</script>

<?php require __DIR__ . '/../templates/layouts/footer.php'; ?>
