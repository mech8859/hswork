<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>分公司管理</h2>
    <div class="d-flex gap-1">
        <button type="button" class="btn btn-primary btn-sm" onclick="showBranchForm()">+ 新增分公司</button>
        <?= back_button('/staff.php') ?>
    </div>
</div>

<!-- 新增表單 -->
<div id="branchForm" class="card mb-2" style="display:none">
    <div class="card-header">新增分公司</div>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="sub_action" value="create">
        <div class="form-grid">
            <div class="form-group">
                <label>名稱 <span style="color:var(--danger)">*</span></label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>代碼</label>
                <input type="text" name="code" class="form-control">
            </div>
            <div class="form-group">
                <label>地址</label>
                <input type="text" name="address" class="form-control">
            </div>
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="phone" class="form-control">
            </div>
        </div>
        <div class="d-flex gap-1 mt-1">
            <button type="submit" class="btn btn-primary btn-sm">建立</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="hideBranchForm()">取消</button>
        </div>
    </form>
</div>

<!-- 分公司列表 -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>名稱</th>
                    <th>代碼</th>
                    <th>地址</th>
                    <th>電話</th>
                    <th>狀態</th>
                    <th style="width:80px">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $b): ?>
                <tr id="branch-row-<?= $b['id'] ?>">
                    <td><?= $b['id'] ?></td>
                    <td>
                        <span class="branch-name-display"><?= e($b['name']) ?></span>
                        <input type="text" class="form-control branch-name-input" value="<?= e($b['name']) ?>" style="display:none;max-width:200px">
                    </td>
                    <td>
                        <span class="branch-code-display"><?= e($b['code'] ?: '-') ?></span>
                        <input type="text" class="form-control branch-code-input" value="<?= e($b['code'] ?: '') ?>" style="display:none;max-width:100px">
                    </td>
                    <td>
                        <span class="branch-addr-display"><?= e($b['address'] ?: '-') ?></span>
                        <input type="text" class="form-control branch-addr-input" value="<?= e($b['address'] ?: '') ?>" style="display:none;max-width:250px">
                    </td>
                    <td>
                        <span class="branch-phone-display"><?= e($b['phone'] ?: '-') ?></span>
                        <input type="text" class="form-control branch-phone-input" value="<?= e($b['phone'] ?: '') ?>" style="display:none;max-width:150px">
                    </td>
                    <td>
                        <?php if ($b['is_active']): ?>
                        <span class="badge badge-success">啟用</span>
                        <?php else: ?>
                        <span class="badge">停用</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-outline btn-sm branch-edit-btn" onclick="editBranch(<?= $b['id'] ?>)" style="padding:2px 8px">編輯</button>
                        <button type="button" class="btn btn-primary btn-sm branch-save-btn" onclick="saveBranch(<?= $b['id'] ?>)" style="padding:2px 8px;display:none">儲存</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
@media (max-width: 767px) { .form-grid { grid-template-columns: 1fr; } }
</style>

<script>
function showBranchForm() { document.getElementById('branchForm').style.display = 'block'; }
function hideBranchForm() { document.getElementById('branchForm').style.display = 'none'; }

function editBranch(id) {
    var row = document.getElementById('branch-row-' + id);
    row.querySelectorAll('.branch-name-display, .branch-code-display, .branch-addr-display, .branch-phone-display').forEach(function(el) { el.style.display = 'none'; });
    row.querySelectorAll('.branch-name-input, .branch-code-input, .branch-addr-input, .branch-phone-input').forEach(function(el) { el.style.display = 'inline-block'; });
    row.querySelector('.branch-edit-btn').style.display = 'none';
    row.querySelector('.branch-save-btn').style.display = 'inline-block';
}

function saveBranch(id) {
    var row = document.getElementById('branch-row-' + id);
    var form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    form.innerHTML = '<?= csrf_field() ?>' +
        '<input name="sub_action" value="update">' +
        '<input name="branch_id" value="' + id + '">' +
        '<input name="name" value="' + row.querySelector('.branch-name-input').value + '">' +
        '<input name="code" value="' + row.querySelector('.branch-code-input').value + '">' +
        '<input name="address" value="' + row.querySelector('.branch-addr-input').value + '">' +
        '<input name="phone" value="' + row.querySelector('.branch-phone-input').value + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>
