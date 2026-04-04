<?php
$isEdit = !empty($record);
$statusOptions = ProcurementModel::requisitionStatusOptions();
$urgencyOptions = ProcurementModel::urgencyOptions();
?>
<?php
$reqStatus = $isEdit ? ($record['status'] ?? '') : '';
$isPending = ($reqStatus === '簽核中');
$isApprover = $isPending && Auth::hasPermission('approvals.manage');
$canSubmitApproval = $isEdit && !in_array($reqStatus, array('簽核中', '已核准', '簽核完成', '已轉採購'));
$isLocked = $isEdit && in_array($reqStatus, array('簽核中', '已核准', '簽核完成', '已轉採購'));
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2 style="margin:0"><?= $isEdit ? '編輯請購單 - ' . e($record['requisition_number']) : '新增請購單' ?></h2>
        <?php if ($isEdit && $reqStatus): ?>
        <div style="margin-top:4px">
            <?php if ($reqStatus === '已核准'): ?>
            <span class="badge badge-success"><?= e($reqStatus) ?></span>
            <?php elseif ($reqStatus === '簽核中'): ?>
            <span class="badge badge-info"><?= e($reqStatus) ?></span>
            <?php elseif ($reqStatus === '退回'): ?>
            <span class="badge badge-danger"><?= e($reqStatus) ?></span>
            <?php else: ?>
            <span class="badge"><?= e($reqStatus) ?></span>
            <?php endif; ?>
            <?php if (!empty($record['approval_user'])): ?>
            <span class="text-muted" style="font-size:.85rem">簽核人：<?= e($record['approval_user']) ?> <?= !empty($record['approval_date']) ? '(' . $record['approval_date'] . ')' : '' ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <?php if ($canSubmitApproval): ?>
        <button type="button" class="btn btn-sm" style="background:#2196F3;color:#fff"
           onclick="if(confirm('確定送出簽核？')){document.getElementById('submit_after_save').value='1';document.getElementById('reqForm').submit();}">送簽核</button>
        <?php endif; ?>
        <a href="/requisitions.php" class="btn btn-outline btn-sm">返回列表</a>
    </div>
</div>

<?php if ($isApprover): ?>
<!-- 簽核人操作區 -->
<div class="card" style="border-left:4px solid #2196F3;margin-bottom:16px">
    <div class="card-header" style="color:#2196F3;font-weight:700">簽核操作</div>
    <p style="font-size:.9rem;color:#666;margin-bottom:12px">請檢查品項並填寫覆核數量後核准或退回。</p>
    <form method="POST" action="/requisitions.php?action=approve&id=<?= $record['id'] ?>">
        <?= csrf_field() ?>
        <div class="table-responsive" style="overflow:visible">
            <table class="table">
                <thead>
                    <tr><th>品項</th><th>型號</th><th style="width:80px">請購數量</th><th style="width:100px">覆核數量</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $item): ?>
                    <tr>
                        <td><?= e($item['product_name'] ?? '') ?></td>
                        <td><?= e($item['model'] ?? '') ?></td>
                        <td><?= (int)($item['quantity'] ?? 0) ?></td>
                        <td>
                            <input type="hidden" name="items[<?= $idx ?>][id]" value="<?= $item['id'] ?>">
                            <input type="number" name="items[<?= $idx ?>][approved_qty]" class="form-control" value="<?= (int)($item['approved_qty'] ?? $item['quantity'] ?? 0) ?>" min="0" style="width:80px">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-group" style="margin-top:8px">
            <label>簽核備註</label>
            <input type="text" name="approval_note" class="form-control" placeholder="選填">
        </div>
        <div class="d-flex gap-1 mt-1">
            <button type="submit" class="btn btn-success">核准</button>
            <button type="button" class="btn btn-danger" onclick="rejectRequisition()">退回</button>
        </div>
    </form>
</div>
<script>
function rejectRequisition() {
    var reason = prompt('退回原因：');
    if (reason === null) return;
    location.href = '/requisitions.php?action=reject&id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>&reason=' + encodeURIComponent(reason);
}
</script>
<?php endif; ?>

<form method="POST" action="/requisitions.php?action=<?= $isEdit ? 'edit&id=' . $record['id'] : 'create' ?>" class="mt-2" id="reqForm">
<input type="hidden" name="submit_after_save" id="submit_after_save" value="0">
    <?= csrf_field() ?>

    <!-- 請購資訊 -->
    <div class="card">
        <div class="card-header">請購資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>請購單號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? $record['requisition_number'] : peek_next_doc_number('requisitions')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group">
                <label>請購日期 *</label>
                <input type="date" max="2099-12-31" name="requisition_date" class="form-control" value="<?= e(!empty($record['requisition_date']) ? $record['requisition_date'] : date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>請購人 *</label>
                <?php
                $reqUsers = Database::getInstance()->query("SELECT id, real_name, role FROM users WHERE is_active = 1 AND role IN ('sales','sales_assistant','admin_staff') ORDER BY role, real_name")->fetchAll(PDO::FETCH_ASSOC);
                $roleLabels = array('sales' => '業務', 'sales_assistant' => '業務助理', 'admin_staff' => '行政人員');
                $curRequester = !empty($record['requester_name']) ? $record['requester_name'] : (Auth::user()['real_name'] ?? '');
                ?>
                <select name="requester_name" class="form-control" required>
                    <option value="">請選擇</option>
                    <?php $lastRole = ''; foreach ($reqUsers as $ru):
                        if ($ru['role'] !== $lastRole): $lastRole = $ru['role']; ?>
                    <optgroup label="<?= e($roleLabels[$ru['role']] ?? $ru['role']) ?>">
                    <?php endif; ?>
                    <option value="<?= e($ru['real_name']) ?>" <?= $curRequester === $ru['real_name'] ? 'selected' : '' ?>><?= e($ru['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>請購分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= (!empty($record['branch_id']) && $record['branch_id'] == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>業務人員</label>
                <?php
                $salesUsers = Database::getInstance()->query("SELECT id, real_name FROM users WHERE is_active = 1 AND is_sales = 1 ORDER BY real_name")->fetchAll(PDO::FETCH_ASSOC);
                $curSales = !empty($record['sales_name']) ? $record['sales_name'] : '';
                ?>
                <select name="sales_name" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($salesUsers as $su): ?>
                    <option value="<?= e($su['real_name']) ?>" <?= $curSales === $su['real_name'] ? 'selected' : '' ?>><?= e($su['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>緊急程度</label>
                <select name="urgency" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($urgencyOptions as $uv => $ul): ?>
                    <option value="<?= e($uv) ?>" <?= (!empty($record['urgency']) && $record['urgency'] === $uv) ? 'selected' : '' ?>><?= e($ul) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>案名</label>
                <input type="text" name="case_name" id="reqCaseName" class="form-control" value="<?= e(!empty($record['case_name']) ? $record['case_name'] : '') ?>" placeholder="選擇報價單自動帶入或手動輸入">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>報價單號</label>
                <div style="position:relative">
                    <input type="text" name="quotation_number" id="reqQuoteInput" class="form-control" value="<?= e(!empty($record['quotation_number']) ? $record['quotation_number'] : '') ?>" placeholder="點擊選擇或手動輸入..." autocomplete="off">
                    <div id="reqQuoteDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid var(--gray-200);border-radius:6px;max-height:250px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
                </div>
                <script>
                (function(){
                    var inp = document.getElementById('reqQuoteInput');
                    var dd = document.getElementById('reqQuoteDropdown');
                    var timer = null;
                    function searchQuote(q) {
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', '/requisitions.php?action=ajax_search_quotation&q=' + encodeURIComponent(q));
                        xhr.onload = function(){
                            var list = JSON.parse(xhr.responseText);
                            if (!list.length) { dd.innerHTML='<div style="padding:8px;color:#999;font-size:.85rem">無符合報價單</div>'; dd.style.display='block'; return; }
                            renderList(list);
                        };
                        xhr.send();
                    }
                    function renderList(list) {
                        var html = '';
                        for (var i=0;i<list.length;i++){
                            html += '<div style="padding:8px 12px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" ' +
                                'data-num="' + (list[i].quotation_number||'') + '" data-name="' + (list[i].customer_name||'') + '" ' +
                                'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'" onclick="selectQuote(this)">' +
                                '<div><strong>' + (list[i].quotation_number||'') + '</strong> <span style="color:#888">$' + Number(list[i].total_amount||0).toLocaleString() + '</span></div>' +
                                '<div style="font-size:.8rem;color:#666">' + (list[i].customer_name||'') + '</div></div>';
                        }
                        dd.innerHTML = html;
                        dd.style.display = 'block';
                    }
                    // 點擊輸入框：顯示已接受的報價單
                    inp.addEventListener('click', function(){
                        searchQuote(this.value.trim());
                    });
                    // 輸入搜尋
                    inp.addEventListener('input', function(){
                        clearTimeout(timer);
                        var q = this.value.trim();
                        if (q.length < 1) { searchQuote(''); return; }
                        timer = setTimeout(function(){ searchQuote(q); }, 300);
                    });
                    window.selectQuote = function(el) {
                        inp.value = el.dataset.num;
                        var cn = document.getElementById('reqCaseName');
                        if (cn) cn.value = el.dataset.name;
                        dd.style.display = 'none';
                    };
                    document.addEventListener('click', function(e){ if(!e.target.closest('#reqQuoteInput')&&!e.target.closest('#reqQuoteDropdown')) dd.style.display='none'; });
                })();
                </script>
            </div>
            <div class="form-group" style="position:relative">
                <label>廠商名稱</label>
                <input type="text" name="vendor_name" id="vendorNameInput" class="form-control" value="<?= e(!empty($record['vendor_name']) ? $record['vendor_name'] : '') ?>" autocomplete="off" placeholder="輸入關鍵字搜尋或手動輸入">
                <div id="vendorDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid var(--gray-200);border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>期望交貨日</label>
                <input type="date" max="2099-12-31" name="expected_date" class="form-control" value="<?= e(!empty($record['expected_date']) ? $record['expected_date'] : '') ?>">
            </div>
            <div class="form-group"></div>
        </div>
    </div>

    <!-- 簽核資訊（唯讀，由系統自動帶出） -->
    <?php if ($isEdit): ?>
    <div class="card">
        <div class="card-header">簽核資訊</div>
        <input type="hidden" name="status" value="<?= e($record['status'] ?? '') ?>">
        <input type="hidden" name="approval_user" value="<?= e($record['approval_user'] ?? '') ?>">
        <input type="hidden" name="approval_date" value="<?= e($record['approval_date'] ?? '') ?>">
        <input type="hidden" name="approval_note" value="<?= e($record['approval_note'] ?? '') ?>">
        <div class="form-row">
            <div class="form-group">
                <label>狀態</label>
                <div class="form-static" style="font-weight:600;font-size:1rem">
                    <?php
                    $st = $record['status'] ?? '';
                    if ($st === '已核准') echo '<span style="color:#2e7d32">✓ 已核准</span>';
                    elseif ($st === '簽核中') echo '<span style="color:#1565c0">⏳ 簽核中</span>';
                    elseif ($st === '退回') echo '<span style="color:#c62828">✗ 退回</span>';
                    else echo e($st ?: '草稿');
                    ?>
                </div>
            </div>
            <div class="form-group">
                <label>簽核人</label>
                <div class="form-static"><?= e($record['approval_user'] ?? '-') ?></div>
            </div>
            <div class="form-group">
                <label>簽核日期</label>
                <div class="form-static"><?= !empty($record['approval_date']) ? e($record['approval_date']) : '-' ?></div>
            </div>
            <div class="form-group">
                <label>簽核備註</label>
                <div class="form-static"><?= e($record['approval_note'] ?? '-') ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 請購商品 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>請購商品</span>
            <?php if (!$isLocked): ?>
            <button type="button" class="btn btn-outline btn-sm" onclick="addItemRow()">+ 新增品項</button>
            <?php endif; ?>
        </div>
        <div class="table-responsive" style="overflow:visible">
            <table class="table" id="itemsTable">
                <thead>
                    <tr>
                        <th style="width:50px">項次</th>
                        <th style="width:150px">商品型號</th>
                        <th style="min-width:200px">商品名稱</th>
                        <th style="width:100px">數量</th>
                        <th style="width:120px">單價</th>
                        <th style="width:110px">小計</th>
                        <th style="min-width:120px">用途說明</th>
                        <th style="width:80px">覆核數量</th>
                        <th style="width:60px">操作</th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <?php
                    $itemList = !empty($items) ? $items : array();
                    if (empty($itemList)) {
                        $itemList = array(array('model' => '', 'product_name' => '', 'quantity' => '', 'unit_price' => '', 'purpose' => '', 'approved_qty' => ''));
                    }
                    foreach ($itemList as $idx => $item):
                    $itemQty = (float)(!empty($item['quantity']) ? $item['quantity'] : 0);
                    $itemPrice = (float)(!empty($item['unit_price']) ? $item['unit_price'] : 0);
                    $itemSubtotal = $itemQty * $itemPrice;
                    ?>
                    <tr class="item-row">
                        <td class="item-seq" style="text-align:center"><?= $idx + 1 ?></td>
                        <td><input type="hidden" name="items[<?= $idx ?>][product_id]" class="req-product-id" value="<?= e(!empty($item['product_id']) ? $item['product_id'] : '') ?>"><input type="text" name="items[<?= $idx ?>][model]" class="form-control req-model" value="<?= e(!empty($item['model']) ? $item['model'] : '') ?>"></td>
                        <td style="position:relative">
                            <input type="text" name="items[<?= $idx ?>][product_name]" class="form-control req-product-name" value="<?= e(!empty($item['product_name']) ? $item['product_name'] : '') ?>" placeholder="輸入關鍵字搜尋..." autocomplete="off">
                            <div class="req-product-dropdown" style="display:none;position:absolute;top:100%;left:0;z-index:1000;background:#fff;border:1px solid var(--gray-300);border-radius:6px;max-height:250px;min-width:350px;overflow-y:auto;box-shadow:0 6px 20px rgba(0,0,0,.2)"></div>
                        </td>
                        <td><input type="number" name="items[<?= $idx ?>][quantity]" class="form-control req-qty" min="1" step="1" value="<?= !empty($item['quantity']) ? (int)$item['quantity'] : '' ?>" oninput="calcReqRow(this)" required></td>
                        <td><input type="text" name="items[<?= $idx ?>][unit_price]" class="form-control req-price" value="<?= !empty($item['unit_price']) ? (int)$item['unit_price'] : '' ?>" oninput="calcReqRow(this)" inputmode="numeric"></td>
                        <td class="req-subtotal text-right" style="font-weight:600"><?= $itemSubtotal > 0 ? '$' . number_format($itemSubtotal) : ($itemPrice > 0 ? '$' . number_format($itemPrice * $itemQty) : '') ?></td>
                        <td><input type="text" name="items[<?= $idx ?>][purpose]" class="form-control" value="<?= e(!empty($item['purpose']) ? $item['purpose'] : '') ?>"></td>
                        <td><input type="number" name="items[<?= $idx ?>][approved_qty]" class="form-control" min="0" value="<?= e(!empty($item['approved_qty']) ? $item['approved_qty'] : '') ?>" <?= $isApprover ? 'required' : 'readonly style="background:#f5f5f5"' ?>></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">刪除</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-right"><strong>合計</strong></td>
                        <td class="text-right" style="font-weight:700;font-size:1.1rem;color:var(--primary)" id="reqGrandTotal">$0</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- 備註 -->
    <div class="card">
        <div class="card-header">備註</div>
        <div class="form-group">
            <textarea name="note" class="form-control" rows="3"><?= e(!empty($record['note']) ? $record['note'] : '') ?></textarea>
        </div>
    </div>

    <?php if (!$isLocked): ?>
    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '更新' : '儲存' ?></button>
        <a href="/requisitions.php" class="btn btn-outline">取消</a>
    </div>
    <?php else: ?>
    <div class="mt-2">
        <a href="/requisitions.php" class="btn btn-outline">返回列表</a>
    </div>
    <?php endif; ?>
</form>
<?php if ($isLocked): ?>
<style>
#reqForm input:not([type="hidden"]), #reqForm select, #reqForm textarea { pointer-events:none; background:#f5f5f5 !important; color:#757575 !important; }
#reqForm .btn-danger { display:none; }
</style>
<?php endif; ?>

<style>
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.item-row td { padding: 4px 6px; vertical-align: middle; }
.item-row .form-control { margin-bottom: 0; }
@media (max-width: 767px) {
    .form-row { grid-template-columns: 1fr; }
}
</style>

<script>
var itemIndex = <?= count($itemList) ?>;

function addItemRow() {
    var tbody = document.getElementById('itemsBody');
    var seq = tbody.querySelectorAll('.item-row').length + 1;
    var html = '<tr class="item-row">' +
        '<td class="item-seq" style="text-align:center">' + seq + '</td>' +
        '<td><input type="hidden" name="items[' + itemIndex + '][product_id]" class="req-product-id" value=""><input type="text" name="items[' + itemIndex + '][model]" class="form-control req-model"></td>' +
        '<td style="position:relative"><input type="text" name="items[' + itemIndex + '][product_name]" class="form-control req-product-name" placeholder="輸入關鍵字搜尋..." autocomplete="off"><div class="req-product-dropdown" style="display:none;position:absolute;top:100%;left:0;z-index:1000;background:#fff;border:1px solid var(--gray-300);border-radius:6px;max-height:250px;min-width:350px;overflow-y:auto;box-shadow:0 6px 20px rgba(0,0,0,.2)"></div></td>' +
        '<td><input type="number" name="items[' + itemIndex + '][quantity]" class="form-control req-qty" min="1" oninput="calcReqRow(this)" required></td>' +
        '<td><input type="text" name="items[' + itemIndex + '][unit_price]" class="form-control req-price" oninput="calcReqRow(this)" inputmode="numeric"></td>' +
        '<td class="req-subtotal text-right" style="font-weight:600"></td>' +
        '<td><input type="text" name="items[' + itemIndex + '][purpose]" class="form-control"></td>' +
        '<td><input type="number" name="items[' + itemIndex + '][approved_qty]" class="form-control" min="0" readonly style="background:#f5f5f5"></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">刪除</button></td>' +
        '</tr>';
    tbody.insertAdjacentHTML('beforeend', html);
    itemIndex++;
}

function removeItemRow(btn) {
    var row = btn.closest('.item-row');
    if (row) {
        row.remove();
        reindexSeq();
    }
}

function reindexSeq() {
    var rows = document.querySelectorAll('#itemsBody .item-row');
    for (var i = 0; i < rows.length; i++) {
        rows[i].querySelector('.item-seq').textContent = i + 1;
    }
}

// 計算小計和合計
function calcReqRow(el) {
    var row = el.closest('.item-row');
    var qty = parseInt(row.querySelector('.req-qty').value) || 0;
    var price = parseInt(row.querySelector('.req-price').value) || 0;
    var subtotal = Math.round(qty * price);
    row.querySelector('.req-subtotal').textContent = subtotal > 0 ? '$' + subtotal.toLocaleString() : '';
    calcReqTotal();
}
function calcReqTotal() {
    var total = 0;
    var rows = document.querySelectorAll('#itemsBody .item-row');
    for (var i = 0; i < rows.length; i++) {
        var qty = parseInt(rows[i].querySelector('.req-qty') ? rows[i].querySelector('.req-qty').value : 0) || 0;
        var price = parseInt(rows[i].querySelector('.req-price') ? rows[i].querySelector('.req-price').value : 0) || 0;
        total += Math.round(qty * price);
    }
    document.getElementById('reqGrandTotal').textContent = '$' + total.toLocaleString();
}
document.addEventListener('DOMContentLoaded', function() {
    // 頁面載入時計算所有行的小計
    var rows = document.querySelectorAll('#itemsBody .item-row');
    for (var i = 0; i < rows.length; i++) {
        var qty = rows[i].querySelector('.req-qty');
        if (qty) calcReqRow(qty);
    }
    calcReqTotal();
});

// 商品名稱即時搜尋產品目錄
var reqProductTimer = null;
document.addEventListener('input', function(e) {
    if (!e.target.classList.contains('req-product-name')) return;
    clearTimeout(reqProductTimer);
    var inp = e.target;
    var dd = inp.parentElement.querySelector('.req-product-dropdown');
    var q = inp.value.trim();
    if (q.length < 1) { dd.style.display = 'none'; return; }
    reqProductTimer = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/requisitions.php?action=ajax_search_product&q=' + encodeURIComponent(q));
        xhr.onload = function() {
            var list = JSON.parse(xhr.responseText);
            if (!list.length) { dd.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合產品</div>'; dd.style.display = 'block'; return; }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" ' +
                    'data-id="' + (list[i].id || '') + '" ' +
                    'data-name="' + (list[i].name || '').replace(/"/g, '&quot;') + '" ' +
                    'data-model="' + (list[i].model || '').replace(/"/g, '&quot;') + '" ' +
                    'data-price="' + (list[i].price || 0) + '" ' +
                    'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">' +
                    '<div style="font-weight:600">' + (list[i].name || '') + '</div>' +
                    '<div style="font-size:.75rem;color:#888">' +
                    (list[i].model ? '<span style="color:#1565c0">' + list[i].model + '</span> | ' : '') +
                    '$' + Number(list[i].price || 0).toLocaleString() + '/' + (list[i].unit || '式') +
                    ' | <span style="color:' + (Number(list[i].stock||0) > 0 ? '#2e7d32' : '#c62828') + '">庫存:' + Number(list[i].stock||0) + '</span>' +
                    '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
        };
        xhr.send();
    }, 300);
});

// 點選產品帶入名稱和型號
document.addEventListener('click', function(e) {
    var item = e.target.closest('.req-product-dropdown > div[data-name]');
    if (item) {
        var td = item.closest('td');
        var nameInp = td.querySelector('.req-product-name');
        var row = td.closest('.item-row');
        var modelInp = row.querySelector('.req-model');
        nameInp.value = item.dataset.name;
        if (modelInp) modelInp.value = item.dataset.model;
        var pidInp = row.querySelector('.req-product-id');
        if (pidInp) pidInp.value = item.dataset.id || '';
        var priceInp = row.querySelector('.req-price');
        if (priceInp && item.dataset.price) priceInp.value = Math.round(Number(item.dataset.price));
        calcReqRow(nameInp);
        item.closest('.req-product-dropdown').style.display = 'none';
        return;
    }
    // 點擊外面關閉
    if (!e.target.classList.contains('req-product-name')) {
        var dds = document.querySelectorAll('.req-product-dropdown');
        for (var i = 0; i < dds.length; i++) dds[i].style.display = 'none';
    }
    // 廠商下拉關閉
    if (e.target.id !== 'vendorNameInput') {
        var vdd = document.getElementById('vendorDropdown');
        if (vdd) vdd.style.display = 'none';
    }
});

// ---- 廠商搜尋 ----
(function(){
    var inp = document.getElementById('vendorNameInput');
    var dd = document.getElementById('vendorDropdown');
    if (!inp || !dd) return;
    var timer = null;
    inp.addEventListener('input', function(){
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 1) { dd.style.display = 'none'; return; }
        timer = setTimeout(function(){
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/payments_out.php?action=ajax_vendor_search&q=' + encodeURIComponent(q));
            xhr.onload = function(){
                var list = JSON.parse(xhr.responseText);
                if (!list.length) { dd.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合廠商，可直接手動輸入</div>'; dd.style.display = 'block'; return; }
                var html = '';
                for (var i = 0; i < list.length; i++) {
                    html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" data-name="' + (list[i].name||'').replace(/"/g,'&quot;') + '" onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">' +
                        '<div style="font-weight:600">' + (list[i].name||'') + '</div>' +
                        (list[i].contact_person ? '<div style="font-size:.75rem;color:#888">' + list[i].contact_person + (list[i].phone ? ' | ' + list[i].phone : '') + '</div>' : '') +
                        '</div>';
                }
                dd.innerHTML = html;
                dd.style.display = 'block';
            };
            xhr.send();
        }, 300);
    });
    dd.addEventListener('click', function(e){
        var item = e.target.closest('div[data-name]');
        if (item) {
            inp.value = item.dataset.name;
            dd.style.display = 'none';
        }
    });
})();
</script>
