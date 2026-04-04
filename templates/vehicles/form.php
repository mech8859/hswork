<?php
$isEdit = !empty($vehicle);
$typeLabels = VehicleModel::typeLabels();
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2><?= $isEdit ? '編輯車輛' : '新增車輛' ?></h2>
    <a href="/vehicles.php" class="btn btn-outline btn-sm">返回列表</a>
</div>

<form method="post" action="/vehicles.php?action=<?= $isEdit ? 'edit&id=' . $vehicle['id'] : 'create' ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">基本資料</div>
        <div class="form-grid">
            <div class="form-group">
                <label>車牌號碼 <span style="color:var(--danger)">*</span></label>
                <input type="text" name="plate_number" class="form-control" required value="<?= e($vehicle['plate_number'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>車輛類型 <span style="color:var(--danger)">*</span></label>
                <select name="vehicle_type" class="form-control" required>
                    <?php foreach ($typeLabels as $tk => $tl): ?>
                    <option value="<?= $tk ?>" <?= ($vehicle['vehicle_type'] ?: 'van') === $tk ? 'selected' : '' ?>><?= $tl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>品牌</label>
                <input type="text" name="brand" class="form-control" value="<?= e($vehicle['brand'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>型號</label>
                <input type="text" name="model" class="form-control" value="<?= e($vehicle['model'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>出廠年份</label>
                <input type="number" name="year" class="form-control" min="1990" max="2030" value="<?= e($vehicle['year'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>顏色</label>
                <input type="text" name="color" class="form-control" value="<?= e($vehicle['color'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>保管人</label>
                <select name="custodian_id" class="form-control">
                    <option value="">未指定</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($vehicle['custodian_id'] ?: '') == $u['id'] ? 'selected' : '' ?>><?= e($u['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>所屬分公司 <span style="color:var(--danger)">*</span></label>
                <select name="branch_id" class="form-control" required>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($vehicle['branch_id'] ?: '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">租賃合約</div>
        <div class="form-grid">
            <div class="form-group">
                <label>車輛編號</label>
                <input type="text" name="vehicle_number" class="form-control" value="<?= e($vehicle['vehicle_number'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>租賃公司</label>
                <input type="text" name="leasing_company" class="form-control" value="<?= e($vehicle['leasing_company'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>統一編號</label>
                <input type="text" name="leasing_tax_id" class="form-control" value="<?= e($vehicle['leasing_tax_id'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>聯絡人</label>
                <input type="text" name="leasing_contact" class="form-control" value="<?= e($vehicle['leasing_contact'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>公司電話</label>
                <input type="text" name="leasing_phone" class="form-control" value="<?= e($vehicle['leasing_phone'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>行動電話</label>
                <input type="text" name="leasing_mobile" class="form-control" value="<?= e($vehicle['leasing_mobile'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>合約期數</label>
                <input type="number" name="contract_months" class="form-control" min="0" value="<?= e($vehicle['contract_months'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>月租金(含稅)</label>
                <input type="number" name="monthly_rent" class="form-control" min="0" value="<?= e($vehicle['monthly_rent'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>合約開始日</label>
                <input type="date" max="2099-12-31" name="contract_start" class="form-control" value="<?= e($vehicle['contract_start'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>合約到期日</label>
                <input type="date" max="2099-12-31" name="contract_end" class="form-control" value="<?= e($vehicle['contract_end'] ?: '') ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">保養資訊</div>
        <div class="form-grid">
            <div class="form-group">
                <label>保養日期</label>
                <input type="date" max="2099-12-31" name="last_maintenance_date" class="form-control" value="<?= e($vehicle['last_maintenance_date'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>保養里程數 (km)</label>
                <input type="number" name="maintenance_mileage" class="form-control" min="0" value="<?= e($vehicle['maintenance_mileage'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>下次保養里程數 (km)</label>
                <input type="number" name="next_maintenance_mileage" class="form-control" min="0" value="<?= e($vehicle['next_maintenance_mileage'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>目前里程 (km)</label>
                <input type="number" name="current_mileage" class="form-control" min="0" value="<?= e($vehicle['current_mileage'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>保養地址</label>
                <input type="text" name="maintenance_address" class="form-control" value="<?= e($vehicle['maintenance_address'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>聯絡窗口</label>
                <input type="text" name="maintenance_contact" class="form-control" value="<?= e($vehicle['maintenance_contact'] ?: '') ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">驗車資訊</div>
        <div class="form-grid">
            <div class="form-group">
                <label>驗車日</label>
                <input type="date" max="2099-12-31" name="inspection_date" class="form-control" value="<?= e($vehicle['inspection_date'] ?: '') ?>">
            </div>
            <div class="form-group">
                <label>驗車窗口</label>
                <input type="text" name="inspection_contact" class="form-control" value="<?= e($vehicle['inspection_contact'] ?: '') ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>車輛配置工具</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="addToolRow()">+ 新增工具</button>
        </div>
        <div id="toolList">
            <?php if ($isEdit && !empty($vehicle['tools'])): ?>
                <?php foreach ($vehicle['tools'] as $i => $tool): ?>
                <div class="tool-row d-flex gap-1 align-center mb-1">
                    <input type="text" name="tools[<?= $i ?>][tool_name]" class="form-control" placeholder="工具名稱" value="<?= e($tool['tool_name']) ?>" style="flex:2">
                    <input type="number" name="tools[<?= $i ?>][quantity]" class="form-control" placeholder="數量" value="<?= (int)$tool['quantity'] ?>" min="1" style="flex:0 0 80px">
                    <input type="text" name="tools[<?= $i ?>][note]" class="form-control" placeholder="備註" value="<?= e($tool['note'] ?: '') ?>" style="flex:1">
                    <button type="button" class="btn btn-outline btn-sm" onclick="this.parentElement.remove()" style="flex:0 0 auto;color:var(--danger)">&times;</button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="3"><?= e($vehicle['note'] ?: '') ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立車輛' ?></button>
        <a href="/vehicles.php" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
@media (max-width: 767px) { .form-grid { grid-template-columns: 1fr; } }
</style>

<script>
var toolIndex = <?= $isEdit && !empty($vehicle['tools']) ? count($vehicle['tools']) : 0 ?>;
function addToolRow() {
    var html = '<div class="tool-row d-flex gap-1 align-center mb-1">' +
        '<input type="text" name="tools[' + toolIndex + '][tool_name]" class="form-control" placeholder="工具名稱" style="flex:2">' +
        '<input type="number" name="tools[' + toolIndex + '][quantity]" class="form-control" placeholder="數量" value="1" min="1" style="flex:0 0 80px">' +
        '<input type="text" name="tools[' + toolIndex + '][note]" class="form-control" placeholder="備註" style="flex:1">' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="this.parentElement.remove()" style="flex:0 0 auto;color:var(--danger)">&times;</button>' +
        '</div>';
    document.getElementById('toolList').insertAdjacentHTML('beforeend', html);
    toolIndex++;
}
</script>
