<div class="d-flex justify-between align-center mb-2">
    <h2>施工回報</h2>
    <a href="/worklog.php" class="btn btn-outline btn-sm">返回</a>
</div>

<!-- 案件資訊 -->
<div class="card">
    <div class="d-flex justify-between align-center">
        <strong><?= e($worklog['case_title']) ?></strong>
        <span class="badge badge-primary"><?= e($worklog['case_number']) ?></span>
    </div>
    <div class="text-muted" style="font-size:.85rem;margin-top:4px">
        <?= format_date($worklog['schedule_date']) ?>
        <?php if ($worklog['address']): ?> | <?= e($worklog['address']) ?><?php endif; ?>
    </div>
    <div class="worklog-time-display mt-1">
        <span>到場: <strong><?= $worklog['arrival_time'] ? format_datetime($worklog['arrival_time'], 'H:i') : '未打卡' ?></strong></span>
        <span>離場: <strong><?= $worklog['departure_time'] ? format_datetime($worklog['departure_time'], 'H:i') : '未打卡' ?></strong></span>
    </div>
</div>

<form method="POST">
    <?= csrf_field() ?>

    <!-- 施作說明 -->
    <div class="card">
        <div class="card-header">施作說明</div>
        <div class="form-group">
            <label>今日施工項目</label>
            <textarea name="work_description" class="form-control" rows="4" placeholder="請描述今日施工項目及完成情況"><?= e($worklog['work_description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label>問題/異常</label>
            <textarea name="issues" class="form-control" rows="3" placeholder="如有遇到問題或異常請填寫"><?= e($worklog['issues'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="hidden" name="next_visit_needed" value="0">
                <input type="checkbox" name="next_visit_needed" value="1" <?= !empty($worklog['next_visit_needed']) ? 'checked' : '' ?>
                       onchange="document.getElementById('nextVisitNote').style.display = this.checked ? 'block' : 'none'">
                <span>需要再次施工</span>
            </label>
        </div>
        <div class="form-group" id="nextVisitNote" style="display:<?= !empty($worklog['next_visit_needed']) ? 'block' : 'none' ?>">
            <label>下次施工備註</label>
            <textarea name="next_visit_note" class="form-control" rows="2" placeholder="下次施工需注意的事項"><?= e($worklog['next_visit_note'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- 材料使用 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>材料使用</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="addMaterial()">+ 新增材料</button>
        </div>
        <p class="text-muted mb-1" style="font-size:.85rem">出貨數量 vs 實際使用數量</p>

        <div id="materialsContainer">
            <?php
            $materials = $worklog['materials'] ?? [];
            if (empty($materials)) {
                $materials = [['material_type'=>'cable','material_name'=>'','unit'=>'','shipped_qty'=>'','used_qty'=>'','returned_qty'=>'','material_note'=>'']];
            }
            foreach ($materials as $idx => $m):
            ?>
            <div class="material-row" data-index="<?= $idx ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>類型</label>
                        <select name="materials[<?= $idx ?>][material_type]" class="form-control">
                            <option value="cable" <?= ($m['material_type'] ?? '') === 'cable' ? 'selected' : '' ?>>線材</option>
                            <option value="equipment" <?= ($m['material_type'] ?? '') === 'equipment' ? 'selected' : '' ?>>器材</option>
                            <option value="consumable" <?= ($m['material_type'] ?? '') === 'consumable' ? 'selected' : '' ?>>耗材</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:2">
                        <label>名稱</label>
                        <input type="text" name="materials[<?= $idx ?>][material_name]" class="form-control" value="<?= e($m['material_name'] ?? '') ?>" placeholder="材料名稱">
                    </div>
                    <div class="form-group">
                        <label>單位</label>
                        <input type="text" name="materials[<?= $idx ?>][unit]" class="form-control" value="<?= e($m['unit'] ?? '') ?>" placeholder="米/個/條">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>出貨數量</label>
                        <input type="number" name="materials[<?= $idx ?>][shipped_qty]" class="form-control" step="0.1" min="0" value="<?= e($m['shipped_qty'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>使用數量</label>
                        <input type="number" name="materials[<?= $idx ?>][used_qty]" class="form-control" step="0.1" min="0" value="<?= e($m['used_qty'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>退回數量</label>
                        <input type="number" name="materials[<?= $idx ?>][returned_qty]" class="form-control" step="0.1" min="0" value="<?= e($m['returned_qty'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="align-self:flex-end;flex:0">
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.material-row').remove()">X</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-block mt-1">儲存回報</button>
</form>

<style>
.worklog-time-display { display: flex; gap: 20px; font-size: .9rem; }
.material-row {
    border: 1px solid var(--gray-200); border-radius: var(--radius);
    padding: 10px; margin-bottom: 8px;
}
.form-row { display: flex; flex-wrap: wrap; gap: 8px; }
.form-row .form-group { flex: 1; min-width: 80px; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.checkbox-label input[type="checkbox"] { width: 18px; height: 18px; }
</style>

<script>
var materialIndex = <?= count($materials) ?>;
function addMaterial() {
    var i = materialIndex;
    var html = '<div class="material-row" data-index="' + i + '">' +
        '<div class="form-row">' +
        '<div class="form-group"><label>類型</label><select name="materials[' + i + '][material_type]" class="form-control"><option value="cable">線材</option><option value="equipment">器材</option><option value="consumable">耗材</option></select></div>' +
        '<div class="form-group" style="flex:2"><label>名稱</label><input type="text" name="materials[' + i + '][material_name]" class="form-control" placeholder="材料名稱"></div>' +
        '<div class="form-group"><label>單位</label><input type="text" name="materials[' + i + '][unit]" class="form-control" placeholder="米/個/條"></div>' +
        '</div>' +
        '<div class="form-row">' +
        '<div class="form-group"><label>出貨數量</label><input type="number" name="materials[' + i + '][shipped_qty]" class="form-control" step="0.1" min="0"></div>' +
        '<div class="form-group"><label>使用數量</label><input type="number" name="materials[' + i + '][used_qty]" class="form-control" step="0.1" min="0"></div>' +
        '<div class="form-group"><label>退回數量</label><input type="number" name="materials[' + i + '][returned_qty]" class="form-control" step="0.1" min="0"></div>' +
        '<div class="form-group" style="align-self:flex-end;flex:0"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.material-row\').remove()">X</button></div>' +
        '</div></div>';
    document.getElementById('materialsContainer').insertAdjacentHTML('beforeend', html);
    materialIndex++;
}
</script>
