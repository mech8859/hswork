<?php
$canManage = Auth::hasPermission('products.manage') || in_array(Auth::user()['role'], array('boss','manager'));
?>
<?php if ($canManage): ?>
<input type="hidden" id="productStarCsrf" value="<?= e(Session::getCsrfToken()) ?>">
<?php endif; ?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>產品目錄 <span class="text-muted" style="font-size:.85rem;font-weight:400">(共 <?= number_format($result['total']) ?> 筆)</span></h2>
    <?php if ($canManage): ?>
    <div class="d-flex gap-1">
        <a href="/products.php?action=create" class="btn btn-primary btn-sm">+ 新增產品</a>
        <a href="/products.php?action=categories" class="btn btn-outline btn-sm">分類管理</a>
    </div>
    <?php endif; ?>
</div>

<!-- 篩選 -->
<div class="card mb-2" style="padding:12px">
    <form method="GET" action="/products.php" class="filter-form" id="productFilterForm">
        <div class="filter-row">
            <div class="form-group" style="flex:2;min-width:180px">
                <input type="text" name="keyword" class="form-control" placeholder="搜尋名稱 / 型號 / 品牌..." value="<?= e($filters['keyword']) ?>" autofocus onkeydown="if(event.key==='Enter'){this.form.submit();}">
            </div>
            <div class="form-group" style="flex:1;min-width:120px">
                <select name="supplier" class="form-control">
                    <option value="">全部供應商</option>
                    <?php foreach ($suppliers as $sup): ?>
                    <option value="<?= e($sup) ?>" <?= $filters['supplier'] === $sup ? 'selected' : '' ?>><?= e($sup) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:0;min-width:100px">
                <select name="is_active" class="form-control">
                    <option value="">全部狀態</option>
                    <option value="1" <?= (isset($filters['is_active']) && $filters['is_active'] === '1') ? 'selected' : '' ?>>啟用</option>
                    <option value="0" <?= (isset($filters['is_active']) && $filters['is_active'] === '0') ? 'selected' : '' ?>>停用</option>
                </select>
            </div>
            <div class="form-group" style="flex:0;min-width:110px">
                <select name="has_stock" class="form-control">
                    <option value="">全部庫存</option>
                    <option value="1" <?= (isset($filters['has_stock']) && $filters['has_stock'] === '1') ? 'selected' : '' ?>>有庫存</option>
                    <option value="0" <?= (isset($filters['has_stock']) && $filters['has_stock'] === '0') ? 'selected' : '' ?>>無庫存</option>
                </select>
            </div>
            <div class="form-group" style="flex:0;min-width:120px">
                <?php $curSort = isset($filters['sort']) ? $filters['sort'] : 'stock_desc'; ?>
                <select name="sort" class="form-control">
                    <option value="stock_desc" <?= $curSort === 'stock_desc' ? 'selected' : '' ?>>庫存多→少</option>
                    <option value="stock_asc" <?= $curSort === 'stock_asc' ? 'selected' : '' ?>>庫存少→多</option>
                    <option value="name_asc" <?= $curSort === 'name_asc' ? 'selected' : '' ?>>名稱 A→Z</option>
                    <option value="name_desc" <?= $curSort === 'name_desc' ? 'selected' : '' ?>>名稱 Z→A</option>
                    <option value="price_desc" <?= $curSort === 'price_desc' ? 'selected' : '' ?>>價格高→低</option>
                    <option value="price_asc" <?= $curSort === 'price_asc' ? 'selected' : '' ?>>價格低→高</option>
                    <?php if ($canManage): ?>
                    <option value="starred_first" <?= $curSort === 'starred_first' ? 'selected' : '' ?>>★ 星標優先</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group" style="flex:0">
                <button type="submit" class="btn btn-primary">搜尋</button>
            </div>
            <?php if ($filters['keyword'] || $filters['category_id'] || $filters['supplier'] || (isset($filters['is_active']) && $filters['is_active'] !== '') || (isset($filters['has_stock']) && $filters['has_stock'] !== '')): ?>
            <div class="form-group" style="flex:0">
                <a href="/products.php" class="btn btn-outline">清除</a>
            </div>
            <?php endif; ?>
        </div>
        <!-- 動態多層分類篩選 -->
        <div class="filter-row mt-1" id="catFilterRow">
            <input type="hidden" name="category_id" id="catFilterValue" value="<?= (int)$filters['category_id'] ?>">
            <div class="form-group" style="flex:1;min-width:140px">
                <select class="form-control cat-select" data-level="0" onchange="onCatFilterChange(this)">
                    <option value="">全部主分類</option>
                </select>
            </div>
        </div>
    </form>
</div>

<!-- 切換顯示模式 -->
<div class="d-flex gap-1 mb-1" style="justify-content:flex-end">
    <button class="btn btn-sm btn-outline" onclick="toggleView('card')" id="btnCard">卡片</button>
    <button class="btn btn-sm btn-primary" onclick="toggleView('table')" id="btnTable">表格</button>
</div>

<!-- 產品卡片 -->
<?php if (empty($result['data'])): ?>
<div class="card" style="text-align:center;padding:48px 16px">
    <p class="text-muted">找不到符合條件的產品</p>
</div>
<?php else: ?>

<!-- 表格模式 -->
<div class="card" id="viewTable">
    <div class="table-responsive">
        <table class="table" style="font-size:.85rem">
            <thead>
                <tr>
                    <th>產品名稱</th>
                    <th>型號</th>
                    <th>主分類</th>
                    <th>子分類</th>
                    <th>細分類</th>
                    <th>品牌</th>
                    <th>單位</th>
                    <th style="text-align:right">定價</th>
                    <th style="text-align:right">成本</th>
                    <th style="text-align:right">庫存</th>
                    <th style="text-align:right">可用</th>
                    <?php if ($canManage): ?><th>操作</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $p):
                    // 判斷分類層級
                    $mainCat = '';
                    $subCat = '';
                    $detailCat = '';
                    if (!empty($p['cat_grandparent_name'])) {
                        // 三層：grandparent > parent > current
                        $mainCat = $p['cat_grandparent_name'];
                        $subCat = $p['cat_parent_name'];
                        $detailCat = $p['category_name'];
                    } elseif (!empty($p['cat_parent_name'])) {
                        // 兩層：parent > current
                        $mainCat = $p['cat_parent_name'];
                        $subCat = $p['category_name'];
                    } elseif (!empty($p['category_name'])) {
                        // 一層：current
                        $mainCat = $p['category_name'];
                    }
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <?php if ($canManage): ?>
                                <?php $starred = !empty($p['is_starred']); ?>
                                <span class="star-toggle <?= $starred ? 'is-on' : '' ?>" data-id="<?= (int)$p['id'] ?>" onclick="toggleProductStar(this, event)" title="<?= $starred ? '取消星標' : '加入星標' ?>">
                                    <?= $starred ? '★' : '☆' ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($p['image'])): ?>
                            <img src="<?= e($p['image']) ?>" alt="" style="width:36px;height:36px;object-fit:contain;border-radius:4px;border:1px solid #eee;flex-shrink:0" onerror="this.style.display='none'">
                            <?php else: ?>
                            <div style="width:36px;height:36px;background:#f5f5f5;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:.8rem;opacity:.3;flex-shrink:0">📦</div>
                            <?php endif; ?>
                            <a href="/products.php?action=view&id=<?= $p['id'] ?>" style="font-weight:600<?= empty($p['is_active']) ? ';opacity:.5' : '' ?>"><?= e($p['name']) ?></a>
                            <?php if (empty($p['is_active'])): ?><span class="badge" style="background:var(--gray-200);color:var(--gray-500);font-size:.65rem;margin-left:4px">停用</span><?php endif; ?>
                            <?php if (!empty($p['discontinue_when_empty'])): ?><span class="badge" style="background:#ffebee;color:#c5221f;font-size:.65rem;margin-left:4px" title="庫存用完不再進貨">⚠ 不再進貨</span><?php endif; ?>
                        </div>
                    </td>
                    <td style="color:var(--gray-500)"><?= e($p['model'] ?? '-') ?></td>
                    <td><span class="badge" style="background:#e3f2fd;color:#1565c0;font-size:.75rem"><?= e($mainCat ?: '-') ?></span></td>
                    <td style="font-size:.8rem"><?= e($subCat ?: '-') ?></td>
                    <td style="font-size:.8rem"><?= e($detailCat ?: '-') ?></td>
                    <td style="font-size:.8rem"><?= e($p['brand'] ?? '-') ?></td>
                    <td><?= e($p['unit'] ?? '-') ?></td>
                    <td style="text-align:right;font-weight:600">$<?= number_format((float)($p['price'] ?? 0)) ?></td>
                    <td style="text-align:right;color:var(--gray-500)">$<?= number_format((float)($p['cost'] ?? 0)) ?></td>
                    <?php $pStock = (int)($p['total_stock'] ?? 0); $pAvail = (int)($p['total_available'] ?? 0); ?>
                    <td style="text-align:right"><span style="color:<?= $pStock > 0 ? 'var(--success)' : 'var(--gray-400)' ?>;font-weight:<?= $pStock > 0 ? '600' : 'normal' ?>"><?= $pStock ?></span></td>
                    <td style="text-align:right"><span style="color:<?= $pAvail > 0 ? 'var(--success)' : 'var(--gray-400)' ?>"><?= $pAvail ?></span></td>
                    <?php if ($canManage): ?>
                    <td>
                        <a href="/products.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">編輯</a>
                        <?php if (empty($p['is_active'])): ?>
                        <a href="/products.php?action=delete&id=<?= $p['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                           class="btn btn-sm btn-danger" onclick="return confirm('確定刪除「<?= e($p['name']) ?>」？此操作無法復原！')">刪除</a>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 卡片模式 -->
<div class="product-grid" id="viewCard" style="display:none">
    <?php foreach ($result['data'] as $p): ?>
    <a href="/products.php?action=view&id=<?= $p['id'] ?>" class="product-card">
        <?php if ($canManage): ?>
            <?php $starred = !empty($p['is_starred']); ?>
            <span class="star-toggle star-card <?= $starred ? 'is-on' : '' ?>" data-id="<?= (int)$p['id'] ?>" onclick="toggleProductStar(this, event)" title="<?= $starred ? '取消星標' : '加入星標' ?>">
                <?= $starred ? '★' : '☆' ?>
            </span>
        <?php endif; ?>
        <div class="product-img">
            <?php if ($p['image']): ?>
            <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy" onerror="this.parentElement.innerHTML='<div class=\'product-img-placeholder\'>📦</div>'">
            <?php else: ?>
            <div class="product-img-placeholder">📦</div>
            <?php endif; ?>
        </div>
        <div class="product-info">
            <div class="product-name"><?= e($p['name']) ?></div>
            <?php if ($p['model']): ?>
            <div class="product-model"><?= e($p['model']) ?></div>
            <?php endif; ?>
            <div class="product-price">$<?= number_format((float)$p['price']) ?></div>
            <?php $pStock2 = (int)($p['total_stock'] ?? 0); $pAvail2 = (int)($p['total_available'] ?? 0); ?>
            <div class="product-stock" style="font-size:.75rem;color:<?= $pStock2 > 0 ? 'var(--success)' : 'var(--gray-400)' ?>">庫存: <?= $pStock2 ?> | 可用: <?= $pAvail2 ?></div>
            <?php if ($p['category_name']): ?>
            <div class="product-cat"><?= e($p['category_name']) ?></div>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- 分頁 -->
<?php if ($result['lastPage'] > 1): ?>
<div class="pagination mt-2">
    <?php
    $lastPage = $result['lastPage'];
    $currentP = $result['page'];
    $start = max(1, $currentP - 2);
    $end = min($lastPage, $currentP + 2);
    ?>
    <?php if ($currentP > 1): ?>
    <?php $qs = $_GET; $qs['page'] = $currentP - 1; ?>
    <a href="/products.php?<?= http_build_query($qs) ?>" class="btn btn-sm btn-outline">&laquo;</a>
    <?php endif; ?>
    <?php if ($start > 1): ?>
    <?php $qs = $_GET; $qs['page'] = 1; ?>
    <a href="/products.php?<?= http_build_query($qs) ?>" class="btn btn-sm btn-outline">1</a>
    <?php if ($start > 2): ?><span class="pagination-dots">…</span><?php endif; ?>
    <?php endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
    <?php $qs = $_GET; $qs['page'] = $i; ?>
    <a href="/products.php?<?= http_build_query($qs) ?>" class="btn btn-sm <?= $i === $currentP ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($end < $lastPage): ?>
    <?php if ($end < $lastPage - 1): ?><span class="pagination-dots">…</span><?php endif; ?>
    <?php $qs = $_GET; $qs['page'] = $lastPage; ?>
    <a href="/products.php?<?= http_build_query($qs) ?>" class="btn btn-sm btn-outline"><?= $lastPage ?></a>
    <?php endif; ?>
    <?php if ($currentP < $lastPage): ?>
    <?php $qs = $_GET; $qs['page'] = $currentP + 1; ?>
    <a href="/products.php?<?= http_build_query($qs) ?>" class="btn btn-sm btn-outline">&raquo;</a>
    <?php endif; ?>
    <span style="margin-left:8px;display:inline-flex;align-items:center;gap:4px;font-size:.85rem">
        前往
        <input type="number" id="goPageInput" min="1" max="<?= $lastPage ?>" value="<?= $currentP ?>"
               style="width:60px;padding:3px 6px;border:1px solid var(--gray-300);border-radius:4px;text-align:center;font-size:.85rem"
               onkeydown="if(event.key==='Enter'){goToPage();}">
        / <?= $lastPage ?> 頁
        <button type="button" class="btn btn-sm btn-outline" onclick="goToPage()" style="padding:3px 8px">Go</button>
    </span>
</div>
<script>
function goToPage() {
    var input = document.getElementById('goPageInput');
    var p = parseInt(input.value);
    if (isNaN(p) || p < 1) p = 1;
    if (p > <?= $lastPage ?>) p = <?= $lastPage ?>;
    var qs = new URLSearchParams(window.location.search);
    qs.set('page', p);
    window.location.href = '/products.php?' + qs.toString();
}
</script>
<?php endif; ?>
<?php endif; ?>

<style>
.filter-row { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; }
.product-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; }
.product-card { background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.1); overflow:hidden; text-decoration:none; color:inherit; transition:box-shadow .2s, transform .15s; display:flex; flex-direction:column; }
.product-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.15); transform:translateY(-2px); }
.product-img { width:100%; aspect-ratio:1; background:#f8f9fa; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.product-img img { width:100%; height:100%; object-fit:contain; padding:8px; }
.product-img-placeholder { font-size:3rem; opacity:.3; }
.product-info { padding:10px 12px 12px; flex:1; display:flex; flex-direction:column; }
.product-name { font-size:.85rem; font-weight:600; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; margin-bottom:4px; }
.product-model { font-size:.75rem; color:var(--gray-500); margin-bottom:6px; }
.product-price { font-size:1rem; font-weight:700; color:var(--primary); margin-top:auto; }
.product-cat { font-size:.7rem; color:var(--gray-400); margin-top:4px; }
.pagination { display:flex; gap:4px; justify-content:center; flex-wrap:wrap; }
.pagination-dots { padding:4px 8px; color:var(--gray-400); }
.cat-select { min-width:130px; }
.star-toggle { cursor:pointer; font-size:1.2rem; line-height:1; color:var(--gray-300); user-select:none; transition:color .15s, transform .15s; flex-shrink:0; }
.star-toggle:hover { color:#f59e0b; transform:scale(1.15); }
.star-toggle.is-on { color:#f59e0b; }
.product-card { position:relative; }
.star-card { position:absolute; top:6px; right:8px; z-index:2; background:rgba(255,255,255,.9); border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 3px rgba(0,0,0,.12); font-size:1rem; }
@media (max-width:991px) { .product-grid { grid-template-columns:repeat(3, 1fr); } }
@media (max-width:767px) { .product-grid { grid-template-columns:repeat(2, 1fr); } }
@media (max-width:480px) { .product-grid { grid-template-columns:1fr; } }
</style>

<script>
// 動態分類篩選
var catCache = {};
function loadSubcategories(parentId, callback) {
    var key = 'cat_' + parentId;
    if (catCache[key]) { callback(catCache[key]); return; }
    fetch('/products.php?action=ajax_subcategories&parent_id=' + parentId)
        .then(function(r) { return r.json(); })
        .then(function(data) { catCache[key] = data; callback(data); });
}

function onCatFilterChange(sel) {
    var level = parseInt(sel.getAttribute('data-level'));
    var val = sel.value;
    var row = document.getElementById('catFilterRow');

    // 移除此層之後的所有 select
    var selects = row.querySelectorAll('.cat-select');
    for (var i = selects.length - 1; i > level; i--) {
        selects[i].parentElement.remove();
    }

    // 更新 hidden value
    document.getElementById('catFilterValue').value = val || '';

    if (!val) return;

    // 查有沒有子分類
    loadSubcategories(val, function(subs) {
        if (subs.length === 0) return; // 沒有子分類，就用當前分類篩選

        // 有子分類，新增下一層 select
        var div = document.createElement('div');
        div.className = 'form-group';
        div.style.cssText = 'flex:1;min-width:140px';
        var newSel = document.createElement('select');
        newSel.className = 'form-control cat-select';
        newSel.setAttribute('data-level', level + 1);
        newSel.setAttribute('onchange', 'onCatFilterChange(this)');

        var labels = ['全部子分類', '全部細分類', '全部'];
        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = labels[level] || '全部';
        newSel.appendChild(opt0);

        subs.forEach(function(s) {
            var opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            newSel.appendChild(opt);
        });

        div.appendChild(newSel);
        row.appendChild(div);
    });
}

// 頁面載入時初始化主分類
document.addEventListener('DOMContentLoaded', function() {
    loadSubcategories(0, function(cats) {
        var sel = document.querySelector('.cat-select[data-level="0"]');
        cats.forEach(function(c) {
            var opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            sel.appendChild(opt);
        });

        // 如果有預設 category_id，回溯選中
        var presetCat = <?= (int)$filters['category_id'] ?>;
        if (presetCat > 0) {
            // 簡單方式：直接選中（不回溯多層）
            sel.value = presetCat;
            if (!sel.value) {
                // 可能是子分類，先選主分類
                // 從 server 回傳的分類路徑處理
            }
        }
    });
});

<?php if ($canManage): ?>
// 切換產品星標（管理權限）
function toggleProductStar(el, ev) {
    if (ev) { ev.preventDefault(); ev.stopPropagation(); }
    if (el.dataset.busy === '1') return;
    el.dataset.busy = '1';
    var pid = el.getAttribute('data-id');
    var token = document.getElementById('productStarCsrf').value;
    var fd = new FormData();
    fd.append('id', pid);
    fd.append('csrf_token', token);
    fetch('/products.php?action=ajax_toggle_star', { method:'POST', body: fd, credentials:'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            el.dataset.busy = '0';
            if (!d.success) { alert('切換失敗：' + (d.error || '未知錯誤')); return; }
            if (d.is_starred) {
                el.textContent = '★';
                el.classList.add('is-on');
                el.title = '取消星標';
            } else {
                el.textContent = '☆';
                el.classList.remove('is-on');
                el.title = '加入星標';
            }
        })
        .catch(function(err) {
            el.dataset.busy = '0';
            alert('網路錯誤：' + err);
        });
}
<?php endif; ?>

// 切換顯示模式
function toggleView(mode) {
    var table = document.getElementById('viewTable');
    var card = document.getElementById('viewCard');
    var btnT = document.getElementById('btnTable');
    var btnC = document.getElementById('btnCard');
    if (mode === 'table') {
        table.style.display = '';
        card.style.display = 'none';
        btnT.className = 'btn btn-sm btn-primary';
        btnC.className = 'btn btn-sm btn-outline';
    } else {
        table.style.display = 'none';
        card.style.display = '';
        btnT.className = 'btn btn-sm btn-outline';
        btnC.className = 'btn btn-sm btn-primary';
    }
    localStorage.setItem('productView', mode);
}
// 記住上次選擇
if (localStorage.getItem('productView') === 'card') { toggleView('card'); }
</script>
