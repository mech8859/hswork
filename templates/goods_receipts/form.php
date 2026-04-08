<?php $isEdit = !empty($record) && !empty($record['id']); ?>

<h2><?= $isEdit ? '編輯進貨單 - ' . e($record['gr_number']) : '新增進貨單' ?></h2>

<form method="POST" class="mt-2" id="grForm" onsubmit="return grValidateVendorBeforeSubmit()">
    <?= csrf_field() ?>

    <?php
    // 統一取值
    $g = !empty($record) ? $record : array();
    $gv = function($key, $default = '') use ($g) { return !empty($g[$key]) ? $g[$key] : $default; };
    ?>
    <!-- 進貨單資訊 -->
    <div class="card">
        <div class="card-header">進貨單資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>進貨單號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? $g['gr_number'] : peek_next_doc_number('goods_receipts')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group">
                <label>進貨日期 *</label>
                <input type="date" max="2099-12-31" name="gr_date" class="form-control"
                       value="<?= e($gv('gr_date', date('Y-m-d'))) ?>" required>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <?php foreach (GoodsReceiptModel::statusOptions() as $k => $v): ?>
                    <?php if ($k === '已確認') continue; ?>
                    <option value="<?= e($k) ?>" <?= $gv('status', '草稿') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>關聯採購單</label>
                <?php if (!empty($g['po_number'])): ?>
                <input type="text" class="form-control" value="<?= e($g['po_number']) ?>" readonly style="background:#f5f5f5">
                <input type="hidden" name="po_id" value="<?= e($gv('po_id')) ?>">
                <input type="hidden" name="po_number" value="<?= e($gv('po_number')) ?>">
                <?php else: ?>
                <select name="po_id" id="poSelect" class="form-control" onchange="onPOSelect(this)">
                    <option value="">-- 無 --</option>
                    <?php if (!empty($pendingPOs)): ?>
                    <?php foreach ($pendingPOs as $po): ?>
                    <option value="<?= $po['id'] ?>"
                            data-number="<?= e($po['po_number']) ?>"
                            data-vendor="<?= e(!empty($po['vendor_name']) ? $po['vendor_name'] : '') ?>"
                            <?= $gv('po_id') == $po['id'] ? 'selected' : '' ?>>
                        <?= e($po['po_number']) ?> - <?= e(!empty($po['vendor_name']) ? $po['vendor_name'] : '') ?> ($<?= number_format($po['total_amount']) ?>)
                    </option>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <input type="hidden" name="po_number" id="po_number" value="<?= e($gv('po_number')) ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>倉庫 *</label>
                <select name="warehouse_id" id="grWarehouse" class="form-control" required onchange="onGrWarehouseChange()">
                    <option value="">請選擇</option>
                    <?php foreach ($warehouses as $wh): ?>
                    <option value="<?= $wh['id'] ?>" data-branch-id="<?= $wh['branch_id'] ?>" <?= $gv('warehouse_id') == $wh['id'] ? 'selected' : '' ?>><?= e($wh['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" id="grBranch" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $br): ?>
                    <option value="<?= $br['id'] ?>" <?= $gv('branch_id') == $br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="branch_name" id="grBranchName" value="<?= e($gv('branch_name')) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="position:relative">
                <label>廠商名稱 <span style="color:#c62828">*</span> <small style="color:#888;font-weight:normal">(必須從廠商管理選擇)</small></label>
                <input type="text" name="vendor_name" id="vendor_name" class="form-control" autocomplete="off"
                       value="<?= e($gv('vendor_name')) ?>" oninput="grVendorAutoSearch(this)" required>
                <input type="hidden" name="vendor_id" id="vendor_id" value="<?= e($gv('vendor_id')) ?>">
                <div id="grVendorACDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:240px;overflow-y:auto;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
                <small id="grVendorWarning" style="display:none;color:#c62828;font-size:.75rem">⚠ 廠商必須從下拉選單選擇，找不到請先到 <a href="/vendors.php" target="_blank">廠商管理</a> 建立</small>
            </div>
            <div class="form-group">
                <label>收貨人</label>
                <input type="text" name="receiver_name" class="form-control"
                       value="<?= e($gv('receiver_name')) ?>">
            </div>
        </div>
        <?php if (!empty($g['po_id'])): ?>
        <div class="form-row">
            <div class="form-group">
                <label>請購單號</label>
                <input type="text" class="form-control" value="<?= e($gv('requisition_number')) ?>" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>案件名稱</label>
                <input type="text" class="form-control" value="<?= e($gv('case_name')) ?>" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>採購人</label>
                <input type="text" class="form-control" value="<?= e($gv('purchaser_name')) ?>" readonly style="background:#f5f5f5">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>已付金額</label>
                <input type="text" class="form-control" value="$<?= number_format((int)$gv('paid_amount', 0)) ?>" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>付款日期</label>
                <input type="text" class="form-control" value="<?= e($gv('payment_date')) ?>" readonly style="background:#f5f5f5">
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 進貨明細 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>進貨明細</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addItemRow()">+ 新增</button>
        </div>
        <div class="table-responsive">
            <table class="table" id="itemTable">
                <thead>
                    <tr>
                        <th style="width:40px">序號</th>
                        <th>型號</th>
                        <th>品名</th>
                        <th>規格</th>
                        <th>單位</th>
                        <th style="width:80px">採購數量</th>
                        <th style="width:80px">收貨數量</th>
                        <th style="width:100px">單價</th>
                        <th style="width:100px">金額</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="itemBody">
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $idx => $item): ?>
                        <tr>
                            <td class="item-seq"><?= $idx + 1 ?></td>
                            <td>
                                <input type="hidden" name="items[<?= $idx ?>][product_id]" value="<?= e(!empty($item['product_id']) ? $item['product_id'] : '') ?>">
                                <input type="text" name="items[<?= $idx ?>][model]" class="form-control" value="<?= e(!empty($item['model']) ? $item['model'] : '') ?>">
                            </td>
                            <td style="position:relative"><input type="text" name="items[<?= $idx ?>][product_name]" class="form-control product-name-input" value="<?= e(!empty($item['product_name']) ? $item['product_name'] : '') ?>" placeholder="搜尋品名..." autocomplete="off" oninput="searchProductByName(this, <?= $idx ?>)"></td>
                            <td><input type="text" name="items[<?= $idx ?>][spec]" class="form-control" value="<?= e(!empty($item['spec']) ? $item['spec'] : '') ?>"></td>
                            <td><input type="text" name="items[<?= $idx ?>][unit]" class="form-control" value="<?= e(!empty($item['unit']) ? $item['unit'] : '') ?>" style="width:60px"></td>
                            <td><input type="number" name="items[<?= $idx ?>][po_qty]" class="form-control" step="1" min="0" value="<?= !empty($item['po_qty']) ? (int)$item['po_qty'] : 0 ?>" readonly></td>
                            <td><input type="number" name="items[<?= $idx ?>][received_qty]" class="form-control item-qty" step="1" min="0" value="<?= !empty($item['received_qty']) ? (int)$item['received_qty'] : 0 ?>" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[<?= $idx ?>][unit_price]" class="form-control item-price" step="1" min="0" value="<?= !empty($item['unit_price']) ? (int)$item['unit_price'] : 0 ?>" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[<?= $idx ?>][amount]" class="form-control item-amount" step="1" min="0" value="<?= !empty($item['amount']) ? (int)$item['amount'] : 0 ?>" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="item-seq">1</td>
                            <td>
                                <input type="hidden" name="items[0][product_id]" value="">
                                <input type="text" name="items[0][model]" class="form-control" value="">
                            </td>
                            <td style="position:relative"><input type="text" name="items[0][product_name]" class="form-control product-name-input" value="" placeholder="搜尋品名..." autocomplete="off" oninput="searchProductByName(this, 0)"></td>
                            <td><input type="text" name="items[0][spec]" class="form-control" value=""></td>
                            <td><input type="text" name="items[0][unit]" class="form-control" value="" style="width:60px"></td>
                            <td><input type="number" name="items[0][po_qty]" class="form-control" step="1" min="0" value="0" readonly></td>
                            <td><input type="number" name="items[0][received_qty]" class="form-control item-qty" step="1" min="0" value="0" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[0][unit_price]" class="form-control item-price" step="1" min="0" value="0" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[0][amount]" class="form-control item-amount" step="1" min="0" value="0" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row" style="background:#f5f5f5;font-weight:600">
                        <td colspan="6" class="text-right">合計</td>
                        <td class="text-right" id="sumQty">0</td>
                        <td></td>
                        <td class="text-right" id="sumAmount">0</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 合計 -->
    <div class="card">
        <div class="card-header">合計</div>
        <div class="form-row">
            <div class="form-group">
                <label>總數量</label>
                <input type="number" name="total_qty" id="total_qty" class="form-control" step="1" min="0"
                       value="<?= $isEdit && !empty($record['total_qty']) ? (int)$record['total_qty'] : 0 ?>" readonly>
            </div>
            <div class="form-group">
                <label>未稅金額</label>
                <input type="number" id="subtotal_amount" class="form-control" step="1" min="0" value="0" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>稅額 (5%)</label>
                <input type="number" id="tax_amount" class="form-control" step="1" min="0" value="0" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>總金額（含稅）</label>
                <input type="number" name="total_amount" id="total_amount" class="form-control" step="1" min="0"
                       value="<?= $isEdit && !empty($record['total_amount']) ? (int)$record['total_amount'] : 0 ?>" readonly style="font-weight:700;color:var(--primary)">
            </div>
        </div>
    </div>

    <!-- 付款資訊 -->
    <div class="card">
        <div class="card-header">付款資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>已付金額</label>
                <input type="number" name="paid_amount" class="form-control" step="1" min="0"
                       value="<?= $isEdit && !empty($record['paid_amount']) ? (int)$record['paid_amount'] : '' ?>" placeholder="0">
            </div>
            <div class="form-group">
                <label>付款日</label>
                <input type="date" name="paid_date" class="form-control" max="2099-12-31"
                       value="<?= $isEdit && !empty($record['paid_date']) ? $record['paid_date'] : '' ?>">
            </div>
        </div>
    </div>

    <!-- 備註 -->
    <div class="card">
        <div class="card-header">備註</div>
        <div class="form-group">
            <textarea name="note" class="form-control" rows="3"><?= e($isEdit && !empty($record['note']) ? $record['note'] : '') ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立進貨單' ?></button>
        <a href="/goods_receipts.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
var itemIdx = <?= !empty($items) ? count($items) : 1 ?>;

function addItemRow() {
    var tbody = document.getElementById('itemBody');
    var tr = document.createElement('tr');
    var seq = tbody.querySelectorAll('tr').length + 1;
    tr.innerHTML = '<td class="item-seq">' + seq + '</td>'
        + '<td><input type="hidden" name="items['+itemIdx+'][product_id]" value="">'
        + '<input type="text" name="items['+itemIdx+'][model]" class="form-control" value=""></td>'
        + '<td style="position:relative"><input type="text" name="items['+itemIdx+'][product_name]" class="form-control product-name-input" value="" placeholder="搜尋品名..." autocomplete="off" oninput="searchProductByName(this,'+itemIdx+')"></td>'
        + '<td><input type="text" name="items['+itemIdx+'][spec]" class="form-control" value=""></td>'
        + '<td><input type="text" name="items['+itemIdx+'][unit]" class="form-control" value="" style="width:60px"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][po_qty]" class="form-control" step="1" min="0" value="0" readonly></td>'
        + '<td><input type="number" name="items['+itemIdx+'][received_qty]" class="form-control item-qty" step="1" min="0" value="0" oninput="calcRowAmount(this.closest(\'tr\'))"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][unit_price]" class="form-control item-price" step="1" min="0" value="0" oninput="calcRowAmount(this.closest(\'tr\'))"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][amount]" class="form-control item-amount" step="1" min="0" value="0" readonly></td>'
        + '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>';
    tbody.appendChild(tr);
    itemIdx++;
}

function removeItemRow(btn) {
    btn.closest('tr').remove();
    reindexItems();
    calcTotals();
}

function reindexItems() {
    var rows = document.querySelectorAll('#itemBody tr');
    for (var i = 0; i < rows.length; i++) {
        rows[i].querySelector('.item-seq').textContent = i + 1;
    }
}

function calcRowAmount(row) {
    var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    var price = parseFloat(row.querySelector('.item-price').value) || 0;
    row.querySelector('.item-amount').value = Math.round(qty * price);
    calcTotals();
}

function calcTotals() {
    var totalQty = 0;
    var subtotal = 0;
    document.querySelectorAll('.item-qty').forEach(function(el) {
        totalQty += parseFloat(el.value) || 0;
    });
    document.querySelectorAll('.item-amount').forEach(function(el) {
        subtotal += parseFloat(el.value) || 0;
    });
    var tax = Math.round(subtotal * 0.05);
    var total = subtotal + tax;
    document.getElementById('total_qty').value = totalQty;
    document.getElementById('subtotal_amount').value = subtotal;
    document.getElementById('tax_amount').value = tax;
    document.getElementById('total_amount').value = total;
    var sumQty = document.getElementById('sumQty');
    var sumAmount = document.getElementById('sumAmount');
    if (sumQty) sumQty.textContent = totalQty.toLocaleString();
    if (sumAmount) sumAmount.textContent = '$' + subtotal.toLocaleString();
}

// 品名即時搜尋
var pnTimer = null;
function _removeAllPnDropdowns() {
    document.querySelectorAll('.pn-dropdown').forEach(function(d){
        if (typeof d._cleanup === 'function') d._cleanup();
        d.remove();
    });
}
function _positionPnDropdown(dd, input) {
    var r = input.getBoundingClientRect();
    dd.style.position = 'fixed';
    dd.style.top = (r.bottom + 2) + 'px';
    dd.style.left = r.left + 'px';
    dd.style.width = r.width + 'px';
    dd.style.zIndex = '10000';
}
function searchProductByName(input, idx) {
    clearTimeout(pnTimer);
    _removeAllPnDropdowns();
    var q = input.value.trim();
    if (q.length < 2) return;
    pnTimer = setTimeout(function() {
        // 改用 inventory 的搜尋 endpoint：僅啟用品項、按品名排序、20 筆
        fetch('/inventory.php?action=ajax_search_products&q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            // 相容兩種回傳格式：{data:[...]} 或直接 [...]
            var results = (resp && resp.data) ? resp.data : (Array.isArray(resp) ? resp : []);
            if (!results || !results.length) return;
            var dd = document.createElement('div');
            dd.className = 'pn-dropdown';
            var html = '';
            for (var i = 0; i < Math.min(results.length, 10); i++) {
                var p = results[i];
                var pModel = p.model || p.model_number || '';
                var pCost  = p.cost || p.price || 0;
                html += '<div class="pn-item" data-id="' + (p.id||'') + '" data-model="' + escAttr(pModel) + '" data-name="' + escAttr(p.name||'') + '" data-unit="' + escAttr(p.unit||'') + '" data-cost="' + pCost + '">';
                html += '<b>' + escH(p.name) + '</b>';
                if (pModel) html += ' <small style="color:#888">' + escH(pModel) + '</small>';
                if (pCost) html += ' <small style="color:#e53935">$' + Number(pCost).toLocaleString() + '</small>';
                html += '</div>';
            }
            dd.innerHTML = html;
            // 用 position:fixed 逃離 .table-responsive 的 overflow
            document.body.appendChild(dd);
            _positionPnDropdown(dd, input);
            // 捲動或視窗調整時重新定位
            var reposition = function(){ _positionPnDropdown(dd, input); };
            window.addEventListener('scroll', reposition, true);
            window.addEventListener('resize', reposition);
            dd._cleanup = function(){
                window.removeEventListener('scroll', reposition, true);
                window.removeEventListener('resize', reposition);
            };
            dd.querySelectorAll('.pn-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    var row = input.closest('tr');
                    input.value = this.getAttribute('data-name');
                    var modelInput = row.querySelector('input[name*="[model]"]');
                    if (modelInput) modelInput.value = this.getAttribute('data-model');
                    var pidInput = row.querySelector('input[name*="[product_id]"]');
                    if (pidInput) pidInput.value = this.getAttribute('data-id');
                    var unitInput = row.querySelector('input[name*="[unit]"]');
                    if (unitInput && !unitInput.value) unitInput.value = this.getAttribute('data-unit');
                    var priceInput = row.querySelector('.item-price');
                    if (priceInput && (!priceInput.value || priceInput.value == '0')) {
                        priceInput.value = this.getAttribute('data-cost');
                        calcRowAmount(row);
                    }
                    if (typeof dd._cleanup === 'function') dd._cleanup();
                    dd.remove();
                });
            });
        });
    }, 300);
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.pn-dropdown') && !e.target.classList.contains('product-name-input')) {
        _removeAllPnDropdowns();
    }
});

function onPOSelect(sel) {
    var opt = sel.options[sel.selectedIndex];
    if (opt.value) {
        document.getElementById('po_number').value = opt.getAttribute('data-number') || '';
        document.getElementById('vendor_name').value = opt.getAttribute('data-vendor') || '';
        // Redirect to create_from_po to load items
        if (!<?= $isEdit ? 'true' : 'false' ?>) {
            location.href = '/goods_receipts.php?action=create&from_po=' + opt.value;
        }
    } else {
        document.getElementById('po_number').value = '';
    }
}

calcTotals();

// 廠商產品對照：型號輸入時自動搜尋
var vpTimer = null;
document.getElementById('itemBody').addEventListener('input', function(e) {
    var input = e.target;
    if (!input.name || input.name.indexOf('[model]') === -1) return;
    var q = input.value.trim();
    clearTimeout(vpTimer);
    // 關閉已有的 dropdown
    var oldDrop = input.parentNode.querySelector('.vp-dropdown');
    if (oldDrop) oldDrop.remove();
    if (q.length < 1) return;

    var vendorId = document.getElementById('vendor_id').value;
    if (!vendorId) return;

    vpTimer = setTimeout(function() {
        fetch('/vendor_products.php?action=search_api&vendor_id=' + vendorId + '&q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(results) {
            if (!results.length) return;
            var dd = document.createElement('div');
            dd.className = 'vp-dropdown';
            var html = '';
            for (var i = 0; i < results.length; i++) {
                var r = results[i];
                html += '<div class="vp-item" data-model="' + escAttr(r.vendor_model) + '" data-name="' + escAttr(r.vendor_name || '') + '" data-price="' + (r.last_purchase_price || 0) + '" data-product-id="' + (r.product_id || '') + '" data-product-name="' + escAttr(r.product_name || '') + '">';
                html += '<b>' + escH(r.vendor_model) + '</b>';
                if (r.vendor_name) html += ' ' + escH(r.vendor_name);
                if (r.product_name) html += ' <small style="color:#1976d2">[' + escH(r.product_name) + ']</small>';
                if (r.last_purchase_price) html += ' <small style="color:#e53935">$' + Number(r.last_purchase_price).toLocaleString() + '</small>';
                html += '</div>';
            }
            dd.innerHTML = html;
            input.parentNode.style.position = 'relative';
            input.parentNode.appendChild(dd);

            dd.addEventListener('click', function(ev) {
                var item = ev.target.closest('.vp-item');
                if (!item) return;
                var tr = input.closest('tr');
                // 填入資料
                input.value = item.getAttribute('data-model');
                var nameInput = tr.querySelector('input[name*="[product_name]"]');
                if (nameInput) nameInput.value = item.getAttribute('data-name');
                var priceInput = tr.querySelector('.item-price');
                if (priceInput && item.getAttribute('data-price') > 0) priceInput.value = Math.round(item.getAttribute('data-price'));
                var pidInput = tr.querySelector('input[name*="[product_id]"]');
                if (pidInput) pidInput.value = item.getAttribute('data-product-id');
                calcRowAmount(tr);
                dd.remove();
            });
        });
    }, 300);
});

function escH(s) { var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
function escAttr(s) { return String(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;'); }

// 點擊外部關閉 dropdown
document.addEventListener('click', function(e) {
    if (!e.target.closest('.vp-dropdown') && !e.target.name) {
        var dd = document.querySelectorAll('.vp-dropdown');
        for (var i = 0; i < dd.length; i++) dd[i].remove();
    }
});
</script>

<style>
.vp-dropdown, .pn-dropdown { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid var(--gray-300); border-radius:6px; max-height:200px; overflow-y:auto; z-index:1000; box-shadow:0 4px 12px rgba(0,0,0,.15); }
.vp-item, .pn-item { padding:6px 10px; cursor:pointer; font-size:.82rem; border-bottom:1px solid var(--gray-100); }
.vp-item:hover, .pn-item:hover { background:var(--gray-50); }
.vp-item:last-child, .pn-item:last-child { border-bottom:none; }
</style>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
</style>
<script>
function onGrWarehouseChange() {
    var sel = document.getElementById('grWarehouse');
    var opt = sel.options[sel.selectedIndex];
    var branchId = opt ? opt.getAttribute('data-branch-id') : '';
    if (branchId) {
        var brSel = document.getElementById('grBranch');
        brSel.value = branchId;
        // Update hidden branch_name
        var brOpt = brSel.options[brSel.selectedIndex];
        document.getElementById('grBranchName').value = brOpt ? brOpt.textContent : '';
    }
}
// Also update branch_name when branch select changes
document.getElementById('grBranch').addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    document.getElementById('grBranchName').value = opt ? opt.textContent : '';
});
// Init on load
if (document.getElementById('grWarehouse').value) onGrWarehouseChange();

// ===== 廠商強制必選 autocomplete =====
function grValidateVendorBeforeSubmit() {
    var vid = document.getElementById('vendor_id').value;
    var vname = document.getElementById('vendor_name').value.trim();
    if (!vname) {
        alert('請輸入廠商名稱');
        return false;
    }
    if (!vid || parseInt(vid) <= 0) {
        alert('廠商必須從下拉清單選擇，不可手動輸入。\n找不到請先到「廠商管理」建立。');
        document.getElementById('grVendorWarning').style.display = 'block';
        return false;
    }
    return true;
}
var grVendorTimer = null;
function grVendorAutoSearch(inp) {
    // 改字立刻清掉 vendor_id (避免改名字後仍套用舊 id)
    document.getElementById('vendor_id').value = '';
    document.getElementById('grVendorWarning').style.display = 'none';
    clearTimeout(grVendorTimer);
    var q = inp.value.trim();
    var dd = document.getElementById('grVendorACDropdown');
    if (q.length < 1) { dd.style.display = 'none'; return; }
    grVendorTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/payments_out.php?action=ajax_vendor_search&q=' + encodeURIComponent(q));
        xhr.onload = function() {
            try { var list = JSON.parse(xhr.responseText); } catch(e) { return; }
            if (!list.length) {
                dd.innerHTML = '<div style="padding:8px 12px;color:#c62828;font-size:.85rem">無符合廠商，請先到 <a href="/vendors.php" target="_blank">廠商管理</a> 建立</div>';
                dd.style.display = 'block';
                return;
            }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<div class="grv-item" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;font-size:.85rem" '
                    + 'data-id="' + (list[i].id||'') + '" data-name="' + (list[i].name||'').replace(/"/g,'&quot;') + '" '
                    + 'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">'
                    + '<div style="font-weight:600">' + (list[i].name||'') + '</div>'
                    + '<div style="font-size:.75rem;color:#888">' + (list[i].vendor_code ? '編號:' + list[i].vendor_code : '') + ' ' + (list[i].contact_person||'') + ' ' + (list[i].phone||'') + '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
            dd.querySelectorAll('.grv-item').forEach(function(it) {
                it.addEventListener('click', function() {
                    document.getElementById('vendor_name').value = this.getAttribute('data-name');
                    document.getElementById('vendor_id').value = this.getAttribute('data-id');
                    dd.style.display = 'none';
                });
            });
        };
        xhr.send();
    }, 250);
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('grVendorACDropdown');
    var inp = document.getElementById('vendor_name');
    if (dd && !dd.contains(e.target) && e.target !== inp) dd.style.display = 'none';
});
</script>
