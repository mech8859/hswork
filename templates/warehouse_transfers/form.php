<?php $isEdit = !empty($record); ?>
<style>
.wt-form .form-row{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px}
.wt-form .form-row .form-group{flex:1 1 0;min-width:140px;margin-bottom:0}
</style>
<div>
    <h2 style="margin-bottom:2px"><?= $isEdit ? '編輯調撥單 - ' . e($record['transfer_number']) : '新增調撥單' ?></h2>
    <?php if ($isEdit && !empty($record['updated_at'])): ?>
    <small class="text-muted">最後修改 <?= e($record['updated_at']) ?><?= !empty($record['updated_by_name']) ? ' / ' . e($record['updated_by_name']) : '' ?></small>
    <?php endif; ?>
</div>

<form method="POST" class="mt-2" id="transferForm">
    <?= csrf_field() ?>

    <!-- 調撥資訊 -->
    <div class="card wt-form">
        <div class="card-header">調撥資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>調撥單號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? $record['transfer_number'] : peek_next_doc_number('warehouse_transfers')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group">
                <label>調撥日期 *</label>
                <input type="date" max="2099-12-31" name="transfer_date" class="form-control"
                       value="<?= e($isEdit && !empty($record['transfer_date']) ? $record['transfer_date'] : date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <?php foreach (ProcurementModel::transferStatusOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= ($isEdit && !empty($record['status']) ? $record['status'] : '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>調出分公司</label>
                <select name="from_branch_id" class="form-control" onchange="autoSelectWarehouse(this, 'from_warehouse_id')">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($isEdit && !empty($record['from_branch_id']) ? $record['from_branch_id'] : '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>調出倉庫 *</label>
                <select name="from_warehouse_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= $w['id'] ?>" data-branch-id="<?= !empty($w['branch_id']) ? $w['branch_id'] : '' ?>" <?= ($isEdit && !empty($record['from_warehouse_id']) ? $record['from_warehouse_id'] : '') == $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>調進分公司</label>
                <select name="to_branch_id" class="form-control" onchange="autoSelectWarehouse(this, 'to_warehouse_id')">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($isEdit && !empty($record['to_branch_id']) ? $record['to_branch_id'] : '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>調進倉庫 *</label>
                <select name="to_warehouse_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= $w['id'] ?>" data-branch-id="<?= !empty($w['branch_id']) ? $w['branch_id'] : '' ?>" <?= ($isEdit && !empty($record['to_warehouse_id']) ? $record['to_warehouse_id'] : '') == $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>出貨人</label>
                <input type="text" name="shipper_name" class="form-control" value="<?= e($isEdit && !empty($record['shipper_name']) ? $record['shipper_name'] : '') ?>" placeholder="選填">
            </div>
            <div class="form-group">
                <label>進貨人</label>
                <input type="text" name="receiver_name" class="form-control" value="<?= e($isEdit && !empty($record['receiver_name']) ? $record['receiver_name'] : '') ?>" placeholder="選填">
            </div>
            <div class="form-group">
                <label>登記人</label>
                <?php $wtUser = Session::getUser(); ?>
                <input type="text" class="form-control" value="<?= e($isEdit && !empty($record['created_by_name']) ? $record['created_by_name'] : (!empty($wtUser['name']) ? $wtUser['name'] : '')) ?>" disabled style="background:#f5f5f5">
                <div class="text-muted" style="font-size:.75rem"><?= $isEdit && !empty($record['created_at']) ? $record['created_at'] : date('Y-m-d H:i') ?></div>
            </div>
            <div class="form-group" style="flex:2">
                <label>備註</label>
                <input type="text" name="note" class="form-control" value="<?= e($isEdit && !empty($record['note']) ? $record['note'] : '') ?>" placeholder="選填">
            </div>
        </div>
    </div>

    <!-- 調撥明細 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>調撥明細</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addItemRow()">+ 新增</button>
        </div>
        <div class="table-responsive" style="overflow:visible">
            <table class="table" id="itemTable">
                <thead>
                    <tr>
                        <th style="width:50px">項次</th>
                        <th>商品型號</th>
                        <th>商品名稱</th>
                        <th style="width:80px">數量</th>
                        <th style="width:100px">單價</th>
                        <th style="width:100px">金額</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="itemBody">
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $idx => $item): ?>
                        <tr>
                            <td class="item-seq"><?= $idx + 1 ?></td>
                            <td><input type="hidden" name="items[<?= $idx ?>][product_id]" class="wt-product-id" value="<?= e(!empty($item['product_id']) ? $item['product_id'] : '') ?>"><input type="text" name="items[<?= $idx ?>][model]" class="form-control wt-model" value="<?= e(!empty($item['model']) ? $item['model'] : '') ?>" readonly></td>
                            <td style="position:relative"><input type="text" name="items[<?= $idx ?>][product_name]" class="form-control wt-product-name" value="<?= e(!empty($item['product_name']) ? $item['product_name'] : '') ?>" autocomplete="off" placeholder="輸入關鍵字搜尋..." oninput="wtSearchProduct(this)"><div class="wt-product-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div></td>
                            <td><input type="number" name="items[<?= $idx ?>][quantity]" class="form-control item-qty" step="1" min="0" value="<?= !empty($item['quantity']) ? (int)$item['quantity'] : 0 ?>" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[<?= $idx ?>][unit_price]" class="form-control item-price" step="1" min="0" value="<?= !empty($item['unit_price']) ? (int)$item['unit_price'] : 0 ?>" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[<?= $idx ?>][amount]" class="form-control item-amount" step="1" min="0" value="<?= !empty($item['amount']) ? (int)$item['amount'] : 0 ?>" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="item-seq">1</td>
                            <td><input type="hidden" name="items[0][product_id]" class="wt-product-id" value=""><input type="text" name="items[0][model]" class="form-control wt-model" value="" readonly></td>
                            <td style="position:relative"><input type="text" name="items[0][product_name]" class="form-control wt-product-name" value="" autocomplete="off" placeholder="輸入關鍵字搜尋..." oninput="wtSearchProduct(this)"><div class="wt-product-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div></td>
                            <td><input type="number" name="items[0][quantity]" class="form-control item-qty" step="1" min="0" value="0" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[0][unit_price]" class="form-control item-price" step="1" min="0" value="0" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[0][amount]" class="form-control item-amount" step="1" min="0" value="0" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-right"><strong>合計</strong></td>
                        <td><input type="number" name="total_amount" id="total_amount" class="form-control" step="1" min="0" value="<?= $isEdit && !empty($record['total_amount']) ? (int)$record['total_amount'] : 0 ?>" readonly style="font-weight:600"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立調撥單' ?></button>
        <a href="/warehouse_transfers.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
// ---- 調撥明細 動態列 ----
var itemIdx = <?= !empty($items) ? count($items) : 1 ?>;

function addItemRow() {
    var tbody = document.getElementById('itemBody');
    var tr = document.createElement('tr');
    var seq = tbody.querySelectorAll('tr').length + 1;
    tr.innerHTML = '<td class="item-seq">' + seq + '</td>'
        + '<td><input type="hidden" name="items['+itemIdx+'][product_id]" class="wt-product-id" value=""><input type="text" name="items['+itemIdx+'][model]" class="form-control wt-model" value="" readonly></td>'
        + '<td style="position:relative"><input type="text" name="items['+itemIdx+'][product_name]" class="form-control wt-product-name" value="" autocomplete="off" placeholder="輸入關鍵字搜尋..." oninput="wtSearchProduct(this)"><div class="wt-product-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div></td>'
        + '<td><input type="number" name="items['+itemIdx+'][quantity]" class="form-control item-qty" step="1" min="0" value="0" oninput="calcRowAmount(this.closest(\'tr\'))"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][unit_price]" class="form-control item-price" step="1" min="0" value="0" oninput="calcRowAmount(this.closest(\'tr\'))"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][amount]" class="form-control item-amount" step="1" min="0" value="0" readonly></td>'
        + '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>';
    tbody.appendChild(tr);
    itemIdx++;
}

function removeItemRow(btn) {
    btn.closest('tr').remove();
    reindexItems();
    calcTotal();
}

function reindexItems() {
    var rows = document.querySelectorAll('#itemBody tr');
    for (var i = 0; i < rows.length; i++) {
        rows[i].querySelector('.item-seq').textContent = i + 1;
    }
}

// ---- 金額計算 ----
function calcRowAmount(row) {
    var price = parseFloat(row.querySelector('.item-price').value) || 0;
    var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    row.querySelector('.item-amount').value = Math.round(price * qty);
    calcTotal();
}

function calcTotal() {
    var total = 0;
    document.querySelectorAll('.item-amount').forEach(function(el) {
        total += parseFloat(el.value) || 0;
    });
    document.getElementById('total_amount').value = total;
}

// 頁面載入時初始計算
calcTotal();

// ===== 分公司自動選倉庫 =====
function autoSelectWarehouse(branchSel, warehouseName) {
    var branchId = branchSel.value;
    if (!branchId) return;
    var whSel = document.querySelector('select[name="' + warehouseName + '"]');
    if (!whSel) return;
    var options = whSel.querySelectorAll('option[data-branch-id]');
    for (var i = 0; i < options.length; i++) {
        if (options[i].getAttribute('data-branch-id') === branchId) {
            whSel.value = options[i].value;
            return;
        }
    }
}

// ===== 商品名稱即時搜尋 =====
var wtSearchTimer = null;
function wtSearchProduct(inp) {
    clearTimeout(wtSearchTimer);
    var q = inp.value.trim();
    var dd = inp.parentNode.querySelector('.wt-product-dropdown');
    if (!dd) return;
    if (q.length < 1) { dd.style.display = 'none'; return; }
    wtSearchTimer = setTimeout(function(){
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/requisitions.php?action=ajax_search_product&q=' + encodeURIComponent(q));
        xhr.onload = function(){
            try { var list = JSON.parse(xhr.responseText); } catch(e) { return; }
            if (!list.length) { dd.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合產品</div>'; dd.style.display = 'block'; return; }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" '
                    + 'data-id="' + (list[i].id||'') + '" '
                    + 'data-name="' + (list[i].name||'').replace(/"/g,'&quot;') + '" '
                    + 'data-model="' + (list[i].model||'').replace(/"/g,'&quot;') + '" '
                    + 'data-price="' + (list[i].price||0) + '" '
                    + 'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">'
                    + '<div style="font-weight:600">' + (list[i].name||'') + '</div>'
                    + '<div style="font-size:.75rem;color:#888">'
                    + (list[i].model ? '<span style="color:#1565c0">' + list[i].model + '</span> | ' : '')
                    + '$' + Number(list[i].price||0).toLocaleString()
                    + ' | <span style="color:' + (Number(list[i].stock||0) > 0 ? '#2e7d32' : '#c62828') + '">庫存:' + Number(list[i].stock||0) + '</span>'
                    + '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
        };
        xhr.send();
    }, 300);
}
document.addEventListener('click', function(e) {
    var item = e.target.closest('.wt-product-dropdown > div[data-id]');
    if (item) {
        var row = item.closest('tr');
        row.querySelector('.wt-product-name').value = item.dataset.name;
        row.querySelector('.wt-model').value = item.dataset.model || '';
        var pidInp = row.querySelector('.wt-product-id');
        if (pidInp) pidInp.value = item.dataset.id || '';
        var priceInp = row.querySelector('.item-price');
        if (priceInp && item.dataset.price) { priceInp.value = Math.round(Number(item.dataset.price)); calcRowAmount(row); }
        item.closest('.wt-product-dropdown').style.display = 'none';
        return;
    }
    if (!e.target.classList.contains('wt-product-name')) {
        var dds = document.querySelectorAll('.wt-product-dropdown');
        for (var i = 0; i < dds.length; i++) dds[i].style.display = 'none';
    }
});
</script>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
</style>
