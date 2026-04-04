<?php
$moduleLabels = array();
foreach ($registry as $mk => $mv) {
    $moduleLabels[$mk] = $mv['label'];
}
$eventLabels = array('created' => '新建', 'updated' => '更新', 'status_changed' => '狀態變更', 'assigned' => '指派');

// 角色 map
$roleMap = array();
foreach ($roles as $r) {
    $roleMap[$r['role_key']] = $r['role_label'];
}

// 記錄欄位 map（所有模組合併）
$allFieldLabels = array();
foreach ($registry as $mv) {
    if (!empty($mv['record_fields'])) {
        foreach ($mv['record_fields'] as $fk => $fl) {
            $allFieldLabels[$fk] = $fl;
        }
    }
}
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>通知設定</h2>
    <button class="btn btn-primary btn-sm" onclick="showAddForm()">+ 新增規則</button>
</div>

<!-- 模組篩選 -->
<div class="filter-pills mb-1">
    <div class="pill-group">
        <a href="/notification_settings.php" class="pill <?= empty($filterModule) ? 'pill-active' : '' ?>">全部</a>
        <?php foreach ($moduleLabels as $mk => $ml): ?>
        <a href="/notification_settings.php?module=<?= e($mk) ?>" class="pill <?= $filterModule === $mk ? 'pill-active' : '' ?>"><?= e($ml) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- 新增表單 (hidden by default) -->
<div id="addForm" class="card mb-2" style="display:none">
    <h3 class="mb-1">新增通知規則</h3>
    <form method="POST" action="/notification_settings.php?action=create">
        <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
        <div class="form-grid-2 mb-1">
            <div class="form-group">
                <label>模組 <span class="text-danger">*</span></label>
                <select name="module" id="addModule" class="form-control" required onchange="onModuleChange(this, 'add')">
                    <option value="">-- 選擇模組 --</option>
                    <?php foreach ($registry as $mk => $mv): ?>
                    <option value="<?= e($mk) ?>"><?= e($mv['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>事件 <span class="text-danger">*</span></label>
                <select name="event" id="addEvent" class="form-control" required>
                    <option value="">-- 先選模組 --</option>
                </select>
            </div>
        </div>
        <div class="form-grid-2 mb-1">
            <div class="form-group">
                <label>條件欄位</label>
                <select name="condition_field" id="addCondField" class="form-control" onchange="onCondFieldChange(this, 'add')">
                    <option value="">無條件（所有情況觸發）</option>
                </select>
            </div>
            <div class="form-group">
                <label>條件值</label>
                <select name="condition_value" id="addCondValue" class="form-control">
                    <option value="">-- 先選條件欄位 --</option>
                </select>
            </div>
        </div>
        <div class="form-grid-2 mb-1">
            <div class="form-group">
                <label>通知對象類型 <span class="text-danger">*</span></label>
                <select name="notify_type" id="addNotifyType" class="form-control" onchange="onNotifyTypeChange(this, 'add')">
                    <option value="role">角色</option>
                    <option value="field">記錄欄位（動態）</option>
                </select>
            </div>
            <div class="form-group">
                <label>通知對象 <span class="text-danger">*</span></label>
                <select name="notify_target" id="addNotifyTarget" class="form-control" required>
                    <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r['role_key']) ?>"><?= e($r['role_label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-grid-2 mb-1">
            <div class="form-group">
                <label>分公司範圍</label>
                <select name="branch_scope" class="form-control">
                    <option value="same">同分公司</option>
                    <option value="all">全部分公司</option>
                </select>
            </div>
            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort_order" class="form-control" value="0" min="0">
            </div>
        </div>
        <div class="form-group mb-1">
            <label>通知標題 <span class="text-danger">*</span></label>
            <input type="text" name="title_template" class="form-control" required placeholder="例：收款單已收款通知，可用 {customer_name} 等變數">
        </div>
        <div class="form-group mb-1">
            <label>通知內容</label>
            <textarea name="message_template" class="form-control" rows="2" placeholder="例：客戶：{customer_name}，金額：NT${total_amount}"></textarea>
        </div>
        <div class="form-group mb-1">
            <label>連結</label>
            <input type="text" name="link_template" class="form-control" placeholder="例：/receipts.php?action=edit&id={id}">
        </div>
        <div class="form-group mb-1">
            <small class="text-muted">可用變數：<code>{id}</code> <code>{title}</code> <code>{customer_name}</code> <code>{total_amount}</code> <code>{status}</code> <code>{sub_status}</code> <code>{actor_name}</code>（觸發者名稱）等記錄欄位</small>
        </div>
        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm">儲存</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="hideAddForm()">取消</button>
        </div>
    </form>
</div>

<!-- 規則列表 -->
<div class="card">
    <?php if (empty($rules)): ?>
        <p class="text-muted text-center mt-2 mb-2">尚無通知規則</p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th style="width:80px">模組</th>
                <th style="width:80px">事件</th>
                <th style="width:120px">條件</th>
                <th style="width:120px">通知對象</th>
                <th style="width:70px">範圍</th>
                <th>標題</th>
                <th style="width:60px">狀態</th>
                <th style="width:120px">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rules as $rule): ?>
            <tr id="rule-<?= $rule['id'] ?>" class="<?= $rule['is_active'] ? '' : 'text-muted' ?>">
                <td><span class="badge badge-info"><?= e(isset($moduleLabels[$rule['module']]) ? $moduleLabels[$rule['module']] : $rule['module']) ?></span></td>
                <td><?= e(isset($eventLabels[$rule['event']]) ? $eventLabels[$rule['event']] : $rule['event']) ?></td>
                <td>
                    <?php if ($rule['condition_field']): ?>
                        <code><?= e($rule['condition_field']) ?></code> = <strong><?= e($rule['condition_value']) ?></strong>
                    <?php else: ?>
                        <span class="text-muted">--</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($rule['notify_type'] === 'role'): ?>
                        <span class="badge badge-primary"><?= e(isset($roleMap[$rule['notify_target']]) ? $roleMap[$rule['notify_target']] : $rule['notify_target']) ?></span>
                    <?php else: ?>
                        <span class="badge badge-warning"><?= e(isset($allFieldLabels[$rule['notify_target']]) ? $allFieldLabels[$rule['notify_target']] : $rule['notify_target']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= $rule['branch_scope'] === 'same' ? '同分公司' : '全部' ?></td>
                <td title="<?= e($rule['message_template']) ?>"><?= e($rule['title_template']) ?></td>
                <td>
                    <?php if ($rule['is_active']): ?>
                        <span class="badge badge-success">啟用</span>
                    <?php else: ?>
                        <span class="badge badge-danger">停用</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-<?= $rule['is_active'] ? 'warning' : 'success' ?>" onclick="toggleRule(<?= $rule['id'] ?>)" title="<?= $rule['is_active'] ? '停用' : '啟用' ?>">
                        <?= $rule['is_active'] ? '停用' : '啟用' ?>
                    </button>
                    <button class="btn btn-sm btn-info" onclick="editRule(<?= $rule['id'] ?>)" title="編輯">編輯</button>
                    <form method="POST" action="/notification_settings.php?action=delete" style="display:inline" onsubmit="return confirm('確定刪除此規則？')">
                        <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
                        <input type="hidden" name="id" value="<?= $rule['id'] ?>">
                        <input type="hidden" name="module" value="<?= e($filterModule) ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="刪除">刪除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- 編輯 Modal -->
<div id="editModal" class="modal" style="display:none">
    <div class="modal-overlay" onclick="closeEditModal()"></div>
    <div class="modal-content" style="max-width:700px">
        <div class="modal-header">
            <h3>編輯通知規則</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editForm" onsubmit="saveEdit(event)">
                <input type="hidden" name="id" id="editId">
                <div class="form-grid-2 mb-1">
                    <div class="form-group">
                        <label>模組</label>
                        <select name="module" id="editModule" class="form-control" required onchange="onModuleChange(this, 'edit')">
                            <?php foreach ($registry as $mk => $mv): ?>
                            <option value="<?= e($mk) ?>"><?= e($mv['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>事件</label>
                        <select name="event" id="editEvent" class="form-control" required></select>
                    </div>
                </div>
                <div class="form-grid-2 mb-1">
                    <div class="form-group">
                        <label>條件欄位</label>
                        <select name="condition_field" id="editCondField" class="form-control" onchange="onCondFieldChange(this, 'edit')">
                            <option value="">無條件</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>條件值</label>
                        <select name="condition_value" id="editCondValue" class="form-control">
                            <option value="">--</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid-2 mb-1">
                    <div class="form-group">
                        <label>通知對象類型</label>
                        <select name="notify_type" id="editNotifyType" class="form-control" onchange="onNotifyTypeChange(this, 'edit')">
                            <option value="role">角色</option>
                            <option value="field">記錄欄位</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>通知對象</label>
                        <select name="notify_target" id="editNotifyTarget" class="form-control" required></select>
                    </div>
                </div>
                <div class="form-grid-2 mb-1">
                    <div class="form-group">
                        <label>分公司範圍</label>
                        <select name="branch_scope" id="editBranchScope" class="form-control">
                            <option value="same">同分公司</option>
                            <option value="all">全部分公司</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" name="sort_order" id="editSortOrder" class="form-control" value="0" min="0">
                    </div>
                </div>
                <div class="form-group mb-1">
                    <label>通知標題</label>
                    <input type="text" name="title_template" id="editTitle" class="form-control" required>
                </div>
                <div class="form-group mb-1">
                    <label>通知內容</label>
                    <textarea name="message_template" id="editMessage" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group mb-1">
                    <label>連結</label>
                    <input type="text" name="link_template" id="editLink" class="form-control">
                </div>
                <div class="d-flex gap-1 mt-1">
                    <button type="submit" class="btn btn-primary btn-sm">儲存變更</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeEditModal()">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var registry = <?= json_encode($registry, JSON_UNESCAPED_UNICODE) ?>;
var allRoles = <?= json_encode($roles, JSON_UNESCAPED_UNICODE) ?>;
var rulesData = <?= json_encode($rules, JSON_UNESCAPED_UNICODE) ?>;

function showAddForm() {
    document.getElementById('addForm').style.display = 'block';
    document.getElementById('addForm').scrollIntoView({behavior: 'smooth'});
}
function hideAddForm() {
    document.getElementById('addForm').style.display = 'none';
}

function onModuleChange(sel, prefix) {
    var mod = sel.value;
    var info = registry[mod] || {};
    // 填充事件
    var eventSel = document.getElementById(prefix + 'Event');
    eventSel.innerHTML = '';
    if (info.events) {
        for (var ek in info.events) {
            var o = document.createElement('option');
            o.value = ek;
            o.textContent = info.events[ek];
            eventSel.appendChild(o);
        }
    }
    // 填充條件欄位
    var cfSel = document.getElementById(prefix + 'CondField');
    cfSel.innerHTML = '<option value="">無條件（所有情況觸發）</option>';
    if (info.condition_fields) {
        for (var fk in info.condition_fields) {
            var o = document.createElement('option');
            o.value = fk;
            o.textContent = info.condition_fields[fk].label;
            cfSel.appendChild(o);
        }
    }
    // 清空條件值
    var cvSel = document.getElementById(prefix + 'CondValue');
    cvSel.innerHTML = '<option value="">--</option>';
    // 更新通知對象（記錄欄位選項）
    onNotifyTypeChange(document.getElementById(prefix + 'NotifyType'), prefix);
}

function onCondFieldChange(sel, prefix) {
    var mod = document.getElementById(prefix + 'Module').value;
    var field = sel.value;
    var cvSel = document.getElementById(prefix + 'CondValue');
    cvSel.innerHTML = '<option value="">--</option>';
    if (field && registry[mod] && registry[mod].condition_fields && registry[mod].condition_fields[field]) {
        var vals = registry[mod].condition_fields[field].values;
        for (var i = 0; i < vals.length; i++) {
            var o = document.createElement('option');
            o.value = vals[i];
            o.textContent = vals[i];
            cvSel.appendChild(o);
        }
    }
}

function onNotifyTypeChange(sel, prefix) {
    var type = sel.value;
    var targetSel = document.getElementById(prefix + 'NotifyTarget');
    targetSel.innerHTML = '';
    if (type === 'role') {
        for (var i = 0; i < allRoles.length; i++) {
            var o = document.createElement('option');
            o.value = allRoles[i].role_key;
            o.textContent = allRoles[i].role_label;
            targetSel.appendChild(o);
        }
    } else {
        var mod = document.getElementById(prefix + 'Module').value;
        var fields = (registry[mod] && registry[mod].record_fields) ? registry[mod].record_fields : {};
        for (var fk in fields) {
            var o = document.createElement('option');
            o.value = fk;
            o.textContent = fields[fk];
            targetSel.appendChild(o);
        }
        if (targetSel.options.length === 0) {
            var o = document.createElement('option');
            o.value = '';
            o.textContent = '此模組無可用欄位';
            targetSel.appendChild(o);
        }
    }
}

function toggleRule(id) {
    fetch('/notification_settings.php?action=toggle', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) location.reload();
        else alert(d.error || '操作失敗');
    });
}

function editRule(id) {
    var rule = null;
    for (var i = 0; i < rulesData.length; i++) {
        if (parseInt(rulesData[i].id) === id) { rule = rulesData[i]; break; }
    }
    if (!rule) return;

    document.getElementById('editId').value = rule.id;
    document.getElementById('editModule').value = rule.module;
    onModuleChange(document.getElementById('editModule'), 'edit');

    // 設定事件
    document.getElementById('editEvent').value = rule.event;

    // 設定條件
    if (rule.condition_field) {
        document.getElementById('editCondField').value = rule.condition_field;
        onCondFieldChange(document.getElementById('editCondField'), 'edit');
        document.getElementById('editCondValue').value = rule.condition_value || '';
    }

    // 設定通知對象
    document.getElementById('editNotifyType').value = rule.notify_type;
    onNotifyTypeChange(document.getElementById('editNotifyType'), 'edit');
    document.getElementById('editNotifyTarget').value = rule.notify_target;

    document.getElementById('editBranchScope').value = rule.branch_scope;
    document.getElementById('editSortOrder').value = rule.sort_order || 0;
    document.getElementById('editTitle').value = rule.title_template || '';
    document.getElementById('editMessage').value = rule.message_template || '';
    document.getElementById('editLink').value = rule.link_template || '';

    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function saveEdit(e) {
    e.preventDefault();
    var form = document.getElementById('editForm');
    var fd = new FormData(form);
    var body = [];
    fd.forEach(function(v, k) { body.push(encodeURIComponent(k) + '=' + encodeURIComponent(v)); });

    fetch('/notification_settings.php?action=update', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body.join('&')
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) location.reload();
        else alert(d.error || '儲存失敗');
    });
}
</script>
