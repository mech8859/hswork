<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>選單管理</h2>
</div>

<!-- 主分頁 -->
<div class="filter-pills mb-1">
    <div class="pill-group">
        <a href="/dropdown_options.php?tab=dropdown" class="pill">表單選項設定</a>
        <a href="/dropdown_options.php?tab=roles" class="pill pill-active">人員角色</a>
        <a href="/dropdown_options.php?tab=numbering" class="pill">自動編號設定</a>
        <a href="/dropdown_options.php?tab=quotation" class="pill">報價單設定</a>
    </div>
</div>

<?php if (Auth::user()['role'] === 'boss'): ?>
<!-- 新增角色 -->
<div class="card mb-2">
    <div class="card-header">新增角色</div>
    <form method="POST" action="/dropdown_options.php?action=add_role" class="d-flex gap-1 align-center" style="padding:12px">
        <?= csrf_field() ?>
        <input type="text" name="role_key" class="form-control" placeholder="角色代碼 (英文小寫，如 warehouse_staff)" required style="flex:1" pattern="[a-z][a-z0-9_]{1,48}">
        <input type="text" name="role_label" class="form-control" placeholder="角色名稱 (中文，如 倉管人員)" required style="flex:1">
        <button type="submit" class="btn btn-primary btn-sm">+ 新增</button>
    </form>
    <div style="padding:0 12px 12px;font-size:.8rem;color:var(--gray-500)">
        角色代碼須為小寫英文開頭，僅可含小寫英文、數字、底線。新角色的權限預設為空，請至人員管理設定權限。
    </div>
</div>
<?php endif; ?>

<!-- 角色列表 -->
<div class="card">
    <?php if (empty($roles)): ?>
        <p class="text-muted text-center mt-2 mb-2">尚無角色資料，請先執行 Migration 042</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th style="width:50px">#</th>
                <th>角色代碼</th>
                <th>角色名稱</th>
                <th style="width:80px">使用人數</th>
                <th style="width:80px">類型</th>
                <th style="width:80px">狀態</th>
                <?php if (Auth::user()['role'] === 'boss'): ?>
                <th style="width:150px">操作</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $i => $role): ?>
            <tr id="role-row-<?= $role['id'] ?>" class="<?= $role['is_active'] ? '' : 'text-muted' ?>">
                <td><?= $i + 1 ?></td>
                <td>
                    <code class="role-key-display" id="role-key-<?= $role['id'] ?>"><?= e($role['role_key']) ?></code>
                    <input type="text" class="form-control role-key-input" id="role-key-input-<?= $role['id'] ?>" value="<?= e($role['role_key']) ?>" style="display:none;font-size:.85rem;width:180px" pattern="[a-z][a-z0-9_]{1,48}">
                </td>
                <td>
                    <span class="role-label-display" id="role-label-<?= $role['id'] ?>"><?= e($role['role_label']) ?></span>
                    <input type="text" class="form-control role-label-input" id="role-label-input-<?= $role['id'] ?>" value="<?= e($role['role_label']) ?>" style="display:none;font-size:.85rem;width:180px">
                </td>
                <td>
                    <?php
                    $count = isset($roleUserCounts[$role['role_key']]) ? $roleUserCounts[$role['role_key']] : 0;
                    echo $count > 0 ? '<span class="badge badge-primary">' . $count . ' 人</span>' : '<span class="text-muted">0</span>';
                    ?>
                </td>
                <td>
                    <?php if ($role['is_system']): ?>
                        <span class="badge badge-info">內建</span>
                    <?php else: ?>
                        <span class="badge badge-default">自訂</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($role['is_active']): ?>
                        <span class="badge badge-success">啟用</span>
                    <?php else: ?>
                        <span class="badge badge-danger">停用</span>
                    <?php endif; ?>
                </td>
                <?php if (Auth::user()['role'] === 'boss'): ?>
                <td>
                    <a href="/dropdown_options.php?action=role_permissions&role_id=<?= $role['id'] ?>" class="btn btn-outline btn-sm" title="設定預設權限">權限</a>
                    <button class="btn btn-outline btn-sm" onclick="editRole(<?= $role['id'] ?>)" id="edit-btn-<?= $role['id'] ?>">編輯</button>
                    <button class="btn btn-outline btn-sm btn-success" onclick="saveRole(<?= $role['id'] ?>)" id="save-btn-<?= $role['id'] ?>" style="display:none">儲存</button>
                    <?php if ($role['role_key'] !== 'boss'): ?>
                        <button class="btn btn-outline btn-sm" style="color:#1976d2;border-color:#1976d2" onclick="syncRolePermissions('<?= e($role['role_key']) ?>', '<?= e($role['role_label']) ?>', <?= $count ?>)">同步</button>
                        <?php if ($role['is_active']): ?>
                        <button class="btn btn-outline btn-sm text-danger" onclick="toggleRole(<?= $role['id'] ?>, 0, <?= $count ?>)">停用</button>
                        <?php else: ?>
                        <button class="btn btn-outline btn-sm" onclick="toggleRole(<?= $role['id'] ?>, 1, 0)">啟用</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<style>
.filter-pills { display: flex; flex-direction: column; gap: 8px; }
.pill-group { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pill {
    display: inline-block; padding: 6px 16px; border-radius: 16px; font-size: .85rem;
    background: var(--gray-100); color: var(--gray-700); text-decoration: none; transition: all .15s;
}
.pill:hover { background: var(--gray-200); }
.pill-active { background: var(--primary); color: #fff; }
.badge-info { background: #17a2b8; color: #fff; }
.badge-default { background: var(--gray-200); color: var(--gray-700); }
code { background: var(--gray-100); padding: 2px 6px; border-radius: 3px; font-size: .85rem; }
</style>

<script>
function syncRolePermissions(roleKey, roleLabel, userCount) {
    if (!confirm('確定將「' + roleLabel + '」角色的 ' + userCount + ' 位人員權限全部重置為角色預設權限？\n\n個別人員的自訂權限將會被清除。')) return;
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fd.append('role_key', roleKey);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/dropdown_options.php?action=sync_role_permissions');
    xhr.onload = function() {
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) {
                alert('已同步 ' + res.count + ' 位「' + roleLabel + '」人員權限為預設值');
                location.reload();
            } else {
                alert(res.error || '同步失敗');
            }
        } catch(e) { alert('同步失敗'); }
    };
    xhr.send(fd);
}

function editRole(id) {
    var keyDisplay = document.getElementById('role-key-' + id);
    var keyInput = document.getElementById('role-key-input-' + id);
    var labelDisplay = document.getElementById('role-label-' + id);
    var labelInput = document.getElementById('role-label-input-' + id);
    var editBtn = document.getElementById('edit-btn-' + id);
    var saveBtn = document.getElementById('save-btn-' + id);

    keyDisplay.style.display = 'none';
    keyInput.style.display = 'inline-block';
    labelDisplay.style.display = 'none';
    labelInput.style.display = 'inline-block';
    editBtn.style.display = 'none';
    saveBtn.style.display = 'inline-block';
    labelInput.focus();

    labelInput.onkeydown = function(e) {
        if (e.key === 'Enter') { e.preventDefault(); saveRole(id); }
        if (e.key === 'Escape') { cancelEdit(id); }
    };
    keyInput.onkeydown = function(e) {
        if (e.key === 'Enter') { e.preventDefault(); saveRole(id); }
        if (e.key === 'Escape') { cancelEdit(id); }
    };
}

function cancelEdit(id) {
    var keyDisplay = document.getElementById('role-key-' + id);
    var keyInput = document.getElementById('role-key-input-' + id);
    var labelDisplay = document.getElementById('role-label-' + id);
    var labelInput = document.getElementById('role-label-input-' + id);
    var editBtn = document.getElementById('edit-btn-' + id);
    var saveBtn = document.getElementById('save-btn-' + id);

    keyInput.value = keyDisplay.textContent;
    labelInput.value = labelDisplay.textContent;
    keyDisplay.style.display = 'inline';
    keyInput.style.display = 'none';
    labelDisplay.style.display = 'inline';
    labelInput.style.display = 'none';
    editBtn.style.display = 'inline-block';
    saveBtn.style.display = 'none';
}

function saveRole(id) {
    var keyInput = document.getElementById('role-key-input-' + id);
    var labelInput = document.getElementById('role-label-input-' + id);
    var newKey = keyInput.value.trim();
    var newLabel = labelInput.value.trim();
    if (!newKey || !newLabel) {
        alert('角色代碼和名稱不可為空');
        return;
    }
    if (!/^[a-z][a-z0-9_]{1,48}$/.test(newKey)) {
        alert('角色代碼格式錯誤：須為小寫英文開頭，僅可含小寫英文、數字、底線');
        return;
    }
    fetch('/dropdown_options.php?action=update_role', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&role_key=' + encodeURIComponent(newKey) + '&role_label=' + encodeURIComponent(newLabel)
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            document.getElementById('role-key-' + id).textContent = newKey;
            document.getElementById('role-label-' + id).textContent = newLabel;
            cancelEdit(id);
        } else {
            alert(d.error || '更新失敗');
        }
    });
}

function toggleRole(id, active, userCount) {
    if (!active && userCount > 0) {
        alert('此角色尚有 ' + userCount + ' 位使用者，無法停用。請先將使用者調整至其他角色。');
        return;
    }
    var msg = active ? '確定要啟用此角色？' : '確定要停用此角色？停用後將無法指派給新使用者。';
    if (!confirm(msg)) return;
    fetch('/dropdown_options.php?action=toggle_role', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&active=' + active
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            location.reload();
        } else {
            alert(d.error || '操作失敗');
        }
    });
}
</script>
