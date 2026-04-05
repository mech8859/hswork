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
<?php if ($model->isHierarchical($category)): ?>
<!-- 層級分類（付款單分類）-->
<div class="card">
    <?php if (empty($options)): ?>
        <p class="text-muted text-center mt-2 mb-2">尚無分類</p>
    <?php else: ?>
    <table class="table">
        <thead><tr>
            <th style="width:50px">#</th>
            <th>主分類</th>
            <th style="width:80px">子分類數</th>
            <th style="width:80px">狀態</th>
            <th style="width:180px">操作</th>
        </tr></thead>
        <tbody>
        <?php foreach ($options as $i => $opt):
            $subOptions = $model->getSubOptions($opt['id']);
            $subCount = count($subOptions);
        ?>
            <tr id="row-<?= $opt['id'] ?>" class="<?= $opt['is_active'] ? '' : 'text-muted' ?>">
                <td><?= $i + 1 ?></td>
                <td>
                    <span class="opt-label" id="label-<?= $opt['id'] ?>" style="font-weight:600"><?= e($opt['label']) ?></span>
                    <input type="text" class="form-control opt-input" id="input-<?= $opt['id'] ?>" value="<?= e($opt['label']) ?>" style="display:none">
                </td>
                <td><span class="badge" style="background:#eee;color:#666"><?= $subCount ?></span></td>
                <td>
                    <?php if ($opt['is_active']): ?>
                        <span class="badge badge-success">啟用</span>
                    <?php else: ?>
                        <span class="badge badge-danger">停用</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-outline btn-sm" onclick="toggleSubList(<?= $opt['id'] ?>)">展開</button>
                    <button class="btn btn-outline btn-sm" onclick="editOpt(<?= $opt['id'] ?>)">編輯</button>
                    <?php if ($opt['is_active']): ?>
                    <button class="btn btn-outline btn-sm text-danger" onclick="toggleOpt(<?= $opt['id'] ?>, 0)">停用</button>
                    <?php else: ?>
                    <button class="btn btn-outline btn-sm" onclick="toggleOpt(<?= $opt['id'] ?>, 1)">啟用</button>
                    <?php endif; ?>
                </td>
            </tr>
            <!-- 子分類列（預設隱藏）-->
            <tr id="sub-row-<?= $opt['id'] ?>" style="display:none">
                <td colspan="5" style="padding:8px 16px 8px 40px;background:#fafbff">
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                        <strong style="font-size:.85rem;color:var(--primary)">子分類：<?= e($opt['label']) ?></strong>
                        <input type="text" id="new-sub-<?= $opt['id'] ?>" class="form-control" placeholder="新增子分類..." style="flex:1;max-width:250px;padding:4px 8px;font-size:.85rem">
                        <button class="btn btn-primary btn-sm" onclick="addSubOpt(<?= $opt['id'] ?>)" style="padding:4px 12px;font-size:.8rem">+ 新增</button>
                    </div>
                    <div id="sub-list-<?= $opt['id'] ?>">
                        <?php if (empty($subOptions)): ?>
                            <span class="text-muted" style="font-size:.85rem">尚無子分類</span>
                        <?php else: ?>
                            <?php foreach ($subOptions as $si => $sub): ?>
                            <div class="sub-opt-item <?= $sub['is_active'] ? '' : 'text-muted' ?>" id="sub-item-<?= $sub['id'] ?>" style="display:flex;gap:8px;align-items:center;padding:4px 0;border-bottom:1px solid #f0f0f0">
                                <span style="width:30px;color:#999;font-size:.8rem"><?= $si + 1 ?></span>
                                <span class="opt-label" id="label-<?= $sub['id'] ?>" style="flex:1;font-size:.85rem"><?= e($sub['label']) ?></span>
                                <input type="text" class="form-control opt-input" id="input-<?= $sub['id'] ?>" value="<?= e($sub['label']) ?>" style="display:none;flex:1;padding:2px 6px;font-size:.85rem">
                                <?php if ($sub['is_active']): ?>
                                    <span class="badge badge-success" style="font-size:.7rem">啟用</span>
                                <?php else: ?>
                                    <span class="badge badge-danger" style="font-size:.7rem">停用</span>
                                <?php endif; ?>
                                <button class="btn btn-outline btn-sm" onclick="editOpt(<?= $sub['id'] ?>)" style="padding:2px 8px;font-size:.75rem">編輯</button>
                                <?php if ($sub['is_active']): ?>
                                <button class="btn btn-outline btn-sm text-danger" onclick="toggleOpt(<?= $sub['id'] ?>, 0)" style="padding:2px 8px;font-size:.75rem">停用</button>
                                <?php else: ?>
                                <button class="btn btn-outline btn-sm" onclick="toggleOpt(<?= $sub['id'] ?>, 1)" style="padding:2px 8px;font-size:.75rem">啟用</button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php else: ?>
<!-- 一般分類（平面清單）-->
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
<?php endif; ?>

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

function toggleSubList(parentId) {
    var row = document.getElementById('sub-row-' + parentId);
    if (!row) return;
    if (row.style.display === 'none') {
        row.style.display = '';
        // 更新按鈕文字
        var btn = event.target;
        btn.textContent = '收合';
    } else {
        row.style.display = 'none';
        var btn = event.target;
        btn.textContent = '展開';
    }
}

function addSubOpt(parentId) {
    var input = document.getElementById('new-sub-' + parentId);
    var label = input.value.trim();
    if (!label) { input.focus(); return; }
    fetch('/dropdown_options.php?action=add_sub_option', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'category=payment_main_category&parent_id=' + parentId + '&label=' + encodeURIComponent(label)
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) location.reload();
        else alert(d.error || '新增失敗');
    });
}
</script>
