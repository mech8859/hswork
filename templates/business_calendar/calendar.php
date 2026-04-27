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

$activityTypes = BusinessCalendarModel::activityTypes();
$regionOptions = BusinessCalendarModel::regionOptions();
$currentRegion = isset($filters['region']) ? $filters['region'] : '';
$currentStaff = isset($filters['staff_id']) ? $filters['staff_id'] : '';
$currentKeyword = isset($filters['keyword']) ? $filters['keyword'] : '';
?>

<style>
/* 篩選列 */
.bc-filter-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.bc-filter-bar > select, .bc-filter-bar > input { width: auto; flex: 0 0 auto; }
@media (max-width: 767px) { .bc-filter-bar > select, .bc-filter-bar > input { flex: 1 1 100%; } }
.bc-region-pills { display: flex; gap: 4px; flex-wrap: wrap; }
.bc-pill {
    display: inline-block; padding: 4px 12px; border-radius: 20px;
    font-size: .85rem; text-decoration: none; color: var(--gray-600);
    background: var(--gray-100); border: 1px solid var(--gray-200);
    transition: all .15s;
}
.bc-pill:hover { background: var(--gray-200); text-decoration: none; color: var(--gray-700); }
.bc-pill-active { background: var(--primary); color: #fff; border-color: var(--primary); }
.bc-pill-active:hover { background: var(--primary); color: #fff; }
.bc-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
.bc-calendar-grid {
    display: grid !important; grid-template-columns: repeat(7, 1fr) !important;
    border: 1px solid var(--gray-200); border-radius: var(--radius);
    background: #fff;
}
.bc-calendar-grid .cal-header {
    padding: 8px; text-align: center; font-weight: 600;
    background: var(--gray-100); border-bottom: 1px solid var(--gray-200);
    font-size: .85rem;
}
.bc-calendar-grid .cal-cell {
    min-height: 110px; padding: 4px; border-right: 1px solid var(--gray-200);
    border-bottom: 1px solid var(--gray-200); position: relative;
}
.bc-calendar-grid .cal-cell:nth-child(7n) { border-right: none; }
.bc-calendar-grid .cal-empty { background: var(--gray-50); }
.bc-calendar-grid .cal-today { background: #e8f0fe; }
.bc-calendar-grid .cal-date {
    display: flex; justify-content: space-between; align-items: center;
    font-size: .85rem; font-weight: 500; margin-bottom: 2px;
}
.bc-day-link { color: inherit; text-decoration: none; font-weight: 600; }
.bc-day-link:hover { color: var(--primary); text-decoration: none; }
.bc-calendar-grid .cal-date-actions { display: flex; gap: 2px; align-items: center; }
.bc-calendar-grid .cal-add {
    width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;
    background: var(--primary); color: #fff; border-radius: 50%;
    font-size: .8rem; text-decoration: none; opacity: 0;
    transition: opacity .15s;
}
.bc-calendar-grid .cal-cell:hover .cal-add { opacity: 1; }
.bc-event {
    display: block; padding: 3px 6px; margin-bottom: 2px;
    border-radius: 4px; font-size: .75rem; text-decoration: none;
    color: var(--gray-700); background: #f8f9fa;
    transition: background .15s;
}
.bc-event:hover { background: #eef1f5; text-decoration: none; }
.bc-event-no_report { background: #fef2f2; }
.bc-event-no_report:hover { background: #fee2e2; }
.bc-badge-no_report { display:inline-block; padding:1px 5px; border-radius:3px; background:#ef4444; color:#fff; font-size:.65rem; font-weight:600; vertical-align:middle; }
.bc-event-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bc-event-info { display: flex; gap: 4px; align-items: center; font-size: .7rem; color: var(--gray-500); }
.bc-event-staff { font-size: .65rem; color: var(--gray-400); }
.bc-type-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.bc-leave-bar { margin-bottom: 3px; }
.bc-leave-tag {
    display: inline-block; font-size: .65rem; padding: 1px 5px;
    background: #e8f5e9; color: #2e7d32; border-radius: 3px;
    margin-right: 2px; margin-bottom: 1px;
}
.bc-calendar-grid .cal-more {
    display: block; font-size: .7rem; color: var(--primary); cursor: pointer;
    padding: 2px 6px; text-align: center; font-weight: 500; text-decoration: none;
}
.bc-calendar-grid .cal-more:hover { text-decoration: underline; }
.bc-badge-type {
    display: inline-block; font-size: .7rem; padding: 1px 6px;
    border-radius: 3px; color: #fff; font-weight: 500;
}
/* 手機版月曆格子（共用 mg-* 樣式） */
.mg-grid { display:grid; grid-template-columns:repeat(7,1fr); background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; width:100%; max-width:100vw; box-sizing:border-box; }
.mg-dow { font-size:.75rem; color:var(--gray-500); padding:8px 0; text-align:center; font-weight:600; border-bottom:1px solid var(--gray-100); }
.mg-cell { min-height:88px; padding:3px; border-bottom:1px solid var(--gray-100); cursor:pointer; position:relative; background:#fff; overflow:hidden; min-width:0; }
.mg-cell:active { background:var(--gray-50); }
.mg-empty { background:var(--gray-50); min-height:88px; }
.mg-today { background:#e8f0fe; }
.mg-today .mg-daynum { background:var(--primary); color:#fff; border-radius:50%; width:24px; height:24px; line-height:24px; display:inline-block; text-align:center; font-weight:700; }
.mg-daynum { font-size:.8rem; font-weight:500; padding:1px 3px; color:var(--gray-600); }
.mg-bar { font-size:.62rem; padding:2px 3px; margin:1px 0; border-radius:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#fff; line-height:1.3; }
.mg-more { font-size:.62rem; color:var(--gray-400); padding:0 3px; }
.mg-leave-dot { position:absolute; top:3px; right:3px; width:7px; height:7px; border-radius:50%; background:#e65100; }
/* 手機版日期詳情卡片 */
.mday-item { display:block; padding:12px; margin-bottom:8px; border-left:3px solid var(--gray-300); background:var(--gray-50); border-radius:0 var(--radius) var(--radius) 0; text-decoration:none; color:inherit; cursor:pointer; }
.mday-item:active { background:var(--gray-100); }

.calendar-mobile { display: none; }
.calendar-desktop { display: block; }
@media (max-width: 767px) {
    .calendar-mobile { display: flex !important; flex-direction:column; gap:4px; overflow:hidden; }
    .calendar-desktop { display: none !important; }
    .hide-mobile { display: none !important; }
    .bc-filter-bar { flex-direction: column; align-items: stretch; }
    .mg-grid { max-width: 100%; }
    .mg-cell { padding: 2px 1px; min-height: 78px; }
    .mg-bar { font-size: .55rem; padding: 1px 2px; }
    .mg-daynum { font-size: .72rem; }
    .mg-dow { font-size: .68rem; padding: 6px 0; }
    .mg-more { font-size: .55rem; }
}
</style>

<!-- 日期彈出視窗 -->
<div id="bcDayPopup" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:1000;background:rgba(0,0,0,.4)">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,.2);min-width:360px;max-width:500px;max-height:80vh;overflow:hidden">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #eee">
            <h3 id="bcPopupTitle" style="margin:0;font-size:1rem"></h3>
            <button onclick="closeDayPopup()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#999">&times;</button>
        </div>
        <div id="bcPopupBody" style="padding:12px 16px;overflow-y:auto;max-height:60vh"></div>
    </div>
</div>
<script>
var bcAllEvents = <?php
    // 建立所有事件的 JSON 資料供 popup 使用
    $allEventsJson = array();
    foreach ($events as $dayNum => $dayEvs) {
        $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $dayNum);
        $allEventsJson[$dateKey] = array();
        foreach ($dayEvs as $ev) {
            $allEventsJson[$dateKey][] = array(
                'id' => $ev['id'],
                'customer' => $ev['customer_name'],
                'staff' => $ev['staff_name'],
                'type' => isset($activityTypes[$ev['activity_type']]) ? $activityTypes[$ev['activity_type']] : $ev['activity_type'],
                'color' => BusinessCalendarModel::activityColor($ev['activity_type']),
                'staff_color' => BusinessCalendarModel::staffColor($ev['staff_name']),
                'phone' => $ev['phone'] ?: '',
                'no_report' => (isset($ev['display_status']) && $ev['display_status'] === 'no_report') ? 1 : 0,
            );
        }
    }
    echo json_encode($allEventsJson, JSON_UNESCAPED_UNICODE);
?>;
function showDayPopup(dateStr) {
    var items = bcAllEvents[dateStr];
    if (!items || items.length === 0) return;
    var d = new Date(dateStr);
    var weekdays = ['日','一','二','三','四','五','六'];
    document.getElementById('bcPopupTitle').textContent = (d.getMonth()+1) + '/' + d.getDate() + ' (' + weekdays[d.getDay()] + ') ' + items.length + ' 筆行程';
    var html = '';
    for (var i = 0; i < items.length; i++) {
        var it = items[i];
        var bg = it.no_report ? '#fef2f2' : '#f8f9fa';
        var bgHover = it.no_report ? '#fee2e2' : '#eef1f5';
        var noReportBadge = it.no_report ? '<span style="display:inline-block;padding:1px 6px;border-radius:3px;background:#ef4444;color:#fff;font-size:.7rem;margin-right:4px">未回報</span>' : '';
        html += '<a href="/business_calendar.php?action=edit&id=' + it.id + '" style="display:block;padding:10px 12px;margin-bottom:6px;border-left:4px solid ' + it.staff_color + ';background:' + bg + ';border-radius:6px;text-decoration:none;color:inherit;transition:background .15s"' +
            ' onmouseover="this.style.background=\'' + bgHover + '\'" onmouseout="this.style.background=\'' + bg + '\'">' +
            '<div style="font-weight:600;font-size:.95rem">' + noReportBadge + it.customer + '</div>' +
            '<div style="display:flex;gap:8px;font-size:.8rem;color:#888;margin-top:2px">' +
            '<span style="display:inline-block;padding:1px 6px;border-radius:3px;background:' + it.color + ';color:#fff;font-size:.7rem">' + it.type + '</span>' +
            '<span>' + it.staff + '</span>' +
            (it.phone ? '<span>' + it.phone + '</span>' : '') +
            '</div></a>';
    }
    document.getElementById('bcPopupBody').innerHTML = html;
    document.getElementById('bcDayPopup').style.display = 'block';
}
function closeDayPopup() { document.getElementById('bcDayPopup').style.display = 'none'; }
document.getElementById('bcDayPopup').addEventListener('click', function(e) { if (e.target === this) closeDayPopup(); });
</script>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>業務行事曆</h2>
    <div class="d-flex gap-1 align-center flex-wrap">
        <a href="/business_calendar.php?action=list" class="btn btn-outline btn-sm">列表檢視</a>
        <a href="/business_calendar.php?action=create" class="btn btn-primary btn-sm">+ 新增行程</a>
    </div>
</div>

<!-- 篩選列 -->
<div class="card mb-1">
    <div class="bc-filter-bar">
        <select id="bcBranchFilter" class="form-control form-control-sm" onchange="bcFilterByBranch(this.value)" style="min-width:140px">
            <option value="">全部分公司</option>
            <?php foreach ($regionOptions as $branchId => $branchName): ?>
            <option value="<?= $branchId ?>" <?= $currentRegion == $branchId ? 'selected' : '' ?>><?= e($branchName) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="bcStaffFilter" class="form-control form-control-sm" onchange="bcApplyFilters()" style="min-width:120px">
            <option value="">全部業務</option>
            <?php foreach ($salespeople as $sp): ?>
            <option value="<?= $sp['id'] ?>" <?= $currentStaff == $sp['id'] ? 'selected' : '' ?>><?= e($sp['real_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" id="bcKeywordFilter" class="form-control form-control-sm" placeholder="搜尋客戶/備註"
               value="<?= e($currentKeyword) ?>" onkeydown="if(event.key==='Enter')bcApplyFilters()" style="min-width:120px;max-width:180px">
        <button type="button" class="btn btn-primary btn-sm" onclick="bcApplyFilters()">搜尋</button>
    </div>
</div>

<!-- 月份切換 -->
<div class="d-flex justify-between align-center mb-1 hide-mobile">
    <a href="/business_calendar.php?year=<?= $prevYear ?>&month=<?= $prevMonth ?>&region=<?= e($currentRegion) ?>&staff_id=<?= e($currentStaff) ?>&keyword=<?= urlencode($currentKeyword) ?>" class="btn btn-outline btn-sm">&laquo; 上月</a>
    <span style="font-weight:600;font-size:1.1rem"><?= $year ?> 年 <?= $month ?> 月</span>
    <a href="/business_calendar.php?year=<?= date('Y') ?>&month=<?= (int)date('m') ?>&region=<?= e($currentRegion) ?>&staff_id=<?= e($currentStaff) ?>&keyword=<?= urlencode($currentKeyword) ?>" class="btn btn-outline btn-sm">今日</a>
    <a href="/business_calendar.php?year=<?= $nextYear ?>&month=<?= $nextMonth ?>&region=<?= e($currentRegion) ?>&staff_id=<?= e($currentStaff) ?>&keyword=<?= urlencode($currentKeyword) ?>" class="btn btn-outline btn-sm">下月 &raquo;</a>
</div>

<!-- 圖例：活動類型（徽章顏色） -->
<div class="d-flex gap-2 flex-wrap mb-1" style="font-size:.8rem">
    <?php foreach ($activityTypes as $atKey => $atLabel): ?>
    <span><span class="bc-dot" style="background:<?= e(BusinessCalendarModel::activityColor($atKey)) ?>"></span> <?= e($atLabel) ?></span>
    <?php endforeach; ?>
    <span style="color:#2e7d32">&#9632; 休假</span>
</div>

<!-- 圖例：業務人員（側邊色條顏色） -->
<div class="d-flex gap-2 flex-wrap mb-1" style="font-size:.8rem;color:var(--gray-600)">
    <span style="color:var(--gray-500)">側邊色條：</span>
    <?php foreach ($salespeople as $sp): ?>
    <span><span class="bc-dot" style="background:<?= e(BusinessCalendarModel::staffColor($sp['real_name'])) ?>"></span><?= e($sp['real_name']) ?></span>
    <?php endforeach; ?>
</div>

<!-- 行事曆 - 桌面版 -->
<div class="calendar-desktop">
    <div class="bc-calendar-grid">
        <div class="cal-header">日</div>
        <div class="cal-header">一</div>
        <div class="cal-header">二</div>
        <div class="cal-header">三</div>
        <div class="cal-header">四</div>
        <div class="cal-header">五</div>
        <div class="cal-header">六</div>

        <?php for ($i = 0; $i < $startWeekday; $i++): ?>
        <div class="cal-cell cal-empty"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dayEvents = isset($events[$day]) ? $events[$day] : array();
            $dayLeaves = isset($leaveByDate[$day]) ? $leaveByDate[$day] : array();
            $isToday = ($dateStr === $today);

            $maxShow = 3;
            $hasMore = count($dayEvents) > $maxShow;
        ?>
        <div class="cal-cell <?= $isToday ? 'cal-today' : '' ?>" data-date="<?= $dateStr ?>" onclick="showDayPopup('<?= $dateStr ?>')" style="cursor:pointer">
            <div class="cal-date">
                <span class="bc-day-link"><?= $day ?></span>
                <span class="cal-date-actions">
                    <a href="/business_calendar.php?action=create&event_date=<?= $dateStr ?>" class="cal-add" title="新增行程" onclick="event.stopPropagation()">+</a>
                </span>
            </div>
            <?php if (!empty($dayLeaves)): ?>
            <div class="bc-leave-bar">
                <?php foreach ($dayLeaves as $lv): ?>
                <span class="bc-leave-tag"><?= e($lv['real_name']) ?> <?= e(isset($lv['leave_type_label']) ? $lv['leave_type_label'] : (isset($lv['leave_type']) ? $lv['leave_type'] : '')) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php
            $shown = 0;
            foreach ($dayEvents as $ev):
                if ($shown >= $maxShow && $hasMore) break;
                $shown++;
                $evColor = BusinessCalendarModel::activityColor($ev['activity_type']);
                $evStaffColor = BusinessCalendarModel::staffColor($ev['staff_name']);
                $evLabel = isset($activityTypes[$ev['activity_type']]) ? $activityTypes[$ev['activity_type']] : $ev['activity_type'];
            ?>
            <?php
            $evTime = !empty($ev['start_time']) ? substr($ev['start_time'], 0, 5) : '';
            $evDispStatus = isset($ev['display_status']) ? $ev['display_status'] : (isset($ev['status']) ? $ev['status'] : 'planned');
            ?>
            <a href="/business_calendar.php?action=edit&id=<?= $ev['id'] ?>" class="bc-event<?= $evDispStatus === 'no_report' ? ' bc-event-no_report' : '' ?>" style="border-left:3px solid <?= e($evStaffColor) ?>">
                <div class="bc-event-title">
                    <?php if ($evDispStatus === 'no_report'): ?><span class="bc-badge-no_report" title="尚未回報">未回報</span> <?php endif; ?>
                    <?= e(mb_substr($ev['customer_name'], 0, 8)) ?><?php if ($evTime): ?> <span style="color:#e65100;font-weight:600"><?= $evTime ?></span><?php endif; ?>
                </div>
                <div class="bc-event-staff"><?= e($ev['staff_name']) ?></div>
            </a>
            <?php endforeach; ?>
            <?php if ($hasMore): ?>
            <a href="javascript:void(0)" class="cal-more" onclick="showDayPopup('<?= $dateStr ?>')">+<?= count($dayEvents) - $maxShow ?> 更多</a>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- 行事曆 - 手機版月曆格子 -->
<?php
$bcMobileData = array();
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $dayEvents = isset($events[$day]) ? $events[$day] : array();
    $dayLeaves = isset($leaveByDate[$day]) ? $leaveByDate[$day] : array();
    $items = array();
    foreach ($dayEvents as $ev) {
        $items[] = array(
            'id' => $ev['id'], 'customer' => $ev['customer_name'],
            'type' => isset($activityTypes[$ev['activity_type']]) ? $activityTypes[$ev['activity_type']] : $ev['activity_type'],
            'color' => BusinessCalendarModel::activityColor($ev['activity_type']),
            'staff_color' => BusinessCalendarModel::staffColor($ev['staff_name']),
            'staff' => $ev['staff_name'],
            'phone' => !empty($ev['phone']) ? $ev['phone'] : '',
            'region' => !empty($ev['region']) ? $ev['region'] : '',
            'time' => !empty($ev['start_time']) ? substr($ev['start_time'], 0, 5) : '',
            'address' => !empty($ev['address']) ? $ev['address'] : '',
            'note' => !empty($ev['note']) ? $ev['note'] : '',
            'no_report' => (isset($ev['display_status']) && $ev['display_status'] === 'no_report') ? 1 : 0,
        );
    }
    $leaves = array();
    foreach ($dayLeaves as $lv) { $leaves[] = $lv['real_name'] . ' ' . $lv['leave_type_label']; }
    $bcMobileData[$dateStr] = array('events' => $items, 'leaves' => $leaves, 'count' => count($items));
}
$bcFP = '';
if ($currentRegion) $bcFP .= '&region=' . urlencode($currentRegion);
if ($currentStaff) $bcFP .= '&staff_id=' . urlencode($currentStaff);
?>
<div class="calendar-mobile" style="flex-direction:column;overflow:hidden;max-width:100vw">
    <div class="mg-grid" style="width:100%;table-layout:fixed">
        <div class="mg-dow">日</div><div class="mg-dow">一</div><div class="mg-dow">二</div>
        <div class="mg-dow">三</div><div class="mg-dow">四</div><div class="mg-dow">五</div><div class="mg-dow">六</div>
        <?php for ($i = 0; $i < $startWeekday; $i++): ?><div class="mg-cell mg-empty"></div><?php endfor; ?>
        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $isToday = ($dateStr === $today);
            $dayEvents = isset($events[$day]) ? $events[$day] : array();
            $dayLeaves = isset($leaveByDate[$day]) ? $leaveByDate[$day] : array();
        ?>
        <div class="mg-cell <?= $isToday ? 'mg-today' : '' ?>" data-date="<?= $dateStr ?>" onclick="bcOpenDay('<?= $dateStr ?>')">
            <div class="mg-daynum"><?= $day ?></div>
            <?php $shown = 0; foreach ($dayEvents as $ev):
                if ($shown >= 3) break; $shown++;
                $evColor = BusinessCalendarModel::activityColor($ev['activity_type']);
                $evT = !empty($ev['start_time']) ? substr($ev['start_time'], 0, 5) : '';
            ?>
            <div class="mg-bar" style="background:<?= e($evColor) ?>"><?php if ($evT): ?><?= $evT ?> <?php endif; ?><?= e(mb_substr($ev['customer_name'], 0, 5)) ?></div>
            <?php endforeach; ?>
            <?php if (count($dayEvents) > 3): ?>
            <div class="mg-more">+<?= count($dayEvents) - 3 ?></div>
            <?php endif; ?>
            <?php if (!empty($dayLeaves)): ?>
            <div class="mg-leave-dot"></div>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>

    <div class="d-flex justify-between align-center" style="padding:8px 0;gap:6px">
        <a href="/business_calendar.php?year=<?= $prevYear ?>&month=<?= $prevMonth ?><?= $bcFP ?>" class="btn btn-outline btn-sm" style="flex:1;text-align:center">&laquo; 上月</a>
        <a href="/business_calendar.php?year=<?= date('Y') ?>&month=<?= (int)date('m') ?><?= $bcFP ?>" class="btn btn-outline btn-sm" style="flex:1;text-align:center" onclick="<?php if ($today >= sprintf('%04d-%02d-01', $year, $month) && $today <= sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth)): ?>event.preventDefault();bcOpenDay('<?= $today ?>');<?php endif; ?>">今天</a>
        <a href="/business_calendar.php?year=<?= $nextYear ?>&month=<?= $nextMonth ?><?= $bcFP ?>" class="btn btn-outline btn-sm" style="flex:1;text-align:center">下月 &raquo;</a>
    </div>
</div>

<!-- 手機日期詳情 overlay -->
<div id="bcMDayOverlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.4);z-index:1000;display:none;align-items:flex-end;justify-content:center">
    <div style="background:#fff;width:100%;max-height:85vh;border-radius:16px 16px 0 0;display:flex;flex-direction:column">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--gray-200);flex-shrink:0">
            <span id="bcMDayTitle" style="font-weight:700;font-size:1.05rem"></span>
            <div style="display:flex;gap:8px;align-items:center">
                <span id="bcMDayAdd"></span>
                <button onclick="bcCloseDay()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--gray-500)">&times;</button>
            </div>
        </div>
        <div id="bcMDayBody" style="overflow-y:auto;-webkit-overflow-scrolling:touch;padding:12px 16px;flex:1;min-height:0"></div>
    </div>
</div>

<script>
var bcMData = <?= json_encode($bcMobileData, JSON_UNESCAPED_UNICODE) ?>;
var bcWD = ['日','一','二','三','四','五','六'];

function bcOpenDay(dateStr) {
    var data = bcMData[dateStr];
    if (!data) return;
    var d = new Date(dateStr.replace(/-/g, '/'));
    var p = dateStr.split('-');
    document.getElementById('bcMDayTitle').textContent = parseInt(p[1]) + '月' + parseInt(p[2]) + '日 ' + bcWD[d.getDay()];
    document.getElementById('bcMDayAdd').innerHTML = '<a href="/business_calendar.php?action=create&event_date=' + dateStr + '" class="btn btn-primary btn-sm" style="padding:4px 12px;font-size:.8rem">+ 行程</a>';

    var html = '';
    if (data.leaves.length) {
        html += '<div style="padding:8px 0;font-size:.82rem;color:#2e7d32">&#9632; 休假：' + bcE(data.leaves.join('、')) + '</div>';
    }
    if (!data.events.length && !data.leaves.length) {
        html += '<div style="text-align:center;color:var(--gray-400);padding:32px 0">無行程</div>';
    }
    for (var i = 0; i < data.events.length; i++) {
        var ev = data.events[i];
        var bgStyle = ev.no_report ? 'background:#fef2f2;' : '';
        var noReportBadge = ev.no_report ? '<span style="display:inline-block;padding:1px 6px;border-radius:3px;background:#ef4444;color:#fff;font-size:.7rem;margin-right:4px">未回報</span>' : '';
        html += '<div class="mday-item" style="' + bgStyle + 'border-left-color:' + ev.staff_color + ';cursor:pointer" onclick="location.href=\'/business_calendar.php?action=edit&id=' + ev.id + '\'">';
        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">';
        html += '<span style="font-weight:600;font-size:.95rem">' + noReportBadge + bcE(ev.customer) + '</span>';
        html += '<span class="bc-badge-type" style="background:' + ev.color + '">' + bcE(ev.type) + '</span>';
        html += '</div>';
        html += '<div style="display:flex;flex-wrap:wrap;gap:8px;font-size:.82rem;color:var(--gray-600)">';
        html += '<span>' + bcE(ev.staff) + '</span>';
        if (ev.time) html += '<span>' + bcE(ev.time) + '</span>';
        if (ev.region) html += '<span>' + bcE(ev.region) + '</span>';
        if (ev.phone) html += '<span onclick="event.stopPropagation();location.href=\'tel:' + ev.phone + '\'">' + bcE(ev.phone) + '</span>';
        html += '</div>';
        if (ev.address) {
            html += '<div style="font-size:.8rem;color:var(--gray-500);margin-top:6px">' + bcE(ev.address);
            html += ' <span onclick="event.stopPropagation();window.open(\'https://maps.google.com/?q=' + encodeURIComponent(ev.address) + '\')" style="color:var(--primary);font-weight:600;cursor:pointer">導航</span>';
            html += '</div>';
        }
        html += '</div>';
    }
    document.getElementById('bcMDayBody').innerHTML = html;
    var overlay = document.getElementById('bcMDayOverlay');
    overlay.style.display = 'flex';
}

function bcCloseDay() { document.getElementById('bcMDayOverlay').style.display = 'none'; }
function bcE(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
document.getElementById('bcMDayOverlay').addEventListener('click', function(e) { if (e.target === this) bcCloseDay(); });
</script>

<script>
function bcFilterByBranch(branchId) {
    var url = '/business_calendar.php?year=<?= $year ?>&month=<?= $month ?>';
    if (branchId) url += '&region=' + branchId;
    var staff = document.getElementById('bcStaffFilter').value;
    if (staff) url += '&staff_id=' + staff;
    var keyword = document.getElementById('bcKeywordFilter').value;
    if (keyword) url += '&keyword=' + encodeURIComponent(keyword);
    window.location.href = url;
}
function bcApplyFilters() {
    var branch = document.getElementById('bcBranchFilter').value;
    var staff = document.getElementById('bcStaffFilter').value;
    var keyword = document.getElementById('bcKeywordFilter').value;
    var url = '/business_calendar.php?year=<?= $year ?>&month=<?= $month ?>';
    if (branch) url += '&region=' + branch;
    if (staff) url += '&staff_id=' + staff;
    if (keyword) url += '&keyword=' + encodeURIComponent(keyword);
    window.location.href = url;
}
</script>
