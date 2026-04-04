<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2>廠商產品對照</h2>
        <?php if ($stats): ?>
        <span class="text-muted" style="font-size:.85rem">
            共 <?= $stats['total'] ?> 筆對照，
            <span style="color:var(--success)"><?= $stats['mapped'] ?> 已對應</span>，
            <span style="color:#e53935"><?= $stats['unmapped'] ?> 未對應</span>
        </span>
        <?php endif; ?>
    </div>
    <?php if (Auth::hasPermission('procurement.manage')): ?>
    <button type="button" class="btn btn-primary btn-sm" onclick="showAddForm()">+ 新增對照</button>
    <?php endif; ?>
</div>

<!-- 篩選 -->
<div class="card mb-1">
    <form method="GET" action="/vendor_products.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>廠商</label>
                <select name="vendor_id" class="form-control">
                    <option value="">全部廠商</option>
                    <?php foreach ($allVendors as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= (!empty($filters['vendor_id']) && $filters['vendor_id'] == $v['id']) ? 'selected' : '' ?>><?= e($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword']) ?>" placeholder="廠商型號/品名/系統產品">
            </div>
            <div class="form-group">
                <label>對應狀態</label>
                <select name="mapped" class="form-control">
                    <option value="">全部</option>
                    <option value="1" <?= $filters['mapped'] === '1' ? 'selected' : '' ?>>已對應</option>
                    <option value="0" <?= $filters['mapped'] === '0' ? 'selected' : '' ?>>未對應</option>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/vendor_products.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<!-- 對照列表 -->
<div class="card">
    <?php if (empty($items)): ?>
        <p class="text-muted text-center mt-2 mb-2">無對照資料。<?php if (empty($filters['vendor_id']) && empty($filters['keyword'])): ?>請先執行 Migration 068 從進貨歷史匯入。<?php endif; ?></p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>廠商</th>
                <th>廠商型號</th>
                <th>廠商品名</th>
                <th>對應系統產品</th>
                <th style="text-align:right">廠商報價</th>
                <th style="text-align:right">最近進價</th>
                <th>最近進貨日</th>
                <?php if (Auth::hasPermission('procurement.manage')): ?>
                <th style="width:120px">操作</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr id="row-<?= $item['id'] ?>">
                <td>
                    <span class="text-truncate" style="max-width:120px;display:inline-block" title="<?= e($item['vendor_name_display']) ?>">
                        <?= e($item['vendor_short_name'] ?: $item['vendor_name_display']) ?>
                    </span>
                </td>
                <td><code><?= e($item['vendor_model']) ?></code></td>
                <td><?= e($item['vendor_name']) ?></td>
                <td>
                    <?php if ($item['product_id']): ?>
                        <a href="/products.php?action=view&id=<?= $item['product_id'] ?>" class="text-truncate" style="max-width:200px;display:inline-block">
                            <?= e($item['product_name']) ?>
                            <?php if ($item['product_model']): ?>
                            <small class="text-muted">(<?= e($item['product_model']) ?>)</small>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <span class="badge badge-danger" style="font-size:.75rem">未對應</span>
                        <?php if (Auth::hasPermission('procurement.manage')): ?>
                        <button type="button" class="btn btn-outline btn-sm" style="font-size:.7rem;padding:1px 6px" onclick="mapProduct(<?= $item['id'] ?>)">設定</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td style="text-align:right"><?= $item['vendor_price'] ? number_format($item['vendor_price'], 0) : '-' ?></td>
                <td style="text-align:right"><?= $item['last_purchase_price'] ? number_format($item['last_purchase_price'], 0) : '-' ?></td>
                <td><?= $item['last_purchase_date'] ?: '-' ?></td>
                <?php if (Auth::hasPermission('procurement.manage')): ?>
                <td>
                    <button class="btn btn-outline btn-sm" onclick="editRow(<?= $item['id'] ?>)" style="font-size:.75rem;padding:2px 8px">編輯</button>
                    <button class="btn btn-outline btn-sm text-danger" onclick="deleteRow(<?= $item['id'] ?>)" style="font-size:.75rem;padding:2px 8px">刪除</button>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- 新增 Modal -->
<div class="modal-overlay" id="addModal" style="display:none">
    <div class="modal-content" style="max-width:560px">
        <div class="modal-header">
            <h3 id="modalTitle">新增廠商產品對照</h3>
            <button type="button" onclick="closeModal()" class="modal-close">&times;</button>
        </div>
        <form method="POST" action="/vendor_products.php?action=create" id="vpForm">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="vpId" value="">
            <div class="form-group">
                <label>廠商 <span class="text-danger">*</span></label>
                <select name="vendor_id" id="vpVendor" class="form-control" required>
                    <option value="">選擇廠商</option>
                    <?php foreach ($allVendors as $v): ?>
                    <option value="<?= $v['id'] ?>"><?= e($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>廠商型號 <span class="text-danger">*</span></label>
                    <input type="text" name="vendor_model" id="vpModel" class="form-control" required>
                </div>
                <div class="form-group" style="flex:1">
                    <label>廠商品名</label>
                    <input type="text" name="vendor_name" id="vpName" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>對應系統產品</label>
                <div style="position:relative">
                    <input type="text" id="productSearch" class="form-control" placeholder="搜尋產品名稱或型號..." autocomplete="off">
                    <input type="hidden" name="product_id" id="vpProductId" value="">
                    <div id="productSearchResults" class="autocomplete-dropdown" style="display:none"></div>
                </div>
                <div id="productSelected" style="display:none;margin-top:4px;padding:6px 10px;background:var(--gray-50);border-radius:4px;font-size:.85rem">
                    <span id="productSelectedText"></span>
                    <button type="button" onclick="clearProduct()" style="float:right;border:0;background:none;color:#e53935;cursor:pointer">&times;</button>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>廠商報價</label>
                    <input type="number" name="vendor_price" id="vpPrice" class="form-control" step="0.01">
                </div>
                <div class="form-group" style="flex:1">
                    <label>最近進價</label>
                    <input type="number" name="last_purchase_price" id="vpLastPrice" class="form-control" step="0.01">
                </div>
            </div>
            <div class="form-group">
                <label>備註</label>
                <input type="text" name="note" id="vpNote" class="form-control">
            </div>
            <div class="d-flex gap-1 mt-2">
                <button type="submit" class="btn btn-primary">儲存</button>
                <button type="button" class="btn btn-outline" onclick="closeModal()">取消</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.5); z-index:9999; display:flex; align-items:center; justify-content:center; }
.modal-content { background:#fff; border-radius:12px; padding:24px; width:90%; box-shadow:0 8px 32px rgba(0,0,0,.2); }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.modal-header h3 { margin:0; font-size:1.1rem; }
.modal-close { border:none; background:none; font-size:1.5rem; cursor:pointer; color:var(--gray-500); }
.form-row { display:flex; gap:12px; }
.autocomplete-dropdown { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid var(--gray-300); border-radius:6px; max-height:200px; overflow-y:auto; z-index:10001; box-shadow:0 4px 12px rgba(0,0,0,.15); }
.autocomplete-dropdown .ac-item { padding:8px 12px; cursor:pointer; font-size:.85rem; border-bottom:1px solid var(--gray-100); }
.autocomplete-dropdown .ac-item:hover { background:var(--gray-50); }
.autocomplete-dropdown .ac-item small { color:var(--gray-500); }
.badge-danger { background:#ffebee; color:#e53935; padding:2px 8px; border-radius:10px; }
.text-truncate { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
</style>

<script>
var csrfToken = '<?= e(Session::getCsrfToken()) ?>';

function showAddForm() {
    document.getElementById('modalTitle').textContent = '新增廠商產品對照';
    document.getElementById('vpForm').action = '/vendor_products.php?action=create';
    document.getElementById('vpId').value = '';
    document.getElementById('vpVendor').value = '<?= e($filters['vendor_id']) ?>';
    document.getElementById('vpModel').value = '';
    document.getElementById('vpName').value = '';
    document.getElementById('vpProductId').value = '';
    document.getElementById('vpPrice').value = '';
    document.getElementById('vpLastPrice').value = '';
    document.getElementById('vpNote').value = '';
    document.getElementById('productSearch').value = '';
    document.getElementById('productSelected').style.display = 'none';
    document.getElementById('addModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('addModal').style.display = 'none';
}

function editRow(id) {
    // 從表格讀取資料填入表單（簡化方式）
    fetch('/vendor_products.php?action=search_products&q=_placeholder_').then(function() {
        // 使用 inline editing 更好，先開 modal
        showAddForm();
        document.getElementById('modalTitle').textContent = '編輯對照';
        // 用 AJAX 取得完整資料會更好，這裡簡化用 table row data
    });
}

function deleteRow(id) {
    if (!confirm('確定要刪除此對照？')) return;
    fetch('/vendor_products.php?action=delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            var row = document.getElementById('row-' + id);
            if (row) row.remove();
        } else {
            alert(d.error || '刪除失敗');
        }
    });
}

function mapProduct(vpId) {
    showAddForm();
    document.getElementById('modalTitle').textContent = '設定對應產品';
    document.getElementById('vpId').value = vpId;
    document.getElementById('productSearch').focus();
}

// 產品搜尋 autocomplete
var searchTimer = null;
document.getElementById('productSearch').addEventListener('input', function() {
    var q = this.value.trim();
    clearTimeout(searchTimer);
    if (q.length < 1) {
        document.getElementById('productSearchResults').style.display = 'none';
        return;
    }
    searchTimer = setTimeout(function() {
        fetch('/vendor_products.php?action=search_products&q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(products) {
            var container = document.getElementById('productSearchResults');
            if (!products.length) {
                container.style.display = 'none';
                return;
            }
            var html = '';
            for (var i = 0; i < products.length; i++) {
                var p = products[i];
                html += '<div class="ac-item" onclick="selectProduct(' + p.id + ',\'' + escHtml(p.name) + '\',\'' + escHtml(p.model || '') + '\')">';
                html += escHtml(p.name);
                if (p.model) html += ' <small>' + escHtml(p.model) + '</small>';
                if (p.brand) html += ' <small>[' + escHtml(p.brand) + ']</small>';
                html += '</div>';
            }
            container.innerHTML = html;
            container.style.display = 'block';
        });
    }, 300);
});

function escHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function selectProduct(id, name, model) {
    document.getElementById('vpProductId').value = id;
    document.getElementById('productSearch').value = '';
    document.getElementById('productSearchResults').style.display = 'none';
    document.getElementById('productSelectedText').textContent = name + (model ? ' (' + model + ')' : '');
    document.getElementById('productSelected').style.display = 'block';
}

function clearProduct() {
    document.getElementById('vpProductId').value = '';
    document.getElementById('productSelected').style.display = 'none';
}

// 點擊外部關閉 autocomplete
document.addEventListener('click', function(e) {
    if (!e.target.closest('#productSearch') && !e.target.closest('#productSearchResults')) {
        document.getElementById('productSearchResults').style.display = 'none';
    }
});

// 表單提交處理（如果是更新模式用 AJAX）
document.getElementById('vpForm').addEventListener('submit', function(e) {
    var vpId = document.getElementById('vpId').value;
    if (vpId) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('csrf_token', csrfToken);
        fetch('/vendor_products.php?action=update', {
            method: 'POST',
            body: new URLSearchParams(formData)
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success) {
                location.reload();
            } else {
                alert(d.error || '儲存失敗');
            }
        });
    }
});
</script>
