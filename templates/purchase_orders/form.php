<?php
if (!isset($isFromRequisition)) $isFromRequisition = false;
$r = !empty($record) ? $record : array();
$isEdit = !empty($r['po_number']);
?>

<div>
    <h2 style="margin-bottom:2px"><?= $isEdit ? '編輯採購單 - ' . e($r['po_number']) : '新增採購單' ?></h2>
    <?php if ($isEdit && !empty($r['updated_at'])): ?>
    <small class="text-muted">最後修改 <?= e($r['updated_at']) ?><?php
        if (!empty($r['updated_by'])) {
            $updater = Database::getInstance()->prepare('SELECT real_name FROM users WHERE id = ?');
            $updater->execute(array($r['updated_by']));
            $un = $updater->fetchColumn();
            if ($un) echo ' / ' . e($un);
        }
    ?></small>
    <?php endif; ?>
</div>

<form method="POST" class="mt-2" id="poForm">
    <?= csrf_field() ?>

    <!-- 採購單資訊 -->
    <div class="card">
        <div class="card-header">採購單資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>採購單號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? $r['po_number'] : peek_next_doc_number('purchase_orders')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group">
                <label>採購日期 *</label>
                <input type="date" max="2099-12-31" name="po_date" class="form-control"
                       value="<?= e(!empty($r['po_date']) ? $r['po_date'] : date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <?php foreach (ProcurementModel::poStatusOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($r['status']) ? $r['status'] : '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>採購人</label>
                <select name="purchaser_name" class="form-control">
                    <option value="">請選擇</option>
                    <?php
                    $db = Database::getInstance();
                    $pStmt = $db->query("SELECT real_name FROM users WHERE is_active = 1 AND role IN ('purchaser','warehouse','admin_staff') ORDER BY real_name");
                    $purchasers = $pStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($purchasers as $p): ?>
                    <option value="<?= e($p['real_name']) ?>" <?= (!empty($r['purchaser_name']) && $r['purchaser_name'] === $p['real_name']) ? 'selected' : '' ?>><?= e($p['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>請購單號</label>
                <input type="text" name="requisition_number" class="form-control"
                       value="<?= e(!empty($r['requisition_number']) ? $r['requisition_number'] : '') ?>" readonly>
                <?php if (!empty($r['requisition_id'])): ?>
                <input type="hidden" name="requisition_id" value="<?= (int)$r['requisition_id'] ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>案件名稱</label>
                <input type="text" name="case_name" class="form-control"
                       value="<?= e(!empty($r['case_name']) ? $r['case_name'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= (!empty($r['branch_id']) ? $r['branch_id'] : '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>業務人員</label>
                <input type="text" name="sales_name" class="form-control"
                       value="<?= e(!empty($r['sales_name']) ? $r['sales_name'] : '') ?>">
            </div>
            <div class="form-group">
                <label>急迫性</label>
                <select name="urgency" class="form-control">
                    <option value="">請選擇</option>
                    <option value="一般" <?= (!empty($r['urgency']) ? $r['urgency'] : '') === '一般' ? 'selected' : '' ?>>一般</option>
                    <option value="急件" <?= (!empty($r['urgency']) ? $r['urgency'] : '') === '急件' ? 'selected' : '' ?>>急件</option>
                    <option value="特急" <?= (!empty($r['urgency']) ? $r['urgency'] : '') === '特急' ? 'selected' : '' ?>>特急</option>
                </select>
            </div>
        </div>
        <?php if (!empty($r['requisition_number'])): ?>
        <div class="form-row">
            <div class="form-group">
                <label>請購廠商</label>
                <input type="text" class="form-control" value="<?= e(!empty($r['req_vendor_name']) ? $r['req_vendor_name'] : '（未指定）') ?>" readonly style="background:#f5f5f5">
                <input type="hidden" name="req_vendor_name" value="<?= e(!empty($r['req_vendor_name']) ? $r['req_vendor_name'] : '') ?>">
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 採購廠商 -->
    <?php
    $hasReqVendor = !empty($r['req_vendor_name']);
    $defaultSame = false;
    ?>
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>採購廠商</span>
            <?php if ($hasReqVendor): ?>
            <label style="font-weight:normal;font-size:.85rem;cursor:pointer;display:flex;align-items:center;gap:4px">
                <input type="checkbox" id="sameAsReqVendor" onchange="toggleSameVendor(this)" <?= $defaultSame ? 'checked' : '' ?>> 同請購廠商
            </label>
            <?php endif; ?>
        </div>
        <input type="hidden" name="vendor_id" id="vendor_id"
               value="<?= e(!empty($r['vendor_id']) ? $r['vendor_id'] : '') ?>">
        <div id="vendorFields">
        <div class="form-row">
            <div class="form-group" style="position:relative">
                <label>廠商名稱 *</label>
                <input type="text" name="vendor_name" id="vendor_name" class="form-control"
                       value="<?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '') ?>" required autocomplete="off" placeholder="輸入關鍵字搜尋或手動輸入">
                <div id="poVendorDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid var(--gray-200);border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>廠商代號</label>
                <input type="text" name="vendor_code" id="vendor_code" class="form-control"
                       value="<?= e(!empty($r['vendor_code']) ? $r['vendor_code'] : '') ?>">
            </div>
            <div class="form-group">
                <label>統一編號</label>
                <input type="text" name="vendor_tax_id" id="vendor_tax_id" class="form-control"
                       value="<?= e(!empty($r['vendor_tax_id']) ? $r['vendor_tax_id'] : '') ?>">
            </div>
            <div class="form-group">
                <label>聯絡人</label>
                <input type="text" name="vendor_contact" id="vendor_contact" class="form-control"
                       value="<?= e(!empty($r['vendor_contact']) ? $r['vendor_contact'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="vendor_phone" id="vendor_phone" class="form-control"
                       value="<?= e(!empty($r['vendor_phone']) ? $r['vendor_phone'] : '') ?>">
            </div>
            <div class="form-group">
                <label>傳真</label>
                <input type="text" name="vendor_fax" id="vendor_fax" class="form-control"
                       value="<?= e(!empty($r['vendor_fax']) ? $r['vendor_fax'] : '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="vendor_email" id="vendor_email" class="form-control"
                       value="<?= e(!empty($r['vendor_email']) ? $r['vendor_email'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:2">
                <label>地址</label>
                <input type="text" name="vendor_address" id="vendor_address" class="form-control"
                       value="<?= e(!empty($r['vendor_address']) ? $r['vendor_address'] : '') ?>">
            </div>
        </div>
        </div>
    </div>

    <!-- 付款資訊 -->
    <div class="card" id="paymentCard">
        <div class="card-header">付款資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>付款方式</label>
                <input type="text" name="payment_method" class="form-control"
                       value="<?= e(!empty($r['payment_method']) ? $r['payment_method'] : '') ?>">
            </div>
            <div class="form-group">
                <label>付款條件</label>
                <input type="text" name="payment_terms" class="form-control"
                       value="<?= e(!empty($r['payment_terms']) ? $r['payment_terms'] : '') ?>">
            </div>
            <div class="form-group">
                <label>發票方式</label>
                <input type="text" name="invoice_method" class="form-control"
                       value="<?= e(!empty($r['invoice_method']) ? $r['invoice_method'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>發票類別</label>
                <input type="text" name="invoice_type" class="form-control"
                       value="<?= e(!empty($r['invoice_type']) ? $r['invoice_type'] : '') ?>">
            </div>
            <div class="form-group">
                <label>付款日期</label>
                <input type="date" max="2099-12-31" name="payment_date" class="form-control"
                       value="<?= e(!empty($r['payment_date']) ? $r['payment_date'] : '') ?>">
            </div>
            <div class="form-group">
                <label>已付金額</label>
                <input type="number" name="paid_amount" class="form-control" step="1" min="0"
                       value="<?= !empty($r['paid_amount']) ? (int)$r['paid_amount'] : 0 ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_paid" value="1"
                           <?= (!empty($r['is_paid'])) ? 'checked' : '' ?>>
                    已付款
                </label>
            </div>
        </div>
    </div>

    <!-- 交貨 -->
    <div class="card">
        <div class="card-header">交貨資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>交貨地點</label>
                <input type="text" name="delivery_location" class="form-control"
                       value="<?= e(!empty($r['delivery_location']) ? $r['delivery_location'] : '') ?>">
            </div>
            <div class="form-group">
                <label>需求日期</label>
                <input type="date" max="2099-12-31" name="required_date" class="form-control"
                       value="<?= e(!empty($r['required_date']) ? $r['required_date'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>預計交貨日</label>
                <input type="date" max="2099-12-31" name="promised_date" class="form-control"
                       value="<?= e(!empty($r['promised_date']) ? $r['promised_date'] : '') ?>">
            </div>
            <div class="form-group">
                <label>實際到貨日</label>
                <input type="date" max="2099-12-31" name="receiving_date" class="form-control"
                       value="<?= e(!empty($r['receiving_date']) ? $r['receiving_date'] : '') ?>">
            </div>
        </div>
    </div>

    <!-- 採購細項 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>採購細項</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addItemRow()">+ 新增</button>
        </div>
        <div class="table-responsive" style="overflow:visible">
            <table class="table" id="itemTable">
                <thead>
                    <tr>
                        <th style="width:50px">項次</th>
                        <th>商品型號</th>
                        <th>商品名稱</th>
                        <th>規格</th>
                        <th style="width:100px">單價</th>
                        <th style="width:80px">數量</th>
                        <th style="width:100px">金額</th>
                        <th>交貨日期</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="itemBody">
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $idx => $item): ?>
                        <tr>
                            <td class="item-seq"><?= $idx + 1 ?></td>
                            <td><input type="hidden" name="items[<?= $idx ?>][product_id]" class="po-product-id" value="<?= e(!empty($item['product_id']) ? $item['product_id'] : '') ?>"><input type="text" name="items[<?= $idx ?>][model]" class="form-control po-model" value="<?= e(!empty($item['model']) ? $item['model'] : '') ?>" readonly></td>
                            <td style="position:relative"><input type="text" name="items[<?= $idx ?>][product_name]" class="form-control po-product-name" value="<?= e(!empty($item['product_name']) ? $item['product_name'] : '') ?>" autocomplete="off" placeholder="輸入關鍵字搜尋..." oninput="poSearchProduct(this)"><div class="po-product-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div></td>
                            <td><input type="text" name="items[<?= $idx ?>][spec]" class="form-control" value="<?= e(!empty($item['spec']) ? $item['spec'] : '') ?>"></td>
                            <td><input type="number" name="items[<?= $idx ?>][unit_price]" class="form-control item-price" step="1" min="0" value="<?= !empty($item['unit_price']) ? (int)$item['unit_price'] : 0 ?>" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[<?= $idx ?>][quantity]" class="form-control item-qty" step="1" min="0" value="<?= !empty($item['quantity']) ? (int)$item['quantity'] : 0 ?>" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[<?= $idx ?>][amount]" class="form-control item-amount" step="1" min="0" value="<?= !empty($item['amount']) ? (int)$item['amount'] : 0 ?>" readonly></td>
                            <td><input type="date" max="2099-12-31" name="items[<?= $idx ?>][delivery_date]" class="form-control" value="<?= e(!empty($item['delivery_date']) ? $item['delivery_date'] : '') ?>"></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="item-seq">1</td>
                            <td><input type="hidden" name="items[0][product_id]" class="po-product-id" value=""><input type="text" name="items[0][model]" class="form-control po-model" value="" readonly></td>
                            <td style="position:relative"><input type="text" name="items[0][product_name]" class="form-control po-product-name" value="" autocomplete="off" placeholder="輸入關鍵字搜尋..." oninput="poSearchProduct(this)"><div class="po-product-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div></td>
                            <td><input type="text" name="items[0][spec]" class="form-control" value=""></td>
                            <td><input type="number" name="items[0][unit_price]" class="form-control item-price" step="1" min="0" value="0" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[0][quantity]" class="form-control item-qty" step="1" min="0" value="0" oninput="calcRowAmount(this.closest('tr'))"></td>
                            <td><input type="number" name="items[0][amount]" class="form-control item-amount" step="1" min="0" value="0" readonly></td>
                            <td><input type="date" max="2099-12-31" name="items[0][delivery_date]" class="form-control" value=""></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 金額 -->
    <div class="card">
        <div class="card-header">金額</div>
        <div class="form-row">
            <div class="form-group">
                <label>小計</label>
                <input type="number" name="subtotal" id="subtotal" class="form-control" step="1" min="0"
                       value="<?= !empty($r['subtotal']) ? (int)$r['subtotal'] : 0 ?>" readonly>
            </div>
            <div class="form-group">
                <label>稅別</label>
                <select name="tax_type" id="tax_type" class="form-control" onchange="calcSubtotal()">
                    <option value="含稅" <?= (!empty($r['tax_type']) ? $r['tax_type'] : '') === '含稅' ? 'selected' : '' ?>>含稅</option>
                    <option value="未稅" <?= (!empty($r['tax_type']) ? $r['tax_type'] : '') === '未稅' ? 'selected' : '' ?>>未稅</option>
                    <option value="免稅" <?= (!empty($r['tax_type']) ? $r['tax_type'] : '') === '免稅' ? 'selected' : '' ?>>免稅</option>
                </select>
            </div>
            <div class="form-group">
                <label>稅率 (%)</label>
                <input type="number" name="tax_rate" id="tax_rate" class="form-control" step="0.01" min="0"
                       value="<?= !empty($r['tax_rate']) ? $r['tax_rate'] : 5 ?>"
                       oninput="calcSubtotal()">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>稅額</label>
                <input type="number" name="tax_amount" id="tax_amount" class="form-control" step="1" min="0"
                       value="<?= !empty($r['tax_amount']) ? (int)$r['tax_amount'] : 0 ?>" readonly>
            </div>
            <div class="form-group">
                <label>運費</label>
                <input type="number" name="shipping_fee" id="shipping_fee" class="form-control" step="1" min="0"
                       value="<?= !empty($r['shipping_fee']) ? (int)$r['shipping_fee'] : 0 ?>"
                       oninput="calcSubtotal()">
            </div>
            <div class="form-group">
                <label>合計金額</label>
                <input type="number" name="total_amount" id="total_amount" class="form-control" step="1" min="0"
                       value="<?= !empty($r['total_amount']) ? (int)$r['total_amount'] : 0 ?>" readonly>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>本次金額</label>
                <input type="number" name="this_amount" class="form-control" step="1" min="0"
                       value="<?= !empty($r['this_amount']) ? (int)$r['this_amount'] : 0 ?>">
            </div>
            <div class="form-group">
                <label>未稅折扣</label>
                <input type="number" name="discount_untaxed" class="form-control" step="1" min="0"
                       value="<?= !empty($r['discount_untaxed']) ? (int)$r['discount_untaxed'] : 0 ?>">
            </div>
            <div class="form-group">
                <label>含稅折扣</label>
                <input type="number" name="discount_taxed" class="form-control" step="1" min="0"
                       value="<?= !empty($r['discount_taxed']) ? (int)$r['discount_taxed'] : 0 ?>">
            </div>
        </div>
    </div>

    <!-- 其他 -->
    <div class="card">
        <div class="card-header">其他</div>
        <div class="form-row">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="use_payment_flow" value="1"
                           <?= (!empty($r['use_payment_flow'])) ? 'checked' : '' ?>>
                    啟用付款流程
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="convert_to_receiving" value="1"
                           <?= (!empty($r['convert_to_receiving'])) ? 'checked' : '' ?>>
                    轉入進貨單
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_cancelled" value="1"
                           <?= (!empty($r['is_cancelled'])) ? 'checked' : '' ?>>
                    已取消
                </label>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>退款日期</label>
                <input type="date" max="2099-12-31" name="refund_date" class="form-control"
                       value="<?= e(!empty($r['refund_date']) ? $r['refund_date'] : '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>備註</label>
            <textarea name="note" class="form-control" rows="3"><?= e(!empty($r['note']) ? $r['note'] : '') ?></textarea>
        </div>
    </div>

    <div class="d-flex justify-between mt-2">
        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立採購單' ?></button>
            <a href="/purchase_orders.php" class="btn btn-outline">取消</a>
        </div>
        <?php if ($isEdit && Auth::hasPermission('procurement.manage') && !in_array($r['status'], array('確認進貨', '已轉進貨單'))): ?>
        <a href="/purchase_orders.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-danger" onclick="return confirm('確定要刪除此採購單？此操作無法復原。')">刪除此採購單</a>
        <?php endif; ?>
    </div>
</form>

<script>
// ---- 產品搜尋（必須在最前面）----
var poSearchTimer = null;
function poSearchProduct(inp) {
    clearTimeout(poSearchTimer);
    var q = inp.value.trim();
    var dd = inp.parentNode.querySelector('.po-product-dropdown');
    if (!dd) return;
    if (q.length < 1) { dd.style.display = 'none'; return; }
    poSearchTimer = setTimeout(function(){
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/requisitions.php?action=ajax_search_product&q=' + encodeURIComponent(q));
        xhr.onload = function(){
            try {
                var list = JSON.parse(xhr.responseText);
            } catch(e) { dd.innerHTML = '<div style="padding:8px;color:#c62828;font-size:.85rem">搜尋錯誤</div>'; dd.style.display = 'block'; return; }
            if (!list.length) { dd.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合產品</div>'; dd.style.display = 'block'; return; }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" ' +
                    'data-id="' + (list[i].id||'') + '" ' +
                    'data-name="' + (list[i].name||'').replace(/"/g,'&quot;') + '" ' +
                    'data-model="' + (list[i].model||'').replace(/"/g,'&quot;') + '" ' +
                    'data-price="' + (list[i].price||0) + '" ' +
                    'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">' +
                    '<div style="font-weight:600">' + (list[i].name||'') + '</div>' +
                    '<div style="font-size:.75rem;color:#888">' +
                    (list[i].model ? '<span style="color:#1565c0">' + list[i].model + '</span> | ' : '') +
                    '$' + Number(list[i].price||0).toLocaleString() +
                    ' | <span style="color:' + (Number(list[i].stock||0) > 0 ? '#2e7d32' : '#c62828') + '">庫存:' + Number(list[i].stock||0) + '</span>' +
                    '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
        };
        xhr.onerror = function(){ dd.innerHTML = '<div style="padding:8px;color:#c62828;font-size:.85rem">網路錯誤</div>'; dd.style.display = 'block'; };
        xhr.send();
    }, 300);
}
document.addEventListener('click', function(e) {
    var item = e.target.closest('.po-product-dropdown > div[data-name]');
    if (item) {
        var row = item.closest('tr');
        row.querySelector('.po-product-name').value = item.dataset.name;
        row.querySelector('.po-model').value = item.dataset.model || '';
        var pidInp = row.querySelector('.po-product-id');
        if (pidInp) pidInp.value = item.dataset.id || '';
        var priceInp = row.querySelector('.item-price');
        if (priceInp && item.dataset.price) priceInp.value = Math.round(Number(item.dataset.price));
        if (typeof calcRowAmount === 'function') calcRowAmount(row);
        item.closest('.po-product-dropdown').style.display = 'none';
        return;
    }
    if (!e.target.classList.contains('po-product-name')) {
        var dds = document.querySelectorAll('.po-product-dropdown');
        for (var i = 0; i < dds.length; i++) dds[i].style.display = 'none';
    }
});

// ---- 同請購廠商 ----
var reqVendorName = <?= json_encode(!empty($r['req_vendor_name']) ? $r['req_vendor_name'] : '') ?>;
function toggleSameVendor(chk) {
    var payment = document.getElementById('paymentCard');
    if (chk.checked && reqVendorName) {
        // 帶入請購廠商名稱，從廠商管理查詢完整資訊
        document.getElementById('vendor_name').value = reqVendorName;
        if (payment) payment.style.display = 'none';
        // 自動搜尋廠商完整資訊
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/payments_out.php?action=ajax_vendor_search&q=' + encodeURIComponent(reqVendorName));
        xhr.onload = function(){
            var list = JSON.parse(xhr.responseText);
            if (list.length > 0) {
                var v = list[0];
                document.getElementById('vendor_id').value = v.id || '';
                document.getElementById('vendor_code').value = v.vendor_code || '';
                document.getElementById('vendor_tax_id').value = v.tax_id || '';
                document.getElementById('vendor_contact').value = v.contact_person || '';
                document.getElementById('vendor_phone').value = v.phone || '';
                var faxEl = document.getElementById('vendor_fax');
                var emailEl = document.getElementById('vendor_email');
                var addrEl = document.getElementById('vendor_address');
                if (faxEl) faxEl.value = v.fax || '';
                if (emailEl) emailEl.value = v.email || '';
                if (addrEl) addrEl.value = v.address || '';
            }
        };
        xhr.send();
    } else {
        if (payment) payment.style.display = '';
    }
}

// ---- 廠商關鍵字搜尋 ----
(function(){
    var inp = document.getElementById('vendor_name');
    var dd = document.getElementById('poVendorDropdown');
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
                    html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" ' +
                        'data-id="' + (list[i].id||'') + '" data-name="' + (list[i].name||'').replace(/"/g,'&quot;') + '" ' +
                        'data-code="' + (list[i].vendor_code||'').replace(/"/g,'&quot;') + '" ' +
                        'data-contact="' + (list[i].contact_person||'').replace(/"/g,'&quot;') + '" ' +
                        'data-phone="' + (list[i].phone||'').replace(/"/g,'&quot;') + '" ' +
                        'data-taxid="' + (list[i].tax_id||'').replace(/"/g,'&quot;') + '" ' +
                        'data-fax="' + (list[i].fax||'').replace(/"/g,'&quot;') + '" ' +
                        'data-email="' + (list[i].email||'').replace(/"/g,'&quot;') + '" ' +
                        'data-address="' + (list[i].address||'').replace(/"/g,'&quot;') + '" ' +
                        'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">' +
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
            document.getElementById('vendor_id').value = item.dataset.id || '';
            inp.value = item.dataset.name || '';
            var codeEl = document.getElementById('vendor_code');
            var contactEl = document.getElementById('vendor_contact');
            var phoneEl = document.getElementById('vendor_phone');
            if (codeEl) codeEl.value = item.dataset.code || '';
            if (contactEl) contactEl.value = item.dataset.contact || '';
            if (phoneEl) phoneEl.value = item.dataset.phone || '';
            var taxEl = document.getElementById('vendor_tax_id');
            var faxEl = document.getElementById('vendor_fax');
            var emailEl = document.getElementById('vendor_email');
            var addrEl = document.getElementById('vendor_address');
            if (taxEl) taxEl.value = item.dataset.taxid || '';
            if (faxEl) faxEl.value = item.dataset.fax || '';
            if (emailEl) emailEl.value = item.dataset.email || '';
            if (addrEl) addrEl.value = item.dataset.address || '';
            dd.style.display = 'none';
        }
    });
    document.addEventListener('click', function(e){
        if (e.target.id !== 'vendor_name' && !e.target.closest('#poVendorDropdown')) dd.style.display = 'none';
    });
})();

// ---- 採購細項 動態列 ----
var itemIdx = <?= !empty($items) ? count($items) : 1 ?>;

function addItemRow() {
    var tbody = document.getElementById('itemBody');
    var tr = document.createElement('tr');
    var seq = tbody.querySelectorAll('tr').length + 1;
    tr.innerHTML = '<td class="item-seq">' + seq + '</td>'
        + '<td><input type="hidden" name="items['+itemIdx+'][product_id]" class="po-product-id" value=""><input type="text" name="items['+itemIdx+'][model]" class="form-control po-model" value="" readonly></td>'
        + '<td style="position:relative"><input type="text" name="items['+itemIdx+'][product_name]" class="form-control po-product-name" value="" autocomplete="off" placeholder="輸入關鍵字搜尋..." oninput="poSearchProduct(this)"><div class="po-product-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div></td>'
        + '<td><input type="text" name="items['+itemIdx+'][spec]" class="form-control" value=""></td>'
        + '<td><input type="number" name="items['+itemIdx+'][unit_price]" class="form-control item-price" step="1" min="0" value="0" oninput="calcRowAmount(this.closest(\'tr\'))"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][quantity]" class="form-control item-qty" step="1" min="0" value="0" oninput="calcRowAmount(this.closest(\'tr\'))"></td>'
        + '<td><input type="number" name="items['+itemIdx+'][amount]" class="form-control item-amount" step="1" min="0" value="0" readonly></td>'
        + '<td><input type="date" max="2099-12-31" name="items['+itemIdx+'][delivery_date]" class="form-control" value=""></td>'
        + '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">X</button></td>';
    tbody.appendChild(tr);
    itemIdx++;
}

function removeItemRow(btn) {
    btn.closest('tr').remove();
    reindexItems();
    calcSubtotal();
}

function reindexItems() {
    var rows = document.querySelectorAll('#itemBody tr');
    for (var i = 0; i < rows.length; i++) {
        rows[i].querySelector('.item-seq').textContent = i + 1;
    }
}

// ---- 金額計算 ----
function calcRowAmount(row) {
    var price = parseInt(row.querySelector('.item-price').value) || 0;
    var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    row.querySelector('.item-amount').value = Math.round(price * qty);
    calcSubtotal();
}

function calcSubtotal() {
    var total = 0;
    document.querySelectorAll('.item-amount').forEach(function(el) {
        total += parseFloat(el.value) || 0;
    });
    document.getElementById('subtotal').value = total;

    var taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    var taxAmount = Math.round(total * taxRate / 100);
    document.getElementById('tax_amount').value = taxAmount;

    var shipping = parseFloat(document.getElementById('shipping_fee').value) || 0;
    document.getElementById('total_amount').value = total + taxAmount + shipping;
}

// 頁面載入時初始計算
calcSubtotal();


</script>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
</style>
