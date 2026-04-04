<?php
$userName = !empty($user['name']) ? $user['name'] : '系統管理者';
$userBranchId = !empty($user['branch_id']) ? $user['branch_id'] : '';
?>
<style>
.so-form .form-row{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px}
.so-form .form-row .form-group{flex:1 1 0;min-width:140px;margin-bottom:0}
</style>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>新增出庫單</h2>
    <a href="/stock_outs.php" class="btn btn-outline btn-sm">返回列表</a>
</div>

<form method="POST" action="/stock_outs.php?action=create" id="soForm">
    <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">
    <input type="hidden" name="customer_id" id="customerId" value="">

    <div class="card mb-2 so-form">
        <div class="card-header">出庫單資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>出庫單號</label>
                <input type="text" class="form-control" value="（自動產生）" disabled style="background:#f0f7ff;font-weight:600;color:var(--primary,#1565c0)">
            </div>
            <div class="form-group">
                <label>預計出庫日期 *</label>
                <input type="date" name="so_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>分公司 *</label>
                <select name="branch_id" id="branchSelect" class="form-control" required onchange="updateBranchName()">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $br): ?>
                    <option value="<?= $br['id'] ?>" <?= $br['id'] == $userBranchId ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="branch_name" id="branchName" value="">
            </div>
            <div class="form-group">
                <label>倉庫 *</label>
                <select name="warehouse_id" id="soWarehouse" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php foreach ($warehouses as $wh): ?>
                    <option value="<?= $wh['id'] ?>"><?= e($wh['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>建立者</label>
                <input type="text" class="form-control" value="<?= e($userName) ?>" disabled style="background:#f5f5f5">
                <div class="text-muted" style="font-size:.75rem"><?= date('Y-m-d H:i') ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="position:relative">
                <label>客戶名稱</label>
                <input type="text" name="customer_name" id="customerName" class="form-control" autocomplete="off" placeholder="輸入客戶名稱或編號搜尋..." oninput="searchCustomer(this)">
                <div id="customerDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
            </div>
            <div class="form-group">
                <label>來源類型</label>
                <select name="source_type" id="sourceType" class="form-control">
                    <option value="manual">手動出庫</option>
                    <option value="case">案件出庫</option>
                    <option value="delivery_order">出貨單</option>
                    <option value="quotation">報價單</option>
                </select>
            </div>
            <div class="form-group">
                <label>來源單號</label>
                <input type="text" name="source_number" class="form-control" placeholder="選填" value="">
            </div>
            <div class="form-group" style="flex:2">
                <label>備註</label>
                <input type="text" name="note" class="form-control" placeholder="選填" value="">
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header d-flex justify-between align-center">
            <span>出庫明細</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addItemRow()">+ 新增品項</button>
        </div>
        <div class="table-responsive" style="overflow:visible">
            <table class="table" id="itemTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:130px">型號</th>
                        <th style="min-width:200px">品名</th>
                        <th style="width:100px">規格</th>
                        <th style="width:70px">單位</th>
                        <th style="width:80px">數量</th>
                        <th style="width:100px">單價</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="itemBody">
                    <tr>
                        <td class="item-seq">1</td>
                        <td>
                            <input type="hidden" name="items[0][product_id]" class="so-product-id" value="">
                            <input type="text" name="items[0][model]" class="form-control so-model" value="" readonly>
                        </td>
                        <td style="position:relative">
                            <input type="text" name="items[0][product_name]" class="form-control so-product-name" value="" autocomplete="off" placeholder="輸入關鍵字搜尋..." oninput="soSearchProduct(this)">
                            <div class="so-product-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
                        </td>
                        <td><input type="text" name="items[0][spec]" class="form-control" value=""></td>
                        <td><input type="text" name="items[0][unit]" class="form-control" value=""></td>
                        <td><input type="number" name="items[0][quantity]" class="form-control" step="1" min="1" value="1"></td>
                        <td><input type="number" name="items[0][unit_price]" class="form-control" step="1" min="0" value="0"></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-1">
        <button type="submit" class="btn btn-primary">儲存出庫單</button>
        <a href="/stock_outs.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
// ===== Branch name sync =====
function updateBranchName() {
    var sel = document.getElementById('branchSelect');
    document.getElementById('branchName').value = sel.options[sel.selectedIndex].text !== '-- 選擇分公司 --' ? sel.options[sel.selectedIndex].text : '';
}
updateBranchName();

// ===== Customer search =====
var custTimer = null;
function searchCustomer(inp) {
    clearTimeout(custTimer);
    var q = inp.value.trim();
    var dd = document.getElementById('customerDropdown');
    if (q.length < 1) { dd.style.display = 'none'; return; }
    custTimer = setTimeout(function(){
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/stock_outs.php?action=ajax_search_customer&keyword=' + encodeURIComponent(q));
        xhr.onload = function(){
            try { var list = JSON.parse(xhr.responseText); } catch(e) { return; }
            if (!list.length) { dd.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合客戶</div>'; dd.style.display = 'block'; return; }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" '
                    + 'data-id="' + (list[i].id||'') + '" '
                    + 'data-name="' + (list[i].name||'').replace(/"/g,'&quot;') + '" '
                    + 'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">'
                    + '<div style="font-weight:600">' + (list[i].name||'') + '</div>'
                    + '<div style="font-size:.75rem;color:#888">'
                    + (list[i].customer_no ? list[i].customer_no + ' | ' : '')
                    + (list[i].site_address || '')
                    + '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
        };
        xhr.send();
    }, 300);
}
document.addEventListener('click', function(e) {
    var item = e.target.closest('#customerDropdown > div[data-id]');
    if (item) {
        document.getElementById('customerName').value = item.dataset.name;
        document.getElementById('customerId').value = item.dataset.id;
        document.getElementById('customerDropdown').style.display = 'none';
        return;
    }
    if (e.target.id !== 'customerName') {
        document.getElementById('customerDropdown').style.display = 'none';
    }
});

// ===== Item rows =====
var itemIdx = 1;

function addItemRow() {
    var tbody = document.getElementById('itemBody');
    var tr = document.createElement('tr');
    var seq = tbody.querySelectorAll('tr').length + 1;
    tr.innerHTML = '<td class="item-seq">' + seq + '</td>'
        + '<td><input type="hidden" name="items['+itemIdx+'][product_id]" class="so-product-id" value=""><input type="text" name="items['+itemIdx+'][model]" class="form-control so-model" value="" readonly></td>'
        + '<td style="position:relative"><input type="text" name="items['+itemIdx+'][product_name]" class="form-control so-product-name" value="" autocomplete="off" placeholder="輸入關鍵字搜尋..." oninput="soSearchProduct(this)"><div class="so-product-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div></td>'
        + '<td><input type="text" name="items['+itemIdx+'][spec]" class="form-control" value=""></td>'
        + '<td><input type="text" name="items['+itemIdx+'][unit]" class="form-control" value=""></td>'
        + '<td><input type="number" name="items['+itemIdx+'][quantity]" class="form-control" step="1" min="1" value="1"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][unit_price]" class="form-control" step="1" min="0" value="0"></td>'
        + '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>';
    tbody.appendChild(tr);
    itemIdx++;
    reindex();
}

function removeItemRow(btn) {
    var tbody = document.getElementById('itemBody');
    if (tbody.querySelectorAll('tr').length <= 1) return;
    btn.closest('tr').remove();
    reindex();
}

function reindex() {
    var rows = document.querySelectorAll('#itemBody tr');
    for (var i = 0; i < rows.length; i++) {
        rows[i].querySelector('.item-seq').textContent = i + 1;
    }
}

// ===== Product search =====
var soSearchTimer = null;
function soSearchProduct(inp) {
    clearTimeout(soSearchTimer);
    var q = inp.value.trim();
    var dd = inp.parentNode.querySelector('.so-product-dropdown');
    if (!dd) return;
    if (q.length < 1) { dd.style.display = 'none'; return; }
    var whId = document.getElementById('soWarehouse').value;
    soSearchTimer = setTimeout(function(){
        var xhr = new XMLHttpRequest();
        var url = '/stock_outs.php?action=ajax_products&keyword=' + encodeURIComponent(q);
        if (whId) url += '&warehouse_id=' + whId;
        xhr.open('GET', url);
        xhr.onload = function(){
            try { var list = JSON.parse(xhr.responseText); } catch(e) { dd.innerHTML = '<div style="padding:8px;color:#c62828;font-size:.85rem">搜尋錯誤</div>'; dd.style.display = 'block'; return; }
            if (!list.length) { dd.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合產品</div>'; dd.style.display = 'block'; return; }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                var stockQty = Number(list[i].stock_qty||0);
                html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" '
                    + 'data-id="' + (list[i].id||'') + '" '
                    + 'data-name="' + (list[i].name||'').replace(/"/g,'&quot;') + '" '
                    + 'data-model="' + (list[i].model||'').replace(/"/g,'&quot;') + '" '
                    + 'data-price="' + (list[i].cost||list[i].price||0) + '" '
                    + 'data-unit="' + (list[i].unit||'').replace(/"/g,'&quot;') + '" '
                    + 'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">'
                    + '<div style="font-weight:600">' + (list[i].name||'') + '</div>'
                    + '<div style="font-size:.75rem;color:#888">'
                    + (list[i].model ? '<span style="color:#1565c0">' + list[i].model + '</span> | ' : '')
                    + '$' + Number(list[i].cost||list[i].price||0).toLocaleString()
                    + ' | <span style="color:' + (stockQty > 0 ? '#2e7d32' : '#c62828') + '">庫存:' + stockQty + '</span>'
                    + '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
        };
        xhr.send();
    }, 300);
}

document.addEventListener('click', function(e) {
    var item = e.target.closest('.so-product-dropdown > div[data-id]');
    if (item) {
        var row = item.closest('tr');
        row.querySelector('.so-product-name').value = item.dataset.name;
        row.querySelector('.so-model').value = item.dataset.model || '';
        var pidInp = row.querySelector('.so-product-id');
        if (pidInp) pidInp.value = item.dataset.id || '';
        var priceInp = row.querySelector('input[name*="[unit_price]"]');
        if (priceInp && item.dataset.price) priceInp.value = Math.round(Number(item.dataset.price));
        var unitInp = row.querySelector('input[name*="[unit]"]');
        if (unitInp && item.dataset.unit) unitInp.value = item.dataset.unit;
        item.closest('.so-product-dropdown').style.display = 'none';
        return;
    }
    if (!e.target.classList.contains('so-product-name')) {
        var dds = document.querySelectorAll('.so-product-dropdown');
        for (var i = 0; i < dds.length; i++) dds[i].style.display = 'none';
    }
});
</script>
