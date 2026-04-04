<div class="d-flex justify-between align-center mb-2">
    <h2>簽核設定</h2>
    <a href="/approvals.php" class="btn btn-outline btn-sm">← 待簽核清單</a>
</div>

<!-- 新增規則 -->
<div class="card mb-2">
    <div class="card-header">新增簽核規則</div>
    <form method="POST" action="/approvals.php?action=save_rule">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="form-group">
                <label>模組 *</label>
                <select name="module" class="form-control" required onchange="onModuleChange(this.value)">
                    <option value="">-- 選擇 --</option>
                    <optgroup label="案件管理">
                        <option value="case_payments">收款簽核（案件 &gt; 帳款交易紀錄）</option>
                        <option value="case_completion">完工簽核（案件 &gt; 完工結案）</option>
                    </optgroup>
                    <optgroup label="業務相關">
                        <option value="quotations">報價單簽核</option>
                    </optgroup>
                    <optgroup label="採購相關">
                        <option value="purchases">請購單簽核</option>
                        <option value="purchase_orders">採購單簽核</option>
                    </optgroup>
                    <optgroup label="財務相關">
                        <option value="expenses">支出單簽核</option>
                    </optgroup>
                    <optgroup label="人事行政">
                        <option value="leaves">請假單簽核</option>
                        <option value="overtime">加班單簽核</option>
                    </optgroup>
                </select>
            </div>
            <div class="form-group" style="flex:2">
                <label>規則名稱 *</label>
                <input type="text" name="rule_name" class="form-control" placeholder="例：10萬以下免簽核、30萬以上需經理簽核" required>
            </div>
        </div>
        <!-- 金額條件（報價單/支出單/請購單） -->
        <div class="form-row" id="condition-amount">
            <div class="form-group">
                <label id="lbl-min-amount">最低金額</label>
                <input type="number" name="min_amount" class="form-control" value="0" min="0">
            </div>
            <div class="form-group">
                <label id="lbl-max-amount">最高金額（空=無上限）</label>
                <input type="number" name="max_amount" class="form-control" min="0">
            </div>
            <div class="form-group" id="condition-profit">
                <label>最低利潤率 %（選填）</label>
                <input type="number" name="min_profit_rate" class="form-control" step="0.1" min="0" max="100">
            </div>
        </div>
        <!-- 條件類型切換（請購單/採購單用） -->
        <div id="condition-type-row" style="display:none;margin-bottom:12px">
            <label style="font-weight:600;margin-bottom:4px">條件類型</label>
            <div class="d-flex gap-2">
                <label class="checkbox-label"><input type="radio" name="condition_type" value="amount" checked onchange="toggleConditionType(this.value)"> 依金額</label>
                <label class="checkbox-label"><input type="radio" name="condition_type" value="product" onchange="toggleConditionType(this.value)"> 依產品</label>
                <label class="checkbox-label"><input type="radio" name="condition_type" value="category" onchange="toggleConditionType(this.value)"> 依產品分類</label>
            </div>
        </div>
        <!-- 產品條件 -->
        <div id="condition-product" style="display:none;margin-bottom:12px">
            <label style="font-weight:600">指定產品（可多選）</label>
            <div style="position:relative">
                <input type="text" id="approvalProductSearch" class="form-control" placeholder="輸入產品名稱或型號搜尋..." autocomplete="off">
                <div id="approvalProductDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid var(--gray-200);border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
            </div>
            <div id="approvalProductTags" style="display:flex;gap:4px;flex-wrap:wrap;margin-top:6px"></div>
            <input type="hidden" name="product_ids" id="approvalProductIds" value="">
        </div>
        <!-- 產品分類條件 -->
        <div id="condition-category" style="display:none;margin-bottom:12px">
            <label style="font-weight:600">指定產品分類</label>
            <select name="product_category_id" id="approvalCategorySelect" class="form-control">
                <option value="">請選擇分類</option>
            </select>
        </div>
        <!-- 完工簽核提示 -->
        <div id="condition-none" style="display:none; margin-bottom:12px;">
            <p class="text-muted">此模組無額外條件，所有案件完工皆需簽核。</p>
        </div>
        <div class="form-row align-center" style="margin-bottom:16px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:bold;">
                <input type="checkbox" id="chk-no-approval" onchange="toggleNoApproval(this)"> 此規則不需簽核（自動通過）
            </label>
        </div>
        <div id="approval-levels-container">
            <div class="approval-level" data-level="1">
                <div class="form-row">
                    <div class="form-group" style="flex:0 0 80px">
                        <label>第 <span class="level-num">1</span> 層</label>
                    </div>
                    <div class="form-group">
                        <label>簽核人角色</label>
                        <select name="approver_role" class="form-control">
                            <option value="">不指定角色</option>
                            <option value="boss">老闆/總經理</option>
                            <option value="sales_manager">業務經理</option>
                            <option value="eng_manager">工程主管</option>
                            <option value="eng_deputy">工程副主管</option>
                            <option value="admin_staff">行政人員(會計)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>或指定簽核人</label>
                        <select name="approver_id" class="form-control">
                            <option value="">不指定人</option>
                            <?php foreach ($approvers as $ap): ?>
                            <option value="<?= $ap['id'] ?>"><?= e($ap['real_name']) ?> (<?= e(ApprovalModel::roleLabel($ap['role'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="level_order" value="1">
                </div>
            </div>
        </div>
        <div id="extra-levels-container"></div>
        <div style="display:flex;gap:8px;margin-top:8px;">
            <button type="submit" class="btn btn-primary">新增規則</button>
            <button type="button" class="btn btn-outline" id="btn-add-level" onclick="addApprovalLevel()">+ 新增下一層簽核</button>
        </div>
    </form>
</div>

<!-- 現有規則 -->
<div class="card">
    <div class="card-header">現有規則</div>
    <?php if (empty($rules)): ?>
    <p class="text-muted text-center" style="padding:20px">尚未設定簽核規則。未設定規則的模組，送簽核將自動通過。</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table" style="font-size:.85rem">
            <thead>
                <tr>
                    <th>模組</th>
                    <th>規則名稱</th>
                    <th>金額區間</th>
                    <th>利潤率</th>
                    <th>簽核人</th>
                    <th>順序</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $rule): ?>
                <tr id="rule-row-<?= $rule['id'] ?>">
                    <td><span class="badge badge-primary"><?= e(ApprovalModel::moduleLabel($rule['module'])) ?></span></td>
                    <td><?= e($rule['rule_name']) ?></td>
                    <td>
                        $<?= number_format($rule['min_amount']) ?>
                        ~ <?= $rule['max_amount'] !== null ? '$' . number_format($rule['max_amount']) : '無上限' ?>
                    </td>
                    <td><?= $rule['min_profit_rate'] !== null ? $rule['min_profit_rate'] . '%' : '-' ?></td>
                    <td>
                        <?php if ($rule['approver_role'] === 'auto_approve'): ?>
                            <span style="color:#e67e22;font-weight:bold">免簽核（自動通過）</span>
                        <?php elseif ($rule['approver_name']): ?>
                            <?= e($rule['approver_name']) ?>
                        <?php elseif ($rule['approver_role']): ?>
                            <?= e(ApprovalModel::roleLabel($rule['approver_role'])) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= $rule['level_order'] ?></td>
                    <td><?= $rule['is_active'] ? '<span style="color:green">啟用</span>' : '<span style="color:#999">停用</span>' ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline" onclick="toggleEditForm(<?= $rule['id'] ?>)">編輯</button>
                        <a href="/approvals.php?action=delete_rule&id=<?= $rule['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                           class="btn btn-danger btn-sm" onclick="return confirm('確定刪除此規則？')">刪除</a>
                    </td>
                </tr>
                <!-- 內嵌編輯表單 -->
                <tr id="edit-row-<?= $rule['id'] ?>" style="display:none; background:#f8f9fa;">
                    <td colspan="8" style="padding:12px 16px;">
                        <form method="POST" action="/approvals.php?action=save_rule" class="edit-rule-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                            <input type="hidden" name="module" value="<?= e($rule['module']) ?>">
                            <div class="form-row">
                                <div class="form-group" style="flex:2">
                                    <label>規則名稱 *</label>
                                    <input type="text" name="rule_name" class="form-control" value="<?= e($rule['rule_name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>最低金額</label>
                                    <input type="number" name="min_amount" class="form-control" value="<?= $rule['min_amount'] ?>" min="0">
                                </div>
                                <div class="form-group">
                                    <label>最高金額（空=無上限）</label>
                                    <input type="number" name="max_amount" class="form-control" value="<?= $rule['max_amount'] !== null ? $rule['max_amount'] : '' ?>" min="0">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>最低利潤率 %</label>
                                    <input type="number" name="min_profit_rate" class="form-control" step="0.1" min="0" max="100" value="<?= $rule['min_profit_rate'] !== null ? $rule['min_profit_rate'] : '' ?>">
                                </div>
                                <div class="form-group">
                                    <label>簽核人角色</label>
                                    <select name="approver_role" class="form-control" onchange="toggleEditApproverFields(this, <?= $rule['id'] ?>)">
                                        <option value="">不指定角色</option>
                                        <option value="auto_approve" <?= $rule['approver_role'] === 'auto_approve' ? 'selected' : '' ?>>免簽核（自動通過）</option>
                                        <option value="boss" <?= $rule['approver_role'] === 'boss' ? 'selected' : '' ?>>老闆/總經理</option>
                                        <option value="sales_manager" <?= $rule['approver_role'] === 'sales_manager' ? 'selected' : '' ?>>業務經理</option>
                                        <option value="eng_manager" <?= $rule['approver_role'] === 'eng_manager' ? 'selected' : '' ?>>工程主管</option>
                                        <option value="eng_deputy" <?= $rule['approver_role'] === 'eng_deputy' ? 'selected' : '' ?>>工程副主管</option>
                                        <option value="admin_staff" <?= $rule['approver_role'] === 'admin_staff' ? 'selected' : '' ?>>行政人員(會計)</option>
                                    </select>
                                </div>
                                <div class="form-group" id="edit-approver-id-group-<?= $rule['id'] ?>" <?= $rule['approver_role'] === 'auto_approve' ? 'style="display:none"' : '' ?>>
                                    <label>或指定簽核人</label>
                                    <select name="approver_id" class="form-control">
                                        <option value="">不指定人</option>
                                        <?php foreach ($approvers as $ap): ?>
                                        <option value="<?= $ap['id'] ?>" <?= (string)$rule['approver_id'] === (string)$ap['id'] ? 'selected' : '' ?>><?= e($ap['real_name']) ?> (<?= e(ApprovalModel::roleLabel($ap['role'])) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="flex:0 0 100px">
                                    <label>順序</label>
                                    <input type="number" name="level_order" class="form-control" value="<?= $rule['level_order'] ?>" min="1" max="10">
                                </div>
                                <div class="form-group" style="flex:0 0 100px">
                                    <label>狀態</label>
                                    <select name="is_active" class="form-control">
                                        <option value="1" <?= $rule['is_active'] ? 'selected' : '' ?>>啟用</option>
                                        <option value="0" <?= !$rule['is_active'] ? 'selected' : '' ?>>停用</option>
                                    </select>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button type="submit" class="btn btn-primary btn-sm">儲存</button>
                                <button type="button" class="btn btn-outline btn-sm" onclick="toggleEditForm(<?= $rule['id'] ?>)">取消</button>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.edit-rule-form { max-width: 100%; }
.edit-rule-form .form-row { margin-bottom: 8px; }
.edit-rule-form .form-group { min-width: 120px; }
</style>

<script>
var extraLevelIdx = 1;

function onModuleChange(mod) {
    var amountRow = document.getElementById('condition-amount');
    var profitGroup = document.getElementById('condition-profit');
    var noneRow = document.getElementById('condition-none');
    var lblMin = document.getElementById('lbl-min-amount');
    var lblMax = document.getElementById('lbl-max-amount');
    var ruleInput = document.querySelector('input[name="rule_name"]');

    amountRow.style.display = '';
    profitGroup.style.display = '';
    noneRow.style.display = 'none';
    document.getElementById('condition-type-row').style.display = 'none';
    document.getElementById('condition-product').style.display = 'none';
    document.getElementById('condition-category').style.display = 'none';

    if (mod === 'quotations') {
        lblMin.textContent = '最低金額';
        lblMax.textContent = '最高金額（空=無上限）';
        profitGroup.style.display = '';
        ruleInput.placeholder = '例：10萬以下免簽核、30萬以上需經理簽核';
    } else if (mod === 'expenses') {
        lblMin.textContent = '最低金額';
        lblMax.textContent = '最高金額（空=無上限）';
        profitGroup.style.display = 'none';
        ruleInput.placeholder = '例：5000以下免簽核';
    } else if (mod === 'purchases' || mod === 'purchase_orders') {
        lblMin.textContent = '最低金額';
        lblMax.textContent = '最高金額（空=無上限）';
        profitGroup.style.display = 'none';
        document.getElementById('condition-type-row').style.display = '';
        ruleInput.placeholder = mod === 'purchases' ? '例：1萬以上需主管簽核' : '例：採購金額5萬以上需經理簽核';
        loadCategories();
    } else if (mod === 'leaves') {
        lblMin.textContent = '最低天數';
        lblMax.textContent = '最高天數（空=無上限）';
        profitGroup.style.display = 'none';
        ruleInput.placeholder = '例：3天以上需主管簽核';
    } else if (mod === 'overtime') {
        lblMin.textContent = '最低時數';
        lblMax.textContent = '最高時數（空=無上限）';
        profitGroup.style.display = 'none';
        ruleInput.placeholder = '例：4小時以上需主管簽核';
    } else if (mod === 'case_completion') {
        amountRow.style.display = 'none';
        profitGroup.style.display = 'none';
        noneRow.style.display = '';
        ruleInput.placeholder = '例：完工簽核 - 工程主管';
    } else if (mod === 'case_payments') {
        lblMin.textContent = '最低金額';
        lblMax.textContent = '最高金額（空=無上限）';
        profitGroup.style.display = 'none';
        ruleInput.placeholder = '例：收款需老闆簽核';
    }
}
var approverOptions = <?= json_encode(array_map(function($ap) { return array('id' => $ap['id'], 'name' => $ap['real_name'], 'role' => ApprovalModel::roleLabel($ap['role'])); }, $approvers)) ?>;

function toggleNoApproval(chk) {
    var levelsContainer = document.getElementById('approval-levels-container');
    var extraContainer = document.getElementById('extra-levels-container');
    var addBtn = document.getElementById('btn-add-level');
    if (chk.checked) {
        levelsContainer.style.display = 'none';
        extraContainer.style.display = 'none';
        addBtn.style.display = 'none';
        // 設定為 auto_approve
        var roleSelect = levelsContainer.querySelector('select[name="approver_role"]');
        if (roleSelect) roleSelect.value = 'auto_approve';
    } else {
        levelsContainer.style.display = '';
        extraContainer.style.display = '';
        addBtn.style.display = '';
        var roleSelect = levelsContainer.querySelector('select[name="approver_role"]');
        if (roleSelect) roleSelect.value = '';
    }
}

function addApprovalLevel() {
    extraLevelIdx++;
    var container = document.getElementById('extra-levels-container');
    var approverOpts = '<option value="">不指定人</option>';
    for (var i = 0; i < approverOptions.length; i++) {
        approverOpts += '<option value="' + approverOptions[i].id + '">' + approverOptions[i].name + ' (' + approverOptions[i].role + ')</option>';
    }
    var html = '<div class="approval-level" data-level="' + extraLevelIdx + '" style="border-top:1px dashed #ddd; padding-top:8px; margin-top:8px;">' +
        '<div class="form-row">' +
            '<div class="form-group" style="flex:0 0 80px">' +
                '<label>第 <span class="level-num">' + extraLevelIdx + '</span> 層</label>' +
            '</div>' +
            '<div class="form-group">' +
                '<label>簽核人角色</label>' +
                '<select name="extra_approver_role[]" class="form-control">' +
                    '<option value="">不指定角色</option>' +
                    '<option value="boss">老闆/總經理</option>' +
                    '<option value="sales_manager">業務經理</option>' +
                    '<option value="eng_manager">工程主管</option>' +
                    '<option value="eng_deputy">工程副主管</option>' +
                    '<option value="admin_staff">行政人員(會計)</option>' +
                '</select>' +
            '</div>' +
            '<div class="form-group">' +
                '<label>或指定簽核人</label>' +
                '<select name="extra_approver_id[]" class="form-control">' + approverOpts + '</select>' +
            '</div>' +
            '<input type="hidden" name="extra_level_order[]" value="' + extraLevelIdx + '">' +
            '<div class="form-group" style="flex:0 0 40px; align-self:flex-end;">' +
                '<button type="button" class="btn btn-danger btn-sm" onclick="removeLevel(this)">&times;</button>' +
            '</div>' +
        '</div>' +
    '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function removeLevel(btn) {
    btn.closest('.approval-level').remove();
    // 重新編號
    var levels = document.querySelectorAll('#extra-levels-container .approval-level');
    for (var i = 0; i < levels.length; i++) {
        var num = i + 2;
        levels[i].querySelector('.level-num').textContent = num;
        levels[i].querySelector('input[name="extra_level_order[]"]').value = num;
    }
    extraLevelIdx = levels.length + 1;
}

function toggleEditForm(id) {
    var row = document.getElementById('edit-row-' + id);
    if (row.style.display === 'none') {
        var allEditRows = document.querySelectorAll('[id^="edit-row-"]');
        for (var i = 0; i < allEditRows.length; i++) {
            allEditRows[i].style.display = 'none';
        }
        row.style.display = '';
    } else {
        row.style.display = 'none';
    }
}

function toggleEditApproverFields(select, ruleId) {
    var group = document.getElementById('edit-approver-id-group-' + ruleId);
    if (select.value === 'auto_approve') {
        group.style.display = 'none';
        group.querySelector('select').value = '';
    } else {
        group.style.display = '';
    }
}

// 條件類型切換
function toggleConditionType(type) {
    document.getElementById('condition-amount').style.display = (type === 'amount') ? '' : 'none';
    document.getElementById('condition-product').style.display = (type === 'product') ? '' : 'none';
    document.getElementById('condition-category').style.display = (type === 'category') ? '' : 'none';
}

// 產品搜尋
var apProductTimer = null;
var selectedProducts = [];
var apSearchInp = document.getElementById('approvalProductSearch');
var apDropdown = document.getElementById('approvalProductDropdown');

if (apSearchInp) {
    apSearchInp.addEventListener('input', function() {
        clearTimeout(apProductTimer);
        var q = this.value.trim();
        if (q.length < 1) { apDropdown.style.display = 'none'; return; }
        apProductTimer = setTimeout(function() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/requisitions.php?action=ajax_search_product&q=' + encodeURIComponent(q));
            xhr.onload = function() {
                var list = JSON.parse(xhr.responseText);
                if (!list.length) { apDropdown.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合產品</div>'; apDropdown.style.display = 'block'; return; }
                var html = '';
                for (var i = 0; i < list.length; i++) {
                    html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" data-id="' + (list[i].id||'') + '" data-name="' + (list[i].name||'') + '" onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'" onclick="addApprovalProduct(this)">' +
                        '<strong>' + (list[i].name||'') + '</strong>' + (list[i].model ? ' <span style="color:#1565c0">' + list[i].model + '</span>' : '') + '</div>';
                }
                apDropdown.innerHTML = html;
                apDropdown.style.display = 'block';
            };
            xhr.send();
        }, 300);
    });
}

function addApprovalProduct(el) {
    var id = el.dataset.id;
    var name = el.dataset.name;
    if (selectedProducts.indexOf(id) !== -1) return;
    selectedProducts.push(id);
    var tags = document.getElementById('approvalProductTags');
    var tag = document.createElement('span');
    tag.style.cssText = 'display:inline-flex;align-items:center;gap:3px;padding:3px 10px;background:#e3f2fd;color:#1565c0;border-radius:12px;font-size:.82rem';
    tag.dataset.id = id;
    tag.innerHTML = name + ' <span style="cursor:pointer;color:#c62828" onclick="removeApprovalProduct(this,\'' + id + '\')">&times;</span>';
    tags.appendChild(tag);
    document.getElementById('approvalProductIds').value = selectedProducts.join(',');
    apDropdown.style.display = 'none';
    apSearchInp.value = '';
}

function removeApprovalProduct(el, id) {
    selectedProducts = selectedProducts.filter(function(p) { return p !== id; });
    document.getElementById('approvalProductIds').value = selectedProducts.join(',');
    el.closest('span').remove();
}

document.addEventListener('click', function(e) {
    if (apDropdown && !e.target.closest('#approvalProductSearch') && !e.target.closest('#approvalProductDropdown')) {
        apDropdown.style.display = 'none';
    }
});

// 載入產品分類
function loadCategories() {
    var sel = document.getElementById('approvalCategorySelect');
    if (sel.options.length > 1) return;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/quotations.php?action=ajax_categories');
    xhr.onload = function() {
        var cats = JSON.parse(xhr.responseText);
        for (var i = 0; i < cats.length; i++) {
            var opt = document.createElement('option');
            opt.value = cats[i].id;
            opt.textContent = cats[i].name;
            sel.appendChild(opt);
        }
    };
    xhr.send();
}
</script>
