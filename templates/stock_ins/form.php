<?php
$userName = !empty($user['name']) ? $user['name'] : '系統管理者';
$userBranchId = !empty($user['branch_id']) ? $user['branch_id'] : '';
?>
<style>
.si-form .form-row{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px}
.si-form .form-row .form-group{flex:1 1 0;min-width:140px;margin-bottom:0}
</style>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>新增入庫單</h2>
    <a href="/stock_ins.php" class="btn btn-outline btn-sm">返回列表</a>
</div>

<form method="POST" action="/stock_ins.php?action=create" id="siForm">
    <input type="hidden" name="csrf_token" value="<?= e(Session::getCsrfToken()) ?>">

    <div class="card mb-2 si-form">
        <div class="card-header">入庫單資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>入庫單號</label>
                <input type="text" class="form-control" value="（自動產生）" disabled style="background:#f0f7ff;font-weight:600;color:var(--primary,#1565c0)">
            </div>
            <div class="form-group">
                <label>入庫日期 *</label>
                <input type="date" name="si_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
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
                <select name="warehouse_id" class="form-control" required>
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
                <input type="text" name="customer_name" id="siCustomerName" class="form-control" placeholder="搜尋客戶..." autocomplete="off" oninput="searchSiCustomer(this)">
                <div id="siCustomerDropdown" class="si-dropdown" style="display:none"></div>
            </div>
            <div class="form-group" style="position:relative">
                <label>廠商名稱</label>
                <input type="text" name="vendor_name" id="siVendorName" class="form-control" placeholder="搜尋廠商..." autocomplete="off" oninput="searchSiVendor(this)">
                <div id="siVendorDropdown" class="si-dropdown" style="display:none"></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>來源類型</label>
                <select name="source_type" class="form-control">
                    <option value="manual">手動入庫</option>
                    <option value="goods_receipt">進貨單</option>
                    <option value="return_material">餘料入庫</option>
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

    <div class="card mb-2 si-form">
        <div class="card-header d-flex justify-between align-center">
            <span>入庫明細</span>
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
                            <input type="hidden" name="items[0][product_id]" class="si-product-id" value="">
                            <input type="text" name="items[0][model]" class="form-control si-model" value="" readonly>
                        </td>
                        <td style="position:relative">
                            <input type="text" name="items[0][product_name]" class="form-control si-product-name" value="" autocomplete="off" placeholder="輸入關鍵字搜尋..." oninput="siSearchProduct(this)">
                            <div class="si-product-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
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
        <button type="submit" class="btn btn-primary">儲存入庫單</button>
        <a href="/stock_ins.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
function updateBranchName() {
    var sel = document.getElementById('branchSelect');
    document.getElementById('branchName').value = sel.options[sel.selectedIndex].text !== '請選擇' ? sel.options[sel.selectedIndex].text : '';
}
updateBranchName();

var itemIdx = 1;

function addItemRow() {
    var tbody = document.getElementById('itemBody');
    var tr = document.createElement('tr');
    var seq = tbody.querySelectorAll('tr').length + 1;
    tr.innerHTML = '<td class="item-seq">' + seq + '</td>'
        + '<td><input type="hidden" name="items['+itemIdx+'][product_id]" class="si-product-id" value=""><input type="text" name="items['+itemIdx+'][model]" class="form-control si-model" value="" readonly></td>'
        + '<td style="position:relative"><input type="text" name="items['+itemIdx+'][product_name]" class="form-control si-product-name" value="" autocomplete="off" placeholder="輸入關鍵字搜尋..." oninput="siSearchProduct(this)"><div class="si-product-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div></td>'
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

var siSearchTimer = null;
function siSearchProduct(inp) {
    clearTimeout(siSearchTimer);
    var q = inp.value.trim();
    var dd = inp.parentNode.querySelector('.si-product-dropdown');
    if (!dd) return;
    if (q.length < 1) { dd.style.display = 'none'; return; }
    siSearchTimer = setTimeout(function(){
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
                    + 'data-unit="' + (list[i].unit||'').replace(/"/g,'&quot;') + '" '
                    + 'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">'
                    + '<div style="font-weight:600">' + (list[i].name||'') + '</div>'
                    + '<div style="font-size:.75rem;color:#888">'
                    + (list[i].model ? '<span style="color:#1565c0">' + list[i].model + '</span> | ' : '')
                    + '$' + Number(list[i].price||0).toLocaleString()
                    + '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
        };
        xhr.send();
    }, 300);
}

document.addEventListener('click', function(e) {
    var item = e.target.closest('.si-product-dropdown > div[data-id]');
    if (item) {
        var row = item.closest('tr');
        row.querySelector('.si-product-name').value = item.dataset.name;
        row.querySelector('.si-model').value = item.dataset.model || '';
        var pidInp = row.querySelector('.si-product-id');
        if (pidInp) pidInp.value = item.dataset.id || '';
        var priceInp = row.querySelector('input[name*="[unit_price]"]');
        if (priceInp && item.dataset.price) priceInp.value = Math.round(Number(item.dataset.price));
        var unitInp = row.querySelector('input[name*="[unit]"]');
        if (unitInp && item.dataset.unit) unitInp.value = item.dataset.unit;
        item.closest('.si-product-dropdown').style.display = 'none';
        return;
    }
    if (!e.target.classList.contains('si-product-name')) {
        var dds = document.querySelectorAll('.si-product-dropdown');
        for (var i = 0; i < dds.length; i++) dds[i].style.display = 'none';
        var dds2 = document.querySelectorAll('.si-dropdown');
        for (var j = 0; j < dds2.length; j++) dds2[j].style.display = 'none';
    }
});

// 客戶搜尋
var siCustTimer = null;
function searchSiCustomer(input) {
    clearTimeout(siCustTimer);
    var dd = document.getElementById('siCustomerDropdown');
    dd.style.display = 'none';
    var q = input.value.trim();
    if (q.length < 2) return;
    siCustTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/cases.php?action=ajax_search_customer&keyword=' + encodeURIComponent(q));
        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
                if (!data.length) { dd.style.display = 'none'; return; }
                var html = '';
                for (var i = 0; i < Math.min(data.length, 10); i++) {
                    var c = data[i];
                    html += '<div class="si-dd-item" onclick="document.getElementById(\'siCustomerName\').value=\'' + c.name.replace(/'/g, "\\'") + '\';document.getElementById(\'siCustomerDropdown\').style.display=\'none\'">';
                    html += '<b>' + c.name + '</b>';
                    if (c.phone) html += ' <small>' + c.phone + '</small>';
                    html += '</div>';
                }
                dd.innerHTML = html;
                dd.style.display = 'block';
            } catch(e) {}
        };
        xhr.send();
    }, 300);
}

// 廠商搜尋
var siVendTimer = null;
function searchSiVendor(input) {
    clearTimeout(siVendTimer);
    var dd = document.getElementById('siVendorDropdown');
    dd.style.display = 'none';
    var q = input.value.trim();
    if (q.length < 2) return;
    siVendTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/stock_ins.php?action=ajax_search_vendor&keyword=' + encodeURIComponent(q));
        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
                if (!data.length) { dd.style.display = 'none'; return; }
                var html = '';
                for (var i = 0; i < Math.min(data.length, 10); i++) {
                    var v = data[i];
                    html += '<div class="si-dd-item" onclick="document.getElementById(\'siVendorName\').value=\'' + v.name.replace(/'/g, "\\'") + '\';document.getElementById(\'siVendorDropdown\').style.display=\'none\'">';
                    html += '<b>' + v.name + '</b>';
                    if (v.contact_person) html += ' <small>' + v.contact_person + '</small>';
                    html += '</div>';
                }
                dd.innerHTML = html;
                dd.style.display = 'block';
            } catch(e) {}
        };
        xhr.send();
    }, 300);
}
</script>
<style>
.si-dropdown { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid var(--gray-300); border-radius:6px; max-height:200px; overflow-y:auto; z-index:1000; box-shadow:0 4px 12px rgba(0,0,0,.15); }
.si-dd-item { padding:8px 12px; cursor:pointer; font-size:.85rem; border-bottom:1px solid var(--gray-100); }
.si-dd-item:hover { background:var(--gray-50); }
.si-dd-item:last-child { border-bottom:none; }
</style>
