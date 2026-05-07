<?php $isEdit = !empty($record) && !empty($record['id']); ?>
<?php require __DIR__ . '/../layouts/_pack_unit_js.php'; ?>

<h2><?= $isEdit ? '編輯進貨單 - ' . e($record['gr_number']) : '新增進貨單' ?></h2>

<form method="POST" class="mt-2" id="grForm" onsubmit="return grValidateVendorBeforeSubmit() && hswPackUnitPrepareSubmit(this)">
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
                <input type="text" class="form-control" value="$<?= number_format((float)$gv('paid_amount', 0), 2) ?>" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>付款日期</label>
                <input type="text" class="form-control" value="<?= e($gv('payment_date')) ?>" readonly style="background:#f5f5f5">
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- AI 辨識 -->
    <div class="card" id="aiRecognizeCard">
        <div class="card-header d-flex justify-between align-center">
            <span>AI 辨識進貨單</span>
            <small style="color:#888">拍照或上傳進貨單圖片，自動辨識填入</small>
        </div>
        <div style="padding:12px">
            <div class="d-flex gap-1 align-center" style="flex-wrap:wrap">
                <input type="file" id="aiImageInput" accept="image/*,application/pdf" capture="environment" style="display:none" onchange="aiStartRecognize()">
                <button type="button" class="btn btn-primary" onclick="document.getElementById('aiImageInput').click()" id="aiUploadBtn">
                    📷 拍照 / 選擇圖片
                </button>
                <span id="aiFileName" style="color:#888;font-size:.85rem"></span>
            </div>
            <!-- 預覽 -->
            <div id="aiPreview" style="display:none;margin-top:10px">
                <img id="aiPreviewImg" style="max-width:300px;max-height:200px;border:1px solid #ddd;border-radius:6px">
            </div>
            <!-- 辨識狀態 -->
            <div id="aiStatus" style="display:none;margin-top:10px;padding:10px;border-radius:6px;font-size:.9rem"></div>
            <!-- 辨識結果摘要 -->
            <div id="aiResultSummary" style="display:none;margin-top:10px;padding:12px;background:#f0f7ff;border-radius:6px;font-size:.85rem"></div>
        </div>
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
                        <th style="min-width:100px">型號</th>
                        <th>品名</th>
                        <th>規格</th>
                        <th>單位</th>
                        <th style="width:80px">採購數量</th>
                        <th style="width:100px">收貨數量</th>
                        <th style="width:100px">單價</th>
                        <th style="width:100px">金額</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="itemBody">
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $idx => $item):
                            $editInputUnit = !empty($item['input_unit']) ? $item['input_unit'] : '';
                            $editInputQty = isset($item['input_qty']) && $item['input_qty'] !== null ? (float)$item['input_qty'] : null;
                            $editBaseUnit = !empty($item['unit']) ? $item['unit'] : '';
                            // 顯示給使用者看的「單位＋數量」：優先用 input_unit/input_qty
                            $displayUnit = $editInputUnit ?: $editBaseUnit;
                            $displayQty = $editInputQty !== null ? $editInputQty : (!empty($item['received_qty']) ? (float)$item['received_qty'] : 0);
                        ?>
                        <tr class="pack-unit-row">
                            <td class="item-seq"><?= $idx + 1 ?></td>
                            <td>
                                <input type="hidden" name="items[<?= $idx ?>][product_id]" value="<?= e(!empty($item['product_id']) ? $item['product_id'] : '') ?>">
                                <input type="text" name="items[<?= $idx ?>][model]" class="form-control" value="<?= e(!empty($item['model']) ? $item['model'] : '') ?>">
                            </td>
                            <td style="position:relative"><input type="text" name="items[<?= $idx ?>][product_name]" class="form-control product-name-input" value="<?= e(!empty($item['product_name']) ? $item['product_name'] : '') ?>" placeholder="搜尋品名..." autocomplete="off" oninput="searchProductByName(this, <?= $idx ?>)"></td>
                            <td><input type="text" name="items[<?= $idx ?>][spec]" class="form-control" value="<?= e(!empty($item['spec']) ? $item['spec'] : '') ?>"></td>
                            <td>
                                <select class="form-control pack-unit-select" style="width:75px"
                                        data-preselect="<?= e($displayUnit) ?>"
                                        data-pack-unit="<?= e(!empty($item['product_pack_unit']) ? $item['product_pack_unit'] : '') ?>"
                                        data-pack-qty="<?= !empty($item['product_pack_qty']) ? (float)$item['product_pack_qty'] : '' ?>"
                                        data-base-unit="<?= e($editBaseUnit) ?>"></select>
                                <input type="hidden" name="items[<?= $idx ?>][unit]" class="pack-unit-hidden-unit" value="<?= e($editBaseUnit) ?>">
                                <input type="hidden" name="items[<?= $idx ?>][input_unit]" class="pack-unit-hidden-input-unit" value="<?= e($editInputUnit) ?>">
                                <input type="hidden" name="items[<?= $idx ?>][input_qty]" class="pack-unit-hidden-input-qty" value="<?= $editInputQty !== null ? e($editInputQty) : '' ?>">
                            </td>
                            <td><input type="number" name="items[<?= $idx ?>][po_qty]" class="form-control" step="1" min="0" value="<?= !empty($item['po_qty']) ? (int)$item['po_qty'] : 0 ?>" readonly></td>
                            <td>
                                <input type="number" class="form-control pack-unit-qty item-qty" step="any" min="0" value="<?= e($displayQty) ?>" oninput="hswPackUnitRowSync(this); calcRowAmount(this.closest('tr'))">
                                <input type="hidden" name="items[<?= $idx ?>][received_qty]" class="pack-unit-hidden-qty" value="<?= !empty($item['received_qty']) ? (int)$item['received_qty'] : 0 ?>">
                                <div class="pack-unit-hint" style="display:none"></div>
                            </td>
                            <td><input type="number" name="items[<?= $idx ?>][unit_price]" class="form-control item-price" step="0.01" min="0" value="<?= !empty($item['unit_price']) ? number_format((float)$item['unit_price'], 2, '.', '') : '0.00' ?>" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[<?= $idx ?>][amount]" class="form-control item-amount" step="0.01" min="0" value="<?= !empty($item['amount']) ? number_format((float)$item['amount'], 2, '.', '') : '0.00' ?>" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="pack-unit-row">
                            <td class="item-seq">1</td>
                            <td>
                                <input type="hidden" name="items[0][product_id]" value="">
                                <input type="text" name="items[0][model]" class="form-control" value="">
                            </td>
                            <td style="position:relative"><input type="text" name="items[0][product_name]" class="form-control product-name-input" value="" placeholder="搜尋品名..." autocomplete="off" oninput="searchProductByName(this, 0)"></td>
                            <td><input type="text" name="items[0][spec]" class="form-control" value=""></td>
                            <td>
                                <select class="form-control pack-unit-select" style="width:75px"></select>
                                <input type="hidden" name="items[0][unit]" class="pack-unit-hidden-unit" value="">
                                <input type="hidden" name="items[0][input_unit]" class="pack-unit-hidden-input-unit" value="">
                                <input type="hidden" name="items[0][input_qty]" class="pack-unit-hidden-input-qty" value="">
                            </td>
                            <td><input type="number" name="items[0][po_qty]" class="form-control" step="1" min="0" value="0" readonly></td>
                            <td>
                                <input type="number" class="form-control pack-unit-qty item-qty" step="any" min="0" value="0" oninput="hswPackUnitRowSync(this); calcRowAmount(this.closest('tr'))">
                                <input type="hidden" name="items[0][received_qty]" class="pack-unit-hidden-qty" value="0">
                                <div class="pack-unit-hint" style="display:none"></div>
                            </td>
                            <td><input type="number" name="items[0][unit_price]" class="form-control item-price" step="0.01" min="0" value="0.00" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[0][amount]" class="form-control item-amount" step="0.01" min="0" value="0.00" readonly></td>
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
                <input type="number" id="subtotal_amount" class="form-control" step="0.01" min="0" value="0.00" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>稅額 (5%)</label>
                <input type="number" id="tax_amount" class="form-control" step="0.01" min="0" value="0.00" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>總金額（含稅）</label>
                <input type="number" name="total_amount" id="total_amount" class="form-control" step="0.01" min="0"
                       value="<?= $isEdit && !empty($record['total_amount']) ? number_format((float)$record['total_amount'], 2, '.', '') : '0.00' ?>" readonly style="font-weight:700;color:var(--primary)">
            </div>
        </div>
    </div>

    <!-- 付款資訊 -->
    <div class="card">
        <div class="card-header">付款資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>已付金額</label>
                <input type="number" name="paid_amount" class="form-control" step="0.01" min="0"
                       value="<?= $isEdit && !empty($record['paid_amount']) ? number_format((float)$record['paid_amount'], 2, '.', '') : '' ?>" placeholder="0.00">
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
    tr.className = 'pack-unit-row';
    var seq = tbody.querySelectorAll('tr').length + 1;
    tr.innerHTML = '<td class="item-seq">' + seq + '</td>'
        + '<td><input type="hidden" name="items['+itemIdx+'][product_id]" value="">'
        + '<input type="text" name="items['+itemIdx+'][model]" class="form-control" value=""></td>'
        + '<td style="position:relative"><input type="text" name="items['+itemIdx+'][product_name]" class="form-control product-name-input" value="" placeholder="搜尋品名..." autocomplete="off" oninput="searchProductByName(this,'+itemIdx+')"></td>'
        + '<td><input type="text" name="items['+itemIdx+'][spec]" class="form-control" value=""></td>'
        + '<td><select class="form-control pack-unit-select" style="width:75px"></select>'
        + '<input type="hidden" name="items['+itemIdx+'][unit]" class="pack-unit-hidden-unit" value="">'
        + '<input type="hidden" name="items['+itemIdx+'][input_unit]" class="pack-unit-hidden-input-unit" value="">'
        + '<input type="hidden" name="items['+itemIdx+'][input_qty]" class="pack-unit-hidden-input-qty" value=""></td>'
        + '<td><input type="number" name="items['+itemIdx+'][po_qty]" class="form-control" step="1" min="0" value="0" readonly></td>'
        + '<td><input type="number" class="form-control pack-unit-qty item-qty" step="any" min="0" value="0" oninput="hswPackUnitRowSync(this); calcRowAmount(this.closest(\'tr\'))">'
        + '<input type="hidden" name="items['+itemIdx+'][received_qty]" class="pack-unit-hidden-qty" value="0">'
        + '<div class="pack-unit-hint" style="display:none"></div></td>'
        + '<td><input type="number" name="items['+itemIdx+'][unit_price]" class="form-control item-price" step="0.01" min="0" value="0.00" oninput="calcRowAmount(this.closest(\'tr\'))"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][amount]" class="form-control item-amount" step="0.01" min="0" value="0.00" readonly></td>'
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

function _round2(n) { return Math.round(n * 100) / 100; }
function calcRowAmount(row) {
    var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    var price = parseFloat(row.querySelector('.item-price').value) || 0;
    row.querySelector('.item-amount').value = _round2(qty * price).toFixed(2);
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
    subtotal = _round2(subtotal);
    var tax = _round2(subtotal * 0.05);
    var total = _round2(subtotal + tax);
    document.getElementById('total_qty').value = totalQty;
    document.getElementById('subtotal_amount').value = subtotal.toFixed(2);
    document.getElementById('tax_amount').value = tax.toFixed(2);
    document.getElementById('total_amount').value = total.toFixed(2);
    var sumQty = document.getElementById('sumQty');
    var sumAmount = document.getElementById('sumAmount');
    if (sumQty) sumQty.textContent = totalQty.toLocaleString();
    if (sumAmount) sumAmount.textContent = '$' + subtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
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
                html += '<div class="pn-item" data-id="' + (p.id||'') + '" data-model="' + escAttr(pModel) + '" data-name="' + escAttr(p.name||'') + '" data-unit="' + escAttr(p.unit||'') + '" data-cost="' + pCost + '" data-pack-unit="' + escAttr(p.pack_unit||'') + '" data-pack-qty="' + (p.pack_qty||'') + '">';
                html += '<b>' + escH(p.name) + '</b>';
                if (pModel) html += ' <small style="color:#888">' + escH(pModel) + '</small>';
                if (pCost) html += ' <small style="color:#e53935">$' + Number(pCost).toLocaleString() + '</small>';
                if (p.pack_unit && p.pack_qty) html += ' <small style="color:#1565c0">1'+p.pack_unit+'='+p.pack_qty+(p.unit||'')+'</small>';
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
                    hswPackUnitSetupRow(row, {
                        unit: this.getAttribute('data-unit') || '',
                        pack_unit: this.getAttribute('data-pack-unit') || '',
                        pack_qty: this.getAttribute('data-pack-qty') || 0
                    });
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
                if (priceInput && item.getAttribute('data-price') > 0) priceInput.value = (parseFloat(item.getAttribute('data-price')) || 0).toFixed(2);
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
        xhr.open('GET', '/goods_receipts.php?action=ajax_vendor_search&q=' + encodeURIComponent(q));
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

// ===== AI 辨識 =====
function aiStartRecognize() {
    var fileInput = document.getElementById('aiImageInput');
    var file = fileInput.files[0];
    if (!file) return;

    // 顯示檔名
    document.getElementById('aiFileName').textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + 'KB)';

    // 預覽圖片
    if (file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('aiPreviewImg').src = e.target.result;
            document.getElementById('aiPreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('aiPreview').style.display = 'none';
    }

    // 顯示辨識中
    var statusEl = document.getElementById('aiStatus');
    statusEl.style.display = 'block';
    statusEl.style.background = '#fff3e0';
    statusEl.style.color = '#e65100';
    statusEl.innerHTML = '⏳ AI 辨識中，請稍候（約 10~30 秒）...';
    document.getElementById('aiResultSummary').style.display = 'none';
    document.getElementById('aiUploadBtn').disabled = true;

    // 透過 PHP 代理呼叫 AI 服務
    var formData = new FormData();
    formData.append('image', file);

    fetch('/goods_receipts.php?action=ajax_ai_recognize', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        document.getElementById('aiUploadBtn').disabled = false;
        if (!data.success) {
            statusEl.style.background = '#ffebee';
            statusEl.style.color = '#c62828';
            statusEl.innerHTML = '❌ 辨識失敗：' + (data.error || '未知錯誤');
            return;
        }
        statusEl.style.background = '#e8f5e9';
        statusEl.style.color = '#2e7d32';
        statusEl.innerHTML = '✅ 辨識完成！';
        aiApplyResult(data);
    })
    .catch(function(err) {
        document.getElementById('aiUploadBtn').disabled = false;
        statusEl.style.background = '#ffebee';
        statusEl.style.color = '#c62828';
        statusEl.innerHTML = '❌ 連線失敗：' + err.message;
    });
}

function aiApplyResult(data) {
    // 1) 廠商
    var vendorInfo = data.vendor || {};
    var summaryParts = [];

    if (vendorInfo.matched_id) {
        document.getElementById('vendor_id').value = vendorInfo.matched_id;
        document.getElementById('vendor_name').value = vendorInfo.matched_name || vendorInfo.name;
        document.getElementById('grVendorWarning').style.display = 'none';
        summaryParts.push('廠商：' + (vendorInfo.matched_name || vendorInfo.name) + ' (信心度 ' + Math.round((vendorInfo.confidence || 0) * 100) + '%)');
    } else if (vendorInfo.name) {
        document.getElementById('vendor_name').value = vendorInfo.name;
        document.getElementById('vendor_id').value = '';
        summaryParts.push('⚠ 廠商「' + vendorInfo.name + '」未比對到系統，請手動選擇');
    }

    // 2) 日期
    if (data.date) {
        var dateInput = document.querySelector('input[name="gr_date"]');
        if (dateInput) {
            dateInput.value = data.date;
            summaryParts.push('日期：' + data.date);
        }
    }

    // 3) 品項
    var items = data.items || [];
    if (items.length > 0) {
        // 清空現有空白列
        var tbody = document.getElementById('itemBody');
        var existingRows = tbody.querySelectorAll('tr:not(.total-row)');
        var hasData = false;
        existingRows.forEach(function(row) {
            var model = row.querySelector('input[name*="[model]"]');
            var name = row.querySelector('input[name*="[product_name]"]');
            if (model && !model.value && name && !name.value) {
                row.remove();
            } else if (model && model.value) {
                hasData = true;
            }
        });

        // 填入辨識的品項
        var feeCount = 0;
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            addItemRow();
            var rows = tbody.querySelectorAll('tr:not(.total-row)');
            var lastRow = rows[rows.length - 1];

            // 運費等非產品項目標記
            if (item.is_fee) {
                lastRow.style.background = '#fff8e1';
                feeCount++;
            }

            // 型號
            var modelInput = lastRow.querySelector('input[name*="[model]"]');
            if (modelInput) modelInput.value = item.model || item.ai_model || '';

            // 品名（運費項目加前綴）
            var nameInput = lastRow.querySelector('input[name*="[product_name]"]');
            var displayName = item.product_name || item.ai_name || '';
            if (item.is_fee && displayName.indexOf('運費') === -1 && displayName.indexOf('費') === -1) {
                displayName = '【費用】' + displayName;
            }
            if (nameInput) nameInput.value = displayName;

            // product_id (如果有比對到)
            var pidInput = lastRow.querySelector('input[name*="[product_id]"]');
            if (pidInput && item.product_id) pidInput.value = item.product_id;

            // 規格
            var specInput = lastRow.querySelector('input[name*="[spec]"]');
            if (specInput && item.spec) specInput.value = item.spec;

            // 單位
            var unitInput = lastRow.querySelector('input[name*="[unit]"]');
            if (unitInput) unitInput.value = item.unit || '';

            // 收貨數量
            var qtyInput = lastRow.querySelector('.item-qty');
            if (qtyInput) qtyInput.value = item.quantity || item.qty || 0;

            // 單價
            var priceInput = lastRow.querySelector('.item-price');
            if (priceInput) priceInput.value = item.unit_price || 0;

            // 金額
            calcRowAmount(lastRow);
        }
        calcTotals();
        summaryParts.push('品項：' + items.length + ' 筆' + (feeCount > 0 ? '（含 ' + feeCount + ' 筆費用）' : ''));

        // 比對狀態
        var matchedCount = 0;
        for (var j = 0; j < items.length; j++) {
            if (items[j].product_id) matchedCount++;
        }
        if (matchedCount > 0) {
            summaryParts.push('已比對：' + matchedCount + '/' + items.length + ' 筆');
        }
    }

    // 4) 總金額
    if (data.total) {
        summaryParts.push('原始金額：$' + Number(data.total).toLocaleString());
    }
    if (data.invoice_number) {
        summaryParts.push('發票號碼：' + data.invoice_number);
    }

    // 顯示摘要
    var summaryEl = document.getElementById('aiResultSummary');
    summaryEl.innerHTML = '<b>辨識結果</b><br>' + summaryParts.join('<br>');
    summaryEl.style.display = 'block';
}
</script>
