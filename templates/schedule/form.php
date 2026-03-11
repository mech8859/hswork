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
                <input type="date" name="schedule_date" id="scheduleDate" class="form-control"
                       value="<?= e($schedule['schedule_date'] ?? $date ?? date('Y-m-d')) ?>" required
                       onchange="reloadSuggestions()">
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
                <input type="number" name="visit_number" class="form-control" min="1"
                       value="<?= e($schedule['visit_number'] ?? 1) ?>">
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
        <p class="text-muted mb-1" style="font-size:.85rem">綠色=技能符合，灰色=當日已排工</p>

        <div id="engineerList">
            <?php if (!empty($engineers)): ?>
                <?php foreach ($engineers as $eng): ?>
                <div class="engineer-option <?= $eng['is_busy'] ? 'engineer-busy' : '' ?> <?= $eng['skill_match'] ? 'engineer-match' : '' ?>">
                    <label class="checkbox-label">
                        <input type="checkbox" name="engineer_ids[]" value="<?= $eng['id'] ?>"
                               onchange="updateCount()"
                               <?= in_array($eng['id'], $currentEngineers) ? 'checked' : '' ?>
                               <?= $eng['is_busy'] && !in_array($eng['id'], $currentEngineers) ? 'disabled' : '' ?>>
                        <span>
                            <?= e($eng['real_name']) ?>
                            <span class="text-muted" style="font-size:.8rem">(<?= e($eng['branch_name']) ?>)</span>
                        </span>
                    </label>
                    <?php if ($eng['skill_match']): ?>
                        <span class="badge badge-success">技能符合</span>
                    <?php endif; ?>
                    <?php if ($eng['is_busy']): ?>
                        <span class="badge badge-warning">已排工</span>
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
</style>

<script>
function updateCount() {
    var checked = document.querySelectorAll('#engineerList input[type="checkbox"]:checked').length;
    document.getElementById('engineerCount').textContent = '已選 ' + checked + ' 人';
}

function reloadSuggestions() {
    var caseId = document.getElementById('caseSelect').value;
    var date = document.getElementById('scheduleDate').value;
    if (!caseId || !date) return;

    // 透過 AJAX 載入推薦工程師
    fetch('/schedule.php?action=ajax_engineers&case_id=' + caseId + '&date=' + date, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        var html = '';
        result.data.forEach(function(eng) {
            html += '<div class="engineer-option ' + (eng.is_busy ? 'engineer-busy' : '') + ' ' + (eng.skill_match ? 'engineer-match' : '') + '">';
            html += '<label class="checkbox-label">';
            html += '<input type="checkbox" name="engineer_ids[]" value="' + eng.id + '" onchange="updateCount()" ' + (eng.is_busy ? 'disabled' : '') + '>';
            html += '<span>' + eng.real_name + ' <span class="text-muted" style="font-size:.8rem">(' + eng.branch_name + ')</span></span>';
            html += '</label>';
            if (eng.skill_match) html += '<span class="badge badge-success">技能符合</span>';
            if (eng.is_busy) html += '<span class="badge badge-warning">已排工</span>';
            html += '</div>';
        });
        document.getElementById('engineerList').innerHTML = html || '<p class="text-muted">無可用工程師</p>';
        updateCount();

        // 更新主工程師下拉
        var leadHtml = '<option value="">不指定</option>';
        result.data.forEach(function(eng) {
            if (!eng.is_busy) leadHtml += '<option value="' + eng.id + '">' + eng.real_name + '</option>';
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
}

updateCount();
</script>
