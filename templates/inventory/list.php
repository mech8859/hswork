<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>庫存管理</h2>
    <div class="d-flex gap-1 flex-wrap">
        <span class="text-muted" style="align-self:center"><?= isset($totalRecords) ? number_format($totalRecords) : count($records) ?> 筆</span>
        <span style="align-self:center;font-weight:600;color:var(--primary)">合計金額：$<?= number_format(!empty($grandTotal['total_cost']) ? $grandTotal['total_cost'] : 0) ?></span>
        <a href="/inventory.php?action=transactions" class="btn btn-outline btn-sm">異動記錄</a>
        <a href="/inventory.php?action=stocktake_list" class="btn btn-outline btn-sm">盤點管理</a>
        <?php if ($lowStockCount > 0): ?>
        <a href="/inventory.php?action=low_stock" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger)">低庫存警示 (<?= $lowStockCount ?>)</a>
        <?php endif; ?>
        <div class="dropdown" style="display:inline-block;position:relative">
            <button type="button" class="btn btn-outline btn-sm" onclick="this.nextElementSibling.classList.toggle('show')">更多 ▾</button>
            <div class="dropdown-menu">
                <a href="/inventory.php?action=export&<?= http_build_query(array_filter($filters)) ?>" class="dropdown-item">匯出 CSV</a>
                <?php if ($canManage): ?>
                <a href="/inventory.php?action=warehouses" class="dropdown-item">倉庫管理</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($summary)): ?>
<div class="inventory-summary-row">
    <?php foreach ($summary as $s): ?>
    <div class="card inventory-summary-card">
        <div class="inventory-summary-title"><?= e(!empty($s['warehouse_name']) ? $s['warehouse_name'] : '-') ?></div>
        <div class="inventory-summary-nums">
            <div>
                <span class="inventory-summary-label">庫存</span>
                <span class="inventory-summary-value"><?= number_format(isset($s['total_stock_qty']) ? $s['total_stock_qty'] : 0) ?></span>
            </div>
            <div>
                <span class="inventory-summary-label">可用</span>
                <span class="inventory-summary-value" style="color:var(--success)"><?= number_format(isset($s['total_available_qty']) ? $s['total_available_qty'] : 0) ?></span>
            </div>
            <div>
                <span class="inventory-summary-label">品項</span>
                <span class="inventory-summary-value"><?= number_format(!empty($s['product_count']) ? $s['product_count'] : 0) ?></span>
            </div>
        </div>
        <div style="border-top:1px solid var(--gray-200);margin-top:8px;padding-top:8px;text-align:center">
            <span class="inventory-summary-label">總金額</span>
            <span class="inventory-summary-value" style="color:var(--primary)">$<?= number_format(!empty($s['total_cost_value']) ? $s['total_cost_value'] : 0) ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <form method="GET" action="/inventory.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>倉庫</label>
                <select name="warehouse_id" class="form-control">
                    <option value="">全部</option>
                    <?php if (!empty($warehouses)): ?>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= e($w['id']) ?>" <?= (!empty($filters['warehouse_id']) && $filters['warehouse_id'] == $w['id']) ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group" id="catFilterRow" style="display:flex;gap:6px;flex-wrap:wrap;flex:2">
                <label style="width:100%">分類</label>
                <input type="hidden" name="category_id" id="catFilterValue" value="<?= e(!empty($filters['category_id']) ? $filters['category_id'] : '') ?>">
                <select class="form-control cat-select" data-level="0" onchange="onInvCatChange(this)" style="flex:1;min-width:120px">
                    <option value="">全部主分類</option>
                </select>
            </div>
            <div class="form-group" style="position:relative">
                <label>關鍵字</label>
                <input type="text" name="keyword" id="invKeyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="名稱/型號/品牌/供應" autocomplete="off" oninput="invSearchSuggest(this)">
                <div id="invSuggestDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:250px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="has_stock" value="1" <?= (!empty($filters['has_stock'])) ? 'checked' : '' ?>>
                    僅顯示有庫存
                </label>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="low_stock" value="1" <?= (!empty($filters['low_stock'])) ? 'checked' : '' ?>>
                    低於安全庫存
                </label>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/inventory.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無資料</p>
    <?php else: ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <?php $isLow = (!empty($r['min_qty']) && $r['min_qty'] > 0 && (int)$r['stock_qty'] <= (int)$r['min_qty']); ?>
        <div class="staff-card" style="<?= $isLow ? 'border-left:3px solid var(--danger)' : '' ?>">
            <div class="d-flex justify-between align-center">
                <a href="/products.php?action=view&id=<?= e($r['product_id']) ?>" style="font-weight:600"><?= e(!empty($r['product_name']) ? $r['product_name'] : '-') ?></a>
                <a href="/inventory.php?action=view&product_id=<?= e($r['product_id']) ?>" class="btn btn-outline btn-sm">庫存明細</a>
            </div>
            <?php if (!empty($r['product_model'])): ?>
            <div style="font-size:.85rem;color:var(--gray-500)"><?= e($r['product_model']) ?></div>
            <?php endif; ?>
            <div class="staff-card-meta">
                <span><?= e(!empty($r['warehouse_name']) ? $r['warehouse_name'] : '-') ?></span>
                <span>庫存 <strong style="color:<?= ($isLow ? 'var(--danger)' : ((!empty($r['stock_qty']) && $r['stock_qty'] > 0) ? 'var(--success)' : 'var(--gray-400)')) ?>"><?= (int)(!empty($r['stock_qty']) ? $r['stock_qty'] : 0) ?></strong></span>
                <span>可用 <strong style="color:<?= (!empty($r['available_qty']) && $r['available_qty'] > 0) ? 'var(--success)' : 'var(--gray-400)' ?>"><?= (int)(!empty($r['available_qty']) ? $r['available_qty'] : 0) ?></strong></span>
                <?php if ($isLow): ?>
                <span style="color:var(--danger);font-size:.75rem">⚠ 低庫存</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>主分類</th>
                    <th>子分類</th>
                    <th>細分類</th>
                    <th>商品名稱</th>
                    <th>型號</th>
                    <th>倉庫</th>
                    <th class="text-right">可用</th>
                    <th class="text-right">庫存</th>
                    <th class="text-right">安全庫存</th>
                    <th class="text-right">預扣</th>
                    <th class="text-right">已備貨</th>
                    <th class="text-right">借出</th>
                    <th class="text-right">展示</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <?php
                    $invMainCat = '';
                    $invSubCat = '';
                    $invDetailCat = '';
                    if (!empty($r['cat_grandparent_name'])) {
                        $invMainCat = $r['cat_grandparent_name'];
                        $invSubCat = $r['cat_parent_name'];
                        $invDetailCat = $r['category_name'];
                    } elseif (!empty($r['cat_parent_name'])) {
                        $invMainCat = $r['cat_parent_name'];
                        $invSubCat = $r['category_name'];
                    } elseif (!empty($r['category_name'])) {
                        $invMainCat = $r['category_name'];
                    }
                    $isLow = (!empty($r['min_qty']) && $r['min_qty'] > 0 && (int)$r['stock_qty'] <= (int)$r['min_qty']);
                ?>
                <tr style="<?= $isLow ? 'background:#fff5f5' : '' ?>">
                    <td><span class="badge" style="background:#e3f2fd;color:#1565c0;font-size:.75rem"><?= e($invMainCat ?: '-') ?></span></td>
                    <td style="font-size:.8rem"><?= e($invSubCat ?: '-') ?></td>
                    <td style="font-size:.8rem"><?= e($invDetailCat ?: '-') ?></td>
                    <td>
                        <a href="/products.php?action=view&id=<?= e($r['product_id']) ?>" style="font-weight:600"><?= e(!empty($r['product_name']) ? $r['product_name'] : '-') ?></a>
                        <?php if ($isLow): ?>
                        <span style="color:var(--danger);font-size:.7rem;font-weight:600"> ⚠</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.85rem"><?= e(!empty($r['product_model']) ? $r['product_model'] : '-') ?></td>
                    <td><?= e(!empty($r['warehouse_name']) ? $r['warehouse_name'] : '-') ?></td>
                    <td class="text-right"><span style="color:<?= (!empty($r['available_qty']) && $r['available_qty'] > 0) ? 'var(--success)' : 'var(--gray-400)' ?>"><?= (int)(!empty($r['available_qty']) ? $r['available_qty'] : 0) ?></span></td>
                    <td class="text-right"><span style="color:<?= $isLow ? 'var(--danger)' : ((!empty($r['stock_qty']) && $r['stock_qty'] > 0) ? 'var(--success)' : 'var(--gray-400)') ?>;font-weight:<?= $isLow ? '700' : 'normal' ?>"><?= (int)(!empty($r['stock_qty']) ? $r['stock_qty'] : 0) ?></span></td>
                    <td class="text-right"><?= (int)(!empty($r['min_qty']) ? $r['min_qty'] : 0) ?></td>
                    <td class="text-right"><?= (int)(!empty($r['reserved_qty']) ? $r['reserved_qty'] : 0) ?></td>
                    <td class="text-right"><?= (int)(!empty($r['prepared_qty']) ? $r['prepared_qty'] : 0) ?></td>
                    <td class="text-right"><?= (int)(!empty($r['loaned_qty']) ? $r['loaned_qty'] : 0) ?></td>
                    <td class="text-right"><?= (int)(!empty($r['display_qty']) ? $r['display_qty'] : 0) ?></td>
                    <td><a href="/inventory.php?action=view&product_id=<?= e($r['product_id']) ?>" class="btn btn-outline btn-sm">明細</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (isset($totalPages) && $totalPages > 1): ?>
    <div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:16px;flex-wrap:wrap">
        <?php
        $queryParams = $_GET;
        unset($queryParams['page']);
        $baseUrl = '/inventory.php?' . http_build_query($queryParams);
        ?>
        <?php if ($currentPageNum > 1): ?>
        <a href="<?= $baseUrl ?>&page=1" class="btn btn-outline btn-sm">&laquo; 首頁</a>
        <a href="<?= $baseUrl ?>&page=<?= $currentPageNum - 1 ?>" class="btn btn-outline btn-sm">&lsaquo; 上一頁</a>
        <?php endif; ?>

        <?php
        $startP = max(1, $currentPageNum - 3);
        $endP = min($totalPages, $currentPageNum + 3);
        for ($p = $startP; $p <= $endP; $p++):
        ?>
        <a href="<?= $baseUrl ?>&page=<?= $p ?>" class="btn btn-sm <?= $p == $currentPageNum ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($currentPageNum < $totalPages): ?>
        <a href="<?= $baseUrl ?>&page=<?= $currentPageNum + 1 ?>" class="btn btn-outline btn-sm">下一頁 &rsaquo;</a>
        <a href="<?= $baseUrl ?>&page=<?= $totalPages ?>" class="btn btn-outline btn-sm">末頁 &raquo;</a>
        <?php endif; ?>
        <span style="font-size:.85rem;color:var(--gray-500)">第 <?= $currentPageNum ?>/<?= $totalPages ?> 頁，共 <?= number_format($totalRecords) ?> 筆</span>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.checkbox-label { display: flex; align-items: center; gap: 6px; cursor: pointer; white-space: nowrap; padding-top: 24px; }
.inventory-summary-row { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.inventory-summary-card { flex: 1; min-width: 180px; padding: 16px; }
.inventory-summary-title { font-weight: 600; font-size: .95rem; margin-bottom: 8px; }
.inventory-summary-nums { display: flex; gap: 16px; }
.inventory-summary-label { font-size: .75rem; color: var(--gray-500); display: block; }
.inventory-summary-value { font-size: 1.1rem; font-weight: 600; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 600; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
.cat-select { min-width:120px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: 100%; background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius); min-width: 140px; box-shadow: 0 4px 12px rgba(0,0,0,.1); z-index: 100; }
.dropdown-menu.show { display: block; }
.dropdown-item { display: block; padding: 8px 16px; color: var(--gray-700); text-decoration: none; white-space: nowrap; }
.dropdown-item:hover { background: var(--gray-50); }
</style>

<script>
var invCatCache = {};
function loadInvSubs(parentId, cb) {
    var key = 'ic_' + parentId;
    if (invCatCache[key]) { cb(invCatCache[key]); return; }
    fetch('/products.php?action=ajax_subcategories&parent_id=' + parentId)
        .then(function(r) { return r.json(); })
        .then(function(d) { invCatCache[key] = d; cb(d); });
}

function onInvCatChange(sel) {
    var level = parseInt(sel.getAttribute('data-level'));
    var val = sel.value;
    var row = document.getElementById('catFilterRow');
    var selects = row.querySelectorAll('.cat-select');
    for (var i = selects.length - 1; i > level; i--) { selects[i].remove(); }
    document.getElementById('catFilterValue').value = val || '';
    if (!val) return;
    loadInvSubs(val, function(subs) {
        if (subs.length === 0) return;
        var newSel = document.createElement('select');
        newSel.className = 'form-control cat-select';
        newSel.setAttribute('data-level', level + 1);
        newSel.setAttribute('onchange', 'onInvCatChange(this)');
        newSel.style.cssText = 'flex:1;min-width:120px';
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
        row.appendChild(newSel);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    loadInvSubs(0, function(cats) {
        var sel = document.querySelector('.cat-select[data-level="0"]');
        cats.forEach(function(c) {
            var opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            sel.appendChild(opt);
        });
    });
});

// 點擊外部關閉 dropdown
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(function(el) { el.classList.remove('show'); });
    }
});

// ===== 關鍵字即時搜尋建議 =====
var invSuggestTimer = null;
function invSearchSuggest(inp) {
    clearTimeout(invSuggestTimer);
    var q = inp.value.trim();
    var dd = document.getElementById('invSuggestDropdown');
    if (q.length < 1) { dd.style.display = 'none'; return; }
    invSuggestTimer = setTimeout(function(){
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/inventory.php?action=ajax_search_products&q=' + encodeURIComponent(q));
        xhr.onload = function(){
            try { var list = JSON.parse(xhr.responseText); } catch(e) { return; }
            if (!list.length) { dd.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合產品</div>'; dd.style.display = 'block'; return; }
            var html = '';
            for (var i = 0; i < list.length && i < 15; i++) {
                var p = list[i];
                html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" '
                    + 'data-name="' + (p.name||'').replace(/"/g,'&quot;') + '" '
                    + 'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">'
                    + '<div style="font-weight:600">' + (p.name||'') + '</div>'
                    + '<div style="font-size:.75rem;color:#888">'
                    + (p.model ? '<span style="color:#1565c0">' + p.model + '</span> | ' : '')
                    + (p.category_name ? p.category_name + ' | ' : '')
                    + '$' + Number(p.cost||p.price||0).toLocaleString()
                    + ' | <span style="color:' + (Number(p.stock||0) > 0 ? '#2e7d32' : '#c62828') + '">庫存:' + Number(p.stock||0) + '</span>'
                    + '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
        };
        xhr.send();
    }, 300);
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('invSuggestDropdown');
    var item = e.target.closest('#invSuggestDropdown > div[data-name]');
    if (item) {
        document.getElementById('invKeyword').value = item.dataset.name;
        dd.style.display = 'none';
        document.getElementById('invKeyword').closest('form').submit();
        return;
    }
    if (e.target.id !== 'invKeyword') dd.style.display = 'none';
});
</script>
