<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>產品分類管理</h2>
    <div class="d-flex gap-1">
        <?= back_button('/products.php') ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="openCatModal()">+ 新增分類</button>
    </div>
</div>

<div class="card">
    <?php if (empty($categoriesTree)): ?>
        <p class="text-muted text-center mt-2">尚無分類</p>
    <?php else: ?>

    <?php
    // 整理成樹狀結構
    $catMap = array();
    foreach ($categoriesTree as $c) {
        $catMap[$c['id']] = $c;
    }
    $roots = array();
    $children = array();
    foreach ($categoriesTree as $c) {
        $pid = !empty($c['parent_id']) ? $c['parent_id'] : 0;
        if (!isset($children[$pid])) $children[$pid] = array();
        $children[$pid][] = $c;
    }
    ?>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>主分類</th>
                    <th>子分類</th>
                    <th>細分類</th>
                    <th class="text-right">產品數</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php
                function renderCatRow($cat, $children, $catMap, $depth, $allCategories) {
                    $bgColor = $depth === 0 ? 'background:#f8f9fa;font-weight:600' : ($depth === 1 ? '' : 'color:var(--gray-500);font-size:0.9em');
                    $col1 = $depth === 0 ? e($cat['name']) : '';
                    $col2 = $depth === 1 ? e($cat['name']) : '';
                    $col3 = $depth >= 2 ? e($cat['name']) : '';
                ?>
                <tr style="<?= $bgColor ?>">
                    <td><?= $col1 ?></td>
                    <td><?= $col2 ?></td>
                    <td><?= $col3 ?></td>
                    <td class="text-right"><?= (int)$cat['product_count'] ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if (!empty($cat['exclude_from_stockout'])): ?><span class="badge" style="background:#ffebee;color:#c62828;font-size:.7rem;padding:2px 6px;margin-right:4px">不進出庫單</span><?php endif; ?>
                            <?php if (!empty($cat['is_non_inventory'])): ?><span class="badge" style="background:#fce4ec;color:#ad1457;font-size:.7rem;padding:2px 6px;margin-right:4px">非庫存</span><?php endif; ?>
                            <?php if (!empty($cat['show_in_material_estimate'])): ?><span class="badge" style="background:#e8f5e9;color:#2e7d32;font-size:.7rem;padding:2px 6px;margin-right:4px">預計線材</span><?php endif; ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick='openCatModal(<?= json_encode(array("id" => $cat["id"], "name" => $cat["name"], "parent_id" => $cat["parent_id"], "exclude_from_stockout" => (int)($cat["exclude_from_stockout"] ?? 0), "show_in_material_estimate" => (int)($cat["show_in_material_estimate"] ?? 0), "is_non_inventory" => (int)($cat["is_non_inventory"] ?? 0))) ?>)'>編輯</button>
                            <?php if ((int)$cat['child_count'] === 0 && (int)$cat['product_count'] === 0): ?>
                            <form method="POST" action="/products.php?action=category_delete" style="display:inline" onsubmit="return confirm('確認刪除分類「<?= e($cat['name']) ?>」？')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($cat['id']) ?>">
                                <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger)">刪除</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php
                    $cid = $cat['id'];
                    if (isset($children[$cid])) {
                        foreach ($children[$cid] as $child) {
                            renderCatRow($child, $children, $catMap, $depth + 1, $allCategories);
                        }
                    }
                }

                // 渲染根分類
                $rootCats = isset($children[0]) ? $children[0] : array();
                // 也要加入 parent_id IS NULL 的
                foreach ($categoriesTree as $c) {
                    if (empty($c['parent_id']) && !isset($children[0])) {
                        $rootCats[] = $c;
                    }
                }
                // 去重
                $rendered = array();
                foreach ($categoriesTree as $c) {
                    if (empty($c['parent_id'])) {
                        if (!isset($rendered[$c['id']])) {
                            renderCatRow($c, $children, $catMap, 0, $allCategories);
                            $rendered[$c['id']] = true;
                        }
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal-overlay" id="catModal" style="display:none">
    <div class="modal-content" id="catModalBox" style="max-width:500px;position:relative">
        <div class="d-flex justify-between align-center mb-2" id="catModalHeader" style="cursor:move;user-select:none">
            <h3 id="catModalTitle">新增分類</h3>
            <a href="javascript:void(0)" onclick="closeCatModal()" style="font-size:1.5rem;color:var(--gray-400)">&times;</a>
        </div>
        <form method="POST" action="/products.php?action=category_save">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="catId">
            <input type="hidden" name="parent_id" id="catParent">

            <div class="form-group">
                <label>主分類 <span class="text-danger">*</span></label>
                <select id="catLevel0" class="form-control" onchange="onLevel0Change()">
                    <option value="">-- 選擇主分類 --</option>
                    <option value="__new__">+ 新增主分類</option>
                    <?php foreach ($allCategories as $cat):
                        if (empty($cat['parent_id']) || $cat['parent_id'] == 0): ?>
                    <option value="<?= e($cat['id']) ?>"><?= e($cat['name']) ?></option>
                    <?php endif; endforeach; ?>
                </select>
                <input type="text" id="catLevel0New" class="form-control mt-1" style="display:none" placeholder="輸入新主分類名稱">
            </div>

            <div class="form-group">
                <label>子分類</label>
                <select id="catLevel1" class="form-control" onchange="onLevel1Change()">
                    <option value="">-- 選擇子分類 --</option>
                </select>
                <input type="text" id="catLevel1New" class="form-control mt-1" style="display:none" placeholder="輸入新子分類名稱">
            </div>

            <div class="form-group">
                <label>細分類</label>
                <select id="catLevel2" class="form-control" onchange="onLevel2Change()">
                    <option value="">-- 選擇細分類 --</option>
                </select>
                <input type="text" id="catLevel2Name" class="form-control mt-1" style="display:none" placeholder="輸入新細分類名稱">
            </div>

            <input type="hidden" name="name" id="catName">

            <div class="form-group" style="margin-top:8px">
                <label class="checkbox-label" style="cursor:pointer">
                    <input type="checkbox" name="exclude_from_stockout" id="catExcludeStockout" value="1">
                    <span style="color:#c62828;font-weight:600">不進出庫單</span>
                </label>
                <small style="color:#888;display:block;margin-top:2px">勾選後，此分類下的產品從報價單建立出庫單時會自動排除（仍可入庫/手動出庫）</small>
            </div>

            <div class="form-group" style="margin-top:8px">
                <label class="checkbox-label" style="cursor:pointer">
                    <input type="checkbox" name="is_non_inventory" id="catNonInventory" value="1">
                    <span style="color:#ad1457;font-weight:600">非庫存</span>
                </label>
                <small style="color:#888;display:block;margin-top:2px">勾選後，此分類下的產品完全不出現在入庫/出庫/採購搜尋（適用工程項次、工資、費用類）</small>
            </div>

            <div class="form-group" style="margin-top:8px">
                <label class="checkbox-label" style="cursor:pointer">
                    <input type="checkbox" name="show_in_material_estimate" id="catShowMaterial" value="1">
                    <span style="color:#2e7d32;font-weight:600">預計線材</span>
                </label>
                <small style="color:#888;display:block;margin-top:2px">勾選後，此分類（含子分類）下的產品會出現在報價單「預計使用線材」搜尋結果</small>
            </div>

            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-primary" onclick="return prepareSave()">儲存</button>
                <button type="button" class="btn btn-outline" onclick="closeCatModal()">取消</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.4); z-index: 999; display: flex; align-items: center; justify-content: center; }
.modal-content { background: #fff; border-radius: var(--radius); padding: 24px; width: 90%; }
</style>

<script>
// 分類資料（用 PHP 輸出成 JS）
var allCats = <?= json_encode(array_map(function($c) {
    return array('id' => $c['id'], 'name' => $c['name'], 'parent_id' => $c['parent_id'], 'exclude_from_stockout' => (int)($c['exclude_from_stockout'] ?? 0));
}, $allCategories), JSON_UNESCAPED_UNICODE) ?>;

function getCatsByParent(parentId) {
    var result = [];
    for (var i = 0; i < allCats.length; i++) {
        var pid = allCats[i].parent_id || 0;
        if (String(pid) === String(parentId)) {
            result.push(allCats[i]);
        }
    }
    return result;
}

function getCatDepth(catId) {
    var depth = 0;
    var current = catId;
    while (current) {
        var found = false;
        for (var i = 0; i < allCats.length; i++) {
            if (String(allCats[i].id) === String(current)) {
                current = allCats[i].parent_id || 0;
                if (current) depth++;
                found = true;
                break;
            }
        }
        if (!found) break;
    }
    return depth;
}

function onLevel0Change() {
    var val = document.getElementById('catLevel0').value;
    var newInput = document.getElementById('catLevel0New');
    var level1 = document.getElementById('catLevel1');
    var level1New = document.getElementById('catLevel1New');
    var level2 = document.getElementById('catLevel2Name');

    newInput.style.display = (val === '__new__') ? 'block' : 'none';
    if (val === '__new__') newInput.focus();

    // 填充子分類
    level1.innerHTML = '<option value="">-- 選擇子分類 --</option><option value="__new__">+ 新增子分類</option>';
    level1New.style.display = 'none';
    level2.value = '';

    if (val && val !== '__new__') {
        var subs = getCatsByParent(val);
        for (var i = 0; i < subs.length; i++) {
            var opt = document.createElement('option');
            opt.value = subs[i].id;
            opt.textContent = subs[i].name;
            level1.appendChild(opt);
        }
    }
}

function onLevel1Change() {
    var val = document.getElementById('catLevel1').value;
    var newInput = document.getElementById('catLevel1New');
    var level2 = document.getElementById('catLevel2');
    var level2New = document.getElementById('catLevel2Name');
    newInput.style.display = (val === '__new__') ? 'block' : 'none';
    if (val === '__new__') newInput.focus();

    // 填充細分類
    level2.innerHTML = '<option value="">-- 選擇細分類 --</option><option value="__new__">+ 新增細分類</option>';
    level2New.style.display = 'none';
    level2New.value = '';

    if (val && val !== '__new__') {
        var subs = getCatsByParent(val);
        for (var i = 0; i < subs.length; i++) {
            var opt = document.createElement('option');
            opt.value = subs[i].id;
            opt.textContent = subs[i].name;
            level2.appendChild(opt);
        }
    }
}

function onLevel2Change() {
    var val = document.getElementById('catLevel2').value;
    var newInput = document.getElementById('catLevel2Name');
    newInput.style.display = (val === '__new__') ? 'block' : 'none';
    if (val === '__new__') newInput.focus();
}

function prepareSave() {
    var id = document.getElementById('catId').value;
    var l0 = document.getElementById('catLevel0').value;
    var l0New = document.getElementById('catLevel0New').value.trim();
    var l1 = document.getElementById('catLevel1').value;
    var l1New = document.getElementById('catLevel1New').value.trim();
    var l2 = document.getElementById('catLevel2').value;
    var l2Name = document.getElementById('catLevel2Name').value.trim();

    // 判斷要儲存什麼
    if (id) {
        // 編輯模式：從 UI 取得最新值
        var depth = getCatDepth(id);
        var editName = '';
        if (depth === 0) {
            editName = document.getElementById('catLevel0New').value.trim();
        } else if (depth === 1) {
            editName = document.getElementById('catLevel1New').value.trim();
        } else {
            editName = document.getElementById('catLevel2Name').value.trim();
        }
        if (!editName) {
            alert('請輸入分類名稱');
            return false;
        }
        document.getElementById('catName').value = editName;

        // 編輯模式下，如果有填新的子層級，也一併建立
        var csrfToken = document.querySelector('input[name="csrf_token"]').value;
        if (depth === 0 && l1 === '__new__' && l1New) {
            var fd1 = new FormData();
            fd1.append('csrf_token', csrfToken);
            fd1.append('name', l1New);
            fd1.append('parent_id', id);
            var xhr1 = new XMLHttpRequest();
            xhr1.open('POST', '/products.php?action=ajax_category_create', false);
            xhr1.send(fd1);
            try {
                var res1 = JSON.parse(xhr1.responseText);
                if (res1.success && l2 === '__new__' && l2Name) {
                    var fd2 = new FormData();
                    fd2.append('csrf_token', csrfToken);
                    fd2.append('name', l2Name);
                    fd2.append('parent_id', res1.id);
                    var xhr2 = new XMLHttpRequest();
                    xhr2.open('POST', '/products.php?action=ajax_category_create', false);
                    xhr2.send(fd2);
                }
            } catch(e) {}
        } else if (depth === 1 && l2 === '__new__' && l2Name) {
            var fd2 = new FormData();
            fd2.append('csrf_token', csrfToken);
            fd2.append('name', l2Name);
            fd2.append('parent_id', id);
            var xhr2 = new XMLHttpRequest();
            xhr2.open('POST', '/products.php?action=ajax_category_create', false);
            xhr2.send(fd2);
        }
        return true;
    }

    // 新增模式：從最深的層級往上判斷
    var csrfToken = document.querySelector('input[name="csrf_token"]').value;

    // 先確保主分類存在
    var level0Id = l0;
    if (l0 === '__new__' && l0New) {
        var fd0 = new FormData();
        fd0.append('csrf_token', csrfToken);
        fd0.append('name', l0New);
        var xhr0 = new XMLHttpRequest();
        xhr0.open('POST', '/products.php?action=ajax_category_create', false);
        xhr0.send(fd0);
        var res0 = JSON.parse(xhr0.responseText);
        if (!res0.success) { alert(res0.error || '主分類建立失敗'); return false; }
        level0Id = res0.id;
    }
    if (!level0Id || level0Id === '__new__') {
        alert('請選擇或輸入主分類');
        return false;
    }

    // 如果沒有子分類要建，就直接存主分類
    if ((!l1 || l1 === '') && l0 === '__new__') {
        document.getElementById('catName').value = l0New;
        document.getElementById('catParent').value = '';
        // 主分類已透過 AJAX 建好，不需再 POST
        alert('分類已新增');
        location.reload();
        return false;
    }

    // 確保子分類存在
    var level1Id = l1;
    if (l1 === '__new__' && l1New) {
        var fd1 = new FormData();
        fd1.append('csrf_token', csrfToken);
        fd1.append('name', l1New);
        fd1.append('parent_id', level0Id);
        var xhr1 = new XMLHttpRequest();
        xhr1.open('POST', '/products.php?action=ajax_category_create', false);
        xhr1.send(fd1);
        var res1 = JSON.parse(xhr1.responseText);
        if (!res1.success) { alert(res1.error || '子分類建立失敗'); return false; }
        level1Id = res1.id;
    }

    // 如果沒有細分類要建
    if ((!l2 || l2 === '') && !l2Name) {
        if (l1 === '__new__' && l1New) {
            // 子分類已透過 AJAX 建好
            alert('分類已新增');
            location.reload();
            return false;
        }
        alert('請輸入分類名稱');
        return false;
    }

    // 建細分類（透過表單 POST）
    if (l2 === '__new__' && l2Name) {
        if (!level1Id || level1Id === '__new__') {
            alert('請選擇或輸入子分類');
            return false;
        }
        document.getElementById('catName').value = l2Name;
        document.getElementById('catParent').value = level1Id;
    } else if (l2 && l2 !== '__new__') {
        // 選了既有細分類，沒什麼要做
        alert('請輸入新分類名稱');
        return false;
    } else {
        alert('請輸入分類名稱');
        return false;
    }
    return true;
}

function openCatModal(data) {
    var modal = document.getElementById('catModal');
    // 重設所有欄位
    document.getElementById('catId').value = '';
    document.getElementById('catName').value = '';
    document.getElementById('catParent').value = '';
    document.getElementById('catExcludeStockout').checked = false;
    document.getElementById('catShowMaterial').checked = false;
    document.getElementById('catNonInventory').checked = false;
    document.getElementById('catLevel0').value = '';
    document.getElementById('catLevel0New').value = '';
    document.getElementById('catLevel0New').style.display = 'none';
    document.getElementById('catLevel1').innerHTML = '<option value="">-- 選擇子分類 --</option><option value="__new__">+ 新增子分類</option>';
    document.getElementById('catLevel1New').value = '';
    document.getElementById('catLevel1New').style.display = 'none';
    document.getElementById('catLevel2').innerHTML = '<option value="">-- 選擇細分類 --</option><option value="__new__">+ 新增細分類</option>';
    document.getElementById('catLevel2Name').value = '';
    document.getElementById('catLevel2Name').style.display = 'none';

    if (data) {
        document.getElementById('catModalTitle').textContent = '編輯分類';
        document.getElementById('catId').value = data.id;
        document.getElementById('catName').value = data.name;
        document.getElementById('catParent').value = data.parent_id || '';
        document.getElementById('catExcludeStockout').checked = !!data.exclude_from_stockout;
        document.getElementById('catShowMaterial').checked = !!data.show_in_material_estimate;
        document.getElementById('catNonInventory').checked = !!data.is_non_inventory;

        // 根據深度設定對應欄位
        var depth = getCatDepth(data.id);
        if (depth === 0) {
            // 編輯主分類
            document.getElementById('catLevel0').value = '__new__';
            document.getElementById('catLevel0New').value = data.name;
            document.getElementById('catLevel0New').style.display = 'block';
        } else if (depth === 1) {
            // 編輯子分類
            document.getElementById('catLevel0').value = data.parent_id;
            onLevel0Change();
            document.getElementById('catLevel1').value = '__new__';
            document.getElementById('catLevel1New').value = data.name;
            document.getElementById('catLevel1New').style.display = 'block';
        } else {
            // 編輯細分類
            var parentCat = null;
            for (var i = 0; i < allCats.length; i++) {
                if (String(allCats[i].id) === String(data.parent_id)) {
                    parentCat = allCats[i];
                    break;
                }
            }
            if (parentCat) {
                document.getElementById('catLevel0').value = parentCat.parent_id || '';
                onLevel0Change();
                document.getElementById('catLevel1').value = data.parent_id;
                onLevel1Change();
            }
            document.getElementById('catLevel2').value = '__new__';
            document.getElementById('catLevel2Name').value = data.name;
            document.getElementById('catLevel2Name').style.display = 'block';
        }
    } else {
        document.getElementById('catModalTitle').textContent = '新增分類';
    }
    modal.style.display = 'flex';
}

function closeCatModal() {
    document.getElementById('catModal').style.display = 'none';
    document.getElementById('catModalBox').style.transform = '';
}
// 拖曳
(function() {
    var header = document.getElementById('catModalHeader');
    var box = document.getElementById('catModalBox');
    var dx = 0, dy = 0, startX = 0, startY = 0, dragging = false;
    header.addEventListener('mousedown', function(e) {
        if (e.target.tagName === 'A') return;
        dragging = true;
        startX = e.clientX - dx;
        startY = e.clientY - dy;
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', stopDrag);
    });
    function onDrag(e) {
        if (!dragging) return;
        dx = e.clientX - startX;
        dy = e.clientY - startY;
        box.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
    }
    function stopDrag() {
        dragging = false;
        document.removeEventListener('mousemove', onDrag);
        document.removeEventListener('mouseup', stopDrag);
    }
})();
document.getElementById('catModal').addEventListener('click', function(e) {
    if (e.target === this) closeCatModal();
});
</script>
