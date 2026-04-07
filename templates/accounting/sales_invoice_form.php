<?php
$isEdit = !empty($record);
$statusOptions = InvoiceModel::invoiceStatusOptions();
$typeOptions = InvoiceModel::salesInvoiceTypeOptions();
$refOptions = InvoiceModel::salesReferenceTypeOptions();
?>

<h2><?= $isEdit ? '編輯銷項發票 - ' . e($record['invoice_number']) : '新增銷項發票' ?></h2>

<?php if (!empty($otherEditors)): ?>
<div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-radius:8px;background:#fff3cd;border:1px solid #ffc107;margin-bottom:12px;">
    <span style="font-size:1.2em">&#9888;</span>
    <span><?php $names = array(); foreach ($otherEditors as $oe) { $names[] = e($oe['user_name']); } echo implode(', ', $names); ?> 正在編輯此發票，儲存時可能會發生衝突。</span>
</div>
<?php endif; ?>

<form method="POST" class="mt-2" id="salesInvoiceForm">
    <?= csrf_field() ?>
    <?php if (!empty($fromCaseId) && !empty($returnTo)): ?>
    <input type="hidden" name="case_id" value="<?= (int)$fromCaseId ?>">
    <input type="hidden" name="return" value="<?= e($returnTo) ?>">
    <div style="background:#e3f2fd;border:1px solid #1976d2;color:#1565c0;padding:10px 14px;border-radius:6px;margin-bottom:12px;font-size:.9rem">
        🔗 此發票由案件管理建立，存檔後將自動關聯至案件並跳回案件編輯頁
    </div>
    <?php endif; ?>

    <!-- 賣方資訊 -->
    <div class="card">
        <div class="card-header">賣方資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>賣方統一編號</label>
                <select name="seller_tax_id" id="sellerTaxId" class="form-control" onchange="onSellerChange(this)">
                    <option value="94081455" <?= ($isEdit && !empty($record['seller_tax_id']) && $record['seller_tax_id'] === '97002927') ? '' : 'selected' ?>>94081455</option>
                    <option value="97002927" <?= ($isEdit && !empty($record['seller_tax_id']) && $record['seller_tax_id'] === '97002927') ? 'selected' : '' ?>>97002927</option>
                </select>
            </div>
            <div class="form-group">
                <label>賣方名稱</label>
                <input type="text" name="seller_name" id="sellerName" class="form-control"
                       value="<?= e($isEdit && !empty($record['seller_name']) ? $record['seller_name'] : '禾順監視數位科技有限公司') ?>" readonly style="background:#f5f5f5">
            </div>
        </div>
    </div>
    <script>
    function onSellerChange(sel) {
        var map = {'94081455': '禾順監視數位科技有限公司', '97002927': '政遠企業有限公司'};
        document.getElementById('sellerName').value = map[sel.value] || '';
    }
    </script>

    <!-- 發票資訊 -->
    <div class="card">
        <div class="card-header">發票資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>申報年月</label>
                <input type="month" name="report_period" class="form-control"
                       value="<?= e($isEdit && !empty($record['report_period']) ? $record['report_period'] : date('Y-m')) ?>">
            </div>
            <div class="form-group">
                <label>銷項發票聯式</label>
                <select name="invoice_format" class="form-control">
                    <option value="">請選擇</option>
                    <option value="31" <?= ($isEdit && !empty($record['invoice_format']) && $record['invoice_format'] === '31') ? 'selected' : '' ?>>31：銷項三聯式、電子計算機統一發票</option>
                    <option value="32" <?= ($isEdit && !empty($record['invoice_format']) && $record['invoice_format'] === '32') ? 'selected' : '' ?>>32：銷項二聯式、二聯式收銀機統一發票</option>
                    <option value="33" <?= ($isEdit && !empty($record['invoice_format']) && $record['invoice_format'] === '33') ? 'selected' : '' ?>>33：三聯式銷貨退回或折讓證明單</option>
                    <option value="34" <?= ($isEdit && !empty($record['invoice_format']) && $record['invoice_format'] === '34') ? 'selected' : '' ?>>34：二聯式銷貨退回或折讓證明單</option>
                    <option value="35" <?= ($isEdit && !empty($record['invoice_format']) && $record['invoice_format'] === '35') ? 'selected' : '' ?>>35：銷項三聯式收銀機統一發票、電子發票</option>
                </select>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="pending" <?= ($isEdit && !empty($record['status']) ? $record['status'] : 'pending') === 'pending' ? 'selected' : '' ?>>待處理</option>
                    <option value="confirmed" <?= ($isEdit && !empty($record['status']) && $record['status'] === 'confirmed') ? 'selected' : '' ?>>開立已確認</option>
                    <option value="voided" <?= ($isEdit && !empty($record['status']) && $record['status'] === 'voided') ? 'selected' : '' ?>>作廢已確認</option>
                    <option value="blank" <?= ($isEdit && !empty($record['status']) && $record['status'] === 'blank') ? 'selected' : '' ?>>空白發票</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>發票號碼 *</label>
                <input type="text" name="invoice_number" class="form-control inv-number-input"
                       value="<?= e($isEdit && !empty($record['invoice_number']) ? $record['invoice_number'] : '') ?>"
                       required placeholder="例: AB12345678" maxlength="10"
                       oninput="formatInvoiceNumber(this)" onblur="validateInvoiceNumber(this)"
                       style="text-transform:uppercase">
            </div>
            <div class="form-group">
                <label>發票日期 *</label>
                <input type="date" max="2099-12-31" name="invoice_date" class="form-control"
                       value="<?= e($isEdit && !empty($record['invoice_date']) ? $record['invoice_date'] : date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>發票類型</label>
                <select name="invoice_type" id="fldInvoiceType" class="form-control" onchange="onTypeChange()">
                    <?php foreach ($typeOptions as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= ($isEdit && !empty($record['invoice_type']) ? $record['invoice_type'] : '三聯式') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- 客戶資訊 -->
    <div class="card">
        <div class="card-header">客戶資訊</div>
        <!-- 隱藏的客戶資料供 JS 查詢 -->
        <script>
        var customerData = [
            <?php foreach ($customers as $c): ?>
            {taxId: <?= json_encode(!empty($c['tax_id']) ? trim($c['tax_id']) : '') ?>, title: <?= json_encode(!empty($c['invoice_title']) ? trim($c['invoice_title']) : (!empty($c['name']) ? trim($c['name']) : '')) ?>},
            <?php endforeach; ?>
        ];
        console.log('customerData loaded:', customerData.length, 'records');
        function onSalesTaxIdInput(el) {
            var taxId = el.value.trim();
            if (taxId.length === 8) {
                for (var i = 0; i < customerData.length; i++) {
                    if (customerData[i].taxId === taxId) {
                        document.getElementById('fldCustomerName').value = customerData[i].title;
                        return;
                    }
                }
            }
        }
        </script>
        <div class="form-row">
            <div class="form-group">
                <label>買方統一編號 *</label>
                <input type="text" name="customer_tax_id" id="fldCustomerTaxId" class="form-control"
                       value="<?= e($isEdit && !empty($record['customer_tax_id']) ? $record['customer_tax_id'] : (!empty($prefillCustomerTaxId) ? $prefillCustomerTaxId : '')) ?>"
                       maxlength="8" placeholder="8碼統編" required oninput="onSalesTaxIdInput(this)">
            </div>
            <div class="form-group">
                <label>買方名稱 *</label>
                <input type="text" name="customer_name" id="fldCustomerName" class="form-control"
                       value="<?= e($isEdit && !empty($record['customer_name']) ? $record['customer_name'] : (!empty($prefillCustomerName) ? $prefillCustomerName : '')) ?>" required>
            </div>
        </div>
    </div>

    <!-- 金額 -->
    <div class="card">
        <div class="card-header">金額</div>
        <div class="form-row">
            <div class="form-group">
                <label>未稅金額 *</label>
                <input type="number" name="amount_untaxed" id="fldUntaxed" class="form-control" step="1" min="0"
                       value="<?= $isEdit && !empty($record['amount_untaxed']) ? (int)$record['amount_untaxed'] : '' ?>"
                       oninput="calcTax()" required>
            </div>
            <div class="form-group">
                <label>稅額</label>
                <input type="number" name="tax_amount" id="fldTaxAmt" class="form-control" step="1" min="0"
                       value="<?= $isEdit && !empty($record['tax_amount']) ? (int)$record['tax_amount'] : '' ?>"
                       oninput="calcTotal()">
            </div>
            <div class="form-group">
                <label>含稅金額</label>
                <input type="number" name="total_amount" id="fldTotal" class="form-control" step="1" min="0"
                       value="<?= $isEdit && !empty($record['total_amount']) ? (int)$record['total_amount'] : '' ?>" readonly>
            </div>
            <input type="hidden" name="tax_rate" id="fldTaxRate" value="<?= $isEdit && isset($record['tax_rate']) ? $record['tax_rate'] : 5 ?>">
        </div>
    </div>

    <!-- 關聯單據 -->
    <div class="card">
        <div class="card-header">關聯單據</div>
        <div class="form-row">
            <div class="form-group">
                <label>關聯類型</label>
                <select name="reference_type" class="form-control">
                    <?php foreach ($refOptions as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= ($isEdit && (!empty($record['reference_type']) ? $record['reference_type'] : '') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關聯單據編號</label>
                <input type="text" name="reference_id" class="form-control"
                       value="<?= e($isEdit && !empty($record['reference_id']) ? $record['reference_id'] : '') ?>"
                       placeholder="選填">
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

    <?php $isConfirmed = $isEdit && !empty($record['status']) && $record['status'] === 'confirmed'; ?>
    <?php if (!$isConfirmed): ?>
    <div class="d-flex justify-between mt-2">
        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '新增銷項發票' ?></button>
            <a href="/sales_invoices.php" class="btn btn-outline">取消</a>
        </div>
        <?php if ($isEdit && Auth::hasPermission('accounting.manage')): ?>
        <div class="d-flex gap-1">
            <?php if (!empty($record['status']) && $record['status'] !== 'voided'): ?>
            <form method="POST" action="/sales_invoices.php?action=void" style="display:inline" onsubmit="return confirm('確定要作廢此發票？')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $record['id'] ?>">
                <button type="submit" class="btn btn-danger">作廢此發票</button>
            </form>
            <?php endif; ?>
            <?php if (!empty($record['status']) && $record['status'] === 'pending'): ?>
            <form method="POST" action="/sales_invoices.php?action=delete" style="display:inline" onsubmit="return confirm('確定要刪除此發票？')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $record['id'] ?>">
                <button type="submit" class="btn btn-outline" style="color:#c62828;border-color:#c62828">刪除</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="mt-2">
        <?= back_button('/sales_invoices.php') ?>
    </div>
    <?php endif; ?>
</form>
<?php if ($isConfirmed && Auth::hasPermission('accounting.manage')): ?>
<div class="d-flex justify-end gap-1 mt-1">
    <form method="POST" action="/sales_invoices.php?action=unconfirm" onsubmit="return confirm('確定要取消確認？取消後可編輯或刪除。')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $record['id'] ?>">
        <button type="submit" class="btn btn-warning">取消確認</button>
    </form>
    <form method="POST" action="/sales_invoices.php?action=void" onsubmit="return confirm('確定要作廢此發票？')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $record['id'] ?>">
        <button type="submit" class="btn btn-danger">作廢此發票</button>
    </form>
</div>
<?php endif; ?>
<?php if ($isConfirmed): ?>
<style>
#salesInvoiceForm input:not([type="hidden"]), #salesInvoiceForm select, #salesInvoiceForm textarea { pointer-events:none; background:#f5f5f5 !important; color:#757575 !important; }
</style>
<?php endif; ?>

<script>
// onCustomerSelect removed - replaced by onSalesTaxIdInput

function onTypeChange() {
    var type = document.getElementById('fldInvoiceType').value;
    if (type === '免稅') {
        document.getElementById('fldTaxAmt').value = 0;
        document.getElementById('fldTaxRate').value = 0;
    } else {
        document.getElementById('fldTaxRate').value = 5;
    }
    calcTax();
}

function calcTax() {
    var untaxed = parseInt(document.getElementById('fldUntaxed').value) || 0;
    var type = document.getElementById('fldInvoiceType').value;
    var tax = 0;
    if (type !== '免稅' && type !== '零稅率') {
        tax = Math.round(untaxed * 0.05);
    }
    document.getElementById('fldTaxAmt').value = tax || '';
    calcTotal();
}

function calcTotal() {
    var untaxed = parseInt(document.getElementById('fldUntaxed').value) || 0;
    var tax = parseInt(document.getElementById('fldTaxAmt').value) || 0;
    document.getElementById('fldTotal').value = (untaxed + tax) || '';
}
</script>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
</style>
