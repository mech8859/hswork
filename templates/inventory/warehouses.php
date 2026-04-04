<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>倉庫管理</h2>
    <div class="d-flex gap-1">
        <a href="/inventory.php" class="btn btn-outline btn-sm">返回庫存列表</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="openWhModal()">+ 新增倉庫</button>
    </div>
</div>

<div class="card">
    <?php if (empty($allWarehouses)): ?>
        <p class="text-muted text-center mt-2">目前無倉庫資料</p>
    <?php else: ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($allWarehouses as $w): ?>
        <div class="staff-card">
            <div class="d-flex justify-between align-center">
                <strong><?= e($w['name']) ?></strong>
                <div class="d-flex gap-1 align-center">
                    <?php if ($w['is_active']): ?>
                    <span class="badge badge-success">啟用</span>
                    <?php else: ?>
                    <span class="badge badge-muted">停用</span>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick='openWhModal(<?= json_encode($w) ?>)'>編輯</button>
                </div>
            </div>
            <div class="staff-card-meta">
                <span>代碼 <?= e($w['code']) ?></span>
                <span><?= e(!empty($w['branch_name']) ? $w['branch_name'] : '-') ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>倉庫代碼</th>
                    <th>倉庫名稱</th>
                    <th>所屬分公司</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allWarehouses as $w): ?>
                <tr>
                    <td><strong><?= e($w['code']) ?></strong></td>
                    <td><?= e($w['name']) ?></td>
                    <td><?= e(!empty($w['branch_name']) ? $w['branch_name'] : '-') ?></td>
                    <td>
                        <?php if ($w['is_active']): ?>
                        <span class="badge badge-success">啟用</span>
                        <?php else: ?>
                        <span class="badge badge-muted">停用</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-outline btn-sm" onclick='openWhModal(<?= json_encode($w) ?>)'>編輯</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal-overlay" id="whModal" style="display:none">
    <div class="modal-content" style="max-width:450px">
        <div class="d-flex justify-between align-center mb-2">
            <h3 id="whModalTitle">新增倉庫</h3>
            <a href="javascript:void(0)" onclick="closeWhModal()" style="font-size:1.5rem;color:var(--gray-400)">&times;</a>
        </div>
        <form method="POST" action="/inventory.php?action=warehouse_save">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="whId">

            <div class="form-group">
                <label>分公司 <span class="text-danger">*</span></label>
                <select name="branch_id" id="whBranch" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= e($b['id']) ?>"><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>倉庫代碼 <span class="text-danger">*</span></label>
                <input type="text" name="code" id="whCode" class="form-control" required placeholder="如 WH-TZ">
            </div>
            <div class="form-group">
                <label>倉庫名稱 <span class="text-danger">*</span></label>
                <input type="text" name="name" id="whName" class="form-control" required>
            </div>
            <div class="form-group" id="whActiveGroup" style="display:none">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" id="whActive" checked>
                    啟用
                </label>
            </div>

            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-primary">儲存</button>
                <button type="button" class="btn btn-outline" onclick="closeWhModal()">取消</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.4); z-index: 999; display: flex; align-items: center; justify-content: center; }
.modal-content { background: #fff; border-radius: var(--radius); padding: 24px; width: 90%; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 600; }
.badge-success { background: #e6f9ee; color: var(--success); }
.badge-muted { background: var(--gray-100); color: var(--gray-500); }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>

<script>
function openWhModal(data) {
    var modal = document.getElementById('whModal');
    if (data) {
        document.getElementById('whModalTitle').textContent = '編輯倉庫';
        document.getElementById('whId').value = data.id;
        document.getElementById('whBranch').value = data.branch_id;
        document.getElementById('whCode').value = data.code;
        document.getElementById('whName').value = data.name;
        document.getElementById('whActive').checked = !!parseInt(data.is_active);
        document.getElementById('whActiveGroup').style.display = '';
    } else {
        document.getElementById('whModalTitle').textContent = '新增倉庫';
        document.getElementById('whId').value = '';
        document.getElementById('whBranch').value = '';
        document.getElementById('whCode').value = '';
        document.getElementById('whName').value = '';
        document.getElementById('whActive').checked = true;
        document.getElementById('whActiveGroup').style.display = 'none';
    }
    modal.style.display = 'flex';
}
function closeWhModal() {
    document.getElementById('whModal').style.display = 'none';
}
document.getElementById('whModal').addEventListener('click', function(e) {
    if (e.target === this) closeWhModal();
});
</script>
