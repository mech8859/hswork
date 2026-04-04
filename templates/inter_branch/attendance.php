<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>點工出勤登錄</h2>
    <div class="d-flex gap-1">
        <a href="/inter_branch.php" class="btn btn-outline btn-sm">返回點工費</a>
        <a href="/inter_branch.php?action=attendance_settle_page" class="btn btn-outline btn-sm">出勤結算</a>
    </div>
</div>

<!-- 日期選擇 -->
<div class="card mb-2">
    <form method="GET" class="d-flex align-center gap-1">
        <input type="hidden" name="action" value="attendance">
        <div class="form-group" style="margin:0">
            <label>出勤日期</label>
            <input type="date" name="date" class="form-control" value="<?= e($date) ?>" onchange="this.form.submit()">
        </div>
        <div class="form-group" style="margin:0;align-self:flex-end">
            <button type="button" class="btn btn-outline btn-sm" onclick="goDate(-1)">前一天</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="goToday()">今天</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="goDate(1)">後一天</button>
        </div>
    </form>
</div>

<?php if (!empty($scheduledWorkers)): ?>
<!-- 排工待確認 -->
<div class="card mb-2" style="border-left:4px solid var(--warning)">
    <div class="card-header" style="background:var(--warning-light,#fff8e1)">
        排工指派待確認（<?= count($scheduledWorkers) ?> 筆）
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>點工人員</th>
                    <th>分公司</th>
                    <th>案件</th>
                    <th>日薪</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scheduledWorkers as $sw): ?>
                <tr id="sched-row-<?= $sw['dispatch_worker_id'] ?>-<?= $sw['schedule_id'] ?>">
                    <td><strong><?= e($sw['worker_name']) ?></strong></td>
                    <td><?= e($sw['branch_name']) ?></td>
                    <td><?= e($sw['case_name']) ?></td>
                    <td>$<?= number_format($sw['daily_rate']) ?></td>
                    <td>
                        <button type="button" class="btn btn-primary btn-sm" onclick="confirmScheduled(<?= $sw['dispatch_worker_id'] ?>, <?= $sw['schedule_id'] ?>, <?= $sw['branch_id'] ?>, <?= $sw['daily_rate'] ?>)">確認出勤</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="markAbsent(<?= $sw['dispatch_worker_id'] ?>, <?= $sw['schedule_id'] ?>, <?= $sw['branch_id'] ?>, <?= $sw['daily_rate'] ?>)">未到</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 已建立出勤記錄 -->
<div class="card mb-2">
    <div class="card-header d-flex justify-between align-center">
        <span>出勤記錄（<?= e($date) ?>）</span>
        <?php
        $isFuture = $date > date('Y-m-d');
        if ($isFuture): ?>
        <button type="button" class="btn btn-sm" style="background:#1976d2;color:#fff" onclick="showAddModal(true)">+ 新增預排</button>
        <?php else: ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="showAddModal(false)">+ 新增臨時出勤</button>
        <?php endif; ?>
    </div>

    <!-- 桌面版表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table" id="attendanceTable">
            <thead>
                <tr>
                    <th>點工人員</th>
                    <th>分公司</th>
                    <th>案件</th>
                    <th>計費</th>
                    <th>日薪</th>
                    <th>金額</th>
                    <th>狀態</th>
                    <th>備註</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="attendanceBody">
                <?php if (empty($attendanceList)): ?>
                <tr id="emptyRow"><td colspan="9" class="text-center text-muted">今日無出勤記錄</td></tr>
                <?php else: ?>
                    <?php foreach ($attendanceList as $a): ?>
                    <tr id="att-row-<?= $a['id'] ?>">
                        <td><strong><?= e($a['worker_name']) ?></strong></td>
                        <td><?= e($a['branch_name']) ?></td>
                        <td><?= $a['case_name'] ? e($a['case_name']) : '<span class="text-muted">-</span>' ?></td>
                        <td>
                            <?php if (!$a['settled']): ?>
                            <select class="form-control form-control-sm" onchange="updateField(<?= $a['id'] ?>, 'charge_type', this.value)" style="width:80px">
                                <option value="full_day" <?= $a['charge_type'] === 'full_day' ? 'selected' : '' ?>>全日</option>
                                <option value="half_day" <?= $a['charge_type'] === 'half_day' ? 'selected' : '' ?>>半日</option>
                            </select>
                            <?php else: ?>
                            <?= $a['charge_type'] === 'full_day' ? '全日' : '半日' ?>
                            <?php endif; ?>
                        </td>
                        <td>$<?= number_format($a['daily_rate']) ?></td>
                        <td><strong>$<?= number_format($a['amount']) ?></strong></td>
                        <td>
                            <?php if ($a['status'] === 'present'): ?>
                            <span class="badge badge-success">出勤</span>
                            <?php elseif ($a['status'] === 'absent'): ?>
                            <span class="badge badge-danger">未到</span>
                            <?php elseif ($a['status'] === 'scheduled'): ?>
                            <span class="badge" style="background:#1976d2;color:#fff">預排</span>
                            <?php else: ?>
                            <span class="badge">取消</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:120px">
                            <?php if (!$a['settled']): ?>
                            <input type="text" class="form-control form-control-sm" value="<?= e($a['note']) ?>"
                                   onblur="updateField(<?= $a['id'] ?>, 'note', this.value)" placeholder="備註" style="width:100%">
                            <?php else: ?>
                            <?= e($a['note']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['settled']): ?>
                            <span class="badge badge-success">已結算</span>
                            <?php elseif ($a['status'] === 'scheduled'): ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="confirmPreScheduled(<?= $a['id'] ?>, <?= $a['dispatch_worker_id'] ?>, <?= $a['branch_id'] ?>, <?= $a['daily_rate'] ?>)">確認出勤</button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteAttendance(<?= $a['id'] ?>)">刪除</button>
                            <?php else: ?>
                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteAttendance(<?= $a['id'] ?>)">刪除</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 手機版卡片 -->
    <div class="show-mobile" id="attendanceMobile">
        <?php if (empty($attendanceList)): ?>
        <p class="text-center text-muted" id="emptyMobile">今日無出勤記錄</p>
        <?php else: ?>
            <?php foreach ($attendanceList as $a): ?>
            <div class="card mb-1" id="att-mobile-<?= $a['id'] ?>" style="margin:8px 0">
                <div class="d-flex justify-between align-center mb-1">
                    <strong><?= e($a['worker_name']) ?></strong>
                    <?php if ($a['status'] === 'present'): ?>
                    <span class="badge badge-success">出勤</span>
                    <?php elseif ($a['status'] === 'absent'): ?>
                    <span class="badge badge-danger">未到</span>
                    <?php elseif ($a['status'] === 'scheduled'): ?>
                    <span class="badge" style="background:#1976d2;color:#fff">預排</span>
                    <?php else: ?>
                    <span class="badge">取消</span>
                    <?php endif; ?>
                </div>
                <div class="text-muted" style="font-size:.85rem">
                    <?= e($a['branch_name']) ?> |
                    <?= $a['charge_type'] === 'full_day' ? '全日' : '半日' ?> |
                    $<?= number_format($a['amount']) ?>
                    <?php if ($a['case_name']): ?> | <?= e($a['case_name']) ?><?php endif; ?>
                </div>
                <?php if ($a['note']): ?>
                <div class="text-muted" style="font-size:.8rem;margin-top:4px"><?= e($a['note']) ?></div>
                <?php endif; ?>
                <?php if (!$a['settled']): ?>
                <div class="d-flex gap-1 mt-1">
                    <?php if ($a['status'] === 'scheduled'): ?>
                    <button type="button" class="btn btn-primary btn-sm" onclick="confirmPreScheduled(<?= $a['id'] ?>, <?= $a['dispatch_worker_id'] ?>, <?= $a['branch_id'] ?>, <?= $a['daily_rate'] ?>)">確認出勤</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteAttendance(<?= $a['id'] ?>)">刪除</button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// 統計
$totalAmount = 0;
$presentCount = 0;
$scheduledCount = 0;
foreach ($attendanceList as $a) {
    if ($a['status'] === 'present') {
        $totalAmount += $a['amount'];
        $presentCount++;
    } elseif ($a['status'] === 'scheduled') {
        $scheduledCount++;
    }
}
?>
<div class="card">
    <div class="d-flex justify-between align-center flex-wrap gap-1">
        <div>
            <span class="text-muted">出勤人數：</span><strong><?= $presentCount ?></strong> 人
            <?php if ($scheduledCount > 0): ?>
            &nbsp;|&nbsp;
            <span class="text-muted">預排：</span><strong style="color:#1976d2"><?= $scheduledCount ?></strong> 人
            <?php endif; ?>
            &nbsp;|&nbsp;
            <span class="text-muted">當日費用合計：</span><strong style="color:var(--primary)">$<?= number_format($totalAmount) ?></strong>
        </div>
    </div>
</div>

<!-- 新增臨時出勤 Modal -->
<div id="addModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:2000;overflow-y:auto">
    <div style="max-width:500px;margin:60px auto;background:#fff;border-radius:8px;padding:24px;position:relative">
        <h3 style="margin-bottom:16px" id="addModalTitle">新增臨時出勤</h3>
        <button type="button" onclick="closeAddModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.5rem;cursor:pointer">&times;</button>

        <div class="form-group">
            <label>點工人員 *</label>
            <select id="addWorkerId" class="form-control" onchange="onWorkerChange()">
                <option value="">-- 選擇 --</option>
                <?php foreach ($allWorkers as $w): ?>
                <option value="<?= $w['id'] ?>" data-rate="<?= $w['daily_rate'] ?>"><?= e($w['name']) ?>（$<?= number_format($w['daily_rate']) ?>/日）</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>分公司 *</label>
            <select id="addBranchId" class="form-control">
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>計費方式</label>
            <select id="addChargeType" class="form-control">
                <option value="full_day">全日</option>
                <option value="half_day">半日</option>
            </select>
        </div>
        <div class="form-group">
            <label>日薪</label>
            <input type="number" id="addDailyRate" class="form-control" value="0">
        </div>
        <div class="form-group">
            <label>狀態</label>
            <select id="addStatus" class="form-control">
                <option value="present">出勤</option>
                <option value="absent">未到</option>
            </select>
        </div>
        <div class="form-group">
            <label>備註</label>
            <input type="text" id="addNote" class="form-control" placeholder="選填">
        </div>
        <div class="d-flex gap-1 mt-2">
            <button type="button" class="btn btn-primary" onclick="saveNewAttendance()">儲存</button>
            <button type="button" class="btn btn-outline" onclick="closeAddModal()">取消</button>
        </div>
    </div>
</div>

<script>
var currentDate = '<?= e($date) ?>';

function goDate(offset) {
    var d = new Date(currentDate);
    d.setDate(d.getDate() + offset);
    var y = d.getFullYear();
    var m = ('0' + (d.getMonth()+1)).slice(-2);
    var day = ('0' + d.getDate()).slice(-2);
    location.href = '/inter_branch.php?action=attendance&date=' + y + '-' + m + '-' + day;
}

function goToday() {
    location.href = '/inter_branch.php?action=attendance&date=' + new Date().toISOString().slice(0,10);
}

var isPreScheduleMode = false;
function showAddModal(preSchedule) {
    isPreScheduleMode = !!preSchedule;
    document.getElementById('addModalTitle').textContent = preSchedule ? '新增預排' : '新增臨時出勤';
    var statusSel = document.getElementById('addStatus');
    if (preSchedule) {
        statusSel.innerHTML = '<option value="scheduled">預排</option>';
    } else {
        statusSel.innerHTML = '<option value="present">出勤</option><option value="absent">未到</option>';
    }
    document.getElementById('addModal').style.display = 'block';
}
function closeAddModal() { document.getElementById('addModal').style.display = 'none'; }

function onWorkerChange() {
    var sel = document.getElementById('addWorkerId');
    var opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.rate) {
        document.getElementById('addDailyRate').value = opt.dataset.rate;
    }
}

function confirmScheduled(workerId, scheduleId, branchId, dailyRate) {
    doSave({
        dispatch_worker_id: workerId,
        schedule_id: scheduleId,
        attendance_date: currentDate,
        branch_id: branchId,
        charge_type: 'full_day',
        daily_rate: dailyRate,
        status: 'present',
        note: ''
    }, function() {
        var row = document.getElementById('sched-row-' + workerId + '-' + scheduleId);
        if (row) row.style.display = 'none';
    });
}

function confirmPreScheduled(attId, workerId, branchId, dailyRate) {
    doSave({
        dispatch_worker_id: workerId,
        schedule_id: '',
        attendance_date: currentDate,
        branch_id: branchId,
        charge_type: 'full_day',
        daily_rate: dailyRate,
        status: 'present',
        note: ''
    }, function() { location.reload(); });
}

function markAbsent(workerId, scheduleId, branchId, dailyRate) {
    doSave({
        dispatch_worker_id: workerId,
        schedule_id: scheduleId,
        attendance_date: currentDate,
        branch_id: branchId,
        charge_type: 'full_day',
        daily_rate: dailyRate,
        status: 'absent',
        note: ''
    }, function() {
        var row = document.getElementById('sched-row-' + workerId + '-' + scheduleId);
        if (row) row.style.display = 'none';
    });
}

function saveNewAttendance() {
    var workerId = document.getElementById('addWorkerId').value;
    var branchId = document.getElementById('addBranchId').value;
    if (!workerId) { alert('請選擇點工人員'); return; }
    if (!branchId) { alert('請選擇分公司'); return; }

    doSave({
        dispatch_worker_id: workerId,
        schedule_id: '',
        attendance_date: currentDate,
        branch_id: branchId,
        charge_type: document.getElementById('addChargeType').value,
        daily_rate: document.getElementById('addDailyRate').value,
        status: document.getElementById('addStatus').value,
        note: document.getElementById('addNote').value
    }, function() {
        closeAddModal();
        location.reload();
    });
}

function updateField(id, field, value) {
    // 找到該行原始資料，重送整筆
    // 簡化：僅重新載入
    var row = document.getElementById('att-row-' + id);
    if (!row) return;

    // 取得行資料
    var data = {
        dispatch_worker_id: '',
        schedule_id: '',
        attendance_date: currentDate,
        branch_id: '',
        charge_type: 'full_day',
        daily_rate: 0,
        status: 'present',
        note: ''
    };

    // 用 AJAX 更新單欄位 - 重新載入頁面以簡化
    if (field === 'charge_type' || field === 'note') {
        // 用簡化方式：直接reload
        location.reload();
    }
}

function deleteAttendance(id) {
    if (!confirm('確定刪除此出勤記錄？')) return;
    var fd = new FormData();
    fd.append('id', id);
    fetch('/inter_branch.php?action=attendance_delete', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            location.reload();
        } else {
            alert('刪除失敗（可能已結算）');
        }
    });
}

function doSave(data, callback) {
    var fd = new FormData();
    for (var k in data) {
        fd.append(k, data[k]);
    }
    fetch('/inter_branch.php?action=attendance_save', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            if (callback) callback();
            else location.reload();
        } else {
            alert('儲存失敗：' + (d.error || ''));
        }
    })
    .catch(function(err) { alert('網路錯誤'); });
}
</script>

<style>
.form-control-sm { padding: 4px 8px; font-size: .85rem; height: auto; }
.badge-danger { background: var(--danger); color: #fff; }
</style>
