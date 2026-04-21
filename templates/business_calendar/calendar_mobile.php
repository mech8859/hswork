<?php
// 業務行事曆手機版：列表優先，可切換月曆格
// 資料來源與桌面版共用：$events (day=>rows) / $year / $month / $leaveByDate / $salespeople / $regionOptions / $filters
$today = date('Y-m-d');
$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$activityTypes = BusinessCalendarModel::activityTypes();
$regionOptions = BusinessCalendarModel::regionOptions();
$currentRegion = isset($filters['region']) ? $filters['region'] : '';
$currentStaff = isset($filters['staff_id']) ? $filters['staff_id'] : '';
$currentKeyword = isset($filters['keyword']) ? $filters['keyword'] : '';

$filterDate = isset($_GET['date']) ? $_GET['date'] : '';
$weekdayLabels = array('日','一','二','三','四','五','六');
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$startWeekday = (int)date('w', $firstDay);
$defaultMode = isset($_GET['mode']) ? $_GET['mode'] : 'list';

// 列表資料：依日期分組（篩 filterDate）
$listByDate = array();
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
    if ($filterDate && $dateStr !== $filterDate) continue;
    $dayEvents = isset($events[$d]) ? $events[$d] : array();
    if (empty($dayEvents)) continue;
    $listByDate[$dateStr] = $dayEvents;
}

// URL helper 保留篩選
function _bcMobileUrl($extra = array()) {
    global $year, $month, $currentRegion, $currentStaff, $currentKeyword, $filterDate;
    $params = array('year' => $year, 'month' => $month);
    if ($currentRegion) $params['region'] = $currentRegion;
    if ($currentStaff) $params['staff_id'] = $currentStaff;
    if ($currentKeyword) $params['keyword'] = $currentKeyword;
    if ($filterDate) $params['date'] = $filterDate;
    foreach ($extra as $k => $v) {
        if ($v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    return '/business_calendar.php?' . http_build_query($params);
}
?>
<style>
.bcm { max-width: 640px; margin: 0 auto; padding-bottom: 80px; }
.bcm-sticky { position:sticky; top:56px; z-index:90; background:#f8f9fa; padding:8px 4px 6px; margin:0 -4px 10px; box-shadow:0 2px 4px rgba(0,0,0,.06); }
.bcm-sticky .bcm-tabs { margin-bottom:6px; }
.bcm-sticky .bcm-filters { margin-bottom:6px; }
.bcm-sticky .bcm-month-nav { margin-bottom:0; }
.bcm-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding: 4px 0; }
.bcm-title { font-size:1.1rem; font-weight:700; }
.bcm-tabs { display:flex; background:#f1f3f5; border-radius:8px; padding:3px; }
.bcm-tab { flex:1; text-align:center; padding:8px 0; font-size:.9rem; border-radius:6px; color:#666; text-decoration:none; font-weight:500; }
.bcm-tab.active { background:#fff; color:#1565c0; box-shadow:0 1px 3px rgba(0,0,0,.1); font-weight:600; }
.bcm-filters { display:flex; gap:6px; flex-wrap:wrap; }
.bcm-filters select, .bcm-filters input { flex:1; min-width:0; font-size:.85rem; padding:8px; }
.bcm-keyword-wrap { display:flex; gap:4px; flex:1 1 100%; }
.bcm-month-nav { display:flex; justify-content:space-between; align-items:center; gap:4px; }
.bcm-month-nav a { flex:1; padding:10px 8px; background:#fff; border:1px solid #ddd; border-radius:6px; text-align:center; text-decoration:none; color:#333; font-size:.9rem; }
.bcm-month-nav a.current { font-weight:700; flex:1.5; background:#e3f2fd; border-color:#1565c0; color:#1565c0; }
.bcm-month-nav a:active { background:#f0f0f0; }

/* 列表模式 */
.bcm-date-group { margin-bottom:14px; }
.bcm-date-header { display:flex; align-items:center; justify-content:space-between; font-size:.95rem; font-weight:700; color:#1565c0; padding:6px 2px; border-bottom:2px solid #e3f2fd; margin-bottom:8px; }
.bcm-date-header.today { color:#e65100; border-color:#ffcc80; }
.bcm-date-count { font-size:.75rem; color:#888; font-weight:normal; }
.bcm-leave-banner { background:#f1f8e9; color:#2e7d32; font-size:.8rem; padding:6px 10px; border-radius:4px; margin-bottom:6px; border-left:3px solid #66bb6a; }

.bcm-card { display:block; background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px; margin-bottom:8px; color:inherit; border-left:4px solid #1565c0; cursor:pointer; }
.bcm-card:active { background:#fafafa; transform:scale(.995); }
.bcm-card-row { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:4px; }
.bcm-customer { font-size:.98rem; font-weight:700; color:#111; }
.bcm-time { font-size:.8rem; color:#1565c0; font-weight:600; white-space:nowrap; }
.bcm-type { display:inline-block; padding:2px 8px; border-radius:4px; font-size:.72rem; font-weight:600; color:#fff; margin-right:6px; vertical-align:middle; }
.bcm-status { display:inline-block; padding:2px 8px; border-radius:4px; font-size:.72rem; font-weight:600; color:#fff; margin-right:6px; vertical-align:middle; }
.bcm-status-planned { background:#90a4ae; }
.bcm-status-completed { background:#22c55e; }
.bcm-status-cancelled { background:#6b7280; }
.bcm-status-no_report { background:#ef4444; }
.bcm-meta { font-size:.82rem; color:#555; margin-top:4px; line-height:1.5; }
.bcm-meta-line { display:flex; gap:4px; align-items:flex-start; }
.bcm-meta-label { color:#888; min-width:22px; flex-shrink:0; }
.bcm-addr { color:#1565c0; text-decoration:none; }
.bcm-tel { color:#1565c0; text-decoration:none; }
.bcm-note { margin-top:6px; padding:6px 8px; background:#fffbea; border-left:3px solid #f9a825; font-size:.82rem; color:#6a4800; border-radius:0 4px 4px 0; white-space:pre-wrap; word-break:break-word; }
.bcm-result { margin-top:4px; padding:6px 8px; background:#e8f5e9; border-left:3px solid #66bb6a; font-size:.82rem; color:#1b5e20; border-radius:0 4px 4px 0; white-space:pre-wrap; word-break:break-word; }
.bcm-no-data { text-align:center; color:#999; padding:40px 20px; font-size:.9rem; background:#fff; border-radius:8px; }

/* 月曆格模式 */
.bcm-grid { background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.bcm-grid-head { display:grid; grid-template-columns:repeat(7, 1fr); background:#f5f5f5; font-size:.75rem; font-weight:600; text-align:center; }
.bcm-grid-head > div { padding:8px 0; }
.bcm-grid-head > div:first-child { color:#c62828; }
.bcm-grid-head > div:last-child { color:#1565c0; }
.bcm-grid-body { display:grid; grid-template-columns:repeat(7, 1fr); }
.bcm-cell { aspect-ratio:1; border-top:1px solid #eee; border-right:1px solid #eee; padding:4px; position:relative; color:#333; display:flex; flex-direction:column; align-items:center; justify-content:flex-start; text-decoration:none; }
.bcm-cell:nth-child(7n) { border-right:none; }
.bcm-cell:active { background:#f0f0f0; }
.bcm-cell .day-num { font-size:.85rem; font-weight:600; }
.bcm-cell.today .day-num { background:#1565c0; color:#fff; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; }
.bcm-cell.has-events .day-num { color:#1565c0; }
.bcm-cell .cnt-dot { font-size:.65rem; color:#666; margin-top:2px; padding:1px 5px; border-radius:8px; background:#e3f2fd; color:#1565c0; font-weight:600; }
.bcm-cell.outside { opacity:.25; pointer-events:none; }
.bcm-cell .leave-dot { position:absolute; top:2px; right:2px; width:6px; height:6px; border-radius:50%; background:#66bb6a; }

/* 浮動按鈕 */
.bcm-fab { position:fixed; right:16px; bottom:16px; width:56px; height:56px; border-radius:50%; background:#1565c0; color:#fff; font-size:1.8rem; box-shadow:0 4px 12px rgba(21,101,192,.4); z-index:100; text-decoration:none; display:flex; align-items:center; justify-content:center; }
.bcm-fab:active { transform:scale(.95); }

.bcm-filter-clear { display:inline-block; font-size:.8rem; background:#e3f2fd; color:#1565c0; padding:4px 10px; border-radius:14px; text-decoration:none; margin-bottom:8px; }
</style>

<div class="bcm">
    <div class="bcm-head">
        <div class="bcm-title"><?= $year ?>/<?= $month ?> 業務行事曆</div>
    </div>

    <div class="bcm-sticky">
        <!-- 模式切換 -->
        <div class="bcm-tabs">
            <a href="<?= _bcMobileUrl(array('mode' => 'list')) ?>" class="bcm-tab <?= $defaultMode !== 'grid' ? 'active' : '' ?>">📋 列表</a>
            <a href="<?= _bcMobileUrl(array('mode' => 'grid', 'date' => null)) ?>" class="bcm-tab <?= $defaultMode === 'grid' ? 'active' : '' ?>">📅 月曆</a>
        </div>

        <!-- 篩選 -->
        <div class="bcm-filters">
            <select id="bcmBranchFilter" onchange="bcmChangeFilter('region', this.value)">
                <option value="">全部分公司</option>
                <?php foreach ($regionOptions as $branchId => $branchName): ?>
                <option value="<?= $branchId ?>" <?= $currentRegion == $branchId ? 'selected' : '' ?>><?= e($branchName) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="bcmStaffFilter" onchange="bcmChangeFilter('staff_id', this.value)">
                <option value="">全部業務</option>
                <?php foreach ($salespeople as $sp): ?>
                <option value="<?= $sp['id'] ?>" <?= $currentStaff == $sp['id'] ? 'selected' : '' ?>><?= e($sp['real_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="bcm-keyword-wrap">
                <input type="text" id="bcmKeyword" placeholder="搜尋客戶/備註" value="<?= e($currentKeyword) ?>" onkeydown="if(event.key==='Enter')bcmApplyKeyword()">
                <button type="button" onclick="bcmApplyKeyword()" style="padding:8px 14px;background:#1565c0;color:#fff;border:none;border-radius:6px;font-size:.85rem">搜尋</button>
            </div>
        </div>

        <!-- 月份切換 -->
        <div class="bcm-month-nav">
            <a href="<?= _bcMobileUrl(array('year' => $prevYear, 'month' => $prevMonth, 'date' => null)) ?>">‹ <?= $prevMonth ?>月</a>
            <a href="<?= _bcMobileUrl(array('year' => (int)date('Y'), 'month' => (int)date('n'), 'date' => null)) ?>#today" class="current" onclick="bcmGoToday(event)">今天</a>
            <a href="<?= _bcMobileUrl(array('year' => $nextYear, 'month' => $nextMonth, 'date' => null)) ?>"><?= $nextMonth ?>月 ›</a>
        </div>
    </div>

    <?php if ($filterDate): ?>
    <a class="bcm-filter-clear" href="<?= _bcMobileUrl(array('date' => null)) ?>">✕ 清除日期篩選（只看 <?= $filterDate ?>）</a>
    <?php endif; ?>

    <?php if ($defaultMode === 'grid'): ?>
    <!-- ========== 月曆格模式 ========== -->
    <div class="bcm-grid">
        <div class="bcm-grid-head">
            <?php foreach ($weekdayLabels as $w): ?><div><?= $w ?></div><?php endforeach; ?>
        </div>
        <div class="bcm-grid-body">
            <?php
            for ($i = 0; $i < $startWeekday; $i++) echo '<div class="bcm-cell outside"></div>';
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $cnt = isset($events[$d]) ? count($events[$d]) : 0;
                $hasLeave = !empty($leaveByDate[$d]);
                $classes = array('bcm-cell');
                if ($dateStr === $today) $classes[] = 'today';
                if ($cnt > 0) $classes[] = 'has-events';
                $href = _bcMobileUrl(array('mode' => 'list', 'date' => $dateStr));
                echo '<a class="' . implode(' ', $classes) . '" href="' . e($href) . '">';
                echo '<span class="day-num">' . $d . '</span>';
                if ($cnt > 0) echo '<span class="cnt-dot">' . $cnt . '</span>';
                if ($hasLeave) echo '<span class="leave-dot" title="有人休假"></span>';
                echo '</a>';
            }
            ?>
        </div>
    </div>
    <?php else: ?>
    <!-- ========== 列表模式 ========== -->
    <?php if (empty($listByDate)): ?>
    <div class="bcm-no-data"><?= $filterDate ? '此日期無行程' : '本月無行程' ?></div>
    <?php else: ?>
        <?php foreach ($listByDate as $dateStr => $rows): ?>
        <?php
            $d = strtotime($dateStr);
            $isToday = ($dateStr === $today);
            $weekLabel = $weekdayLabels[(int)date('w', $d)];
            $dayNum = (int)date('j', $d);
            $dayLeaves = isset($leaveByDate[$dayNum]) ? $leaveByDate[$dayNum] : array();
        ?>
        <div class="bcm-date-group" id="d-<?= e($dateStr) ?>" <?= $isToday ? 'data-today="1"' : '' ?>>
            <div class="bcm-date-header <?= $isToday ? 'today' : '' ?>">
                <span><?= date('n/j', $d) ?>（<?= $weekLabel ?>）<?= $isToday ? '· 今天' : '' ?></span>
                <span class="bcm-date-count"><?= count($rows) ?> 筆</span>
            </div>
            <?php if (!empty($dayLeaves)): ?>
            <div class="bcm-leave-banner">
                ■ 休假：<?php foreach ($dayLeaves as $i => $lv): ?><?= e($lv['real_name']) ?> <?= e(isset($lv['leave_type_label']) ? $lv['leave_type_label'] : (isset($lv['leave_type']) ? $lv['leave_type'] : '')) ?><?= $i < count($dayLeaves)-1 ? '、' : '' ?><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php foreach ($rows as $ev): ?>
            <?php
                $evId = (int)$ev['id'];
                $cardUrl = '/business_calendar.php?action=edit&id=' . $evId;
                $timeStr = '';
                if (!empty($ev['start_time'])) {
                    $timeStr = substr($ev['start_time'], 0, 5);
                    if (!empty($ev['end_time'])) $timeStr .= '~' . substr($ev['end_time'], 0, 5);
                }
                $typeLabel = isset($activityTypes[$ev['activity_type']]) ? $activityTypes[$ev['activity_type']] : $ev['activity_type'];
                $typeColor = BusinessCalendarModel::activityColor($ev['activity_type']);

                // 未回報判定：planned + 無 result + (今日17:00後 或 過期)
                $evStatus = isset($ev['status']) ? $ev['status'] : 'planned';
                $hasResult = !empty($ev['result']);
                if ($evStatus === 'planned' && !$hasResult) {
                    if ($dateStr < $today) {
                        $evStatus = 'no_report';
                    } elseif ($dateStr === $today && (int)date('H') >= 17) {
                        $evStatus = 'no_report';
                    }
                }
                $statusLabels = array('planned'=>'未進行','completed'=>'已完成','cancelled'=>'取消','no_report'=>'未回報');
                $statusText = isset($statusLabels[$evStatus]) ? $statusLabels[$evStatus] : $evStatus;
                $showStatus = ($evStatus !== 'planned' || $hasResult);
            ?>
            <div class="bcm-card" style="border-left-color:<?= e($typeColor) ?>" onclick="location.href='<?= e($cardUrl) ?>'">
                <div class="bcm-card-row">
                    <div style="flex:1;min-width:0">
                        <div class="bcm-customer">
                            <span class="bcm-type" style="background:<?= e($typeColor) ?>"><?= e($typeLabel) ?></span>
                            <?php if ($showStatus): ?>
                            <span class="bcm-status bcm-status-<?= e($evStatus) ?>"><?= e($statusText) ?></span>
                            <?php endif; ?>
                            <?= e($ev['customer_name'] ?: '(無客戶)') ?>
                        </div>
                    </div>
                    <?php if ($timeStr): ?>
                    <div class="bcm-time"><?= $timeStr ?></div>
                    <?php endif; ?>
                </div>
                <div class="bcm-meta">
                    <?php if (!empty($ev['staff_name'])): ?>
                    <div class="bcm-meta-line"><span class="bcm-meta-label">👤</span><span><?= e($ev['staff_name']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($ev['phone'])): ?>
                    <div class="bcm-meta-line"><span class="bcm-meta-label">📞</span><a class="bcm-tel" href="tel:<?= e($ev['phone']) ?>" onclick="event.stopPropagation()"><?= e($ev['phone']) ?></a></div>
                    <?php endif; ?>
                    <?php if (!empty($ev['address'])): ?>
                    <div class="bcm-meta-line">
                        <span class="bcm-meta-label">📍</span>
                        <a class="bcm-addr" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($ev['address']) ?>" target="_blank" onclick="event.stopPropagation()"><?= e($ev['address']) ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($ev['region'])): ?>
                    <div class="bcm-meta-line"><span class="bcm-meta-label">🏢</span><span style="color:#888"><?= e($ev['region']) ?></span></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($ev['note'])): ?>
                <div class="bcm-note"><?= e($ev['note']) ?></div>
                <?php endif; ?>
                <?php if (!empty($ev['result'])): ?>
                <div class="bcm-result">✅ <?= e($ev['result']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>

<a href="/business_calendar.php?action=create" class="bcm-fab" title="新增行程">+</a>

<script>
function bcmChangeFilter(key, value) {
    var url = new URL(window.location.href);
    if (value) url.searchParams.set(key, value);
    else url.searchParams.delete(key);
    url.searchParams.delete('date');
    window.location.href = url.toString();
}
function bcmApplyKeyword() {
    var url = new URL(window.location.href);
    var kw = document.getElementById('bcmKeyword').value.trim();
    if (kw) url.searchParams.set('keyword', kw);
    else url.searchParams.delete('keyword');
    url.searchParams.delete('date');
    window.location.href = url.toString();
}
function bcmScrollToToday() {
    var today = <?= json_encode($today) ?>;
    var el = document.querySelector('[data-today="1"]');
    if (!el) {
        var groups = document.querySelectorAll('.bcm-date-group[id^="d-"]');
        for (var i = 0; i < groups.length; i++) {
            var dstr = groups[i].id.substring(2);
            if (dstr >= today) { el = groups[i]; break; }
        }
    }
    if (!el) return false;
    var navbar = document.querySelector('.navbar');
    var sticky = document.querySelector('.bcm-sticky');
    var offset = (navbar ? navbar.offsetHeight : 56) + (sticky ? sticky.offsetHeight : 0) + 8;
    var y = el.getBoundingClientRect().top + window.pageYOffset - offset;
    window.scrollTo({ top: y, behavior: 'smooth' });
    return true;
}
function bcmGoToday(e) {
    var curYear = <?= (int)date('Y') ?>, curMonth = <?= (int)date('n') ?>;
    var pageYear = <?= (int)$year ?>, pageMonth = <?= (int)$month ?>;
    if (curYear === pageYear && curMonth === pageMonth) {
        if (bcmScrollToToday()) e.preventDefault();
    }
}
document.addEventListener('DOMContentLoaded', function() {
    var isCurMonth = (<?= (int)date('Y') ?> === <?= (int)$year ?> && <?= (int)date('n') ?> === <?= (int)$month ?>);
    var hasFilter = <?= $filterDate ? 'true' : 'false' ?>;
    if (isCurMonth && !hasFilter) setTimeout(bcmScrollToToday, 50);
});
</script>
