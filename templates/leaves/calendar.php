<?php
$year = (int)substr($yearMonth, 0, 4);
$month = (int)substr($yearMonth, 5, 2);
$prevMonth = date('Y-m', strtotime($yearMonth . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($yearMonth . '-01 +1 month'));
$firstDow = (int)date('w', strtotime($yearMonth . '-01'));
$daysInMonth = (int)date('t', strtotime($yearMonth . '-01'));
$today = date('Y-m-d');
$dayNames = array('日','一','二','三','四','五','六');
$typeColors = array('annual'=>'#4CAF50','personal'=>'#FF9800','sick'=>'#F44336','official'=>'#2196F3');
$typeLabels = array('annual'=>'特休','day_off'=>'排休','personal'=>'事假','sick'=>'病假','menstrual'=>'生理假','bereavement'=>'喪假','official'=>'公假');
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>請假行事曆</h2>
    <div class="d-flex gap-1">
        <a href="/leaves.php?action=list" class="btn btn-outline btn-sm">請假清單</a>
        <a href="/leaves.php?action=create" class="btn btn-primary btn-sm">+ 申請請假</a>
    </div>
</div>

<!-- 月份導航 -->
<div class="cal-nav">
    <a href="/leaves.php?action=calendar&month=<?= $prevMonth ?>" class="btn btn-outline btn-sm">&laquo;</a>
    <h3 style="margin:0"><?= $year ?>年<?= $month ?>月</h3>
    <a href="/leaves.php?action=calendar&month=<?= $nextMonth ?>" class="btn btn-outline btn-sm">&raquo;</a>
</div>

<!-- 圖例 -->
<div class="leave-legend">
    <?php foreach ($typeLabels as $tk => $tl): ?>
    <span class="legend-item"><span class="legend-dot" style="background:<?= $typeColors[$tk] ?>"></span><?= $tl ?></span>
    <?php endforeach; ?>
</div>

<!-- 桌面月曆 -->
<div class="leave-cal hide-mobile">
    <div class="cal-grid">
        <?php foreach ($dayNames as $dn): ?>
        <div class="cal-header"><?= $dn ?></div>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < $firstDow; $i++): ?>
        <div class="cal-cell cal-empty"></div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $daysInMonth; $d++):
            $date = $yearMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
            $dayLeaves = isset($calendarData[$date]) ? $calendarData[$date] : array();
            $isToday = ($date === $today);
            $isWeekend = (($firstDow + $d - 1) % 7 === 0 || ($firstDow + $d - 1) % 7 === 6);
            $leaveCount = count($dayLeaves);
            // 去重人數
            $uniqueUsers = array();
            foreach ($dayLeaves as $lv) {
                $uniqueUsers[$lv['user_id']] = $lv;
            }
            $personCount = count($uniqueUsers);
        ?>
        <div class="cal-cell <?= $isToday ? 'cal-today' : '' ?> <?= $isWeekend ? 'cal-weekend' : '' ?>" data-date="<?= $date ?>">
            <div class="cal-day-header">
                <span class="cal-day-num"><?= $d ?></span>
                <?php if ($personCount > 0): ?>
                <span class="cal-leave-count"><?= $personCount ?>人</span>
                <?php endif; ?>
                <?php if ($canManage): ?>
                <button type="button" class="cal-add-btn" onclick="showLeaveModal('<?= $date ?>')" title="新增請假">+</button>
                <?php endif; ?>
            </div>
            <div class="cal-leave-list">
                <?php
                $shown = 0;
                foreach ($uniqueUsers as $uid => $lv):
                    if ($shown >= 4) break;
                    $color = isset($typeColors[$lv['leave_type']]) ? $typeColors[$lv['leave_type']] : '#999';
                ?>
                <div class="cal-leave-item" style="border-left:3px solid <?= $color ?>">
                    <span class="cal-leave-name"><?= e($lv['real_name']) ?></span>
                    <span class="cal-leave-type" style="color:<?= $color ?>"><?= isset($typeLabels[$lv['leave_type']]) ? $typeLabels[$lv['leave_type']] : '' ?></span>
                    <?php if ($lv['status'] === 'pending'): ?><span class="badge badge-warning" style="font-size:.6rem">待審</span><?php endif; ?>
                    <?php if ($canManage && $lv['start_date'] === $lv['end_date']): ?>
                    <button type="button" class="cal-leave-del" onclick="cancelLeave(<?= $uid ?>,'<?= $date ?>')" title="取消">&times;</button>
                    <?php endif; ?>
                </div>
                <?php $shown++; endforeach; ?>
                <?php if ($personCount > 4): ?>
                <div class="cal-more" onclick="showLeaveModal('<?= $date ?>')">+<?= $personCount - 4 ?> 更多</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- 手機版列表 -->
<div class="show-mobile">
    <?php for ($d = 1; $d <= $daysInMonth; $d++):
        $date = $yearMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
        $dayLeaves = isset($calendarData[$date]) ? $calendarData[$date] : array();
        $isToday = ($date === $today);
        $uniqueUsers = array();
        foreach ($dayLeaves as $lv) { $uniqueUsers[$lv['user_id']] = $lv; }
        if (empty($uniqueUsers) && !$isToday) continue;
        $dow = $dayNames[(int)date('w', strtotime($date))];
    ?>
    <div class="card mb-1 <?= $isToday ? 'cal-today-card' : '' ?>">
        <div class="d-flex justify-between align-center">
            <strong><?= $month ?>月<?= $d ?>日 (<?= $dow ?>)</strong>
            <?php if (count($uniqueUsers) > 0): ?>
            <span class="badge badge-warning"><?= count($uniqueUsers) ?>人請假</span>
            <?php endif; ?>
            <?php if ($canManage): ?>
            <button type="button" class="btn btn-outline btn-sm" onclick="showLeaveModal('<?= $date ?>')" style="padding:2px 8px">+</button>
            <?php endif; ?>
        </div>
        <?php foreach ($uniqueUsers as $uid => $lv):
            $color = isset($typeColors[$lv['leave_type']]) ? $typeColors[$lv['leave_type']] : '#999';
        ?>
        <div class="d-flex align-center gap-1 mt-1" style="font-size:.9rem">
            <span style="width:8px;height:8px;border-radius:50%;background:<?= $color ?>;flex-shrink:0"></span>
            <span><?= e($lv['real_name']) ?></span>
            <span class="text-muted" style="font-size:.75rem"><?= isset($typeLabels[$lv['leave_type']]) ? $typeLabels[$lv['leave_type']] : '' ?></span>
            <?php if ($lv['status'] === 'pending'): ?><span class="badge badge-warning" style="font-size:.6rem">待審</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($uniqueUsers)): ?>
        <p class="text-muted" style="font-size:.85rem;margin:4px 0 0">無人請假</p>
        <?php endif; ?>
    </div>
    <?php endfor; ?>
</div>

<!-- 新增請假 Modal -->
<?php if ($canManage): ?>
<div id="leaveModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)hideLeaveModal()">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalDate"></h3>
            <button type="button" onclick="hideLeaveModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer">&times;</button>
        </div>
        <div class="form-group">
            <label>假別</label>
            <select id="modalLeaveType" class="form-control">
                <option value="annual">特休</option>
                <option value="day_off">排休</option>
                <option value="personal">事假</option>
                <option value="sick">病假</option>
                <option value="menstrual">生理假</option>
                <option value="bereavement">喪假</option>
                <option value="official">公假</option>
            </select>
        </div>
        <div class="form-group">
            <label>選擇人員（可多選）</label>
            <div id="modalUserList" class="modal-user-list">
                <?php foreach ($users as $u): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="modal_users[]" value="<?= $u['id'] ?>">
                    <span><?= e($u['real_name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- 顯示該日已請假人員 -->
        <div id="modalExisting" style="display:none">
            <label style="font-size:.85rem;color:var(--gray-500)">已請假人員</label>
            <div id="modalExistingList"></div>
        </div>
        <div class="d-flex gap-1 mt-2">
            <button type="button" class="btn btn-primary" onclick="submitBatchLeave()">確定新增</button>
            <button type="button" class="btn btn-outline" onclick="hideLeaveModal()">取消</button>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.cal-nav { display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 12px; }
.leave-legend { display: flex; gap: 16px; justify-content: center; margin-bottom: 12px; font-size: .8rem; }
.legend-item { display: flex; align-items: center; gap: 4px; }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; }

.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: var(--gray-200); border: 1px solid var(--gray-200); border-radius: var(--radius); overflow: hidden; }
.cal-header { background: var(--gray-100); text-align: center; padding: 10px 4px; font-weight: 600; font-size: .9rem; }
.cal-cell { background: #fff; min-height: 130px; padding: 6px; position: relative; }
.cal-empty { background: var(--gray-50); }
.cal-today { background: #FFF8E1; }
.cal-weekend { background: #FAFAFA; }
.cal-today-card { border-left: 3px solid var(--primary); }

.cal-day-header { display: flex; align-items: center; gap: 4px; margin-bottom: 4px; }
.cal-day-num { font-weight: 600; font-size: 1rem; }
.cal-leave-count { font-size: .7rem; color: var(--danger); font-weight: 600; }
.cal-add-btn {
    margin-left: auto; width: 20px; height: 20px; border-radius: 50%;
    background: var(--primary); color: #fff; border: none; font-size: 14px;
    cursor: pointer; display: none; align-items: center; justify-content: center; line-height: 1;
}
.cal-cell:hover .cal-add-btn { display: flex; }

.cal-leave-list { display: flex; flex-direction: column; gap: 2px; }
.cal-leave-item {
    display: flex; align-items: center; gap: 4px; padding: 3px 6px;
    background: var(--gray-50); border-radius: 4px; font-size: .8rem; position: relative;
}
.cal-leave-name { font-weight: 500; }
.cal-leave-type { font-size: .65rem; }
.cal-leave-del {
    display: none; position: absolute; right: 2px; top: 0;
    background: var(--danger); color: #fff; border: none; border-radius: 50%;
    width: 14px; height: 14px; font-size: 10px; cursor: pointer; line-height: 1;
}
.cal-leave-item:hover .cal-leave-del { display: block; }
.cal-more { font-size: .7rem; color: var(--primary); cursor: pointer; padding: 2px 4px; }

.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.5); z-index: 1000; display: flex; align-items: center; justify-content: center; }
.modal-box { background: #fff; border-radius: var(--radius); padding: 20px; width: 90%; max-width: 400px; max-height: 80vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.modal-user-list { max-height: 300px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; padding: 8px; border: 1px solid var(--gray-200); border-radius: var(--radius); }
.modal-user-list .checkbox-label { font-size: .9rem; }
.modal-user-list input[type="checkbox"] { width: 18px; height: 18px; }

.show-mobile { display: none; }
.hide-mobile { display: block; }
@media (max-width: 767px) {
    .show-mobile { display: block !important; }
    .hide-mobile { display: none !important; }
    .cal-cell { min-height: 60px; }
}
</style>

<script>
var currentModalDate = '';
var calendarLeaves = <?= json_encode($calendarData) ?>;

function showLeaveModal(date) {
    currentModalDate = date;
    var parts = date.split('-');
    document.getElementById('modalDate').textContent = parseInt(parts[1]) + '月' + parseInt(parts[2]) + '日 請假';

    // 清除所有勾選
    var cbs = document.querySelectorAll('#modalUserList input[type="checkbox"]');
    for (var i = 0; i < cbs.length; i++) cbs[i].checked = false;

    // 標記已請假人員
    var existing = calendarLeaves[date] || [];
    var existingIds = {};
    var existingHtml = '';
    for (var i = 0; i < existing.length; i++) {
        existingIds[existing[i].user_id] = true;
        existingHtml += '<div style="font-size:.85rem;padding:2px 0">' + existing[i].real_name + '</div>';
    }
    // 隱藏已請假的 checkbox
    for (var i = 0; i < cbs.length; i++) {
        var uid = parseInt(cbs[i].value);
        cbs[i].closest('.checkbox-label').style.display = existingIds[uid] ? 'none' : 'flex';
    }
    var existingDiv = document.getElementById('modalExisting');
    if (existingHtml) {
        existingDiv.style.display = 'block';
        document.getElementById('modalExistingList').innerHTML = existingHtml;
    } else {
        existingDiv.style.display = 'none';
    }

    document.getElementById('leaveModal').style.display = 'flex';
}

function hideLeaveModal() {
    document.getElementById('leaveModal').style.display = 'none';
}

function submitBatchLeave() {
    var cbs = document.querySelectorAll('#modalUserList input[type="checkbox"]:checked');
    if (cbs.length === 0) { alert('請選擇人員'); return; }
    var userIds = [];
    for (var i = 0; i < cbs.length; i++) userIds.push(cbs[i].value);

    var fd = new FormData();
    fd.append('date', currentModalDate);
    fd.append('leave_type', document.getElementById('modalLeaveType').value);
    for (var i = 0; i < userIds.length; i++) fd.append('user_ids[]', userIds[i]);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/leaves.php?action=batch_create');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.error || '新增失敗');
                }
            } catch(e) { alert('新增失敗'); }
        }
    };
    xhr.send(fd);
}

function cancelLeave(userId, date) {
    if (!confirm('確定取消此人當天的請假?')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/leaves.php?action=cancel_leave');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) location.reload();
            } catch(e) {}
        }
    };
    xhr.send('user_id=' + userId + '&date=' + date);
}
</script>
