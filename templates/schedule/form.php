<?php
$isEdit = !empty($schedule);
$currentEngineers = [];
if ($isEdit) {
    $currentEngineers = array_column($schedule['engineers'], 'user_id');
}
?>

<h2><?= $isEdit ? '編輯排工' : '新增排工' ?></h2>

<form method="POST" class="mt-2" id="scheduleForm">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">排工資料</div>
        <div class="form-row">
            <div class="form-group">
                <label>施工日期 *</label>
                <input type="date" max="2099-12-31" name="schedule_date" id="scheduleDate" class="form-control"
                       value="<?= e($schedule['schedule_date'] ?? $date ?? date('Y-m-d')) ?>" required
                       onchange="reloadSuggestions()">
                <?php if (!$isEdit): ?>
                <div style="margin-top:6px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                    <button type="button" class="btn btn-outline btn-sm" onclick="addDateToBatch()">+ 加入批次多日</button>
                    <span style="color:var(--gray-400);font-size:.85rem">|</span>
                    <span style="font-size:.85rem;color:var(--gray-600)">連續</span>
                    <input type="number" id="consecutiveDays" min="2" max="30" value="3" style="width:55px;padding:4px 6px;border:1px solid var(--gray-300);border-radius:4px;font-size:.85rem;text-align:center">
                    <span style="font-size:.85rem;color:var(--gray-600)">天</span>
                    <button type="button" class="btn btn-primary btn-sm" onclick="generateConsecutiveDates()" style="padding:4px 10px">產生</button>
                    <small class="text-muted">跳過週日，上限30天</small>
                </div>
                <div id="batchDatesList" style="margin-top:6px;display:flex;gap:4px;flex-wrap:wrap"></div>
                <small class="text-muted" id="batchHint" style="display:none;margin-top:4px">
                    ⚡ 將批次建立 <strong id="batchCount">0</strong> 筆排工（工程師/車輛以主日期為準，其他日期請自行確認無衝突）
                </small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>案件 *</label>
                <select name="case_id" id="caseSelect" class="form-control" required onchange="reloadSuggestions()">
                    <option value="">請選擇案件</option>
                    <?php foreach ($cases as $c): ?>
                    <option value="<?= $c['id'] ?>"
                            data-max="<?= $c['max_engineers'] ?>"
                            data-visits="<?= $c['total_visits'] ?>"
                            data-current="<?= $c['current_visit'] ?>"
                            data-designated-time="<?= e(!empty($c['planned_start_time']) ? $c['planned_start_time'] : '') ?>"
                            data-work-start="<?= e(!empty($c['work_time_start']) ? $c['work_time_start'] : '') ?>"
                            data-work-end="<?= e(!empty($c['work_time_end']) ? $c['work_time_end'] : '') ?>"
                            data-est-days="<?= e(!empty($c['est_labor_days']) ? $c['est_labor_days'] : '') ?>"
                            data-planned-start="<?= e(!empty($c['planned_start_date']) ? $c['planned_start_date'] : '') ?>"
                            <?= ($schedule['case_id'] ?? $caseId ?? '') == $c['id'] ? 'selected' : '' ?>>
                        <?= e($c['case_number']) ?> - <?= e($c['title']) ?> (<?= e($c['branch_name']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>第幾次施工</label>
                <input type="text" class="form-control" readonly
                       value="<?= $isEdit ? e($schedule['visit_number']) : '（儲存時自動計算）' ?>"
                       style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <?php foreach (['planned'=>'已規劃','confirmed'=>'已確認','in_progress'=>'施工中','completed'=>'已完工','cancelled'=>'已取消'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($schedule['status'] ?? 'planned') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>預計時間</label>
                <input type="time" name="start_time" id="startTime" class="form-control"
                       value="<?= e($schedule['start_time'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>結束時間</label>
                <input type="time" name="end_time" id="endTime" class="form-control"
                       value="<?= e($schedule['end_time'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>指定時間 <small class="text-muted">（案件自動帶入）</small></label>
                <input type="time" name="designated_time" id="designatedTime" class="form-control"
                       value="<?= e($schedule['designated_time'] ?? '') ?>"
                       style="<?= !empty($schedule['designated_time']) ? 'background:#fff3e0;font-weight:600' : '' ?>">
            </div>
        </div>
        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="2"><?= e($schedule['note'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- 車輛選擇 -->
    <div class="card">
        <div class="card-header">車輛指派</div>
        <div class="form-group">
            <label>車輛</label>
            <select name="vehicle_id" class="form-control" id="vehicleSelect">
                <option value="">不指派車輛</option>
                <?php foreach ($vehicles as $v): ?>
                <option value="<?= $v['id'] ?>"
                        <?= $v['is_busy'] ? 'disabled' : '' ?>
                        <?= ($schedule['vehicle_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                    <?= e($v['plate_number']) ?> - <?= e($v['vehicle_type']) ?> (<?= $v['seats'] ?>座)
                    <?= $v['driver_name'] ? ' [' . e($v['driver_name']) . ']' : '' ?>
                    <?= $v['is_busy'] ? ' [已派出]' : '' ?>
                    (<?= e($v['branch_name']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- 工程師選擇 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>工程師指派</span>
            <span class="text-muted" style="font-size:.8rem" id="engineerCount">已選 0 人</span>
        </div>
        <p class="text-muted mb-1" style="font-size:.85rem">綠色=技能符合，灰色=已滿/休假，黃色=有排工但仍有餘量</p>

        <input type="hidden" name="engineer_ids_submitted" value="1">
        <div id="engineerList">
            <?php if (!empty($engineers)): ?>
                <?php foreach ($engineers as $eng): ?>
                <div class="engineer-option <?= $eng['is_busy'] ? 'engineer-busy' : '' ?> <?= $eng['skill_match'] ? 'engineer-match' : '' ?>">
                    <label class="checkbox-label">
                        <input type="checkbox" name="engineer_ids[]" value="<?= $eng['id'] ?>"
                               onchange="updateCount();<?= $eng['is_busy'] ? 'warnOvertime(this,\'' . e($eng['real_name']) . '\',' . (isset($eng['hours_used']) ? $eng['hours_used'] : 0) . ')' : '' ?>"
                               data-overtime="<?= $eng['is_busy'] ? '1' : '0' ?>"
                               data-name="<?= e($eng['real_name']) ?>"
                               data-used="<?= isset($eng['hours_used']) ? $eng['hours_used'] : 0 ?>"
                               <?= in_array($eng['id'], $currentEngineers) ? 'checked' : '' ?>>
                        <span>
                            <?= e($eng['real_name']) ?>
                            <span class="text-muted" style="font-size:.8rem">(<?= e($eng['branch_name']) ?>)</span>
                        </span>
                    </label>
                    <?php if ($eng['skill_match']): ?>
                        <span class="badge badge-success">技能符合</span>
                    <?php endif; ?>
                    <?php if (!empty($eng['is_on_leave'])): ?>
                        <span class="badge" style="background:#e53935;color:#fff">休假</span>
                    <?php elseif ($eng['is_busy']): ?>
                        <span class="badge" style="background:#e53935;color:#fff">已滿(<?= isset($eng['hours_used']) ? $eng['hours_used'] : 0 ?>h)</span>
                    <?php elseif (isset($eng['hours_used']) && $eng['hours_used'] > 0): ?>
                        <span class="badge badge-warning">已排<?= $eng['hours_used'] ?>h / 剩<?= $eng['remaining_hours'] ?>h</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">請先選擇案件以載入推薦工程師</p>
            <?php endif; ?>
        </div>

        <div class="form-group mt-1">
            <label>主工程師</label>
            <select name="lead_engineer_id" class="form-control" id="leadSelect">
                <option value="">不指定</option>
                <?php if (!empty($engineers)):
                    foreach ($engineers as $eng):
                        if ($eng['is_busy'] && !in_array($eng['id'], $currentEngineers)) continue;
                ?>
                <option value="<?= $eng['id'] ?>"><?= e($eng['real_name']) ?></option>
                <?php endforeach; endif; ?>
            </select>
        </div>
    </div>

    <!-- 點工人員選擇 -->
    <?php
    $currentDW = array();
    if ($isEdit && !empty($schedule['dispatch_workers'])) {
        $currentDW = array_column($schedule['dispatch_workers'], 'dispatch_worker_id');
    }
    ?>
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>點工人員</span>
            <span class="text-muted" style="font-size:.8rem" id="dwCount">已選 <?= count($currentDW) ?> 人</span>
        </div>
        <?php if (!empty($dispatch_workers)): ?>
        <div id="dwList">
            <?php foreach ($dispatch_workers as $dw): ?>
            <div class="engineer-option">
                <label class="checkbox-label">
                    <input type="checkbox" name="dispatch_worker_ids[]" value="<?= $dw['id'] ?>"
                           onchange="updateDWCount()"
                           <?= in_array($dw['id'], $currentDW) ? 'checked' : '' ?>>
                    <span>
                        <?= e($dw['name']) ?>
                        <?php if ($dw['vendor']): ?>
                        <span class="text-muted" style="font-size:.8rem">(<?= e($dw['vendor']) ?>)</span>
                        <?php endif; ?>
                    </span>
                </label>
                <?php if ($dw['specialty']): ?>
                <span class="badge" style="background:#e3f2fd;color:#1565c0"><?= e($dw['specialty']) ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted">尚無點工人員資料，請先在人員管理新增</p>
        <?php endif; ?>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立排工' ?></button>
        <a href="/schedule.php" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.engineer-option {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px; border-bottom: 1px solid var(--gray-100);
}
.engineer-match { background: #e6f4ea; }
.engineer-busy { opacity: .6; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.checkbox-label input[type="checkbox"] { width: 18px; height: 18px; }
.batch-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px; border-radius: 12px;
    background: #e3f2fd; color: #1565c0;
    font-size: .78rem; font-weight: 600;
    border: 1px solid #90caf9;
}
.batch-chip.batch-chip-primary { background: #fff3e0; color: #e65100; border-color: #ffb74d; }
.batch-chip a { color: inherit; text-decoration: none; font-weight: 700; cursor: pointer; margin-left: 2px; }
.batch-chip a:hover { color: #c62828; }
</style>

<script>
function updateCount() {
    var checked = document.querySelectorAll('#engineerList input[type="checkbox"]:checked').length;
    document.getElementById('engineerCount').textContent = '已選 ' + checked + ' 人';
}
function warnOvertime(cb, name, usedHours) {
    if (cb.checked) {
        alert('⚠️ 注意：' + name + ' 當日已排工 ' + usedHours + ' 小時，已超過預估工作時數上限。\n\n如確認安排，請注意工作量分配。');
    }
}
function updateDWCount() {
    var checked = document.querySelectorAll('#dwList input[type="checkbox"]:checked').length;
    document.getElementById('dwCount').textContent = '已選 ' + checked + ' 人';
}

function reloadSuggestions() {
    var caseId = document.getElementById('caseSelect').value;
    var date = document.getElementById('scheduleDate').value;
    if (!caseId || !date) return;

    // 從案件帶入指定時間 + 預估天數 + 預計施工日
    var opt = document.getElementById('caseSelect').selectedOptions[0];
    if (opt) {
        var dt = opt.dataset.designatedTime || '';
        var ws = opt.dataset.workStart || '';
        var we = opt.dataset.workEnd || '';
        var dtInput = document.getElementById('designatedTime');
        if (dt && !dtInput.value) {
            dtInput.value = dt.substring(0, 5);
            dtInput.style.background = '#fff3e0';
            dtInput.style.fontWeight = '600';
        }
        var stInput = document.getElementById('startTime');
        var etInput = document.getElementById('endTime');
        if (ws && !stInput.value) stInput.value = ws.substring(0, 5);
        if (we && !etInput.value) etInput.value = we.substring(0, 5);

        // 帶入預計施工日
        var ps = opt.dataset.plannedStart || '';
        var dateInput = document.getElementById('scheduleDate');
        if (ps && dateInput && !dateInput.dataset.userSet) {
            dateInput.value = ps;
        }
        // 帶入預估施工天數到連續天數
        var ed = opt.dataset.estDays || '';
        var cdInput = document.getElementById('consecutiveDays');
        if (ed && cdInput && parseFloat(ed) >= 2) {
            cdInput.value = Math.ceil(parseFloat(ed));
        }
    }

    // 透過 AJAX 載入推薦工程師（含多日可用性）
    var engUrl = '/schedule.php?action=ajax_engineers&case_id=' + caseId + '&date=' + date;
    if (typeof batchDates !== 'undefined' && batchDates.length > 1) {
        engUrl += '&dates=' + encodeURIComponent(batchDates.join(','));
    }
    fetch(engUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        var hasBatch = (typeof batchDates !== 'undefined' && batchDates.length > 1);
        var html = '';
        result.data.forEach(function(eng) {
            var usedH = eng.hours_used || 0;
            var overtimeAttr = eng.is_busy ? 'warnOvertime(this,\'' + eng.real_name.replace(/'/g, "\\'") + '\',' + usedH + ')' : '';
            html += '<div class="engineer-option ' + (eng.is_busy ? 'engineer-busy' : '') + ' ' + (eng.skill_match ? 'engineer-match' : '') + '">';
            html += '<label class="checkbox-label">';
            html += '<input type="checkbox" name="engineer_ids[]" value="' + eng.id + '" onchange="updateCount();' + overtimeAttr + '">';
            html += '<span>' + eng.real_name + ' <span class="text-muted" style="font-size:.8rem">(' + eng.branch_name + ')</span></span>';
            html += '</label>';
            if (eng.skill_match) html += '<span class="badge badge-success">技能符合</span>';
            if (eng.is_on_leave) {
                html += '<span class="badge" style="background:#e53935;color:#fff">休假</span>';
            } else if (eng.is_busy) {
                html += '<span class="badge" style="background:#e53935;color:#fff">已滿(' + usedH + 'h)</span>';
            } else if (usedH > 0) {
                html += '<span class="badge badge-warning">已排' + usedH + 'h / 剩' + eng.remaining_hours + 'h</span>';
            }
            // 連續多日可用性標記
            if (hasBatch && eng.multi_day_total) {
                if (eng.multi_day_all_ok) {
                    html += '<span class="badge" style="background:#2e7d32;color:#fff">' + eng.multi_day_total + '天全可</span>';
                } else if (eng.multi_day_available > 0) {
                    var unavailShort = '';
                    if (eng.multi_day_unavail_dates && eng.multi_day_unavail_dates.length > 0) {
                        unavailShort = eng.multi_day_unavail_dates.map(function(d){ return d.substring(5); }).join(',');
                    }
                    html += '<span class="badge" style="background:#e65100;color:#fff">' + eng.multi_day_available + '/' + eng.multi_day_total + '天可' + (unavailShort ? ' 衝突:' + unavailShort : '') + '</span>';
                } else {
                    html += '<span class="badge" style="background:#c62828;color:#fff">全部不可用</span>';
                }
            }
            html += '</div>';
        });
        document.getElementById('engineerList').innerHTML = html || '<p class="text-muted">無可用工程師</p>';
        updateCount();

        // 更新主工程師下拉
        var leadHtml = '<option value="">不指定</option>';
        result.data.forEach(function(eng) {
            leadHtml += '<option value="' + eng.id + '">' + eng.real_name + (eng.is_busy ? ' (已滿)' : '') + '</option>';
        });
        document.getElementById('leadSelect').innerHTML = leadHtml;
    });

    // 載入可用車輛
    fetch('/schedule.php?action=ajax_vehicles&date=' + date, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        var html = '<option value="">不指派車輛</option>';
        result.data.forEach(function(v) {
            html += '<option value="' + v.id + '" ' + (v.is_busy ? 'disabled' : '') + '>';
            html += v.plate_number + ' - ' + v.vehicle_type + ' (' + v.seats + '座)';
            if (v.driver_name) html += ' [' + v.driver_name + ']';
            if (v.is_busy) html += ' [已派出]';
            html += ' (' + v.branch_name + ')</option>';
        });
        document.getElementById('vehicleSelect').innerHTML = html;
    });

    // 載入可用點工人員
    fetch('/schedule.php?action=ajax_dispatch_workers&date=' + date, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        var html = '';
        if (result.data && result.data.length > 0) {
            result.data.forEach(function(dw) {
                html += '<div class="engineer-option">';
                html += '<label class="checkbox-label">';
                html += '<input type="checkbox" name="dispatch_worker_ids[]" value="' + dw.id + '" onchange="updateDWCount()">';
                html += '<span>' + dw.name;
                if (dw.vendor) html += ' <span class="text-muted" style="font-size:.8rem">(' + dw.vendor + ')</span>';
                html += '</span></label>';
                if (dw.specialty) html += '<span class="badge" style="background:#e3f2fd;color:#1565c0">' + dw.specialty + '</span>';
                html += '</div>';
            });
        } else {
            html = '<p class="text-muted" style="padding:12px">該日期無已登錄可上工的點工人員</p>';
        }
        document.getElementById('dwList').innerHTML = html;
        updateDWCount();
    });
}

updateCount();

// ===== 多日批次排工 =====
var batchDates = [];
var isEditMode = <?= $isEdit ? 'true' : 'false' ?>;

function addDateToBatch() {
    var dateInput = document.getElementById('scheduleDate');
    var d = dateInput.value;
    if (!d) { alert('請先選擇施工日期'); return; }
    if (batchDates.indexOf(d) !== -1) {
        alert('此日期已在批次清單中');
        return;
    }
    batchDates.push(d);
    batchDates.sort();
    renderBatchDates();
}

function removeBatchDate(d) {
    batchDates = batchDates.filter(function(x) { return x !== d; });
    renderBatchDates();
}

function renderBatchDates() {
    var box = document.getElementById('batchDatesList');
    if (!box) return;
    var primary = document.getElementById('scheduleDate').value;
    var html = '';
    for (var i = 0; i < batchDates.length; i++) {
        var d = batchDates[i];
        var display = formatShortDate(d);
        var cls = (d === primary) ? 'batch-chip batch-chip-primary' : 'batch-chip';
        var title = (d === primary) ? ' title="主日期（推薦工程師依此日期）"' : '';
        html += '<span class="' + cls + '"' + title + '>' + display +
                ' <a onclick="removeBatchDate(\'' + d + '\')">×</a></span>';
    }
    box.innerHTML = html;
    var hint = document.getElementById('batchHint');
    var cnt = document.getElementById('batchCount');
    if (hint && cnt) {
        hint.style.display = batchDates.length > 0 ? 'block' : 'none';
        cnt.textContent = batchDates.length;
    }
}

function formatShortDate(ymd) {
    // 2026-04-08 → 4/8 (一)
    var parts = ymd.split('-');
    if (parts.length !== 3) return ymd;
    var dateObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    var weekdays = ['日','一','二','三','四','五','六'];
    return parseInt(parts[1]) + '/' + parseInt(parts[2]) + ' (' + weekdays[dateObj.getDay()] + ')';
}

// 主日期變更時重新渲染 chip 顏色標記
(function() {
    if (isEditMode) return;
    var dateInput = document.getElementById('scheduleDate');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            renderBatchDates();
        });
    }
})();

// 提交時處理：若有批次日期則送出 schedule_dates[]
(function() {
    if (isEditMode) return;
    var form = document.getElementById('scheduleForm');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        if (batchDates.length === 0) return; // 單日模式，用原本 schedule_date

        // 若主日期不在批次清單中，提示使用者
        var primary = document.getElementById('scheduleDate').value;
        if (primary && batchDates.indexOf(primary) === -1) {
            var ok = confirm('主日期 ' + primary + ' 尚未加入批次清單。\n\n按「確定」會自動將主日期加入一起建立；按「取消」請自行調整。');
            if (!ok) { e.preventDefault(); return; }
            batchDates.push(primary);
            batchDates.sort();
        }

        // 確認批次建立
        var msg = '將批次建立以下 ' + batchDates.length + ' 筆排工：\n\n';
        for (var i = 0; i < batchDates.length; i++) msg += '• ' + batchDates[i] + '\n';
        msg += '\n工程師/車輛/點工均相同，請確認無衝突。確定建立？';
        if (!confirm(msg)) { e.preventDefault(); return; }

        // 移除 schedule_date name 以避免送出單日欄位
        var dateInput = document.getElementById('scheduleDate');
        dateInput.removeAttribute('name');
        // 注入 hidden schedule_dates[]
        for (var j = 0; j < batchDates.length; j++) {
            var h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'schedule_dates[]';
            h.value = batchDates[j];
            form.appendChild(h);
        }
    });
})();

// ===== 連續天數快速產生 =====
function generateConsecutiveDates() {
    var dateInput = document.getElementById('scheduleDate');
    var daysInput = document.getElementById('consecutiveDays');
    if (!dateInput || !daysInput) return;
    var startDate = dateInput.value;
    if (!startDate) { alert('請先選擇施工日期'); return; }
    var days = parseInt(daysInput.value, 10);
    if (isNaN(days) || days < 2) { alert('請輸入至少 2 天'); return; }
    if (days > 30) { days = 30; daysInput.value = 30; }

    // 清空現有批次
    batchDates = [];

    // 從主日期開始，產生連續天數（跳過週日）
    var parts = startDate.split('-');
    var current = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    var count = 0;
    var maxLoop = days + 10; // 避免無限迴圈
    var loop = 0;
    while (count < days && loop < maxLoop) {
        var dow = current.getDay(); // 0=日
        if (dow !== 0) { // 跳過週日
            var y = current.getFullYear();
            var m = String(current.getMonth() + 1).padStart(2, '0');
            var d = String(current.getDate()).padStart(2, '0');
            batchDates.push(y + '-' + m + '-' + d);
            count++;
        }
        current.setDate(current.getDate() + 1);
        loop++;
    }

    renderBatchDates();
    // 重新載入工程師（帶入多日參數）
    reloadSuggestions();
}
</script>
