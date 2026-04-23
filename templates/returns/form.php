<?php $isEdit = !empty($record); ?>
<?php require __DIR__ . '/../layouts/_pack_unit_js.php'; ?>

<h2><?= $isEdit ? '編輯退貨單 - ' . e($record['return_number']) : '新增退貨單' ?></h2>

<form method="POST" class="mt-2" id="returnForm" onsubmit="return hswPackUnitPrepareSubmit(this)">
    <?= csrf_field() ?>

    <!-- 退貨單資訊 -->
    <div class="card">
        <div class="card-header">退貨單資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>退貨單號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? $record['return_number'] : peek_next_doc_number('returns')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group">
                <label>退貨日期 *</label>
                <input type="date" max="2099-12-31" name="return_date" class="form-control"
                       value="<?= e($isEdit && !empty($record['return_date']) ? $record['return_date'] : date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>退貨類型 *</label>
                <select name="return_type" id="returnType" class="form-control" required>
                    <?php foreach (ReturnModel::returnTypeOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= ($isEdit && !empty($record['return_type']) ? $record['return_type'] : 'customer_return') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" id="branchSelect" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($isEdit && !empty($record['branch_id']) ? $record['branch_id'] : '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>倉庫 *</label>
                <select name="warehouse_id" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($warehouses as $wh): ?>
                    <option value="<?= $wh['id'] ?>" <?= ($isEdit && !empty($record['warehouse_id']) ? $record['warehouse_id'] : '') == $wh['id'] ? 'selected' : '' ?>><?= e($wh['name']) ?><?= !empty($wh['branch_name']) ? ' (' . e($wh['branch_name']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group customer-field" id="customerField" style="position:relative">
                <label>客戶名稱</label>
                <input type="text" name="customer_name" id="rtCustomerInput" class="form-control"
                       value="<?= e($isEdit && !empty($record['customer_name']) ? $record['customer_name'] : '') ?>"
                       autocomplete="off" placeholder="搜尋客戶..." oninput="searchRtCustomer(this)">
                <div id="rtCustomerDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid var(--gray-200);border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
            </div>
            <div class="form-group" style="position:relative">
                <label>廠商名稱 <span style="color:#c62828">*</span> <small style="color:#888;font-weight:normal">(必須從廠商管理選擇)</small></label>
                <input type="text" name="vendor_name" id="rtVendorInput" class="form-control" required
                       value="<?= e($isEdit && !empty($record['vendor_name']) ? $record['vendor_name'] : '') ?>" autocomplete="off" placeholder="輸入關鍵字搜尋廠商">
                <input type="hidden" name="vendor_id" id="rtVendorId" value="<?= e($isEdit && !empty($record['vendor_id']) ? $record['vendor_id'] : '') ?>">
                <div id="rtVendorDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid var(--gray-200);border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
                <small style="color:#888;font-size:.7rem">⚠ 找不到請先到 <a href="/vendors.php" target="_blank">廠商管理</a> 建立</small>
            </div>
            <div class="form-group">
                <label>來源單據類型</label>
                <input type="text" name="reference_type" class="form-control"
                       value="<?= e($isEdit && !empty($record['reference_type']) ? $record['reference_type'] : '') ?>" placeholder="如: 採購單, 出庫單">
            </div>
            <div class="form-group">
                <label>來源單據ID</label>
                <input type="text" name="reference_id" class="form-control"
                       value="<?= e($isEdit && !empty($record['reference_id']) ? $record['reference_id'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label>退貨原因</label>
                <input type="text" name="reason" class="form-control"
                       value="<?= e($isEdit && !empty($record['reason']) ? $record['reason'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label>備註</label>
                <textarea name="note" class="form-control" rows="2"><?= e($isEdit && !empty($record['note']) ? $record['note'] : '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- 退貨明細 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>退貨明細</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addItem()">+ 新增品項</button>
        </div>
        <div class="table-responsive">
            <table class="table" id="itemsTable">
                <thead>
                    <tr>
                        <th style="width:50px">序號</th>
                        <th style="min-width:120px">型號</th>
                        <th style="min-width:180px">品名</th>
                        <th style="width:80px">單位</th>
                        <th style="width:80px">數量</th>
                        <th style="width:100px">未稅單價</th>
                        <th style="width:110px">未稅總額</th>
                        <th style="width:90px">稅額</th>
                        <th style="width:110px">小計</th>
                        <th style="min-width:120px">原因</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-right"><strong>合計</strong></td>
                        <td><strong id="untaxedTotalDisplay" style="color:var(--primary)">$0</strong></td>
                        <td></td>
                        <td><strong id="totalDisplay">$0</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <input type="hidden" name="total_amount" id="totalAmount" value="<?= e($isEdit && !empty($record['total_amount']) ? $record['total_amount'] : '0') ?>">
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary">儲存</button>
        <a href="/returns.php" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
.form-row .form-group { flex: 1; min-width: 180px; margin-bottom: 0; }
/* vendor-field always visible now */
</style>

<script>
var existingItems = <?= json_encode(!empty($items) ? $items : array()) ?>;
var rowIndex = 0;

function toggleTypeFields() {
    var type = document.getElementById('returnType').value;
    var cFields = document.querySelectorAll('.customer-field');
    var vFields = document.querySelectorAll('.vendor-field');
    var i;
    for (i = 0; i < cFields.length; i++) {
        cFields[i].style.display = (type === 'customer_return') ? '' : 'none';
    }
    for (i = 0; i < vFields.length; i++) {
        vFields[i].style.display = (type === 'vendor_return') ? '' : 'none';
    }
}

function addItem(data) {
    var tbody = document.getElementById('itemsBody');
    var tr = document.createElement('tr');
    tr.className = 'pack-unit-row';
    var idx = rowIndex++;
    var productId = (data && data.product_id) ? data.product_id : '';
    var model = (data && data.model) ? data.model : '';
    var productName = (data && data.product_name) ? data.product_name : '';
    var baseUnit = (data && data.unit) ? data.unit : '';
    var packUnit = (data && data.pack_unit) ? data.pack_unit : '';
    var packQty = (data && data.pack_qty) ? data.pack_qty : '';
    var inputUnit = (data && data.input_unit) ? data.input_unit : '';
    var rawInputQty = (data && data.input_qty !== undefined && data.input_qty !== null && data.input_qty !== '') ? data.input_qty : null;
    var quantity = (data && data.quantity) ? parseFloat(data.quantity) || 0 : 0;
    var displayUnit = inputUnit || baseUnit;
    var displayQty = rawInputQty !== null ? rawInputQty : (quantity || '');
    var price = (data && data.unit_price) ? parseInt(data.unit_price) || '' : '';
    var tax = (data && data.tax_amount) ? parseInt(data.tax_amount) || '' : '';
    var amount = (data && data.amount) ? parseInt(data.amount) || '' : '';
    var reason = (data && data.reason) ? data.reason : '';

    var untaxedTotal = (displayQty && price) ? (displayQty * price) : '';
    tr.innerHTML = '<td>' + (idx + 1) + '</td>' +
        '<td><input type="hidden" name="items[' + idx + '][product_id]" class="rt-product-id" value="' + escHtml(productId) + '"><input type="text" name="items[' + idx + '][model]" class="form-control rt-model" value="' + escHtml(model) + '" placeholder="型號" readonly></td>' +
        '<td style="position:relative"><input type="text" name="items[' + idx + '][product_name]" class="form-control rt-product-name" value="' + escHtml(productName) + '" placeholder="輸入關鍵字搜尋..." autocomplete="off" oninput="searchProduct(this)"><div class="rt-product-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div></td>' +
        '<td><select class="form-control pack-unit-select" data-preselect="' + escHtml(displayUnit) + '" data-base-unit="' + escHtml(baseUnit) + '" data-pack-unit="' + escHtml(packUnit) + '" data-pack-qty="' + packQty + '"></select>' +
            '<input type="hidden" name="items[' + idx + '][unit]" class="pack-unit-hidden-unit" value="' + escHtml(baseUnit) + '">' +
            '<input type="hidden" name="items[' + idx + '][input_unit]" class="pack-unit-hidden-input-unit" value="' + escHtml(inputUnit) + '">' +
            '<input type="hidden" name="items[' + idx + '][input_qty]" class="pack-unit-hidden-input-qty" value="' + (rawInputQty !== null ? escHtml(rawInputQty) : '') + '"></td>' +
        '<td><input type="number" class="form-control pack-unit-qty item-qty" value="' + escHtml(displayQty) + '" min="0" step="any" oninput="hswPackUnitRowSync(this); calcRow(this)">' +
            '<input type="hidden" name="items[' + idx + '][quantity]" class="pack-unit-hidden-qty" value="' + quantity + '">' +
            '<div class="pack-unit-hint" style="display:none"></div></td>' +
        '<td><input type="number" name="items[' + idx + '][unit_price]" class="form-control item-price" value="' + escHtml(price) + '" min="0" step="1" oninput="calcRow(this)"></td>' +
        '<td><input type="number" class="form-control item-untaxed" value="' + escHtml(untaxedTotal) + '" readonly style="background:#f5f5f5"></td>' +
        '<td><input type="number" name="items[' + idx + '][tax_amount]" class="form-control item-tax" value="' + escHtml(tax) + '" min="0" step="1" readonly style="background:#f5f5f5"></td>' +
        '<td><input type="number" name="items[' + idx + '][amount]" class="form-control item-amount" value="' + escHtml(amount) + '" min="0" step="1" readonly></td>' +
        '<td><input type="text" name="items[' + idx + '][reason]" class="form-control" value="' + escHtml(reason) + '" placeholder="原因"></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">&times;</button></td>';
    tbody.appendChild(tr);
    // 初始化該列單位 select
    if (typeof hswPackUnitInitFromDOM === 'function') hswPackUnitInitFromDOM(tr);
    calcTotal();
}

function removeItem(btn) {
    btn.closest('tr').remove();
    calcTotal();
}

function calcRow(el) {
    var tr = el.closest('tr');
    var qty = parseFloat(tr.querySelector('.item-qty').value) || 0;
    var price = parseInt(tr.querySelector('.item-price').value) || 0;
    var untaxed = Math.round(qty * price);
    var tax = Math.round(untaxed * 0.05);
    tr.querySelector('.item-untaxed').value = untaxed || '';
    tr.querySelector('.item-tax').value = tax;
    tr.querySelector('.item-amount').value = untaxed + tax;
    calcTotal();
}

function calcTotal() {
    var amounts = document.querySelectorAll('.item-amount');
    var total = 0;
    for (var i = 0; i < amounts.length; i++) {
        total += parseInt(amounts[i].value) || 0;
    }
    document.getElementById('totalAmount').value = total;
    document.getElementById('totalDisplay').textContent = '$' + total.toLocaleString();

    // 未稅總額合計
    var untaxedEls = document.querySelectorAll('.item-untaxed');
    var untaxedSum = 0;
    for (var j = 0; j < untaxedEls.length; j++) {
        untaxedSum += parseInt(untaxedEls[j].value) || 0;
    }
    var disp = document.getElementById('untaxedTotalDisplay');
    if (disp) disp.textContent = '$' + untaxedSum.toLocaleString();
}

function escHtml(str) {
    if (!str && str !== 0) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Init
document.getElementById('returnType').addEventListener('change', toggleTypeFields);
toggleTypeFields();

if (existingItems.length > 0) {
    for (var i = 0; i < existingItems.length; i++) {
        addItem(existingItems[i]);
    }
} else {
    addItem();
}

// ---- 產品搜尋 ----
var rtSearchTimer = null;
function searchProduct(inp) {
    clearTimeout(rtSearchTimer);
    var q = inp.value.trim();
    var dd = inp.parentNode.querySelector('.rt-product-dropdown');
    if (q.length < 1) { dd.style.display = 'none'; return; }
    rtSearchTimer = setTimeout(function(){
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/requisitions.php?action=ajax_search_product&q=' + encodeURIComponent(q));
        xhr.onload = function(){
            var list = JSON.parse(xhr.responseText);
            if (!list.length) { dd.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合產品，可直接手動輸入</div>'; dd.style.display = 'block'; return; }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" data-id="' + (list[i].id||'') + '" data-name="' + escHtml(list[i].name||'') + '" data-model="' + escHtml(list[i].model||'') + '" data-price="' + (list[i].price||0) + '" data-unit="' + escHtml(list[i].unit||'') + '" data-pack-unit="' + escHtml(list[i].pack_unit||'') + '" data-pack-qty="' + (list[i].pack_qty||'') + '" onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">' +
                    '<div style="font-weight:600">' + escHtml(list[i].name||'') + '</div>' +
                    '<div style="font-size:.75rem;color:#888">' +
                    (list[i].model ? '<span style="color:#1565c0">' + escHtml(list[i].model) + '</span> | ' : '') +
                    '$' + Number(list[i].price||0).toLocaleString() +
                    ' | <span style="color:' + (Number(list[i].stock||0) > 0 ? '#2e7d32' : '#c62828') + '">庫存:' + Number(list[i].stock||0) + '</span>' +
                    (list[i].pack_unit && list[i].pack_qty ? ' | 1'+list[i].pack_unit+'='+list[i].pack_qty+(list[i].unit||'') : '') +
                    '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
        };
        xhr.send();
    }, 300);
}
document.addEventListener('click', function(e) {
    // 產品下拉選取
    var item = e.target.closest('.rt-product-dropdown > div[data-name]');
    if (item) {
        var row = item.closest('tr');
        row.querySelector('.rt-product-name').value = item.dataset.name;
        row.querySelector('.rt-model').value = item.dataset.model || '';
        var pidEl = row.querySelector('.rt-product-id');
        if (pidEl) pidEl.value = item.dataset.id || '';
        var priceEl = row.querySelector('.item-price');
        if (priceEl && item.dataset.price) priceEl.value = Math.round(Number(item.dataset.price));
        hswPackUnitSetupRow(row, {
            unit: item.dataset.unit || '',
            pack_unit: item.dataset.packUnit || '',
            pack_qty: item.dataset.packQty || 0
        });
        calcRow(row.querySelector('.item-qty'));
        item.closest('.rt-product-dropdown').style.display = 'none';
        return;
    }
    // 關閉所有下拉
    if (!e.target.classList.contains('rt-product-name')) {
        var dds = document.querySelectorAll('.rt-product-dropdown');
        for (var i = 0; i < dds.length; i++) dds[i].style.display = 'none';
    }
    if (e.target.id !== 'rtVendorInput') {
        var vdd = document.getElementById('rtVendorDropdown');
        if (vdd) vdd.style.display = 'none';
    }
});

// ---- 廠商搜尋（強制必選）----
(function(){
    var inp = document.getElementById('rtVendorInput');
    var dd = document.getElementById('rtVendorDropdown');
    var idEl = document.getElementById('rtVendorId');
    if (!inp || !dd) return;
    var timer = null;
    inp.addEventListener('input', function(){
        if (idEl) idEl.value = '';  // 改字立刻清掉 vendor_id
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 1) { dd.style.display = 'none'; return; }
        timer = setTimeout(function(){
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/payments_out.php?action=ajax_vendor_search&q=' + encodeURIComponent(q));
            xhr.onload = function(){
                var list;
                try { list = JSON.parse(xhr.responseText); } catch(e) { return; }
                if (!list.length) {
                    dd.innerHTML = '<div style="padding:8px;color:#c62828;font-size:.85rem">無符合廠商，請先到 <a href="/vendors.php" target="_blank">廠商管理</a> 建立</div>';
                    dd.style.display = 'block';
                    return;
                }
                var html = '';
                for (var i = 0; i < list.length; i++) {
                    html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" data-id="' + (list[i].id||'') + '" data-name="' + escHtml(list[i].name||'') + '" onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">' +
                        '<div style="font-weight:600">' + escHtml(list[i].name||'') + '</div>' +
                        (list[i].contact_person ? '<div style="font-size:.75rem;color:#888">' + escHtml(list[i].contact_person) + '</div>' : '') + '</div>';
                }
                dd.innerHTML = html;
                dd.style.display = 'block';
            };
            xhr.send();
        }, 300);
    });
    dd.addEventListener('click', function(e){
        var item = e.target.closest('div[data-id]');
        if (item) {
            inp.value = item.dataset.name;
            if (idEl) idEl.value = item.dataset.id;
            dd.style.display = 'none';
        }
    });
})();

// ---- 提交前驗證：廠商必須有 ID ----
(function(){
    var form = document.getElementById('returnForm');
    if (!form) return;
    form.addEventListener('submit', function(e){
        var vname = document.getElementById('rtVendorInput');
        var vid = document.getElementById('rtVendorId');
        if (!vname || !vname.value.trim()) {
            e.preventDefault();
            alert('請輸入廠商');
            return false;
        }
        if (!vid || !vid.value || parseInt(vid.value) <= 0) {
            e.preventDefault();
            alert('廠商必須從下拉清單選擇，不可手動輸入。\n找不到請先到「廠商管理」建立。');
            return false;
        }
    });
})();

// 客戶即時搜尋
var rtCustTimer = null;
function searchRtCustomer(input) {
    clearTimeout(rtCustTimer);
    var dd = document.getElementById('rtCustomerDropdown');
    dd.style.display = 'none';
    var q = input.value.trim();
    if (q.length < 2) return;
    rtCustTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/cases.php?action=ajax_search_customer&keyword=' + encodeURIComponent(q));
        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
                if (!data.length) { dd.style.display = 'none'; return; }
                var html = '';
                for (var i = 0; i < Math.min(data.length, 10); i++) {
                    var c = data[i];
                    html += '<div data-name="' + c.name.replace(/"/g, '&quot;') + '" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f1f1;font-size:.85rem">';
                    html += '<b>' + c.name + '</b>';
                    if (c.phone) html += ' <small style="color:#888">' + c.phone + '</small>';
                    if (c.customer_no) html += ' <small style="color:#1976d2">' + c.customer_no + '</small>';
                    html += '</div>';
                }
                dd.innerHTML = html;
                dd.style.display = 'block';
                dd.querySelectorAll('div[data-name]').forEach(function(item) {
                    item.addEventListener('click', function() {
                        input.value = this.getAttribute('data-name');
                        dd.style.display = 'none';
                    });
                    item.addEventListener('mouseenter', function() { this.style.background = '#f5f5f5'; });
                    item.addEventListener('mouseleave', function() { this.style.background = ''; });
                });
            } catch(e) {}
        };
        xhr.send();
    }, 300);
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('#rtCustomerDropdown') && e.target.id !== 'rtCustomerInput') {
        document.getElementById('rtCustomerDropdown').style.display = 'none';
    }
});
</script>
