<?php
// 手機版工程行事曆：列表優先，可切換月曆格
// 資料來源與桌面版共用：$schedules / $schedulesByDate / $year / $month / $branchList / $engineerList / $filterBranchId / $filterUserId / $totalEngineers / $daySettings / $dailyCapacity / $currentUserId
$canManage = Auth::hasPermission('schedule.manage');

$today = date('Y-m-d');
$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$filterDate = isset($_GET['date']) ? $_GET['date'] : '';
$currentKeyword = isset($filterKeyword) ? $filterKeyword : '';
$weekdayLabels = array('日','一','二','三','四','五','六');
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$startWeekday = (int)date('w', $firstDay);

// 預設列表模式；?mode=grid 或 localStorage 切月曆
$defaultMode = isset($_GET['mode']) ? $_GET['mode'] : 'list';

// 準備列表資料：依日期分組
// 若有 filterDate 只顯示該天；否則從今天開始（若本月含今天）或月初開始
$listDates = array();
foreach ($schedulesByDate as $d => $rows) {
    if ($filterDate && $d !== $filterDate) continue;
    $listDates[$d] = $rows;
}
ksort($listDates);

// URL helper 保留篩選
function _scMobileUrl($extra = array()) {
    global $year, $month, $filterBranchId, $filterUserId, $filterDate, $currentKeyword;
    $params = array('year' => $year, 'month' => $month);
    if ($filterBranchId) $params['branch_id'] = $filterBranchId;
    if ($filterUserId) $params['user_id'] = $filterUserId;
    if ($filterDate) $params['date'] = $filterDate;
    if ($currentKeyword !== '') $params['keyword'] = $currentKeyword;
    foreach ($extra as $k => $v) {
        if ($v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    return '/schedule.php?' . http_build_query($params);
}
?>
<style>
.scm { max-width: 640px; margin: 0 auto; padding-bottom: 80px; }
.scm-sticky { position:sticky; top: 56px; z-index:90; background:#f8f9fa; padding:8px 4px 6px; margin:0 -4px 10px; box-shadow:0 2px 4px rgba(0,0,0,.06); }
.scm-sticky .scm-tabs { margin-bottom:6px; }
.scm-sticky .scm-filters { margin-bottom:6px; }
.scm-sticky .scm-month-nav { margin-bottom:0; }
.scm-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding: 4px 0; }
.scm-title { font-size:1.1rem; font-weight:700; }
.scm-tabs { display:flex; background:#f1f3f5; border-radius:8px; padding:3px; margin-bottom:10px; }
.scm-tab { flex:1; text-align:center; padding:8px 0; font-size:.9rem; border-radius:6px; color:#666; text-decoration:none; font-weight:500; }
.scm-tab.active { background:#fff; color:#1565c0; box-shadow:0 1px 3px rgba(0,0,0,.1); font-weight:600; }
.scm-filters { display:flex; gap:6px; margin-bottom:10px; flex-wrap:wrap; }
.scm-filters select { flex:1; min-width:0; font-size:.85rem; padding:8px 8px; }
.scm-keyword-wrap { display:flex; gap:4px; flex:1 1 100%; }
.scm-keyword-wrap input { flex:1; min-width:0; font-size:.85rem; padding:8px; border:1px solid #ccc; border-radius:6px; }
.scm-keyword-wrap button { padding:8px 14px; background:#1565c0; color:#fff; border:none; border-radius:6px; font-size:.85rem; cursor:pointer; }
.scm-keyword-wrap button:active { opacity:.85; }
.scm-kw-badge { display:inline-block; font-size:.75rem; background:#e3f2fd; color:#1565c0; padding:3px 10px; border-radius:12px; margin-bottom:8px; }
.scm-kw-badge a { color:#c62828; margin-left:6px; text-decoration:none; font-weight:600; }
.scm-month-nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; gap:4px; }
.scm-month-nav a { flex:1; padding:10px 8px; background:#fff; border:1px solid #ddd; border-radius:6px; text-align:center; text-decoration:none; color:#333; font-size:.9rem; }
.scm-month-nav a.current { font-weight:700; flex:1.5; background:#e3f2fd; border-color:#1565c0; color:#1565c0; }
.scm-month-nav a:active { background:#f0f0f0; }

/* 列表模式 */
.scm-date-group { margin-bottom:14px; }
.scm-date-header { display:flex; align-items:center; justify-content:space-between; font-size:.95rem; font-weight:700; color:#1565c0; padding:6px 2px; border-bottom:2px solid #e3f2fd; margin-bottom:8px; }
.scm-date-header.today { color:#e65100; border-color:#ffcc80; }
.scm-date-count { font-size:.75rem; color:#888; font-weight:normal; }
.scm-card { display:block; background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px; margin-bottom:8px; text-decoration:none; color:inherit; border-left:4px solid #1565c0; cursor:pointer; }
.scm-card:active { background:#fafafa; transform:scale(.995); }
.scm-card.status-full { border-left-color:#c62828; }
.scm-card.status-partial { border-left-color:#f9a825; }
.scm-card-row { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:4px; }
.scm-case-no { font-size:.75rem; color:#888; font-family:monospace; }
.scm-case-title { font-size:.95rem; font-weight:600; color:#111; flex:1; }
.scm-time { font-size:.8rem; color:#1565c0; font-weight:600; white-space:nowrap; }
.scm-meta { font-size:.8rem; color:#555; margin-top:4px; line-height:1.5; }
.scm-meta-line { display:flex; gap:4px; align-items:flex-start; }
.scm-meta-label { color:#888; min-width:36px; flex-shrink:0; }
.scm-addr { color:#555; }
.scm-addr a { color:#1565c0; text-decoration:none; }
.scm-engineers { margin-top:6px; padding-top:6px; border-top:1px dashed #eee; font-size:.8rem; }
.scm-eng-tag { display:inline-block; background:#e3f2fd; color:#1565c0; padding:2px 8px; border-radius:10px; margin-right:4px; margin-top:2px; font-size:.75rem; }
.scm-eng-tag.lead { background:#fff3e0; color:#e65100; font-weight:600; }
.scm-status { display:inline-block; padding:2px 8px; border-radius:4px; font-size:.72rem; font-weight:600; color:#fff; margin-right:6px; vertical-align:middle; }
.scm-status-planned { background:#90a4ae; }
.scm-status-confirmed { background:#1976d2; }
.scm-status-in_progress { background:#f59e0b; color:#fff; }
.scm-status-checked_out { background:#8b5cf6; }
.scm-status-needs_revisit { background:#f97316; }
.scm-status-no_report { background:#ef4444; }
.scm-status-completed { background:#22c55e; }
.scm-status-cancelled { background:#6b7280; }
.scm-note { margin-top:4px; padding:6px 8px; background:#fffbea; border-left:3px solid #f9a825; font-size:.8rem; color:#6a4800; border-radius:0 4px 4px 0; white-space:pre-wrap; word-break:break-word; }
.scm-sales-note { margin-top:4px; padding:6px 8px; background:#e3f2fd; border-left:3px solid #1565c0; font-size:.8rem; color:#0d47a1; border-radius:0 4px 4px 0; white-space:pre-wrap; word-break:break-word; }
.scm-case-type-tabs { display:flex; flex-wrap:wrap; gap:4px; padding:6px 0; overflow-x:auto; }
.scm-ct-tab { flex-shrink:0; padding:4px 10px; border:1px solid #cbd5e1; background:#fff; border-radius:14px; font-size:.78rem; color:#555; cursor:pointer; }
.scm-ct-tab.active { background:#1565c0; color:#fff; border-color:#1565c0; }
.scm-card.ct-hidden { display:none !important; }
.scm-ct-badge { display:inline-block; padding:1px 8px; border-radius:10px; font-size:.7rem; font-weight:600; margin-right:4px; vertical-align:middle; }
.scm-no-data { text-align:center; color:#999; padding:40px 20px; font-size:.9rem; background:#fff; border-radius:8px; }

/* 月曆格模式 */
.scm-grid { background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.scm-grid-head { display:grid; grid-template-columns:repeat(7, 1fr); background:#f5f5f5; font-size:.75rem; font-weight:600; text-align:center; }
.scm-grid-head > div { padding:8px 0; }
.scm-grid-head > div:first-child { color:#c62828; }
.scm-grid-head > div:last-child { color:#1565c0; }
.scm-grid-body { display:grid; grid-template-columns:repeat(7, 1fr); }
.scm-cell { aspect-ratio:1; border-top:1px solid #eee; border-right:1px solid #eee; padding:4px; position:relative; text-decoration:none; color:#333; display:flex; flex-direction:column; align-items:center; justify-content:flex-start; }
.scm-cell:nth-child(7n) { border-right:none; }
.scm-cell:active { background:#f0f0f0; }
.scm-cell .day-num { font-size:.85rem; font-weight:600; }
.scm-cell.today .day-num { background:#1565c0; color:#fff; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; }
.scm-cell.has-events .day-num { color:#1565c0; }
.scm-cell .cnt-dot { font-size:.65rem; color:#666; margin-top:2px; padding:1px 5px; border-radius:8px; background:#e3f2fd; color:#1565c0; font-weight:600; }
.scm-cell.full .cnt-dot { background:#ffebee; color:#c62828; }
.scm-cell.partial .cnt-dot { background:#fff8e1; color:#e65100; }
.scm-cell.outside { opacity:.25; pointer-events:none; }
.scm-cell.closed { background:#fafafa; }

/* 浮動新增按鈕 */
.scm-fab { position:fixed; right:16px; bottom:16px; width:56px; height:56px; border-radius:50%; background:#1565c0; color:#fff; border:none; font-size:1.8rem; box-shadow:0 4px 12px rgba(21,101,192,.4); z-index:100; cursor:pointer; }
.scm-fab:active { transform:scale(.95); }

.scm-warn { position:relative; background:#fff3e0; border-left:4px solid #f9a825; padding:8px 30px 8px 12px; border-radius:4px; font-size:.8rem; color:#6a4800; margin-bottom:10px; }
.scm-warn ul { margin:4px 0 0; padding-left:16px; }
.scm-warn-close { position:absolute; top:4px; right:6px; background:none; border:none; font-size:1.2rem; line-height:1; color:#6a4800; cursor:pointer; padding:4px 8px; }
.scm-warn-close:active { color:#000; }

.scm-filter-clear { display:inline-block; font-size:.8rem; background:#e3f2fd; color:#1565c0; padding:4px 10px; border-radius:14px; text-decoration:none; margin-bottom:8px; }
</style>

<div class="scm">
    <div class="scm-head">
        <div class="scm-title"><?= $year ?>/<?= $month ?> 工程行事曆</div>
    </div>

    <div class="scm-sticky">
    <!-- 模式切換 -->
    <div class="scm-tabs">
        <a href="<?= _scMobileUrl(array('mode' => 'list')) ?>" class="scm-tab <?= $defaultMode !== 'grid' ? 'active' : '' ?>">📋 列表</a>
        <a href="<?= _scMobileUrl(array('mode' => 'grid', 'date' => null)) ?>" class="scm-tab <?= $defaultMode === 'grid' ? 'active' : '' ?>">📅 月曆</a>
    </div>

    <!-- 篩選：分公司 + 人員 + 關鍵字 -->
    <?php if ($canManage): ?>
    <div class="scm-filters">
        <?php if (count($branchList) > 1): ?>
        <select id="scmBranchFilter" onchange="scmChangeFilter('branch_id', this.value)">
            <option value="0">全部分公司</option>
            <?php foreach ($branchList as $br): ?>
            <option value="<?= $br['id'] ?>" <?= $filterBranchId == $br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select id="scmPersonFilter" onchange="scmChangeFilter('user_id', this.value)">
            <option value="0">全部人員</option>
            <?php foreach ($engineerList as $eng): ?>
            <option value="<?= $eng['id'] ?>" <?= $filterUserId == $eng['id'] ? 'selected' : '' ?>><?= e($eng['real_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="scm-keyword-wrap">
            <input type="text" id="scmKeyword" placeholder="搜尋客戶/案件/地址"
                   value="<?= e($currentKeyword) ?>" onkeydown="if(event.key==='Enter')scmApplyKeyword()">
            <button type="button" onclick="scmApplyKeyword()">搜尋</button>
        </div>
    </div>
    <?php if ($currentKeyword !== ''): ?>
    <span class="scm-kw-badge">🔍 <?= e($currentKeyword) ?> <a href="javascript:void(0)" onclick="scmClearKeyword()">✕</a></span>
    <?php endif; ?>
    <?php endif; ?>

    <!-- 月份切換 -->
    <div class="scm-month-nav">
        <a href="<?= _scMobileUrl(array('year' => $prevYear, 'month' => $prevMonth, 'date' => null)) ?>">‹ <?= $prevMonth ?>月</a>
        <a href="<?= _scMobileUrl(array('year' => (int)date('Y'), 'month' => (int)date('n'), 'date' => null)) ?>#today" class="current" onclick="scmGoToday(event)">今天</a>
        <a href="<?= _scMobileUrl(array('year' => $nextYear, 'month' => $nextMonth, 'date' => null)) ?>"><?= $nextMonth ?>月 ›</a>
    </div>

    <!-- 案別篩選 -->
    <div class="scm-case-type-tabs">
        <button type="button" class="scm-ct-tab active" data-ct="">全部</button>
        <button type="button" class="scm-ct-tab" data-ct="new_install">新案</button>
        <button type="button" class="scm-ct-tab" data-ct="addition">老客戶追加</button>
        <button type="button" class="scm-ct-tab" data-ct="old_repair">舊客維修</button>
        <button type="button" class="scm-ct-tab" data-ct="new_repair">新客維修</button>
        <button type="button" class="scm-ct-tab" data-ct="maintenance">維護保養</button>
    </div>
    </div>

    <!-- 多次施工人員不同組警告（只顯示 filterDate 或今日的） -->
    <?php
    $warnDate = $filterDate ?: $today;
    $filteredWarnings = array();
    foreach ($visitWarnings as $w) {
        if (!empty($w['visit_date']) && $w['visit_date'] === $warnDate) {
            $filteredWarnings[] = $w;
        }
    }
    if (!empty($filteredWarnings)):
    ?>
    <div class="scm-warn" id="scmWarnBox">
        <button type="button" class="scm-warn-close" onclick="scmCloseWarn()" aria-label="關閉">&times;</button>
        <strong>⚠ 多次施工人員組別不同（<?= e($warnDate) ?>）：</strong>
        <ul>
            <?php foreach ($filteredWarnings as $w): ?>
            <li><?= e($w['customer_name'] ?: ($w['case_title'] ?: $w['case_number'])) ?> 第<?= $w['visit_number'] ?>次與第<?= $w['previous_visit_number'] ?>次不同</li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($filterDate): ?>
    <a class="scm-filter-clear" href="<?= _scMobileUrl(array('date' => null)) ?>">✕ 清除日期篩選（只看 <?= $filterDate ?>）</a>
    <?php endif; ?>

    <?php if ($defaultMode === 'grid'): ?>
    <!-- ========== 月曆格模式 ========== -->
    <div class="scm-grid">
        <div class="scm-grid-head">
            <?php foreach ($weekdayLabels as $w): ?>
            <div><?= $w ?></div>
            <?php endforeach; ?>
        </div>
        <div class="scm-grid-body">
            <?php
            // 月初前空白
            for ($i = 0; $i < $startWeekday; $i++) echo '<div class="scm-cell outside"></div>';
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $cnt = isset($schedulesByDate[$dateStr]) ? count($schedulesByDate[$dateStr]) : 0;
                $cap = isset($dailyCapacity[$dateStr]) ? (int)$dailyCapacity[$dateStr] : 0;
                $daySet = isset($daySettings[$dateStr]) ? $daySettings[$dateStr] : array();
                $isOpen = !isset($daySet['is_open']) || $daySet['is_open'];
                $classes = array('scm-cell');
                if ($dateStr === $today) $classes[] = 'today';
                if ($cnt > 0) $classes[] = 'has-events';
                if (!$isOpen) $classes[] = 'closed';
                if ($cnt > 0 && $totalEngineers > 0) {
                    $ratio = $cnt / max($totalEngineers, 1);
                    if ($ratio >= 1) $classes[] = 'full';
                    elseif ($ratio >= 0.6) $classes[] = 'partial';
                }
                $href = _scMobileUrl(array('mode' => 'list', 'date' => $dateStr));
                echo '<a class="' . implode(' ', $classes) . '" href="' . e($href) . '">';
                echo '<span class="day-num">' . $d . '</span>';
                if ($cnt > 0) echo '<span class="cnt-dot">' . $cnt . '</span>';
                echo '</a>';
            }
            ?>
        </div>
    </div>
    <?php else: ?>
    <!-- ========== 列表模式 ========== -->
    <?php if (empty($listDates)): ?>
    <div class="scm-no-data">
        <?= $filterDate ? '此日期無排工' : '本月無排工資料' ?>
    </div>
    <?php else: ?>
        <?php foreach ($listDates as $dateStr => $rows): ?>
        <?php
            $d = strtotime($dateStr);
            $isToday = ($dateStr === $today);
            $weekLabel = $weekdayLabels[(int)date('w', $d)];
        ?>
        <div class="scm-date-group" id="d-<?= e($dateStr) ?>" <?= $isToday ? 'data-today="1"' : '' ?>>
            <div class="scm-date-header <?= $isToday ? 'today' : '' ?>">
                <span><?= date('n/j', $d) ?>（<?= $weekLabel ?>）<?= $isToday ? '· 今天' : '' ?></span>
                <span class="scm-date-count"><?= count($rows) ?> 筆</span>
            </div>
            <?php foreach ($rows as $s): ?>
            <?php
                $scheduleId = (int)($s['id'] ?? 0);
                $cardUrl = '/schedule.php?action=view&id=' . $scheduleId;
                $timeStr = '';
                if (!empty($s['designated_time'])) $timeStr = substr($s['designated_time'], 0, 5) . '（指定）';
                elseif (!empty($s['start_time'])) {
                    $timeStr = substr($s['start_time'], 0, 5);
                    if (!empty($s['end_time'])) $timeStr .= '~' . substr($s['end_time'], 0, 5);
                }
                $engineers = isset($s['engineers']) ? $s['engineers'] : array();
                $dstat = isset($s['display_status']) ? $s['display_status'] : (isset($s['status']) ? $s['status'] : 'planned');
                $statusLabels = array(
                    'planned' => '已排', 'confirmed' => '已確認', 'in_progress' => '施工中',
                    'checked_out' => '已下工', 'needs_revisit' => '需再施工', 'no_report' => '未回報',
                    'completed' => '已完工', 'cancelled' => '取消',
                );
                $statusText = isset($statusLabels[$dstat]) ? $statusLabels[$dstat] : $dstat;
                $cardClass = 'scm-card';
                if ($dstat === 'no_report' || $dstat === 'needs_revisit') $cardClass .= ' status-full';
                elseif ($dstat === 'in_progress') $cardClass .= ' status-partial';
                // 案別顯示
                $caseTypeMap = array(
                    'new_install'  => array('新案',    '#1976d2', '#e3f2fd'),
                    'addition'     => array('老客追加', '#6a1b9a', '#f3e5f5'),
                    'old_repair'   => array('舊客維修', '#e65100', '#fff3e0'),
                    'new_repair'   => array('新客維修', '#c62828', '#ffebee'),
                    'maintenance'  => array('維護保養', '#2e7d32', '#e8f5e9'),
                );
                $ctKey = isset($s['case_type']) ? $s['case_type'] : '';
                $ctInfo = isset($caseTypeMap[$ctKey]) ? $caseTypeMap[$ctKey] : null;
            ?>
            <div class="<?= $cardClass ?>" data-case-type="<?= e($s['case_type'] ?? '') ?>" onclick="location.href='<?= e($cardUrl) ?>'">
                <div class="scm-card-row">
                    <div style="flex:1;min-width:0">
                        <div class="scm-case-no"><?= e($s['case_number'] ?? '-') ?></div>
                        <div class="scm-case-title">
                            <?php if ($ctInfo): ?>
                            <span class="scm-ct-badge" style="background:<?= $ctInfo[2] ?>;color:<?= $ctInfo[1] ?>"><?= e($ctInfo[0]) ?></span>
                            <?php endif; ?>
                            <span class="scm-status scm-status-<?= e($dstat) ?>"><?= e($statusText) ?></span>
                            <?= e($s['case_title'] ?? '-') ?>
                        </div>
                        <?php if (!empty($s['note'])): ?>
                        <div class="scm-note"><?= e($s['note']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($s['case_sales_note'])): ?>
                        <div class="scm-sales-note"><strong>業務備註：</strong><?= e($s['case_sales_note']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($timeStr): ?>
                    <div class="scm-time"><?= $timeStr ?></div>
                    <?php endif; ?>
                </div>
                <div class="scm-meta">
                    <?php if (!empty($s['address'])): ?>
                    <div class="scm-meta-line">
                        <a class="scm-addr" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($s['address']) ?>" target="_blank" onclick="event.stopPropagation()">
                            <span class="scm-meta-label">📍</span><span><?= e($s['address']) ?></span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($s['case_type'])): ?>
                    <div class="scm-meta-line">
                        <span class="scm-meta-label">🔧</span><span><?= e($s['case_type']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($s['branch_name'])): ?>
                    <div class="scm-meta-line">
                        <span class="scm-meta-label">🏢</span><span style="color:#888"><?= e($s['branch_name']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($engineers)): ?>
                <div class="scm-engineers">
                    <?php foreach ($engineers as $eng): ?>
                    <span class="scm-eng-tag <?= !empty($eng['is_lead']) ? 'lead' : '' ?>"><?= e($eng['real_name']) ?><?= !empty($eng['is_lead']) ? ' ★' : '' ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>

</div>

<!-- 浮動「新增排工」按鈕（有權限才顯示） -->
<?php if ($canManage): ?>
<a href="/schedule.php?action=create" class="scm-fab" title="新增排工">+</a>
<?php endif; ?>

<script>
function scmChangeFilter(key, value) {
    var url = new URL(window.location.href);
    if (value && value !== '0') url.searchParams.set(key, value);
    else url.searchParams.delete(key);
    url.searchParams.delete('date'); // 切篩選清掉單日
    window.location.href = url.toString();
}

function scmApplyKeyword() {
    var kw = (document.getElementById('scmKeyword').value || '').trim();
    var url = new URL(window.location.href);
    if (kw !== '') url.searchParams.set('keyword', kw);
    else url.searchParams.delete('keyword');
    url.searchParams.delete('date'); // 搜尋時清掉單日篩選
    window.location.href = url.toString();
}

function scmClearKeyword() {
    var url = new URL(window.location.href);
    url.searchParams.delete('keyword');
    window.location.href = url.toString();
}

function scmCloseWarn() {
    var box = document.getElementById('scmWarnBox');
    if (box) box.style.display = 'none';
}

function scmScrollToToday() {
    var today = <?= json_encode($today) ?>;
    // 先找今天
    var el = document.querySelector('[data-today="1"]');
    // 找不到就找最近的未來日（>= today）
    if (!el) {
        var groups = document.querySelectorAll('.scm-date-group[id^="d-"]');
        for (var i = 0; i < groups.length; i++) {
            var dstr = groups[i].id.substring(2);
            if (dstr >= today) { el = groups[i]; break; }
        }
    }
    if (!el) return false;
    // 計算 sticky header 高度（navbar + sticky 控制列）避免被遮
    var navbar = document.querySelector('.navbar');
    var sticky = document.querySelector('.scm-sticky');
    var offset = (navbar ? navbar.offsetHeight : 56) + (sticky ? sticky.offsetHeight : 0) + 8;
    var y = el.getBoundingClientRect().top + window.pageYOffset - offset;
    window.scrollTo({ top: y, behavior: 'smooth' });
    return true;
}

function scmGoToday(e) {
    // 已經在當月 → 不跳轉，只捲動
    var curYear = <?= (int)date('Y') ?>;
    var curMonth = <?= (int)date('n') ?>;
    var pageYear = <?= (int)$year ?>;
    var pageMonth = <?= (int)$month ?>;
    if (curYear === pageYear && curMonth === pageMonth) {
        if (scmScrollToToday()) {
            e.preventDefault();
        }
    }
}

// 頁面載入或 hash = #today 時，自動捲到今天
document.addEventListener('DOMContentLoaded', function() {
    var isCurrentMonth = (<?= (int)date('Y') ?> === <?= (int)$year ?> && <?= (int)date('n') ?> === <?= (int)$month ?>);
    var hasFilterDate = <?= $filterDate ? 'true' : 'false' ?>;
    if (isCurrentMonth && !hasFilterDate) {
        // 延遲一點等 layout
        setTimeout(scmScrollToToday, 50);
    }

    // 案別篩選 tabs
    var ctTabs = document.querySelectorAll('.scm-ct-tab');
    ctTabs.forEach(function(t) {
        t.addEventListener('click', function() {
            ctTabs.forEach(function(x){ x.classList.remove('active'); });
            t.classList.add('active');
            var sel = t.getAttribute('data-ct');
            document.querySelectorAll('.scm-card').forEach(function(el) {
                if (!sel) { el.classList.remove('ct-hidden'); }
                else { el.classList.toggle('ct-hidden', el.getAttribute('data-case-type') !== sel); }
            });
            // 如果整個日期沒有符合的卡，也隱藏該日期 group
            document.querySelectorAll('.scm-date-group').forEach(function(g) {
                var visible = g.querySelectorAll('.scm-card:not(.ct-hidden)').length;
                g.style.display = visible ? '' : 'none';
            });
        });
    });
});
</script>
