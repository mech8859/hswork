<?php
$isEdit = isset($record['id']);
$items = array();
if ($isEdit && !empty($record['items'])) {
    $items = $record['items'];
} elseif (!$isEdit && !empty($record['items'])) {
    // 從報價單預填
    $items = $record['items'];
}
?>

<h2><?= $isEdit ? '編輯出貨單 - ' . e($record['do_number']) : '新增出貨單' ?></h2>

<form method="POST" class="mt-2" id="deliveryForm">
    <?= csrf_field() ?>

    <!-- 出貨單資訊 -->
    <div class="card">
        <div class="card-header">出貨單資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>出貨單號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? $record['do_number'] : peek_next_doc_number('delivery_orders')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group">
                <label>出貨日期 *</label>
                <input type="date" max="2099-12-31" name="do_date" class="form-control"
                       value="<?= e($isEdit && !empty($record['do_date']) ? $record['do_date'] : date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>出貨倉庫</label>
                <select name="warehouse_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= $w['id'] ?>" <?= (isset($record['warehouse_id']) ? $record['warehouse_id'] : '') == $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?><?= !empty($w['branch_name']) ? ' (' . e($w['branch_name']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>案件/客戶名稱</label>
                <input type="text" name="case_name" class="form-control"
                       value="<?= e(isset($record['case_name']) ? $record['case_name'] : '') ?>">
            </div>
            <div class="form-group">
                <label>關聯案件</label>
                <select name="case_id" class="form-control">
                    <option value="">無</option>
                    <?php foreach ($cases as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (isset($record['case_id']) ? $record['case_id'] : '') == $c['id'] ? 'selected' : '' ?>><?= e($c['case_number']) ?> - <?= e($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>收貨人</label>
                <input type="text" name="receiver_name" class="form-control"
                       value="<?= e(isset($record['receiver_name']) ? $record['receiver_name'] : '') ?>">
            </div>
            <div class="form-group">
                <label>送貨地址</label>
                <input type="text" name="delivery_address" class="form-control"
                       value="<?= e(isset($record['delivery_address']) ? $record['delivery_address'] : '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="2"><?= e(isset($record['note']) ? $record['note'] : '') ?></textarea>
        </div>
    </div>

    <!-- 出貨明細 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>出貨明細</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addItemRow()">+ 新增</button>
        </div>

        <!-- 產品搜尋 -->
        <div class="form-group mb-1" style="position:relative">
            <label>搜尋產品</label>
            <input type="text" id="productSearch" class="form-control" placeholder="輸入產品名稱或型號搜尋...">
            <div id="productResults" style="position:absolute;z-index:100;background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius);max-height:200px;overflow-y:auto;width:100%;display:none;box-shadow:var(--shadow)"></div>
        </div>

        <div class="table-responsive">
            <table class="table" id="itemTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>品名</th>
                        <th>型號</th>
                        <th style="width:70px">單位</th>
                        <th style="width:80px">數量</th>
                        <th>備註</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="itemBody">
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $idx => $item): ?>
                        <tr>
                            <td class="item-seq"><?= $idx + 1 ?></td>
                            <td>
                                <input type="hidden" name="items[<?= $idx ?>][product_id]" value="<?= e(isset($item['product_id']) ? $item['product_id'] : '') ?>">
                                <input type="text" name="items[<?= $idx ?>][product_name]" class="form-control" value="<?= e(isset($item['product_name']) ? $item['product_name'] : '') ?>">
                            </td>
                            <td><input type="text" name="items[<?= $idx ?>][model]" class="form-control" value="<?= e(isset($item['model']) ? $item['model'] : '') ?>"></td>
                            <td><input type="text" name="items[<?= $idx ?>][unit]" class="form-control" value="<?= e(isset($item['unit']) ? $item['unit'] : '個') ?>"></td>
                            <td><input type="number" name="items[<?= $idx ?>][quantity]" class="form-control item-qty" step="1" min="0" value="<?= isset($item['quantity']) ? (int)$item['quantity'] : 0 ?>"></td>
                            <td><input type="text" name="items[<?= $idx ?>][note]" class="form-control" value="<?= e(isset($item['note']) ? $item['note'] : '') ?>"></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="item-seq">1</td>
                            <td>
                                <input type="hidden" name="items[0][product_id]" value="">
                                <input type="text" name="items[0][product_name]" class="form-control" value="">
                            </td>
                            <td><input type="text" name="items[0][model]" class="form-control" value=""></td>
                            <td><input type="text" name="items[0][unit]" class="form-control" value="個"></td>
                            <td><input type="number" name="items[0][quantity]" class="form-control item-qty" step="1" min="0" value="0"></td>
                            <td><input type="text" name="items[0][note]" class="form-control" value=""></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立出貨單' ?></button>
        <a href="/delivery_orders.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
// ---- 動態列 ----
var itemIdx = <?= !empty($items) ? count($items) : 1 ?>;

function addItemRow(productId, name, model, unit) {
    var tbody = document.getElementById('itemBody');
    var tr = document.createElement('tr');
    var seq = tbody.querySelectorAll('tr').length + 1;
    var pid = productId || '';
    var pname = name || '';
    var pmodel = model || '';
    var punit = unit || '個';

    tr.innerHTML = '<td class="item-seq">' + seq + '</td>'
        + '<td><input type="hidden" name="items['+itemIdx+'][product_id]" value="'+pid+'"><input type="text" name="items['+itemIdx+'][product_name]" class="form-control" value="'+escHtml(pname)+'"></td>'
        + '<td><input type="text" name="items['+itemIdx+'][model]" class="form-control" value="'+escHtml(pmodel)+'"></td>'
        + '<td><input type="text" name="items['+itemIdx+'][unit]" class="form-control" value="'+escHtml(punit)+'"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][quantity]" class="form-control item-qty" step="1" min="0" value="1"></td>'
        + '<td><input type="text" name="items['+itemIdx+'][note]" class="form-control" value=""></td>'
        + '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>';
    tbody.appendChild(tr);
    itemIdx++;
}

function removeItemRow(btn) {
    btn.closest('tr').remove();
    reindexItems();
}

function reindexItems() {
    var rows = document.querySelectorAll('#itemBody tr');
    for (var i = 0; i < rows.length; i++) {
        rows[i].querySelector('.item-seq').textContent = i + 1;
    }
}

function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

// ---- 產品搜尋 ----
var searchTimer = null;
var searchInput = document.getElementById('productSearch');
var searchResults = document.getElementById('productResults');

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimer);
    var keyword = this.value.trim();
    if (keyword.length < 1) {
        searchResults.style.display = 'none';
        return;
    }
    searchTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/delivery_orders.php?action=ajax_products&keyword=' + encodeURIComponent(keyword));
        xhr.onload = function() {
            if (xhr.status === 200) {
                var products = JSON.parse(xhr.responseText);
                if (products.length === 0) {
                    searchResults.innerHTML = '<div style="padding:8px;color:var(--gray-500)">查無產品</div>';
                } else {
                    var html = '';
                    for (var i = 0; i < products.length; i++) {
                        var p = products[i];
                        html += '<div class="product-result-item" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--gray-100)" '
                            + 'onclick="selectProduct('+p.id+',\''+escAttr(p.name)+'\',\''+escAttr(p.model || '')+'\',\''+escAttr(p.unit || '個')+'\')">'
                            + '<strong>' + escHtml(p.name) + '</strong>'
                            + (p.model ? ' <span style="color:var(--gray-500)">' + escHtml(p.model) + '</span>' : '')
                            + '</div>';
                    }
                    searchResults.innerHTML = html;
                }
                searchResults.style.display = 'block';
            }
        };
        xhr.send();
    }, 300);
});

function escAttr(str) {
    return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function selectProduct(id, name, model, unit) {
    addItemRow(id, name, model, unit);
    searchInput.value = '';
    searchResults.style.display = 'none';
}

document.addEventListener('click', function(e) {
    if (!searchResults.contains(e.target) && e.target !== searchInput) {
        searchResults.style.display = 'none';
    }
});
</script>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.product-result-item:hover { background: var(--gray-50); }
</style>
