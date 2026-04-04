<?php $isKeyed = $model->isKeyedCategory($category); ?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>選單管理</h2>
</div>

<!-- 主分頁 -->
<div class="filter-pills mb-1">
    <div class="pill-group">
        <a href="/dropdown_options.php?tab=dropdown" class="pill pill-active">表單選項設定</a>
        <a href="/dropdown_options.php?tab=roles" class="pill">人員角色</a>
        <a href="/dropdown_options.php?tab=numbering" class="pill">自動編號設定</a>
        <a href="/dropdown_options.php?tab=quotation" class="pill">報價單設定</a>
    </div>
</div>

<!-- 分類切換 -->
<div class="filter-pills mb-1">
    <div class="pill-group">
        <?php foreach ($categories as $ck => $cv): ?>
        <a href="/dropdown_options.php?category=<?= e($ck) ?>" class="pill <?= $category === $ck ? 'pill-active' : '' ?>"><?= e($cv) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- 新增選項 -->
<div class="card mb-2">
    <form method="POST" action="/dropdown_options.php?action=add" class="d-flex gap-1 align-center flex-wrap">
        <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
        <input type="hidden" name="category" value="<?= e($category) ?>">
        <?php if ($isKeyed): ?>
        <input type="text" name="option_key" class="form-control" placeholder="代碼 (英文)" required style="flex:0 0 160px">
        <?php endif; ?>
        <input type="text" name="label" class="form-control" placeholder="輸入新選項名稱..." required style="flex:1">
        <button type="submit" class="btn btn-primary btn-sm">+ 新增</button>
    </form>
</div>

<!-- 選項列表 -->
<div class="card">
    <?php if (empty($options)): ?>
        <p class="text-muted text-center mt-2 mb-2">尚無選項</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th style="width:50px">#</th>
                <?php if ($isKeyed): ?>
                <th style="width:160px">代碼</th>
                <?php endif; ?>
                <th>選項名稱</th>
                <th style="width:80px">狀態</th>
                <th style="width:60px">系統</th>
                <th style="width:120px">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($options as $i => $opt): ?>
            <?php $isSystem = !empty($opt['is_system']); ?>
            <tr id="row-<?= $opt['id'] ?>" class="<?= $opt['is_active'] ? '' : 'text-muted' ?>">
                <td><?= $i + 1 ?></td>
                <?php if ($isKeyed): ?>
                <td><code><?= e(isset($opt['option_key']) ? $opt['option_key'] : '') ?></code></td>
                <?php endif; ?>
                <td>
                    <span class="opt-label" id="label-<?= $opt['id'] ?>"><?= e($opt['label']) ?></span>
                    <input type="text" class="form-control opt-input" id="input-<?= $opt['id'] ?>" value="<?= e($opt['label']) ?>" style="display:none">
                </td>
                <td>
                    <?php if ($opt['is_active']): ?>
                        <span class="badge badge-success">啟用</span>
                    <?php else: ?>
                        <span class="badge badge-danger">停用</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isSystem): ?>
                        <span class="badge badge-info">內建</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-outline btn-sm" onclick="editOpt(<?= $opt['id'] ?>)">編輯</button>
                    <?php if ($opt['is_active']): ?>
                    <button class="btn btn-outline btn-sm text-danger" onclick="toggleOpt(<?= $opt['id'] ?>, 0)">停用</button>
                    <?php else: ?>
                    <button class="btn btn-outline btn-sm" onclick="toggleOpt(<?= $opt['id'] ?>, 1)">啟用</button>
                    <?php endif; ?>
                </td>
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
.opt-input { padding: 4px 8px; font-size: .9rem; }
</style>

<script>
function editOpt(id) {
    var label = document.getElementById('label-' + id);
    var input = document.getElementById('input-' + id);
    if (input.style.display === 'none') {
        label.style.display = 'none';
        input.style.display = 'inline-block';
        input.focus();
        input.onblur = function() { saveOpt(id); };
        input.onkeydown = function(e) { if (e.key === 'Enter') { e.preventDefault(); saveOpt(id); } };
    }
}

function saveOpt(id) {
    var input = document.getElementById('input-' + id);
    var label = document.getElementById('label-' + id);
    var newVal = input.value.trim();
    if (!newVal) { input.style.display = 'none'; label.style.display = 'inline'; return; }
    fetch('/dropdown_options.php?action=update', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&label=' + encodeURIComponent(newVal)
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            label.textContent = newVal;
        }
        input.style.display = 'none';
        label.style.display = 'inline';
    });
}

function toggleOpt(id, active) {
    fetch('/dropdown_options.php?action=toggle', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&active=' + active
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) location.reload();
    });
}
</script>
