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
                            <button type="button" class="btn btn-outline btn-sm" onclick='openCatModal(<?= json_encode(array("id" => $cat["id"], "name" => $cat["name"], "parent_id" => $cat["parent_id"])) ?>)'>編輯</button>
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
    <div class="modal-content" style="max-width:450px">
        <div class="d-flex justify-between align-center mb-2">
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
                <input type="text" id="catLevel2Name" class="form-control" placeholder="輸入細分類名稱（選填）">
            </div>

            <input type="hidden" name="name" id="catName">

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
    return array('id' => $c['id'], 'name' => $c['name'], 'parent_id' => $c['parent_id']);
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
    newInput.style.display = (val === '__new__') ? 'block' : 'none';
    if (val === '__new__') newInput.focus();
    document.getElementById('catLevel2Name').value = '';
}

function prepareSave() {
    var id = document.getElementById('catId').value;
    var l0 = document.getElementById('catLevel0').value;
    var l0New = document.getElementById('catLevel0New').value.trim();
    var l1 = document.getElementById('catLevel1').value;
    var l1New = document.getElementById('catLevel1New').value.trim();
    var l2Name = document.getElementById('catLevel2Name').value.trim();

    // 判斷要儲存什麼
    if (id) {
        // 編輯模式：name 和 parent_id 已在 openCatModal 中設定
        return true;
    }

    // 新增模式：從最深的層級往上判斷
    if (l2Name) {
        // 新增細分類
        var parentId = l1;
        if (l1 === '__new__') {
            alert('請先儲存子分類，再新增細分類');
            return false;
        }
        if (!parentId) {
            alert('請選擇子分類');
            return false;
        }
        document.getElementById('catName').value = l2Name;
        document.getElementById('catParent').value = parentId;
    } else if (l1 === '__new__' && l1New) {
        // 新增子分類
        if (l0 === '__new__') {
            alert('請先儲存主分類，再新增子分類');
            return false;
        }
        if (!l0) {
            alert('請選擇主分類');
            return false;
        }
        document.getElementById('catName').value = l1New;
        document.getElementById('catParent').value = l0;
    } else if (l0 === '__new__' && l0New) {
        // 新增主分類
        document.getElementById('catName').value = l0New;
        document.getElementById('catParent').value = '';
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
    document.getElementById('catLevel0').value = '';
    document.getElementById('catLevel0New').value = '';
    document.getElementById('catLevel0New').style.display = 'none';
    document.getElementById('catLevel1').innerHTML = '<option value="">-- 選擇子分類 --</option><option value="__new__">+ 新增子分類</option>';
    document.getElementById('catLevel1New').value = '';
    document.getElementById('catLevel1New').style.display = 'none';
    document.getElementById('catLevel2Name').value = '';

    if (data) {
        document.getElementById('catModalTitle').textContent = '編輯分類';
        document.getElementById('catId').value = data.id;
        document.getElementById('catName').value = data.name;
        document.getElementById('catParent').value = data.parent_id || '';

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
            // 找到父層和祖父層
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
            }
            document.getElementById('catLevel2Name').value = data.name;
        }
    } else {
        document.getElementById('catModalTitle').textContent = '新增分類';
    }
    modal.style.display = 'flex';
}

function closeCatModal() {
    document.getElementById('catModal').style.display = 'none';
}
document.getElementById('catModal').addEventListener('click', function(e) {
    if (e.target === this) closeCatModal();
});
</script>
