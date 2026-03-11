<?php
// 計算月曆資料
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$startWeekday = (int)date('w', $firstDay); // 0=Sun
$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
$today = date('Y-m-d');
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2><?= $year ?> 年 <?= $month ?> 月 排工行事曆</h2>
    <div class="d-flex gap-1">
        <?php if (Auth::hasPermission('schedule.manage')): ?>
        <a href="/schedule.php?action=create" class="btn btn-primary btn-sm">+ 新增排工</a>
        <?php endif; ?>
    </div>
</div>

<!-- 多次施工人員不同組通知 -->
<?php if (!empty($visitWarnings)): ?>
<div class="alert alert-warning">
    <strong>多次施工人員組別不同：</strong>
    <ul style="margin:4px 0 0 16px;padding:0">
        <?php foreach ($visitWarnings as $w): ?>
        <li><?= e($w['case_number']) ?> <?= e($w['case_title']) ?> - 第<?= $w['visit_number'] ?>次施工人員與第<?= $w['previous_visit_number'] ?>次不同</li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- 月份切換 -->
<div class="d-flex justify-between align-center mb-1">
    <a href="/schedule.php?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="btn btn-outline btn-sm">&lt; 上月</a>
    <a href="/schedule.php?year=<?= date('Y') ?>&month=<?= date('m') ?>" class="btn btn-outline btn-sm">今天</a>
    <a href="/schedule.php?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" class="btn btn-outline btn-sm">下月 &gt;</a>
</div>

<!-- 行事曆 - 桌面版甘特式 -->
<div class="calendar-desktop hide-mobile">
    <div class="calendar-grid">
        <div class="cal-header">日</div>
        <div class="cal-header">一</div>
        <div class="cal-header">二</div>
        <div class="cal-header">三</div>
        <div class="cal-header">四</div>
        <div class="cal-header">五</div>
        <div class="cal-header">六</div>

        <?php
        // 填空白
        for ($i = 0; $i < $startWeekday; $i++):
        ?>
        <div class="cal-cell cal-empty"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $daySchedules = $schedulesByDate[$dateStr] ?? [];
            $isToday = ($dateStr === $today);
            $isWeekend = in_array(($startWeekday + $day - 1) % 7, [0, 6]);
        ?>
        <div class="cal-cell <?= $isToday ? 'cal-today' : '' ?> <?= $isWeekend ? 'cal-weekend' : '' ?>">
            <div class="cal-date">
                <?= $day ?>
                <?php if (Auth::hasPermission('schedule.manage')): ?>
                <a href="/schedule.php?action=create&date=<?= $dateStr ?>" class="cal-add" title="新增排工">+</a>
                <?php endif; ?>
            </div>
            <?php foreach ($daySchedules as $ds): ?>
            <a href="/schedule.php?action=view&id=<?= $ds['id'] ?>" class="cal-event cal-event-<?= $ds['status'] ?>">
                <div class="cal-event-title"><?= e(mb_substr($ds['case_title'], 0, 10)) ?></div>
                <div class="cal-event-info">
                    <?php if ($ds['plate_number']): ?><span><?= e($ds['plate_number']) ?></span><?php endif; ?>
                    <span><?= count($ds['engineers']) ?>人</span>
                    <?php if ($ds['total_visits'] > 1): ?><span><?= $ds['visit_number'] ?>/<?= $ds['total_visits'] ?></span><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- 行事曆 - 手機版列表 -->
<div class="calendar-mobile show-mobile">
    <?php for ($day = 1; $day <= $daysInMonth; $day++):
        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $daySchedules = $schedulesByDate[$dateStr] ?? [];
        if (empty($daySchedules) && $dateStr < $today) continue;
        $isToday = ($dateStr === $today);
        $weekdays = ['日','一','二','三','四','五','六'];
        $wd = $weekdays[($startWeekday + $day - 1) % 7];
    ?>
    <div class="mobile-day <?= $isToday ? 'mobile-day-today' : '' ?>">
        <div class="mobile-day-header">
            <span class="mobile-day-num"><?= $month ?>/<?= $day ?> (<?= $wd ?>)</span>
            <?php if ($isToday): ?><span class="badge badge-primary">今天</span><?php endif; ?>
            <?php if (Auth::hasPermission('schedule.manage')): ?>
            <a href="/schedule.php?action=create&date=<?= $dateStr ?>" class="btn btn-outline btn-sm">+ 排工</a>
            <?php endif; ?>
        </div>
        <?php if (empty($daySchedules)): ?>
            <p class="text-muted" style="font-size:.85rem;padding:4px 0">無排工</p>
        <?php endif; ?>
        <?php foreach ($daySchedules as $ds): ?>
        <a href="/schedule.php?action=view&id=<?= $ds['id'] ?>" class="mobile-schedule-card">
            <div class="d-flex justify-between align-center">
                <strong><?= e($ds['case_title']) ?></strong>
                <span class="badge badge-<?= $ds['status'] === 'completed' ? 'success' : 'primary' ?>"><?= e($ds['status']) ?></span>
            </div>
            <div class="mobile-schedule-meta">
                <?php if ($ds['plate_number']): ?><span><?= e($ds['plate_number']) ?></span><?php endif; ?>
                <span><?= implode(', ', array_column($ds['engineers'], 'real_name')) ?: '未指派' ?></span>
                <?php if ($ds['total_visits'] > 1): ?><span>第<?= $ds['visit_number'] ?>/<?= $ds['total_visits'] ?>次</span><?php endif; ?>
            </div>
            <?php if ($ds['address']): ?>
            <div class="text-muted" style="font-size:.8rem"><?= e($ds['address']) ?></div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endfor; ?>
</div>

<style>
/* 桌面版行事曆 */
.calendar-grid {
    display: grid; grid-template-columns: repeat(7, 1fr);
    border: 1px solid var(--gray-200); border-radius: var(--radius);
    background: #fff;
}
.cal-header {
    padding: 8px; text-align: center; font-weight: 600;
    background: var(--gray-100); border-bottom: 1px solid var(--gray-200);
    font-size: .85rem;
}
.cal-cell {
    min-height: 100px; padding: 4px; border-right: 1px solid var(--gray-200);
    border-bottom: 1px solid var(--gray-200); position: relative;
}
.cal-cell:nth-child(7n) { border-right: none; }
.cal-empty { background: var(--gray-50); }
.cal-today { background: #e8f0fe; }
.cal-weekend { background: #fafafa; }
.cal-date {
    display: flex; justify-content: space-between; align-items: center;
    font-size: .85rem; font-weight: 500; margin-bottom: 4px;
}
.cal-add {
    width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;
    background: var(--primary); color: #fff; border-radius: 50%;
    font-size: .8rem; text-decoration: none; opacity: 0;
    transition: opacity .15s;
}
.cal-cell:hover .cal-add { opacity: 1; }
.cal-event {
    display: block; padding: 3px 6px; margin-bottom: 2px;
    border-radius: 4px; font-size: .75rem; text-decoration: none;
    color: #fff; transition: opacity .15s;
}
.cal-event:hover { opacity: .85; text-decoration: none; }
.cal-event-planned { background: var(--primary); }
.cal-event-confirmed { background: var(--info); }
.cal-event-in_progress { background: var(--warning); color: #333; }
.cal-event-completed { background: var(--success); }
.cal-event-cancelled { background: var(--gray-500); }
.cal-event-title { font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cal-event-info { display: flex; gap: 4px; opacity: .9; font-size: .7rem; }

/* 手機版列表 */
.calendar-mobile { display: flex; flex-direction: column; gap: 4px; }
.mobile-day { background: #fff; border-radius: var(--radius); padding: 10px; box-shadow: var(--shadow); }
.mobile-day-today { border-left: 3px solid var(--primary); }
.mobile-day-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.mobile-day-num { font-weight: 600; }
.mobile-schedule-card {
    display: block; border: 1px solid var(--gray-200); border-radius: var(--radius);
    padding: 8px; margin-bottom: 4px; text-decoration: none; color: inherit;
}
.mobile-schedule-card:hover { box-shadow: var(--shadow); text-decoration: none; }
.mobile-schedule-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; flex-wrap: wrap; margin-top: 2px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) {
    .show-mobile { display: none !important; }
    .hide-mobile { display: block !important; }
}
</style>
