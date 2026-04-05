<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>技能項目管理</h2>
    <?= back_button('/staff.php') ?>
</div>

<!-- 新增技能 -->
<div class="card">
    <div class="card-header">新增技能</div>
    <form method="POST" class="mt-1">
        <?= csrf_field() ?>
        <input type="hidden" name="sub_action" value="create">
        <div class="form-row">
            <div class="form-group">
                <label>技能名稱 *</label>
                <input type="text" name="name" class="form-control" required placeholder="例：大華數位系統安裝維修">
            </div>
            <div class="form-group">
                <label>技能群組 *</label>
                <select name="skill_group" class="form-control" required id="newSkillGroup">
                    <option value="">請選擇</option>
                    <option value="系統安裝技能">系統安裝技能</option>
                    <option value="設備安裝技能">設備安裝技能</option>
                    <option value="通用能力">通用能力</option>
                    <?php foreach ($distinctGroups as $g): ?>
                        <?php if (!in_array($g, array('系統安裝技能', '設備安裝技能', '通用能力'))): ?>
                        <option value="<?= e($g) ?>"><?= e($g) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>分類 *</label>
                <input type="text" name="category" class="form-control" required placeholder="例：監控"
                       list="categoryList">
                <datalist id="categoryList">
                    <?php foreach ($distinctCategories as $c): ?>
                    <option value="<?= e($c) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group" style="width:80px;flex:0 0 80px">
                <label>排序</label>
                <input type="number" name="sort_order" class="form-control" value="0" min="0">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">新增</button>
            </div>
        </div>
    </form>
</div>

<!-- 現有技能列表 -->
<?php foreach ($skillGroups as $groupName => $categories): ?>
<div class="card">
    <div class="card-header"><?= e($groupName) ?></div>
    <?php foreach ($categories as $catName => $skills): ?>
    <div class="skill-cat-section">
        <div class="skill-cat-title"><?= e($catName) ?></div>
        <?php foreach ($skills as $sk): ?>
        <div class="skill-manage-item" id="skill-<?= $sk['id'] ?>">
            <div class="skill-manage-info">
                <span class="skill-manage-name"><?= e($sk['name']) ?></span>
                <span class="text-muted" style="font-size:.75rem">排序: <?= (int)$sk['sort_order'] ?></span>
            </div>
            <div class="skill-manage-actions">
                <button type="button" class="btn btn-outline btn-sm" onclick="editSkill(<?= $sk['id'] ?>, '<?= e(addslashes($sk['name'])) ?>', '<?= e(addslashes($sk['skill_group'])) ?>', '<?= e(addslashes($sk['category'])) ?>', <?= (int)$sk['sort_order'] ?>)">編輯</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('確定刪除技能「<?= e(addslashes($sk['name'])) ?>」？已設定此技能的人員資料不會被刪除，但此技能將不再顯示。')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="sub_action" value="delete">
                    <input type="hidden" name="skill_id" value="<?= $sk['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">刪除</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<!-- 編輯彈窗 -->
<div id="editModal" class="modal-overlay hidden" onclick="if(event.target===this)closeEditModal()">
    <div class="modal-box">
        <div class="modal-header">
            <h3>編輯技能</h3>
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="sub_action" value="update">
            <input type="hidden" name="skill_id" id="editSkillId">
            <div class="form-group">
                <label>技能名稱 *</label>
                <input type="text" name="name" id="editSkillName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>技能群組 *</label>
                <select name="skill_group" id="editSkillGroup" class="form-control" required>
                    <option value="系統安裝技能">系統安裝技能</option>
                    <option value="設備安裝技能">設備安裝技能</option>
                    <option value="通用能力">通用能力</option>
                </select>
            </div>
            <div class="form-group">
                <label>分類 *</label>
                <input type="text" name="category" id="editSkillCategory" class="form-control" required list="categoryList">
            </div>
            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort_order" id="editSkillSort" class="form-control" value="0" min="0">
            </div>
            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-primary">儲存</button>
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">取消</button>
            </div>
        </form>
    </div>
</div>

<style>
.skill-cat-section { margin-bottom: 12px; }
.skill-cat-title {
    font-weight: 600; color: var(--primary); font-size: .85rem;
    padding: 8px 0 4px; border-bottom: 1px solid var(--gray-200);
}
.skill-manage-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 0; border-bottom: 1px solid var(--gray-100);
}
.skill-manage-info { display: flex; align-items: center; gap: 12px; }
.skill-manage-name { font-size: .9rem; }
.skill-manage-actions { display: flex; gap: 6px; flex-shrink: 0; }
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 140px; }

/* Modal */
.modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,.4); z-index: 2000;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
}
.modal-box {
    background: #fff; border-radius: 12px; padding: 24px;
    width: 100%; max-width: 480px; box-shadow: var(--shadow-lg);
}
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.modal-header h3 { margin: 0; font-size: 1.1rem; }
.modal-close { font-size: 1.5rem; cursor: pointer; color: var(--gray-500); line-height: 1; }
.modal-close:hover { color: var(--danger); }

@media (max-width: 600px) {
    .skill-manage-item { flex-direction: column; align-items: flex-start; gap: 6px; }
}
</style>

<script>
function editSkill(id, name, group, category, sortOrder) {
    document.getElementById('editSkillId').value = id;
    document.getElementById('editSkillName').value = name;
    document.getElementById('editSkillGroup').value = group;
    document.getElementById('editSkillCategory').value = category;
    document.getElementById('editSkillSort').value = sortOrder;
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>
