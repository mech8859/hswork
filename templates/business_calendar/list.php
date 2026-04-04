<?php
$activityTypes = BusinessCalendarModel::activityTypes();
$regionOptions = BusinessCalendarModel::regionOptions();
$currentStaff = isset($filters['staff_id']) ? $filters['staff_id'] : '';
$currentRegion = isset($filters['region']) ? $filters['region'] : '';
$currentKeyword = isset($filters['keyword']) ? $filters['keyword'] : '';
$currentDateFrom = isset($filters['date_from']) ? $filters['date_from'] : '';
$currentDateTo = isset($filters['date_to']) ? $filters['date_to'] : '';

$statusLabels = array(
    'planned' => '計劃中',
    'completed' => '已完成',
    'cancelled' => '已取消',
);
$statusBadges = array(
    'planned' => 'primary',
    'completed' => 'success',
    'cancelled' => 'secondary',
);
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>業務行事曆</h2>
    <div class="d-flex gap-1 align-center">
        <a href="/business_calendar.php" class="btn btn-outline btn-sm">行事曆檢視</a>
        <span class="btn btn-primary btn-sm" style="cursor:default">列表檢視</span>
        <a href="/business_calendar.php?action=create" class="btn btn-primary btn-sm">+ 新增行程</a>
    </div>
</div>

<div class="card">
    <form method="GET" action="/business_calendar.php" class="filter-form">
        <input type="hidden" name="action" value="list">
        <div class="filter-row">
            <div class="form-group">
                <label>起始日期</label>
                <input type="date" max="2099-12-31" name="date_from" class="form-control" value="<?= e($currentDateFrom) ?>">
            </div>
            <div class="form-group">
                <label>結束日期</label>
                <input type="date" max="2099-12-31" name="date_to" class="form-control" value="<?= e($currentDateTo) ?>">
            </div>
            <div class="form-group">
                <label>業務人員</label>
                <select name="staff_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($salespeople as $sp): ?>
                    <option value="<?= $sp['id'] ?>" <?= $currentStaff == $sp['id'] ? 'selected' : '' ?>><?= e($sp['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>地區</label>
                <select name="region" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($regionOptions as $rKey => $rLabel): ?>
                    <option value="<?= e($rKey) ?>" <?= $currentRegion === $rKey ? 'selected' : '' ?>><?= e($rLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($currentKeyword) ?>" placeholder="客戶/備註">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/business_calendar.php?action=list" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($items)): ?>
        <p class="text-muted text-center mt-2">目前無行程資料</p>
    <?php else: ?>

    <!-- 手機版卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($items as $item):
            $evColor = BusinessCalendarModel::activityColor($item['activity_type']);
            $atLabel = isset($activityTypes[$item['activity_type']]) ? $activityTypes[$item['activity_type']] : $item['activity_type'];
            $stLabel = isset($statusLabels[$item['status']]) ? $statusLabels[$item['status']] : $item['status'];
            $stBadge = isset($statusBadges[$item['status']]) ? $statusBadges[$item['status']] : 'secondary';
        ?>
        <div class="staff-card" onclick="location.href='/business_calendar.php?action=edit&id=<?= $item['id'] ?>'" style="border-left:3px solid <?= e($evColor) ?>">
            <div class="d-flex justify-between align-center">
                <strong><?= e($item['customer_name'] ?: '-') ?></strong>
                <span class="badge badge-<?= $stBadge ?>"><?= e($stLabel) ?></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e($item['event_date']) ?></span>
                <span><?= e($item['staff_name']) ?></span>
                <span class="bc-badge-type" style="background:<?= e($evColor) ?>"><?= e($atLabel) ?></span>
                <?php if (!empty($item['region'])): ?>
                <span><?= e(isset($regionOptions[$item['region']]) ? $regionOptions[$item['region']] : $item['region']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($item['phone'])): ?>
            <div style="font-size:.8rem;color:var(--gray-500);margin-top:2px"><?= e($item['phone']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>日期</th>
                    <th>業務</th>
                    <th>客戶</th>
                    <th>活動類型</th>
                    <th>電話</th>
                    <th>地區</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item):
                    $evColor = BusinessCalendarModel::activityColor($item['activity_type']);
                    $atLabel = isset($activityTypes[$item['activity_type']]) ? $activityTypes[$item['activity_type']] : $item['activity_type'];
                    $stLabel = isset($statusLabels[$item['status']]) ? $statusLabels[$item['status']] : $item['status'];
                    $stBadge = isset($statusBadges[$item['status']]) ? $statusBadges[$item['status']] : 'secondary';
                    $regionLabel = isset($regionOptions[$item['region']]) ? $regionOptions[$item['region']] : ($item['region'] ?: '-');
                ?>
                <tr>
                    <td><?= e($item['event_date']) ?></td>
                    <td><?= e($item['staff_name']) ?></td>
                    <td>
                        <a href="/business_calendar.php?action=edit&id=<?= $item['id'] ?>"><?= e($item['customer_name'] ?: '-') ?></a>
                    </td>
                    <td>
                        <span class="bc-badge-type" style="background:<?= e($evColor) ?>"><?= e($atLabel) ?></span>
                    </td>
                    <td><?= e($item['phone'] ?: '-') ?></td>
                    <td><?= e($regionLabel) ?></td>
                    <td><span class="badge badge-<?= $stBadge ?>"><?= e($stLabel) ?></span></td>
                    <td>
                        <a href="/business_calendar.php?action=edit&id=<?= $item['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: box-shadow .15s; }
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; align-items: center; }
.bc-badge-type {
    display: inline-block; font-size: .7rem; padding: 1px 6px;
    border-radius: 3px; color: #fff; font-weight: 500;
}
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
