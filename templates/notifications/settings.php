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

// 人員 id → 姓名 map
$userNameMap = array();
if (!empty($allUsers)) {
    foreach ($allUsers as $u) {
        $userNameMap[(int)$u['id']] = $u['real_name'];
    }
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

// 條件值 key=>label map（所有模組合併）
$condValueLabels = array();
foreach ($registry as $mv) {
    if (!empty($mv['condition_fields'])) {
        foreach ($mv['condition_fields'] as $cf) {
            if (!empty($cf['values']) && !is_int(key($cf['values']))) {
                foreach ($cf['values'] as $vk => $vl) {
                    $condValueLabels[$vk] = $vl;
                }
            }
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
                    <option value="user">指定人員</option>
                    <option value="field">記錄欄位（動態）</option>
                </select>
            </div>
            <div class="form-group">
                <label>通知對象 <span class="text-danger">*</span>（可多選）</label>
                <div id="addNotifyTarget" class="notify-target-checkboxes" style="border:1px solid #ddd;border-radius:6px;padding:8px;max-height:160px;overflow-y:auto">
                    <?php foreach ($roles as $r): ?>
                    <label style="display:block;padding:3px 0;font-size:.85rem;cursor:pointer">
                        <input type="checkbox" name="notify_target[]" value="<?= e($r['role_key']) ?>"> <?= e($r['role_label']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
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
                        <?php $cvLabel = isset($condValueLabels[$rule['condition_value']]) ? $condValueLabels[$rule['condition_value']] : $rule['condition_value']; ?>
                        <code><?= e($rule['condition_field']) ?></code> = <strong><?= e($cvLabel) ?></strong>
                    <?php else: ?>
                        <span class="text-muted">--</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $targets = explode(',', $rule['notify_target']);
                    foreach ($targets as $t):
                        $t = trim($t);
                        if (!$t) continue;
                        if ($rule['notify_type'] === 'role'):
                    ?>
                        <span class="badge badge-primary" style="margin:1px"><?= e(isset($roleMap[$t]) ? $roleMap[$t] : $t) ?></span>
                    <?php elseif ($rule['notify_type'] === 'user'): ?>
                        <span class="badge badge-info" style="margin:1px"><?= e(isset($userNameMap[(int)$t]) ? $userNameMap[(int)$t] : '使用者#' . $t) ?></span>
                    <?php else: ?>
                        <span class="badge badge-warning" style="margin:1px"><?= e(isset($allFieldLabels[$t]) ? $allFieldLabels[$t] : $t) ?></span>
                    <?php endif; ?>
                    <?php endforeach; ?>
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
<div id="editModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;justify-content:center;align-items:flex-start;padding-top:5vh" onclick="if(event.target===this)closeEditModal()">
    <div style="width:90%;max-width:800px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.2);max-height:85vh;overflow-y:auto;padding:24px">
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
                            <option value="user">指定人員</option>
                            <option value="field">記錄欄位</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>通知對象（可多選）</label>
                        <div id="editNotifyTarget" class="notify-target-checkboxes" style="border:1px solid #ddd;border-radius:6px;padding:8px;max-height:160px;overflow-y:auto"></div>
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
var allUsers = <?= json_encode($allUsers ?? array(), JSON_UNESCAPED_UNICODE) ?>;
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
        if (Array.isArray(vals)) {
            for (var i = 0; i < vals.length; i++) {
                var o = document.createElement('option');
                o.value = vals[i];
                o.textContent = vals[i];
                cvSel.appendChild(o);
            }
        } else {
            var keys = Object.keys(vals);
            for (var i = 0; i < keys.length; i++) {
                var o = document.createElement('option');
                o.value = keys[i];
                o.textContent = vals[keys[i]];
                cvSel.appendChild(o);
            }
        }
    }
}

function onNotifyTypeChange(sel, prefix) {
    var type = sel ? sel.value : document.getElementById(prefix + 'NotifyType').value;
    var container = document.getElementById(prefix + 'NotifyTarget');
    var formName = (prefix === 'edit') ? 'edit_notify_target[]' : 'notify_target[]';
    container.innerHTML = '';
    if (type === 'role') {
        for (var i = 0; i < allRoles.length; i++) {
            var label = document.createElement('label');
            label.style = 'display:block;padding:3px 0;font-size:.85rem;cursor:pointer';
            label.innerHTML = '<input type="checkbox" name="' + formName + '" value="' + allRoles[i].role_key + '"> ' + allRoles[i].role_label;
            container.appendChild(label);
        }
    } else if (type === 'user') {
        // 加關鍵字搜尋
        var search = document.createElement('input');
        search.type = 'text';
        search.placeholder = '搜尋人員...';
        search.style = 'width:100%;padding:4px 6px;border:1px solid #ddd;border-radius:4px;margin-bottom:6px;font-size:.85rem';
        search.oninput = function() {
            var kw = this.value.toLowerCase();
            container.querySelectorAll('label[data-user-row]').forEach(function(lb) {
                var name = lb.getAttribute('data-search') || '';
                lb.style.display = (!kw || name.indexOf(kw) !== -1) ? 'block' : 'none';
            });
        };
        container.appendChild(search);
        for (var j = 0; j < allUsers.length; j++) {
            var u = allUsers[j];
            var lb = document.createElement('label');
            lb.setAttribute('data-user-row', '1');
            lb.setAttribute('data-search', (u.real_name + ' ' + u.role).toLowerCase());
            lb.style = 'display:block;padding:3px 0;font-size:.85rem;cursor:pointer';
            lb.innerHTML = '<input type="checkbox" name="' + formName + '" value="' + u.id + '"> ' + u.real_name + ' <span style="color:#888;font-size:.75rem">(' + u.role + ')</span>';
            container.appendChild(lb);
        }
    } else {
        var mod = document.getElementById(prefix + 'Module').value;
        var fields = (registry[mod] && registry[mod].record_fields) ? registry[mod].record_fields : {};
        var hasFields = false;
        for (var fk in fields) {
            hasFields = true;
            var label = document.createElement('label');
            label.style = 'display:block;padding:3px 0;font-size:.85rem;cursor:pointer';
            label.innerHTML = '<input type="checkbox" name="' + formName + '" value="' + fk + '"> ' + fields[fk];
            container.appendChild(label);
        }
        if (!hasFields) {
            container.innerHTML = '<span style="color:#999;font-size:.85rem">此模組無可用欄位</span>';
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

    // 設定通知對象（支援多選）
    document.getElementById('editNotifyType').value = rule.notify_type;
    onNotifyTypeChange(null, 'edit');
    // 勾選已選的目標（逗號分隔）
    var targets = (rule.notify_target || '').split(',');
    var checkboxes = document.querySelectorAll('#editNotifyTarget input[type="checkbox"]');
    for (var c = 0; c < checkboxes.length; c++) {
        checkboxes[c].checked = targets.indexOf(checkboxes[c].value) !== -1;
    }

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
    // 收集多選通知對象
    var targets = [];
    var cbs = document.querySelectorAll('#editNotifyTarget input[type="checkbox"]:checked');
    for (var i = 0; i < cbs.length; i++) targets.push(cbs[i].value);
    fd.forEach(function(v, k) {
        if (k === 'edit_notify_target[]') return; // 跳過，用下面的合併值
        body.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
    });
    body.push('notify_target=' + encodeURIComponent(targets.join(',')));

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
