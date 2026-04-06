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

// 容量閥值
$capYellow = max(1, (int)ceil($totalEngineers * 0.6));
$capRed    = $totalEngineers;
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2><?= $year ?> 年 <?= $month ?> 月 工程行事曆</h2>
    <div class="d-flex gap-1 align-center flex-wrap">
        <?php if (Auth::hasPermission('schedule.manage')): ?>
        <?php if (count($branchList) > 1): ?>
        <div class="cal-filter-wrap">
            <select id="branchFilter" class="form-control form-control-sm" onchange="filterByBranch(this.value)" style="min-width:120px">
                <option value="0">全部分公司</option>
                <?php foreach ($branchList as $br): ?>
                <option value="<?= $br['id'] ?>" <?= $filterBranchId == $br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="cal-filter-wrap">
            <select id="personFilter" class="form-control form-control-sm" onchange="filterByPerson(this.value)" style="min-width:120px">
                <option value="0">全部人員</option>
                <?php foreach ($engineerList as $eng): ?>
                <option value="<?= $eng['id'] ?>" <?= $filterUserId == $eng['id'] ? 'selected' : '' ?>><?= e($eng['real_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if (Auth::hasPermission('schedule.manage')): ?>
        <a href="/schedule.php?action=create" class="btn btn-primary btn-sm">+ 新增排工</a>
        <?php endif; ?>
    </div>
</div>

<!-- 圖例 -->
<div class="d-flex gap-2 flex-wrap mb-1" style="font-size:.8rem">
    <span><span class="status-dot status-open"></span> 可排工</span>
    <span><span class="status-dot status-partial"></span> 已排</span>
    <span><span class="status-dot status-full"></span> 已滿</span>
    <span><span class="status-dot status-closed"></span> 不可排工</span>
    <span style="color:#e65100">🏖 休假</span>
    <span class="text-muted">共 <?= $totalEngineers ?> 位工程人員</span>
    <?php if ($filterBranchId): ?>
    <span class="badge badge-primary"><?= e(array_reduce($branchList, function($carry, $b) use ($filterBranchId) { return $b['id'] == $filterBranchId ? $b['name'] : $carry; }, '')) ?></span>
    <?php endif; ?>
    <?php if ($filterUserId): ?>
    <span class="badge badge-primary">篩選中：<?= e(array_reduce($engineerList, function($carry, $e) use ($filterUserId) { return $e['id'] == $filterUserId ? $e['real_name'] : $carry; }, '')) ?></span>
    <?php endif; ?>
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
<div class="d-flex justify-between align-center mb-1 hide-mobile">
    <a href="/schedule.php?year=<?= $prevYear ?>&month=<?= $prevMonth ?><?= $filterBranchId ? '&branch_id='.$filterBranchId : '' ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>" class="btn btn-outline btn-sm">&laquo; 上月</a>
    <a href="/schedule.php?year=<?= date('Y') ?>&month=<?= date('m') ?><?= $filterBranchId ? '&branch_id='.$filterBranchId : '' ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>" class="btn btn-outline btn-sm">今天</a>
    <a href="/schedule.php?year=<?= $nextYear ?>&month=<?= $nextMonth ?><?= $filterBranchId ? '&branch_id='.$filterBranchId : '' ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>" class="btn btn-outline btn-sm">下月 &raquo;</a>
</div>

<?php
// 預先計算每日狀態供重複使用
$dayStatusData = array();
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $dow = ($startWeekday + $day - 1) % 7;
    $isSunday = ($dow === 0);

    // 是否開放
    $isOpen = $model->isDayOpen($dateStr, $daySettings);
    $setting = isset($daySettings[$dateStr]) ? $daySettings[$dateStr] : null;
    $maxTeams = $setting ? $setting['max_teams'] : null;
    $maxEng = $setting ? $setting['max_engineers'] : null;
    $settingNote = $setting ? $setting['note'] : '';

    // 已用容量
    $usedEng = isset($dailyCapacity[$dateStr]) ? $dailyCapacity[$dateStr] : 0;
    $usedTeams = isset($dailyTeams[$dateStr]) ? $dailyTeams[$dateStr] : 0;

    // 判斷狀態
    // closed / open / partial / full
    if (!$isOpen) {
        $status = 'closed';
        $statusLabel = '不可排工';
    } else {
        // 檢查是否已滿
        $isFull = false;
        if ($maxTeams !== null && $usedTeams >= $maxTeams) $isFull = true;
        if ($maxEng !== null && $usedEng >= $maxEng) $isFull = true;
        if ($maxTeams === null && $maxEng === null && $usedEng >= $totalEngineers) $isFull = true;

        if ($isFull) {
            $status = 'full';
            $statusLabel = '已滿';
        } elseif ($usedTeams > 0) {
            $status = 'partial';
            $statusLabel = '已排';
        } else {
            $status = 'open';
            $statusLabel = '可排工';
        }
    }

    // 容量文字 + 百分比
    $capText = '';
    if ($isOpen && ($usedEng > 0 || $usedTeams > 0)) {
        $capMax = ($maxEng !== null) ? $maxEng : $totalEngineers;
        $pct = ($capMax > 0) ? (int)round($usedEng / $capMax * 100) : 0;
        if ($maxTeams !== null) {
            $capText = $usedTeams . '/' . $maxTeams . '組 ';
        }
        $capText .= $usedEng . '/' . $capMax . '人 ' . $pct . '%';
    }

    $dayStatusData[$dateStr] = array(
        'isOpen' => $isOpen,
        'status' => $status,
        'statusLabel' => $statusLabel,
        'capText' => $capText,
        'maxTeams' => $maxTeams,
        'maxEng' => $maxEng,
        'usedTeams' => $usedTeams,
        'usedEng' => $usedEng,
        'note' => $settingNote,
        'isSunday' => $isSunday,
    );
}
?>

<!-- 行事曆 - 桌面版 -->
<div class="calendar-desktop hide-mobile">
    <div class="calendar-grid">
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
            $daySchedules = isset($schedulesByDate[$dateStr]) ? $schedulesByDate[$dateStr] : array();
            $isToday = ($dateStr === $today);
            $isWeekend = in_array(($startWeekday + $day - 1) % 7, array(0, 6));
            $ds_info = $dayStatusData[$dateStr];

            // 當日休假人員（去重）
            $dayLeaves = isset($leavesByDate[$dateStr]) ? $leavesByDate[$dateStr] : array();
            $leaveNames = array();
            foreach ($dayLeaves as $lv) {
                if (!isset($leaveNames[$lv['user_id']])) {
                    $leaveNames[$lv['user_id']] = $lv['real_name'];
                }
            }

            // 登入者的排工放前面
            $mySchedules = array();
            $otherSchedules = array();
            foreach ($daySchedules as $ds) {
                $engIds = array_column($ds['engineers'], 'user_id');
                if (in_array($currentUserId, $engIds)) {
                    $mySchedules[] = $ds;
                } else {
                    $otherSchedules[] = $ds;
                }
            }
            $sortedSchedules = array_merge($mySchedules, $otherSchedules);

            // +N 收合
            $maxShow = 3;
            $hasMore = count($sortedSchedules) > $maxShow;
        ?>
        <div class="cal-cell <?= $isToday ? 'cal-today' : '' ?> <?= !$ds_info['isOpen'] ? 'cal-closed' : '' ?> <?= $ds_info['status'] === 'full' ? 'cal-status-full' : '' ?>"
             data-date="<?= $dateStr ?>" onclick="showSchedulePopup('<?= $dateStr ?>')" style="cursor:pointer">
            <div class="cal-date">
                <span><?= $day ?></span>
                <span class="cal-date-actions">
                    <?php if ($isBoss): ?>
                    <span class="cal-setting-btn" onclick="event.stopPropagation();openDaySetting('<?= $dateStr ?>')" title="排工日設定">&#9881;</span>
                    <?php endif; ?>
                    <?php if (Auth::hasPermission('schedule.manage')): ?>
                    <span class="cal-leave-btn" onclick="event.stopPropagation();openLeaveModal('<?= $dateStr ?>')" title="標記休假">🏖</span>
                    <?php if ($ds_info['isOpen']): ?>
                    <a href="/schedule.php?action=create&date=<?= $dateStr ?>" class="cal-add" title="新增排工" onclick="event.stopPropagation()">+</a>
                    <?php endif; ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="cal-status-row">
                <span class="status-tag status-tag-<?= $ds_info['status'] ?>"><?= $ds_info['statusLabel'] ?></span>
                <?php if ($ds_info['capText']): ?>
                <span class="cap-badge cap-badge-<?= $ds_info['status'] ?>"><?= $ds_info['capText'] ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($leaveNames)): ?>
            <div class="cal-leave-bar" title="休假：<?= e(implode('、', $leaveNames)) ?>"<?php if (Auth::hasPermission('schedule.manage')): ?> onclick="event.stopPropagation();openLeaveModal('<?= $dateStr ?>')" style="cursor:pointer"<?php endif; ?>>
                🏖 <?= e(implode('、', $leaveNames)) ?>
            </div>
            <?php endif; ?>
            <?php if ($ds_info['note']): ?>
            <div class="cal-note-bar"><?= e($ds_info['note']) ?></div>
            <?php endif; ?>
            <?php
            $shown = 0;
            foreach ($sortedSchedules as $ds):
                if ($shown >= $maxShow && $hasMore) break;
                $shown++;
                $isMine = in_array($currentUserId, array_column($ds['engineers'], 'user_id'));
            ?>
            <a href="/schedule.php?action=view&id=<?= $ds['id'] ?>" class="cal-event cal-event-<?= $ds['status'] ?> <?= $isMine ? 'cal-event-mine' : '' ?>">
                <?php
                $schedTime = '';
                if (!empty($ds['designated_time'])) {
                    $schedTime = substr($ds['designated_time'], 0, 5);
                } elseif (!empty($ds['start_time'])) {
                    $schedTime = substr($ds['start_time'], 0, 5);
                }
                ?>
                <div class="cal-event-title"><?= e(mb_substr($ds['case_title'], 0, 10)) ?><?= $schedTime ? ' <span style="color:#e65100;font-size:.7rem">' . $schedTime . '</span>' : '' ?></div>
                <div class="cal-event-info">
                    <?php if ($ds['plate_number']): ?><span><?= e($ds['plate_number']) ?></span><?php endif; ?>
                    <span><?= count($ds['engineers']) ?>人</span>
                    <?php if ($ds['total_visits'] > 1): ?><span><?= $ds['visit_number'] ?>/<?= $ds['total_visits'] ?></span><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
            <?php if ($hasMore): ?>
            <div class="cal-more" onclick="showSchedulePopup('<?= $dateStr ?>')" data-date="<?= $dateStr ?>">+<?= count($sortedSchedules) - $maxShow ?> 更多</div>
            <?php endif; ?>
            <?php if (!empty($sortedSchedules)): ?>
            <div class="sch-day-all" data-date="<?= $dateStr ?>" style="display:none">
                <?php foreach ($sortedSchedules as $ds):
                    $engNames = array();
                    foreach ($ds['engineers'] as $eng) $engNames[] = $eng['real_name'];
                ?>
                <div data-id="<?= $ds['id'] ?>" data-title="<?= e($ds['case_title']) ?>" data-status="<?= e($ds['status']) ?>" data-plate="<?= e($ds['plate_number'] ?: '') ?>" data-engineers="<?= e(implode('、', $engNames)) ?>" data-count="<?= count($ds['engineers']) ?>"></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- 行事曆 - 手機版月曆格子 -->
<?php
$userRole = Auth::user()['role'];
$isEngRole = in_array($userRole, array('engineer', 'eng_deputy'));
$mobileData = array();
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $daySchedules = isset($schedulesByDate[$dateStr]) ? $schedulesByDate[$dateStr] : array();
    $dayLeaves = isset($leavesByDate[$dateStr]) ? $leavesByDate[$dateStr] : array();
    $ds_info = $dayStatusData[$dateStr];
    $items = array();
    foreach ($daySchedules as $ds) {
        $engIds = array_column($ds['engineers'], 'user_id');
        $isMine = in_array($currentUserId, $engIds);
        $scTime = '';
        if (!empty($ds['designated_time'])) $scTime = substr($ds['designated_time'], 0, 5);
        elseif (!empty($ds['start_time'])) $scTime = substr($ds['start_time'], 0, 5);
        $items[] = array(
            'id' => $ds['id'], 'title' => $ds['case_title'],
            'case_number' => isset($ds['case_number']) ? $ds['case_number'] : '',
            'status' => $ds['status'], 'address' => $ds['address'] ?: '',
            'plate' => $ds['plate_number'] ?: '',
            'engineers' => implode(', ', array_column($ds['engineers'], 'real_name')),
            'engCount' => count($ds['engineers']),
            'visit' => $ds['total_visits'] > 1 ? $ds['visit_number'] . '/' . $ds['total_visits'] : '',
            'note' => $ds['note'] ?: '', 'isMine' => $isMine,
            'titleShort' => mb_substr($ds['case_title'], 0, 8),
            'time' => $scTime,
        );
    }
    $leaves = array();
    foreach ($dayLeaves as $lv) { if (!isset($leaves[$lv['user_id']])) $leaves[$lv['user_id']] = $lv['real_name']; }
    $mobileData[$dateStr] = array(
        'schedules' => $items, 'leaves' => array_values($leaves),
        'status' => $ds_info['status'], 'statusLabel' => $ds_info['statusLabel'],
        'capText' => $ds_info['capText'], 'isOpen' => $ds_info['isOpen'], 'note' => $ds_info['note'],
    );
}
?>
<div class="calendar-mobile show-mobile" style="flex-direction:column">
    <!-- 月曆格子 -->
    <div class="mg-grid">
        <div class="mg-dow">日</div><div class="mg-dow">一</div><div class="mg-dow">二</div>
        <div class="mg-dow">三</div><div class="mg-dow">四</div><div class="mg-dow">五</div><div class="mg-dow">六</div>
        <?php for ($i = 0; $i < $startWeekday; $i++): ?><div class="mg-cell mg-empty"></div><?php endfor; ?>
        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $ds_info = $dayStatusData[$dateStr];
            $isToday = ($dateStr === $today);
            $daySchedules = isset($schedulesByDate[$dateStr]) ? $schedulesByDate[$dateStr] : array();
            $dayLeaves = isset($leavesByDate[$dateStr]) ? $leavesByDate[$dateStr] : array();
        ?>
        <div class="mg-cell <?= $isToday ? 'mg-today' : '' ?> <?= !$ds_info['isOpen'] ? 'mg-closed' : '' ?>"
             data-date="<?= $dateStr ?>" onclick="openMobileDay('<?= $dateStr ?>')">
            <div class="mg-daynum"><?= $day ?></div>
            <?php
            $showMax = 3;
            $shown = 0;
            foreach ($daySchedules as $ds):
                if ($shown >= $showMax) break;
                $shown++;
                $isMine = in_array($currentUserId, array_column($ds['engineers'], 'user_id'));
                $barClass = $ds['status'] === 'completed' ? 'mg-bar-done' : ($isMine ? 'mg-bar-mine' : 'mg-bar-default');
            ?>
            <div class="mg-bar <?= $barClass ?>"><?= e(mb_substr($ds['case_title'], 0, 5)) ?></div>
            <?php endforeach; ?>
            <?php if (count($daySchedules) > $showMax): ?>
            <div class="mg-more">+<?= count($daySchedules) - $showMax ?></div>
            <?php endif; ?>
            <?php if (!empty($dayLeaves)): ?>
            <div class="mg-leave-dot" title="有人休假"></div>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>

    <!-- 月份切換 -->
    <div class="d-flex justify-between align-center" style="padding:8px 0">
        <a href="/schedule.php?year=<?= $prevYear ?>&month=<?= $prevMonth ?><?= $filterBranchId ? '&branch_id='.$filterBranchId : '' ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>" class="btn btn-outline btn-sm" style="flex:1;text-align:center">&laquo; 上月</a>
        <a href="/schedule.php?year=<?= date('Y') ?>&month=<?= date('m') ?><?= $filterBranchId ? '&branch_id='.$filterBranchId : '' ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>" class="btn btn-outline btn-sm" style="flex:1;text-align:center" onclick="<?php if ($today >= sprintf('%04d-%02d-01', $year, $month) && $today <= sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth)): ?>event.preventDefault();openMobileDay('<?= $today ?>');<?php endif; ?>">今天</a>
        <a href="/schedule.php?year=<?= $nextYear ?>&month=<?= $nextMonth ?><?= $filterBranchId ? '&branch_id='.$filterBranchId : '' ?><?= $filterUserId ? '&user_id='.$filterUserId : '' ?>" class="btn btn-outline btn-sm" style="flex:1;text-align:center">下月 &raquo;</a>
    </div>
</div>

<!-- 手機日期詳情 overlay -->
<div id="mDayOverlay" class="mday-overlay" style="display:none">
    <div class="mday-panel">
        <div class="mday-header">
            <span id="mDayTitle" class="mday-title"></span>
            <div class="mday-header-actions">
                <span id="mDayAddBtn"></span>
                <button onclick="closeMobileDay()" class="mday-close">&times;</button>
            </div>
        </div>
        <div id="mDayBody" class="mday-body"></div>
    </div>
</div>

<script>
var mData = <?= json_encode($mobileData, JSON_UNESCAPED_UNICODE) ?>;
var mCanManage = <?= Auth::hasPermission('schedule.manage') ? 'true' : 'false' ?>;
var mWeekdays = ['日','一','二','三','四','五','六'];

function openMobileDay(dateStr) {
    var data = mData[dateStr];
    if (!data) return;
    var d = new Date(dateStr.replace(/-/g, '/'));
    var wd = mWeekdays[d.getDay()];
    var p = dateStr.split('-');
    document.getElementById('mDayTitle').textContent = parseInt(p[1]) + '月' + parseInt(p[2]) + '日 ' + wd;
    document.getElementById('mDayAddBtn').innerHTML = mCanManage && data.isOpen
        ? '<a href="/schedule.php?action=create&date=' + dateStr + '" class="btn btn-primary btn-sm" style="padding:4px 12px;font-size:.8rem">+ 排工</a>' : '';

    var html = '';

    // 休假
    if (data.leaves.length) {
        html += '<div style="padding:8px 0;font-size:.82rem;color:#e65100">&#x1F3D6; 休假：' + mEsc(data.leaves.join('、')) + '</div>';
    }

    // 排工列表 - 時間軸風格
    var items = data.schedules.slice();
    items.sort(function(a, b) { return (b.isMine ? 1 : 0) - (a.isMine ? 1 : 0); });

    if (!items.length && !data.leaves.length) {
        html += '<div style="text-align:center;color:var(--gray-400);padding:32px 0">' + (data.isOpen ? '無排工' : '不可排工') + '</div>';
    }

    for (var i = 0; i < items.length; i++) {
        var s = items[i];
        var borderColor = s.status === 'completed' ? '#34a853' : (s.isMine ? 'var(--primary)' : 'var(--gray-300)');
        html += '<div class="mday-item" style="border-left-color:' + borderColor + '" onclick="location.href=\'/schedule.php?action=view&id=' + s.id + '\'">';
        html += '<div class="mday-item-title">' + mEsc(s.title) + (s.time ? ' <span style="color:#e65100;font-size:.8rem;font-weight:600">' + s.time + '</span>' : '') + '</div>';
        html += '<div class="mday-item-meta">';
        if (s.engineers) html += '<span>&#x1F477; ' + mEsc(s.engineers) + '</span>';
        if (s.plate) html += '<span>&#x1F697; ' + mEsc(s.plate) + '</span>';
        if (s.visit) html += '<span>第' + s.visit + '次</span>';
        html += '</div>';
        if (s.address) {
            html += '<div class="mday-item-addr">';
            html += mEsc(s.address);
            html += ' <span onclick="event.stopPropagation();window.open(\'https://maps.google.com/?q=' + encodeURIComponent(s.address) + '\')" class="mday-nav-link">&#x1F5FA; 導航</span>';
            html += '</div>';
        }
        if (s.note) html += '<div class="mday-item-note">' + mEsc(s.note) + '</div>';
        html += '</div>';
    }

    document.getElementById('mDayBody').innerHTML = html;
    document.getElementById('mDayOverlay').style.display = 'flex';
}

function closeMobileDay() {
    document.getElementById('mDayOverlay').style.display = 'none';
}

function mEsc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// 點背景關閉
document.getElementById('mDayOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeMobileDay();
});
</script>

<!-- 排工日期彈出視窗 -->
<div id="schDayPopup" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:1000;background:rgba(0,0,0,.4)">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,.2);min-width:380px;max-width:520px;max-height:80vh;overflow:hidden">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #eee">
            <h3 id="schPopupTitle" style="margin:0;font-size:1rem"></h3>
            <button onclick="closeSchedulePopup()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#999">&times;</button>
        </div>
        <div id="schPopupBody" style="padding:12px 16px;overflow-y:auto;max-height:60vh"></div>
    </div>
</div>

<?php
// 準備手機版 JSON 資料
$userRole = Auth::user()['role'];
$isEngRole = in_array($userRole, array('engineer', 'eng_deputy'));
$mobileData = array();
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $daySchedules = isset($schedulesByDate[$dateStr]) ? $schedulesByDate[$dateStr] : array();
    $dayLeaves = isset($leavesByDate[$dateStr]) ? $leavesByDate[$dateStr] : array();
    $ds_info = $dayStatusData[$dateStr];

    $items = array();
    foreach ($daySchedules as $ds) {
        $engIds = array_column($ds['engineers'], 'user_id');
        $isMine = in_array($currentUserId, $engIds);
        $items[] = array(
            'id' => $ds['id'],
            'title' => $ds['case_title'],
            'case_number' => isset($ds['case_number']) ? $ds['case_number'] : '',
            'case_type' => isset($ds['case_type']) ? $ds['case_type'] : '',
            'status' => $ds['status'],
            'address' => $ds['address'] ?: '',
            'plate' => $ds['plate_number'] ?: '',
            'engineers' => implode(', ', array_column($ds['engineers'], 'real_name')),
            'visit' => $ds['total_visits'] > 1 ? $ds['visit_number'] . '/' . $ds['total_visits'] : '',
            'note' => $ds['note'] ?: '',
            'isMine' => $isMine,
            'time' => !empty($ds['designated_time']) ? substr($ds['designated_time'], 0, 5) : (!empty($ds['start_time']) ? substr($ds['start_time'], 0, 5) : ''),
        );
    }
    $leaves = array();
    foreach ($dayLeaves as $lv) {
        if (!isset($leaves[$lv['user_id']])) $leaves[$lv['user_id']] = $lv['real_name'];
    }
    $mobileData[$dateStr] = array(
        'schedules' => $items,
        'leaves' => array_values($leaves),
        'status' => $ds_info['status'],
        'statusLabel' => $ds_info['statusLabel'],
        'capText' => $ds_info['capText'],
        'isOpen' => $ds_info['isOpen'],
        'note' => $ds_info['note'],
        'count' => count($items),
    );
}
?>

<!-- 休假 Modal -->
<?php if (Auth::hasPermission('schedule.manage')): ?>
<div id="leaveModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeLeaveModal()">
    <div class="modal-content" style="max-width:400px">
        <div class="modal-header">
            <h3 id="leaveModalTitle">標記休假</h3>
            <button type="button" onclick="closeLeaveModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:.85rem;color:var(--gray-500);margin-bottom:8px">勾選當日休假的人員：</p>
            <div id="leaveEngList" style="max-height:300px;overflow-y:auto">
                <?php foreach ($engineerList as $eng): ?>
                <label class="leave-eng-item" data-uid="<?= $eng['id'] ?>">
                    <input type="checkbox" value="<?= $eng['id'] ?>" class="leave-cb">
                    <span><?= e($eng['real_name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary btn-sm" onclick="saveLeave()">儲存</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeLeaveModal()">取消</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 排工日設定 Modal（僅管理者） -->
<?php if ($isBoss): ?>
<div id="daySettingModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeDaySetting()">
    <div class="modal-content" style="max-width:420px">
        <div class="modal-header">
            <h3 id="daySettingTitle">排工日設定</h3>
            <button type="button" onclick="closeDaySetting()" style="background:none;border:none;font-size:1.2rem;cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group mb-1">
                <label style="font-weight:600">是否開放排工</label>
                <div class="d-flex gap-2" style="margin-top:4px">
                    <label><input type="radio" name="ds_is_open" value="1"> 開放</label>
                    <label><input type="radio" name="ds_is_open" value="0"> 不可排工</label>
                </div>
            </div>
            <div id="dsCapFields">
                <div class="form-group mb-1">
                    <label>最大組數 <span class="text-muted" style="font-size:.8rem">(留空=不限)</span></label>
                    <input type="number" id="ds_max_teams" class="form-control" min="0" placeholder="不限">
                </div>
                <div class="form-group mb-1">
                    <label>最大可排人數 <span class="text-muted" style="font-size:.8rem">(留空=不限)</span></label>
                    <input type="number" id="ds_max_engineers" class="form-control" min="0" placeholder="不限">
                </div>
            </div>
            <div class="form-group mb-1">
                <label>備註</label>
                <input type="text" id="ds_note" class="form-control" placeholder="例：國定假日、教育訓練日">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary btn-sm" onclick="saveDaySetting()">儲存</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="closeDaySetting()">取消</button>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* 狀態圖例 */
.status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
.status-open { background: #34a853; }
.status-partial { background: #fbbc04; }
.status-full { background: #ea4335; }
.status-closed { background: #9e9e9e; }

/* 狀態標籤（每日顯示） */
.status-tag { font-size: .6rem; padding: 1px 4px; border-radius: 3px; font-weight: 600; vertical-align: middle; margin-left: 2px; }
.status-tag-open { background: #e6f4ea; color: #137333; }
.status-tag-partial { background: #fef7e0; color: #b06000; }
.status-tag-full { background: #fce8e6; color: #c5221f; }
.status-tag-closed { background: #f0f0f0; color: #666; }

/* 容量 badge */
.cap-badge { font-size: .6rem; padding: 1px 4px; border-radius: 8px; font-weight: 600; vertical-align: middle; margin-left: 2px; }
.cap-badge-open { background: #e6f4ea; color: #137333; }
.cap-badge-partial { background: #fef7e0; color: #b06000; }
.cap-badge-full { background: #fce8e6; color: #c5221f; }
.cap-badge-closed { display: none; }

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
    min-height: 130px; padding: 4px; border-right: 1px solid var(--gray-200);
    border-bottom: 1px solid var(--gray-200); position: relative;
}
.cal-cell:nth-child(7n) { border-right: none; }
.cal-empty { background: var(--gray-50); }
.cal-today { background: #e8f0fe; }
.cal-closed { background: #f5f5f5; opacity: .75; }
.cal-status-full { border-left: 3px solid var(--danger); background: #fff5f5; }

.cal-date {
    display: flex; justify-content: space-between; align-items: center;
    font-size: .85rem; font-weight: 500; margin-bottom: 0;
}
.cal-status-row {
    display: flex; gap: 3px; align-items: center; margin-bottom: 3px;
    flex-wrap: nowrap;
}
.cal-date-actions { display: flex; gap: 2px; align-items: center; }
.cal-add {
    width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;
    background: var(--primary); color: #fff; border-radius: 50%;
    font-size: .8rem; text-decoration: none; opacity: 0;
    transition: opacity .15s;
}
.cal-leave-btn, .cal-setting-btn {
    width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: .75rem; opacity: 0;
    transition: opacity .15s; border-radius: 50%;
}
.cal-leave-btn:hover { background: #fff3e0; }
.cal-setting-btn:hover { background: #e3f2fd; }
.cal-cell:hover .cal-add, .cal-cell:hover .cal-leave-btn, .cal-cell:hover .cal-setting-btn { opacity: 1; }

.cal-event {
    display: block; padding: 3px 6px; margin-bottom: 2px;
    border-radius: 4px; font-size: .75rem; text-decoration: none;
    color: #fff; transition: opacity .15s;
}
.cal-event:hover { opacity: .85; text-decoration: none; }
.cal-event-planned { background: #e8eaed; color: #333; }
.cal-event-confirmed { background: var(--info); }
.cal-event-in_progress { background: var(--warning); color: #333; }
.cal-event-completed { background: var(--success); }
.cal-event-cancelled { background: var(--gray-500); }
.cal-event-title { font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cal-event-info { display: flex; gap: 4px; opacity: .9; font-size: .7rem; }

/* 登入者的排工（醒目邊框） */
.cal-event-mine { box-shadow: 0 0 0 2px #ff6b35; }
.mobile-card-mine { border-left: 3px solid #ff6b35; }

/* 休假人員 */
.cal-leave-bar {
    font-size: .7rem; padding: 2px 5px; margin-bottom: 3px;
    background: #fff3e0; color: #e65100; border-radius: 3px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    border-left: 2px solid #ff9800;
}

/* 備註 */
.cal-note-bar {
    font-size: .65rem; padding: 1px 5px; margin-bottom: 3px;
    background: #e3f2fd; color: #1565c0; border-radius: 3px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* +N 展開 */
.cal-more {
    font-size: .7rem; color: var(--primary); cursor: pointer;
    padding: 2px 6px; text-align: center; font-weight: 500;
}
.cal-more:hover { text-decoration: underline; }

/* 手機版月曆格子 */
.mg-grid { display:grid; grid-template-columns:repeat(7,1fr); background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; width:100%; }
.mg-dow { font-size:.75rem; color:var(--gray-500); padding:8px 0; text-align:center; font-weight:600; border-bottom:1px solid var(--gray-100); }
.mg-cell { min-height:88px; padding:3px; border-bottom:1px solid var(--gray-100); cursor:pointer; position:relative; background:#fff; }
.mg-cell:active { background:var(--gray-50); }
.mg-empty { background:var(--gray-50); min-height:88px; }
.mg-today { background:#e8f0fe; }
.mg-today .mg-daynum { background:var(--primary); color:#fff; border-radius:50%; width:24px; height:24px; line-height:24px; display:inline-block; text-align:center; font-weight:700; }
.mg-closed { background:#f9f9f9; opacity:.5; }
.mg-daynum { font-size:.8rem; font-weight:500; padding:1px 3px; color:var(--gray-600); }
.mg-bar { font-size:.62rem; padding:2px 3px; margin:1px 0; border-radius:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#fff; line-height:1.3; }
.mg-bar-default { background:#4285f4; }
.mg-bar-mine { background:#1565c0; }
.mg-bar-done { background:#34a853; }
.mg-more { font-size:.62rem; color:var(--gray-400); padding:0 3px; }
.mg-leave-dot { position:absolute; top:3px; right:3px; width:7px; height:7px; border-radius:50%; background:#e65100; }

/* 手機日期詳情 overlay */
.mday-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.4); z-index:1000; display:flex; align-items:flex-end; justify-content:center; }
.mday-panel { background:#fff; width:100%; max-height:85vh; border-radius:16px 16px 0 0; display:flex; flex-direction:column; }
.mday-header { display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-bottom:1px solid var(--gray-200); flex-shrink:0; }
.mday-title { font-weight:700; font-size:1.05rem; }
.mday-header-actions { display:flex; gap:8px; align-items:center; }
.mday-close { background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--gray-500); padding:0 4px; }
.mday-body { overflow-y:auto; -webkit-overflow-scrolling:touch; padding:12px 16px; flex:1; min-height:0; }
.mday-item { display:block; padding:12px; margin-bottom:8px; border-left:3px solid var(--gray-300); background:var(--gray-50); border-radius:0 var(--radius) var(--radius) 0; text-decoration:none; color:inherit; cursor:pointer; }
.mday-item:active { background:var(--gray-100); }
.mday-item-title { font-weight:600; font-size:.95rem; margin-bottom:4px; }
.mday-item-meta { display:flex; flex-wrap:wrap; gap:8px; font-size:.82rem; color:var(--gray-600); }
.mday-item-addr { font-size:.8rem; color:var(--gray-500); margin-top:6px; }
.mday-nav-link { color:var(--primary); font-weight:600; text-decoration:none; font-size:.8rem; }
.mday-item-note { font-size:.78rem; color:var(--gray-400); margin-top:4px; font-style:italic; }

/* 篩選 */
.cal-filter-wrap select { font-size: .85rem; padding: 4px 8px; border-radius: var(--radius); border: 1px solid var(--gray-300); }

/* Modal 共用 */
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.5); z-index: 1000; display: flex; align-items: center; justify-content: center; }
.modal-content { background: #fff; border-radius: var(--radius); padding: 20px; width: 90%; max-height: 80vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.modal-header h3 { margin: 0; font-size: 1.1rem; }
.modal-body { margin-bottom: 16px; }
.modal-footer { display: flex; gap: 8px; }

.leave-eng-item {
    display: flex; align-items: center; gap: 8px; padding: 6px 8px;
    border-bottom: 1px solid var(--gray-100); cursor: pointer; font-size: .9rem;
}
.leave-eng-item:hover { background: var(--gray-50); }
.leave-eng-item input[type="checkbox"] { width: 16px; height: 16px; }

.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) {
    .show-mobile { display: none !important; }
    .hide-mobile { display: block !important; }
}
</style>

<script>
function getFilterParams() {
    var params = '';
    var branchSel = document.getElementById('branchFilter');
    var personSel = document.getElementById('personFilter');
    if (branchSel && branchSel.value && branchSel.value !== '0') params += '&branch_id=' + branchSel.value;
    if (personSel && personSel.value && personSel.value !== '0') params += '&user_id=' + personSel.value;
    return params;
}
function filterByBranch(branchId) {
    var url = '/schedule.php?year=<?= $year ?>&month=<?= $month ?>';
    if (branchId && branchId !== '0') url += '&branch_id=' + branchId;
    var personSel = document.getElementById('personFilter');
    if (personSel) personSel.value = '0'; // 切分公司時重設人員篩選
    window.location.href = url;
}
function filterByPerson(userId) {
    var url = '/schedule.php?year=<?= $year ?>&month=<?= $month ?>';
    var branchSel = document.getElementById('branchFilter');
    if (branchSel && branchSel.value && branchSel.value !== '0') url += '&branch_id=' + branchSel.value;
    if (userId && userId !== '0') url += '&user_id=' + userId;
    window.location.href = url;
}

function showSchedulePopup(dateStr) {
    var container = document.querySelector('.sch-day-all[data-date="' + dateStr + '"]');
    if (!container) return;
    var items = container.querySelectorAll('div[data-id]');
    var d = new Date(dateStr);
    var weekdays = ['日','一','二','三','四','五','六'];
    document.getElementById('schPopupTitle').textContent = (d.getMonth()+1) + '/' + d.getDate() + ' (' + weekdays[d.getDay()] + ') ' + items.length + ' 筆排工';
    var statusColors = {planned:'#3b82f6',confirmed:'#2563eb',in_progress:'#f59e0b',completed:'#22c55e',cancelled:'#ef4444'};
    var statusLabels = {planned:'已排',confirmed:'已確認',in_progress:'施工中',completed:'完工',cancelled:'取消'};
    var html = '';
    for (var i = 0; i < items.length; i++) {
        var it = items[i];
        var id = it.getAttribute('data-id');
        var title = it.getAttribute('data-title');
        var status = it.getAttribute('data-status');
        var plate = it.getAttribute('data-plate');
        var engineers = it.getAttribute('data-engineers');
        var count = it.getAttribute('data-count');
        var color = statusColors[status] || '#3b82f6';
        var label = statusLabels[status] || status;
        html += '<a href="/schedule.php?action=view&id=' + id + '" style="display:block;padding:10px 12px;margin-bottom:6px;border-left:4px solid ' + color + ';background:#f8f9fa;border-radius:6px;text-decoration:none;color:inherit;transition:background .15s"' +
            ' onmouseover="this.style.background=\'#eef1f5\'" onmouseout="this.style.background=\'#f8f9fa\'">' +
            '<div style="font-weight:600;font-size:.95rem">' + title + '</div>' +
            '<div style="display:flex;gap:8px;font-size:.8rem;color:#888;margin-top:2px">' +
            '<span style="display:inline-block;padding:1px 6px;border-radius:3px;background:' + color + ';color:#fff;font-size:.7rem">' + label + '</span>' +
            (plate ? '<span>' + plate + '</span>' : '') +
            '<span>' + count + '人</span>' +
            '</div>' +
            '<div style="font-size:.8rem;color:#666;margin-top:2px">' + engineers + '</div>' +
            '</a>';
    }
    document.getElementById('schPopupBody').innerHTML = html;
    document.getElementById('schDayPopup').style.display = 'block';
}
function closeSchedulePopup() {
    document.getElementById('schDayPopup').style.display = 'none';
}
document.getElementById('schDayPopup').addEventListener('click', function(e) {
    if (e.target === this) closeSchedulePopup();
});

// ===== 休假功能 =====
var leaveModalDate = '';
var leaveData = <?= json_encode(
    array_map(function($dayLeaves) {
        $uids = array();
        foreach ($dayLeaves as $lv) {
            $uids[$lv['user_id']] = true;
        }
        return array_keys($uids);
    }, $leavesByDate),
    JSON_FORCE_OBJECT
) ?>;

function openLeaveModal(dateStr) {
    leaveModalDate = dateStr;
    document.getElementById('leaveModalTitle').textContent = dateStr + ' 休假設定';
    var onLeave = leaveData[dateStr] || [];
    var cbs = document.querySelectorAll('#leaveEngList .leave-cb');
    for (var i = 0; i < cbs.length; i++) {
        cbs[i].checked = onLeave.indexOf(parseInt(cbs[i].value)) !== -1;
    }
    document.getElementById('leaveModal').style.display = 'flex';
}
function closeLeaveModal() { document.getElementById('leaveModal').style.display = 'none'; }

function saveLeave() {
    var cbs = document.querySelectorAll('#leaveEngList .leave-cb');
    var selected = [];
    for (var i = 0; i < cbs.length; i++) {
        if (cbs[i].checked) selected.push(cbs[i].value);
    }
    var form = new FormData();
    form.append('date', leaveModalDate);
    for (var j = 0; j < selected.length; j++) form.append('user_ids[]', selected[j]);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/schedule.php?action=set_day_leave');
    xhr.onload = function() { if (xhr.status === 200) window.location.reload(); else alert('儲存失敗'); };
    xhr.send(form);
}

// ===== 排工日設定（管理者） =====
var dsDate = '';
var dsData = <?= json_encode(array_map(function($s) {
    return array(
        'is_open' => (int)$s['is_open'],
        'max_teams' => $s['max_teams'],
        'max_engineers' => $s['max_engineers'],
        'note' => $s['note'],
    );
}, $daySettings), JSON_FORCE_OBJECT) ?>;
// 記錄星期日（預設不可排）
var sundayDates = <?= json_encode(array_keys(array_filter($dayStatusData, function($d) { return $d['isSunday']; }))) ?>;

function openDaySetting(dateStr) {
    dsDate = dateStr;
    document.getElementById('daySettingTitle').textContent = dateStr + ' 排工日設定';
    var setting = dsData[dateStr];
    var isSunday = sundayDates.indexOf(dateStr) !== -1;

    // 預設值
    var isOpen = 1;
    var maxTeams = '';
    var maxEng = '';
    var note = '';

    if (setting) {
        isOpen = setting.is_open;
        maxTeams = setting.max_teams !== null ? setting.max_teams : '';
        maxEng = setting.max_engineers !== null ? setting.max_engineers : '';
        note = setting.note || '';
    } else if (isSunday) {
        isOpen = 0; // 星期日預設不可排
    }

    var radios = document.querySelectorAll('input[name="ds_is_open"]');
    for (var i = 0; i < radios.length; i++) {
        radios[i].checked = (parseInt(radios[i].value) === isOpen);
    }
    document.getElementById('ds_max_teams').value = maxTeams;
    document.getElementById('ds_max_engineers').value = maxEng;
    document.getElementById('ds_note').value = note;
    toggleCapFields();
    document.getElementById('daySettingModal').style.display = 'flex';
}
function closeDaySetting() { document.getElementById('daySettingModal').style.display = 'none'; }

// 切換開放/不可排時顯示/隱藏容量欄位
document.addEventListener('change', function(e) {
    if (e.target.name === 'ds_is_open') toggleCapFields();
});
function toggleCapFields() {
    var isOpen = document.querySelector('input[name="ds_is_open"]:checked');
    var show = isOpen && isOpen.value === '1';
    document.getElementById('dsCapFields').style.display = show ? 'block' : 'none';
}

function saveDaySetting() {
    var isOpen = document.querySelector('input[name="ds_is_open"]:checked');
    if (!isOpen) { alert('請選擇是否開放'); return; }

    var form = new FormData();
    form.append('date', dsDate);
    form.append('is_open', isOpen.value);
    form.append('max_teams', document.getElementById('ds_max_teams').value);
    form.append('max_engineers', document.getElementById('ds_max_engineers').value);
    form.append('note', document.getElementById('ds_note').value);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/schedule.php?action=save_day_setting');
    xhr.onload = function() { if (xhr.status === 200) window.location.reload(); else alert('儲存失敗'); };
    xhr.send(form);
}
</script>
