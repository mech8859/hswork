<?php
$activityTypes = BusinessCalendarModel::activityTypes();
$regionOptions = BusinessCalendarModel::regionOptions();
$dateFormatted = date('Y/m/d', strtotime($date));
$weekdays = array('日','一','二','三','四','五','六');
$wd = $weekdays[(int)date('w', strtotime($date))];

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

// 日期導航
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$calYear = (int)date('Y', strtotime($date));
$calMonth = (int)date('m', strtotime($date));
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <a href="/business_calendar.php?year=<?= $calYear ?>&month=<?= $calMonth ?>" class="btn btn-outline btn-sm mb-1">&laquo; 返回月曆</a>
        <h2 style="margin:0">業務行程 - <?= e($dateFormatted) ?> (<?= $wd ?>)</h2>
    </div>
    <a href="/business_calendar.php?action=create&event_date=<?= e($date) ?>" class="btn btn-primary btn-sm">+ 新增行程</a>
</div>

<!-- 日期切換 -->
<div class="d-flex justify-between align-center mb-2">
    <a href="/business_calendar.php?action=day&date=<?= $prevDate ?>" class="btn btn-outline btn-sm">&laquo; 前一天</a>
    <a href="/business_calendar.php?action=day&date=<?= date('Y-m-d') ?>" class="btn btn-outline btn-sm">今天</a>
    <a href="/business_calendar.php?action=day&date=<?= $nextDate ?>" class="btn btn-outline btn-sm">後一天 &raquo;</a>
</div>

<?php if (empty($items)): ?>
<div class="card">
    <p class="text-muted text-center mt-2">此日無行程</p>
</div>
<?php else: ?>
<div class="bc-day-list">
    <?php foreach ($items as $item):
        $evColor = BusinessCalendarModel::activityColor($item['activity_type']);
        $staffColor = BusinessCalendarModel::staffColor($item['staff_name']);
        $atLabel = isset($activityTypes[$item['activity_type']]) ? $activityTypes[$item['activity_type']] : $item['activity_type'];
        $dispStatus = isset($item['display_status']) ? $item['display_status'] : $item['status'];
        $stLabel = isset($statusLabels[$dispStatus]) ? $statusLabels[$dispStatus] : ($dispStatus === 'no_report' ? '未回報' : $dispStatus);
        $stBadge = $dispStatus === 'no_report' ? 'danger' : (isset($statusBadges[$dispStatus]) ? $statusBadges[$dispStatus] : 'secondary');
        $regionLabel = isset($regionOptions[$item['region']]) ? $regionOptions[$item['region']] : ($item['region'] ?: '');
    ?>
    <div class="card bc-day-card" style="border-left:4px solid <?= e($staffColor) ?>">
        <div class="d-flex justify-between align-center flex-wrap gap-1 mb-1">
            <div class="d-flex align-center gap-1">
                <span class="bc-badge-type" style="background:<?= e($evColor) ?>"><?= e($atLabel) ?></span>
                <span class="badge badge-<?= $stBadge ?>"><?= e($stLabel) ?></span>
            </div>
            <a href="/business_calendar.php?action=edit&id=<?= $item['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
        </div>

        <div class="bc-day-card-body">
            <div class="bc-day-field">
                <span class="bc-day-label">客戶</span>
                <span class="bc-day-value"><?= e($item['customer_name'] ?: '-') ?></span>
            </div>
            <div class="bc-day-field">
                <span class="bc-day-label">業務人員</span>
                <span class="bc-day-value"><?= e($item['staff_name']) ?></span>
            </div>
            <?php if (!empty($item['phone'])): ?>
            <div class="bc-day-field">
                <span class="bc-day-label">電話</span>
                <span class="bc-day-value"><a href="tel:<?= e($item['phone']) ?>"><?= e($item['phone']) ?></a></span>
            </div>
            <?php endif; ?>
            <?php if ($regionLabel): ?>
            <div class="bc-day-field">
                <span class="bc-day-label">地區</span>
                <span class="bc-day-value"><?= e($regionLabel) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['address'])): ?>
            <div class="bc-day-field">
                <span class="bc-day-label">地址</span>
                <span class="bc-day-value">
                    <?= e($item['address']) ?>
                    <a href="https://maps.google.com/?q=<?= urlencode($item['address']) ?>" target="_blank" rel="noopener" class="bc-map-link" title="Google Maps">&#x1F5FA;</a>
                </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['start_time']) || !empty($item['end_time'])): ?>
            <div class="bc-day-field">
                <span class="bc-day-label">時間</span>
                <span class="bc-day-value">
                    <?= !empty($item['start_time']) ? e(substr($item['start_time'], 0, 5)) : '' ?>
                    <?= (!empty($item['start_time']) && !empty($item['end_time'])) ? ' ~ ' : '' ?>
                    <?= !empty($item['end_time']) ? e(substr($item['end_time'], 0, 5)) : '' ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['note'])): ?>
            <div class="bc-day-field">
                <span class="bc-day-label">備註</span>
                <span class="bc-day-value"><?= nl2br(e($item['note'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['result'])): ?>
            <div class="bc-day-field">
                <span class="bc-day-label">執行結果</span>
                <span class="bc-day-value"><?= nl2br(e($item['result'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.bc-day-list { display: flex; flex-direction: column; gap: 10px; }
.bc-day-card { padding: 16px; }
.bc-day-card-body { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; }
.bc-day-field { display: flex; flex-direction: column; }
.bc-day-label { font-size: .75rem; color: var(--gray-500); font-weight: 600; margin-bottom: 2px; }
.bc-day-value { font-size: .9rem; color: var(--gray-700); }
.bc-badge-type {
    display: inline-block; font-size: .75rem; padding: 2px 8px;
    border-radius: 3px; color: #fff; font-weight: 500;
}
.bc-map-link {
    display: inline-block; margin-left: 4px; text-decoration: none;
    font-size: .85rem; vertical-align: middle;
}

@media (max-width: 767px) {
    .bc-day-card-body { grid-template-columns: 1fr; }
}
</style>
