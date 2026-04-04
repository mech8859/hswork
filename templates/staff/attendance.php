<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>工程人員出勤狀況表</h2>
    <div class="d-flex gap-1 align-center">
        <a href="/attendance.php?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="btn btn-outline btn-sm">&laquo; 上月</a>
        <span style="font-weight:600;font-size:1.1rem"><?= $year ?>年<?= $month ?>月</span>
        <a href="/attendance.php?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" class="btn btn-outline btn-sm">下月 &raquo;</a>
        <a href="/attendance.php" class="btn btn-outline btn-sm">本月</a>
    </div>
</div>

<div class="card" style="padding:8px">
    <div class="d-flex gap-2 flex-wrap mb-1" style="font-size:.8rem">
        <span><span class="att-dot att-dot-leave"></span> 休假人員</span>
        <span class="text-muted">共 <?= $totalEngineers ?> 位工程人員</span>
    </div>

    <div class="att-calendar">
        <div class="att-header">
            <?php
            $dayNames = array('日','一','二','三','四','五','六');
            foreach ($dayNames as $dn):
            ?>
            <div class="att-day-name"><?= $dn ?></div>
            <?php endforeach; ?>
        </div>
        <div class="att-grid">
            <?php
            // 空白格
            for ($i = 0; $i < $firstDayOfWeek; $i++):
            ?>
            <div class="att-cell att-empty"></div>
            <?php endfor; ?>

            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $isToday = ($dateStr === date('Y-m-d'));
                $isWeekend = in_array(($firstDayOfWeek + $d - 1) % 7, array(0, 6));
                $leaveList = isset($dateLeaves[$dateStr]) ? $dateLeaves[$dateStr] : array();
                $leaveCount = count($leaveList);
            ?>
            <div class="att-cell <?= $isToday ? 'att-today' : '' ?> <?= $isWeekend ? 'att-weekend' : '' ?>"
                 onclick="showDayDetail('<?= $dateStr ?>')"
                 data-date="<?= $dateStr ?>">
                <div class="att-date"><?= $d ?></div>
                <?php if ($leaveCount > 0): ?>
                <div class="att-info att-info-leave"><?= $leaveCount ?>人</div>
                <?php foreach ($leaveList as $lv): ?>
                <div class="att-leave-item">
                    <span class="att-leave-name"><?= e($lv['name']) ?></span>
                    <span class="att-leave-type"><?= e($lv['type']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- 日期詳情彈窗 -->
<div id="dayModal" class="modal-overlay hidden" onclick="if(event.target===this)closeDayModal()">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="dayModalTitle">-</h3>
            <span class="modal-close" onclick="closeDayModal()">&times;</span>
        </div>
        <div id="dayModalContent">
            <p class="text-muted">載入中...</p>
        </div>
    </div>
</div>

<style>
.att-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
.att-dot-leave { background: var(--warning); }

.att-calendar { overflow-x: auto; }
.att-header { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; }
.att-day-name { font-weight: 600; font-size: .8rem; color: var(--gray-700); padding: 6px 0; }
.att-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.att-cell {
    min-height: 130px; border: 1px solid var(--gray-200); border-radius: 4px;
    padding: 4px; cursor: pointer; transition: background .15s;
    font-size: .8rem;
}
.att-cell:hover { background: var(--gray-100); }
.att-empty { border: none; cursor: default; }
.att-empty:hover { background: transparent; }
.att-today { background: #e8f0fe; border-color: var(--primary); }
.att-weekend { background: #fafafa; }
.att-date { font-weight: 600; font-size: .85rem; margin-bottom: 2px; }
.att-today .att-date { color: var(--primary); }
.att-info { padding: 1px 4px; border-radius: 3px; margin-top: 2px; font-size: .7rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.att-info-leave { background: #fef7e0; color: #b06000; display: inline-block; margin-bottom: 2px; }
.att-leave-item { display: flex; align-items: center; gap: 2px; margin-top: 1px; font-size: .7rem; border-left: 2px solid #e8a000; padding-left: 3px; }
.att-leave-name { color: #b06000; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.att-leave-type { color: #e07000; font-size: .65rem; white-space: nowrap; }

/* Modal */
.modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,.4); z-index: 2000;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
}
.modal-box {
    background: #fff; border-radius: 12px; padding: 24px;
    width: 100%; max-width: 500px; box-shadow: var(--shadow-lg);
    max-height: 80vh; overflow-y: auto;
}
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.modal-header h3 { margin: 0; font-size: 1.1rem; }
.modal-close { font-size: 1.5rem; cursor: pointer; color: var(--gray-500); line-height: 1; }
.modal-close:hover { color: var(--danger); }

.att-detail-section { margin-bottom: 12px; }
.att-detail-title { font-weight: 600; font-size: .85rem; color: var(--gray-700); margin-bottom: 4px; }
.att-detail-list { display: flex; flex-wrap: wrap; gap: 4px; }
.att-detail-tag { padding: 2px 8px; border-radius: 12px; font-size: .8rem; }
.att-detail-tag-leave { background: #fef7e0; color: #b06000; }

@media (max-width: 600px) {
    .att-cell { min-height: 60px; padding: 2px; }
    .att-info { font-size: .65rem; }
}
</style>

<script>
<?php
// 假別對照
$leaveTypeLabels = "var leaveTypes = {";
$appCfg = require __DIR__ . '/../../config/app.php';
$first = true;
foreach ($appCfg['leave_types'] as $k => $v) {
    if (!$first) $leaveTypeLabels .= ',';
    $leaveTypeLabels .= "'" . $k . "':'" . $v . "'";
    $first = false;
}
$leaveTypeLabels .= '};';
echo $leaveTypeLabels;
?>

function showDayDetail(date) {
    document.getElementById('dayModalTitle').textContent = date;
    document.getElementById('dayModalContent').innerHTML = '<p class="text-muted">載入中...</p>';
    document.getElementById('dayModal').classList.remove('hidden');

    fetch('/attendance.php?action=day_detail&date=' + date)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var html = '';
        if (data.on_leave && data.on_leave.length > 0) {
            html += '<div class="att-detail-section"><div class="att-detail-title">請假人員</div><div class="att-detail-list">';
            data.on_leave.forEach(function(l) {
                var typeLabel = leaveTypes[l.leave_type] || l.leave_type;
                html += '<span class="att-detail-tag att-detail-tag-leave">' + l.real_name + '（' + typeLabel + '）</span>';
            });
            html += '</div></div>';
        } else {
            html = '<p class="text-muted">當日無人請假</p>';
        }
        document.getElementById('dayModalContent').innerHTML = html;
    })
    .catch(function() {
        document.getElementById('dayModalContent').innerHTML = '<p class="text-danger">載入失敗</p>';
    });
}

function closeDayModal() {
    document.getElementById('dayModal').classList.add('hidden');
}
</script>
