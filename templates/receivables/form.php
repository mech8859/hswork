<?php
$isEdit = !empty($record);
$statusOptions = FinanceModel::receivableStatusOptions();
$categoryOptions = FinanceModel::invoiceCategoryOptions();
$paymentMethodOptions = FinanceModel::paymentMethodOptions();
$paymentTermsOptions = FinanceModel::paymentTermsOptions();
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= $isEdit ? '編輯請款單 - ' . e($record['receivable_number'] ?: $record['invoice_number']) : '新增請款單' ?></h2>
        <?php if ($isEdit && !empty($record['updated_at'])): ?>
        <?php
        $updaterName = '';
        if (!empty($record['updated_by'])) {
            $uStmt = Database::getInstance()->prepare("SELECT real_name FROM users WHERE id = ?");
            $uStmt->execute(array($record['updated_by']));
            $updaterName = $uStmt->fetchColumn() ?: '';
        }
        ?>
        <span style="font-size:.8rem;color:var(--gray-500)">最後修改：<?= date('Y/m/d H:i', strtotime($record['updated_at'])) ?><?= $updaterName ? ' by ' . e($updaterName) : '' ?></span>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <?php if ($isEdit && (Auth::hasPermission('finance.delete') || Auth::hasPermission('all'))): ?>
        <button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('確定刪除此請款單？'))document.getElementById('deleteForm').submit()">刪除</button>
        <?php endif; ?>
        <?= back_button('/receivables.php') ?>
    </div>
</div>
<?php if ($isEdit): ?>
<form id="deleteForm" method="POST" action="/receivables.php?action=delete&id=<?= $record['id'] ?>" style="display:none"><?= csrf_field() ?></form>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/editing_lock_warning.php'; ?>

<form method="POST" class="mt-2">
    <?= csrf_field() ?>

    <!-- 訂單資訊 -->
    <div class="card">
        <div class="card-header">訂單資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>請款單號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? ($record['receivable_number'] ?: $record['invoice_number']) : peek_next_doc_number('receivables')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>傳票號碼</label>
                <input type="text" name="voucher_number" class="form-control" value="<?= e($record['voucher_number'] ?? '') ?>" placeholder="AR1-...">
            </div>
            <div class="form-group">
                <label>請款日期 *</label>
                <input type="date" max="2099-12-31" name="invoice_date" class="form-control" value="<?= e(!empty($record['invoice_date']) ? $record['invoice_date'] : date('Y-m-d')) ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>進件編號</label>
                <input type="text" name="case_number" class="form-control" value="<?= e($record['case_number'] ?? '') ?>" placeholder="例：2026-1729">
            </div>
            <div class="form-group">
                <label>客戶編號</label>
                <input type="text" name="customer_no" class="form-control" value="<?= e($record['customer_no'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>客戶名稱</label>
                <input type="text" name="customer_name" class="form-control" value="<?= e($record['customer_name'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= (!empty($record['branch_id']) && $record['branch_id'] == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>業務人員</label>
                <select name="sales_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($salesUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (!empty($record['sales_id']) && $record['sales_id'] == $u['id']) ? 'selected' : '' ?>><?= e($u['real_name']) ?><?= !empty($u['branch_name']) ? ' (' . e($u['branch_name']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>請款類別</label>
                <select name="invoice_category" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($categoryOptions as $cv => $cl): ?>
                    <option value="<?= e($cv) ?>" <?= (!empty($record['invoice_category']) && $record['invoice_category'] === $cv) ? 'selected' : '' ?>><?= e($cl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <?php foreach ($statusOptions as $sv => $sl): ?>
                    <option value="<?= e($sv) ?>" <?= (($record['status'] ?? '待請款') === $sv) ? 'selected' : '' ?>><?= e($sl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- 客戶資訊 -->
    <div class="card">
        <div class="card-header">客戶資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>發票抬頭</label>
                <input type="text" name="invoice_title" class="form-control" value="<?= e($record['invoice_title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>統一編號</label>
                <input type="text" name="tax_id" class="form-control" value="<?= e($record['tax_id'] ?? '') ?>" maxlength="8">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>電話</label>
                <input type="text" name="phone" class="form-control" value="<?= e($record['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>手機</label>
                <input type="text" name="mobile" class="form-control" value="<?= e($record['mobile'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>付款方式</label>
                <select name="payment_method" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($paymentMethodOptions as $pv => $pl): ?>
                    <option value="<?= e($pv) ?>" <?= (!empty($record['payment_method']) && $record['payment_method'] === $pv) ? 'selected' : '' ?>><?= e($pl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>付款條件</label>
                <select name="payment_terms" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($paymentTermsOptions as $tv => $tl): ?>
                    <option value="<?= e($tv) ?>" <?= (!empty($record['payment_terms']) && $record['payment_terms'] === $tv) ? 'selected' : '' ?>><?= e($tl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>發票 Email</label>
                <input type="email" name="invoice_email" class="form-control" value="<?= e($record['invoice_email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>發票地址</label>
                <input type="text" name="invoice_address" class="form-control" value="<?= e($record['invoice_address'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- 發票/憑證 -->
    <div class="card">
        <div class="card-header">發票/憑證</div>
        <div class="form-row">
            <div class="form-group">
                <label>發票日期</label>
                <input type="date" max="2099-12-31" name="real_invoice_date" class="form-control" value="<?= e($record['invoice_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>發票號碼</label>
                <input type="text" name="real_invoice_number" class="form-control" value="<?= e($record['real_invoice_number'] ?? '') ?>" placeholder="例：ZN00168842">
            </div>
            <div class="form-group">
                <label>憑證類別</label>
                <select name="voucher_type" class="form-control">
                    <option value="">請選擇</option>
                    <option value="三聯發票" <?= ($record['voucher_type'] ?? '') === '三聯發票' ? 'selected' : '' ?>>三聯發票</option>
                    <option value="二聯發票" <?= ($record['voucher_type'] ?? '') === '二聯發票' ? 'selected' : '' ?>>二聯發票</option>
                    <option value="免稅發票" <?= ($record['voucher_type'] ?? '') === '免稅發票' ? 'selected' : '' ?>>免稅發票</option>
                    <option value="收據" <?= ($record['voucher_type'] ?? '') === '收據' ? 'selected' : '' ?>>收據</option>
                    <option value="免開" <?= ($record['voucher_type'] ?? '') === '免開' ? 'selected' : '' ?>>免開</option>
                </select>
            </div>
            <div class="form-group">
                <label>稅率</label>
                <select name="tax_rate" class="form-control">
                    <option value="">請選擇</option>
                    <option value="5%" <?= ($record['tax_rate'] ?? '') === '5%' ? 'selected' : '' ?>>5%</option>
                    <option value="0%" <?= ($record['tax_rate'] ?? '') === '0%' ? 'selected' : '' ?>>0%（免稅）</option>
                </select>
            </div>
        </div>
    </div>

    <!-- 金額 -->
    <div class="card">
        <div class="card-header">金額</div>
        <div class="form-row">
            <div class="form-group">
                <label>訂金</label>
                <input type="number" name="deposit" class="form-control" value="<?= e($record['deposit'] ?? 0) ?>" min="0">
            </div>
            <div class="form-group">
                <label>折讓金額</label>
                <input type="number" name="discount" class="form-control" value="<?= e($record['discount'] ?? 0) ?>" min="0">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>小計（未稅）</label>
                <input type="number" name="subtotal" id="subtotalInput" class="form-control" value="<?= e($record['subtotal'] ?? 0) ?>" min="0" oninput="calcTotal()">
            </div>
            <div class="form-group">
                <label>稅額</label>
                <input type="number" name="tax" id="taxInput" class="form-control" value="<?= e($record['tax'] ?? 0) ?>" min="0" oninput="calcTotal()">
            </div>
            <div class="form-group">
                <label>運費</label>
                <input type="number" name="shipping" class="form-control" value="<?= e($record['shipping'] ?? 0) ?>" min="0" oninput="calcTotal()">
            </div>
            <div class="form-group">
                <label>總計</label>
                <input type="number" name="total_amount" id="totalInput" class="form-control" value="<?= e($record['total_amount'] ?? 0) ?>" readonly style="background:#f0f7ff;font-weight:600">
            </div>
        </div>
    </div>

    <!-- 合併請款案件 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>請款明細</span>
            <button type="button" class="btn btn-outline btn-sm" onclick="addItemRow()">+ 新增一行</button>
        </div>
        <div class="table-responsive" style="overflow:visible">
            <table class="table" id="itemsTable">
                <thead>
                    <tr><th style="width:40px">項次</th><th>品名</th><th class="text-right" style="width:100px">單價</th><th class="text-right" style="width:80px">數量</th><th class="text-right" style="width:110px">未稅金額</th><th style="width:150px">備註</th><th style="width:50px"></th></tr>
                </thead>
                <tbody id="itemsBody">
                    <?php
                    $itemList = !empty($items) ? $items : array(array('item_name'=>'','unit_price'=>'','quantity'=>'1','amount'=>'','note'=>''));
                    foreach ($itemList as $idx => $item):
                    ?>
                    <tr class="item-row">
                        <td class="item-seq"><?= $idx + 1 ?></td>
                        <td><input type="text" name="items[<?= $idx ?>][item_name]" class="form-control" value="<?= htmlspecialchars(isset($item['item_name']) ? $item['item_name'] : '') ?>"></td>
                        <td><input type="number" name="items[<?= $idx ?>][unit_price]" class="form-control text-right recv-price" step="1" min="0" value="<?= isset($item['unit_price']) ? (int)$item['unit_price'] : '' ?>" oninput="calcRecvRow(this)"></td>
                        <td><input type="number" name="items[<?= $idx ?>][quantity]" class="form-control text-right recv-qty" step="1" min="1" value="<?= isset($item['quantity']) ? (int)$item['quantity'] : 1 ?>" oninput="calcRecvRow(this)"></td>
                        <td><input type="number" name="items[<?= $idx ?>][amount]" class="form-control text-right recv-amount" step="1" min="0" value="<?= isset($item['amount']) ? (int)$item['amount'] : '' ?>" readonly style="background:#f5f5f5"></td>
                        <td><input type="text" name="items[<?= $idx ?>][note]" class="form-control" value="<?= htmlspecialchars(isset($item['note']) ? $item['note'] : '') ?>"></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRecvRow(this)">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 備註 + 登記人 -->
    <div class="card">
        <div class="card-header">備註</div>
        <div class="form-row">
            <div class="form-group" style="flex:3">
                <textarea name="note" class="form-control" rows="3"><?= e($record['note'] ?? '') ?></textarea>
            </div>
            <div class="form-group" style="flex:1">
                <label>登記人</label>
                <?php
                $regName = '';
                if ($isEdit) {
                    if (!empty($record['registrar'])) {
                        $regName = $record['registrar'];
                    } elseif (!empty($record['created_by'])) {
                        $cuStmt = Database::getInstance()->prepare('SELECT real_name FROM users WHERE id = ?');
                        $cuStmt->execute(array($record['created_by']));
                        $regName = $cuStmt->fetchColumn() ?: '';
                    }
                } else {
                    $regName = Session::getUser()['real_name'] ?? '';
                }
                ?>
                <input type="text" class="form-control" value="<?= e($regName) ?>" readonly style="background:#f5f5f5">
                <small class="text-muted"><?= $isEdit && !empty($record['created_at']) ? date('Y/m/d H:i', strtotime($record['created_at'])) : date('Y/m/d H:i') ?></small>
            </div>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '更新' : '儲存' ?></button>
        <a href="/receivables.php" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.item-row td { padding: 4px 6px; vertical-align: middle; }
.item-row .form-control { margin-bottom: 0; }
</style>

<script>
var itemIndex = <?= count($itemList) ?>;
function addItemRow() {
    var seq = document.querySelectorAll('#itemsBody tr').length + 1;
    var html = '<tr class="item-row">' +
        '<td class="item-seq">' + seq + '</td>' +
        '<td><input type="text" name="items[' + itemIndex + '][item_name]" class="form-control"></td>' +
        '<td><input type="number" name="items[' + itemIndex + '][unit_price]" class="form-control text-right recv-price" step="1" min="0" oninput="calcRecvRow(this)"></td>' +
        '<td><input type="number" name="items[' + itemIndex + '][quantity]" class="form-control text-right recv-qty" step="1" min="1" value="1" oninput="calcRecvRow(this)"></td>' +
        '<td><input type="number" name="items[' + itemIndex + '][amount]" class="form-control text-right recv-amount" step="1" readonly style="background:#f5f5f5"></td>' +
        '<td><input type="text" name="items[' + itemIndex + '][note]" class="form-control"></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeRecvRow(this)">✕</button></td></tr>';
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', html);
    itemIndex++;
}
// 防呆：移除千位逗號避免 parseFloat('1,143') → 1 的 bug
function readNumRecv(el) {
    if (!el) return 0;
    var v = String(el.value || '0').replace(/,/g, '').trim();
    var n = parseFloat(v);
    return isNaN(n) ? 0 : n;
}
// 標記稅額是否被使用者手動編輯過
// 編輯模式：如果存進來的稅額 ≠ 小計×5% → 視為已手動（保留原值）
var recvTaxManualEdited = false;
<?php if ($isEdit): ?>
(function() {
    var savedSubtotal = <?= (int)($record['subtotal'] ?? 0) ?>;
    var savedTax = <?= (int)($record['tax'] ?? 0) ?>;
    var calcTax = Math.round(savedSubtotal * 0.05);
    if (savedTax !== calcTax) {
        recvTaxManualEdited = true;
    }
})();
<?php endif; ?>
var recvTaxEl = document.getElementById('taxInput');
if (recvTaxEl) {
    recvTaxEl.addEventListener('input', function() { recvTaxManualEdited = true; });
}
function calcRecvRow(el) {
    var row = el.closest('tr');
    var price = readNumRecv(row.querySelector('.recv-price'));
    var qty = readNumRecv(row.querySelector('.recv-qty')) || 1;
    row.querySelector('.recv-amount').value = Math.round(price * qty);
}
function removeRecvRow(btn) {
    var tbody = document.getElementById('itemsBody');
    if (tbody.querySelectorAll('tr').length <= 1) return;
    btn.closest('tr').remove();
    var rows = tbody.querySelectorAll('tr');
    for (var i = 0; i < rows.length; i++) rows[i].querySelector('.item-seq').textContent = i + 1;
}
function calcTotal() {
    var s = readNumRecv(document.getElementById('subtotalInput'));
    // 小計變動 + 稅額未被手動編輯 → 自動以 5% 計算
    if (!recvTaxManualEdited && recvTaxEl) {
        recvTaxEl.value = Math.round(s * 0.05);
    }
    var t = readNumRecv(document.getElementById('taxInput'));
    var sh = readNumRecv(document.querySelector('[name="shipping"]'));
    document.getElementById('totalInput').value = s + t + sh;
}
calcTotal();
</script>
