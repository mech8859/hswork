<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>成本中心管理</h1>
    <?php if ($canManage): ?>
    <button class="btn btn-primary" onclick="openCcModal()">+ 新增成本中心</button>
    <?php endif; ?>
</div>

<div class="card" style="overflow-x:auto">
    <table class="data-table" style="width:100%">
        <thead>
            <tr>
                <th style="width:100px">代碼</th>
                <th>名稱</th>
                <th style="width:100px">類型</th>
                <th>所屬分處</th>
                <th style="width:60px">狀態</th>
                <?php if ($canManage): ?><th style="width:80px">操作</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($costCenters)): ?>
            <tr><td colspan="<?= $canManage ? 6 : 5 ?>" style="text-align:center;padding:20px;color:#999">尚無成本中心</td></tr>
            <?php endif; ?>
            <?php foreach ($costCenters as $cc): ?>
            <tr style="<?= $cc['is_active'] ? '' : 'opacity:0.5' ?>">
                <td><strong><?= e($cc['code']) ?></strong></td>
                <td><?= e($cc['name']) ?></td>
                <td><?= isset($typeOptions[$cc['type']]) ? e($typeOptions[$cc['type']]) : e($cc['type']) ?></td>
                <td><?= e($cc['branch_name']) ?></td>
                <td><?= $cc['is_active'] ? '<span style="color:green">啟用</span>' : '<span style="color:red">停用</span>' ?></td>
                <?php if ($canManage): ?>
                <td>
                    <button class="btn btn-sm btn-secondary" onclick='openCcModal(<?= json_encode($cc, JSON_UNESCAPED_UNICODE) ?>)'>編輯</button>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($canManage): ?>
<div id="ccModal" class="modal" style="display:none">
    <div class="modal-overlay" onclick="closeCcModal()"></div>
    <div class="modal-content" style="max-width:500px">
        <div class="modal-header">
            <h3 id="ccModalTitle">新增成本中心</h3>
            <button class="modal-close" onclick="closeCcModal()">&times;</button>
        </div>
        <form method="post" action="/accounting.php?action=cost_center_save">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="cc_id" value="">
            <div class="modal-body" style="display:grid;gap:12px">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label>代碼 <span style="color:red">*</span></label>
                        <input type="text" name="code" id="cc_code" class="form-control" required>
                    </div>
                    <div>
                        <label>名稱 <span style="color:red">*</span></label>
                        <input type="text" name="name" id="cc_name" class="form-control" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label>類型</label>
                        <select name="type" id="cc_type" class="form-control">
                            <?php foreach ($typeOptions as $k => $v): ?>
                            <option value="<?= e($k) ?>"><?= e($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>所屬分處</label>
                        <select name="branch_id" id="cc_branch" class="form-control">
                            <option value="">-- 無 --</option>
                            <?php foreach ($branches as $br): ?>
                            <option value="<?= $br['id'] ?>"><?= e($br['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label><input type="checkbox" name="is_active" id="cc_active" value="1" checked> 啟用</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCcModal()">取消</button>
                <button type="submit" class="btn btn-primary">儲存</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCcModal(cc) {
    document.getElementById('ccModal').style.display = 'flex';
    if (cc) {
        document.getElementById('ccModalTitle').textContent = '編輯成本中心';
        document.getElementById('cc_id').value = cc.id || '';
        document.getElementById('cc_code').value = cc.code || '';
        document.getElementById('cc_name').value = cc.name || '';
        document.getElementById('cc_type').value = cc.type || 'branch';
        document.getElementById('cc_branch').value = cc.branch_id || '';
        document.getElementById('cc_active').checked = cc.is_active == 1;
    } else {
        document.getElementById('ccModalTitle').textContent = '新增成本中心';
        document.getElementById('cc_id').value = '';
        document.getElementById('cc_code').value = '';
        document.getElementById('cc_name').value = '';
        document.getElementById('cc_type').value = 'branch';
        document.getElementById('cc_branch').value = '';
        document.getElementById('cc_active').checked = true;
    }
}
function closeCcModal() {
    document.getElementById('ccModal').style.display = 'none';
}
</script>

<style>
.modal { position:fixed;top:0;left:0;right:0;bottom:0;z-index:1000;display:flex;align-items:center;justify-content:center }
.modal-overlay { position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5) }
.modal-content { position:relative;background:#fff;border-radius:8px;width:90%;max-height:90vh;overflow-y:auto }
.modal-header { display:flex;justify-content:space-between;align-items:center;padding:16px;border-bottom:1px solid #eee }
.modal-header h3 { margin:0 }
.modal-close { background:none;border:none;font-size:24px;cursor:pointer }
.modal-body { padding:16px }
.modal-footer { padding:16px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:8px }
</style>
<?php endif; ?>
