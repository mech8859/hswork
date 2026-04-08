<?php
$isEdit = !empty($record);
$statusOptions = InvoiceModel::invoiceStatusOptions();
$typeOptions = InvoiceModel::purchaseInvoiceTypeOptions();
$deductionOptions = InvoiceModel::deductionTypeOptions();
$refOptions = InvoiceModel::referenceTypeOptions();
?>

<div>
    <h2 style="margin-bottom:2px"><?= $isEdit ? '編輯進項發票 - ' . e($record['invoice_number']) : '新增進項發票' ?></h2>
    <?php if ($isEdit && !empty($record['updated_at'])): ?>
    <small class="text-muted">最後修改 <?= e($record['updated_at']) ?><?php
        if (!empty($record['updated_by'])) {
            $updater = Database::getInstance()->prepare('SELECT real_name FROM users WHERE id = ?');
            $updater->execute(array($record['updated_by']));
            $un = $updater->fetchColumn();
            if ($un) echo ' / ' . e($un);
        }
    ?></small>
    <?php endif; ?>
</div>

<?php if (!empty($otherEditors)): ?>
<div style="display:flex;align-items:center;gap:8px;padding:12px 16px;border-radius:8px;background:#fff3cd;border:1px solid #ffc107;margin-bottom:12px;">
    <span style="font-size:1.2em">&#9888;</span>
    <span><?php $names = array(); foreach ($otherEditors as $oe) { $names[] = e($oe['user_name']); } echo implode(', ', $names); ?> 正在編輯此發票，儲存時可能會發生衝突。</span>
</div>
<?php endif; ?>

<?php $isVoided = $isEdit && !empty($record['status']) && $record['status'] === 'voided'; ?>
<?php if ($isVoided): ?>
<div style="padding:10px 14px;background:#ffebee;border:1px solid #e53935;border-radius:8px;margin-bottom:12px;color:#c62828;font-size:.9rem">
    ⊘ 此發票已作廢，無法編輯內容。如需徹底移除資料，請按右下角「刪除」。
</div>
<?php endif; ?>

<form method="POST" class="mt-2" id="purchaseInvoiceForm">
    <?= csrf_field() ?>
    <?php if ($isVoided): ?>
    <fieldset disabled style="border:none;padding:0;margin:0">
    <?php endif; ?>

    <!-- 買方資訊 -->
    <div class="card">
        <div class="card-header">買方資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>買方統一編號</label>
                <select name="buyer_tax_id" id="buyerTaxId" class="form-control" onchange="onBuyerChange(this)">
                    <option value="94081455" <?= ($isEdit && !empty($record['buyer_tax_id']) && $record['buyer_tax_id'] === '97002927') ? '' : 'selected' ?>>94081455</option>
                    <option value="97002927" <?= ($isEdit && !empty($record['buyer_tax_id']) && $record['buyer_tax_id'] === '97002927') ? 'selected' : '' ?>>97002927</option>
                </select>
            </div>
            <div class="form-group">
                <label>買方名稱</label>
                <input type="text" name="buyer_name" id="buyerName" class="form-control"
                       value="<?= e($isEdit && !empty($record['buyer_name']) ? $record['buyer_name'] : '禾順監視數位科技有限公司') ?>" readonly style="background:#f5f5f5">
            </div>
        </div>
    </div>
    <script>
    function onBuyerChange(sel) {
        var map = {'94081455': '禾順監視數位科技有限公司', '97002927': '政遠企業有限公司'};
        document.getElementById('buyerName').value = map[sel.value] || '';
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
                <label>進項發票聯式</label>
                <select name="invoice_format" class="form-control">
                    <option value="">請選擇</option>
                    <option value="21" <?= ($isEdit && !empty($record['invoice_format']) && $record['invoice_format'] === '21') ? 'selected' : '' ?>>21：進項三聯式、電子計算機統一發票</option>
                    <option value="22" <?= ($isEdit && !empty($record['invoice_format']) && $record['invoice_format'] === '22') ? 'selected' : '' ?>>22：進項二聯式收銀機統一發票、載有稅額之其他憑證</option>
                    <option value="23" <?= ($isEdit && !empty($record['invoice_format']) && $record['invoice_format'] === '23') ? 'selected' : '' ?>>23：三聯式進貨退出或折讓證明單</option>
                    <option value="24" <?= ($isEdit && !empty($record['invoice_format']) && $record['invoice_format'] === '24') ? 'selected' : '' ?>>24：二聯式進貨退出或折讓證明單</option>
                    <option value="25" <?= ($isEdit && !empty($record['invoice_format']) && $record['invoice_format'] === '25') ? 'selected' : '' ?>>25：進項三聯式收銀機統一發票、公用事業憑證</option>
                </select>
            </div>
            <div class="form-group">
                <label>扣抵別</label>
                <select name="deduction_category" class="form-control">
                    <option value="">請選擇</option>
                    <option value="deductible_purchase" <?= ($isEdit && !empty($record['deduction_category']) && $record['deduction_category'] === 'deductible_purchase') ? 'selected' : '' ?>>可扣抵之進貨及費用</option>
                    <option value="deductible_asset" <?= ($isEdit && !empty($record['deduction_category']) && $record['deduction_category'] === 'deductible_asset') ? 'selected' : '' ?>>可扣抵之固定資產</option>
                    <?php if ($isEdit && !empty($record['deduction_category']) && $record['deduction_category'] === 'non_deductible'): ?>
                    <option value="non_deductible" selected>不可扣抵之進貨及費用（已停用）</option>
                    <?php endif; ?>
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
                    <option value="<?= e($k) ?>" <?= ($isEdit && !empty($record['invoice_type']) ? $record['invoice_type'] : '應稅') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="pending" <?= ($isEdit && !empty($record['status']) ? $record['status'] : 'pending') === 'pending' ? 'selected' : '' ?>>待處理</option>
                    <option value="confirmed" <?= ($isEdit && !empty($record['status']) && $record['status'] === 'confirmed') ? 'selected' : '' ?>>已確認</option>
                    <option value="voided" <?= ($isEdit && !empty($record['status']) && $record['status'] === 'voided') ? 'selected' : '' ?>>已作廢</option>
                </select>
            </div>
            <?php if ($isEdit): ?>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <?php foreach ($statusOptions as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($record['status']) ? $record['status'] : 'pending') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 供應商 -->
    <?php
    // 預填值：編輯模式用 record，新增模式用 preset（從 URL 帶入）
    $prefVendorId    = $isEdit && !empty($record['vendor_id']) ? $record['vendor_id'] : (!empty($preset['vendor_id']) ? $preset['vendor_id'] : '');
    $prefVendorName  = $isEdit && !empty($record['vendor_name']) ? $record['vendor_name'] : (!empty($preset['vendor_name']) ? $preset['vendor_name'] : '');
    $prefVendorTaxId = $isEdit && !empty($record['vendor_tax_id']) ? $record['vendor_tax_id'] : (!empty($preset['vendor_tax_id']) ? $preset['vendor_tax_id'] : '');
    ?>
    <div class="card">
        <div class="card-header">供應商資訊<?php if (!$isEdit && !empty($returnToPayable)): ?> <span style="font-size:.8rem;color:#e65100">（儲存後將自動回寫至應付帳款單 #<?= (int)$returnToPayable ?>）</span><?php endif; ?></div>
        <input type="hidden" name="vendor_id" id="fldVendorId" value="<?= e($prefVendorId) ?>">
        <?php if (!$isEdit && !empty($returnToPayable)): ?>
        <input type="hidden" name="return_to_payable" value="<?= (int)$returnToPayable ?>">
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group" style="position:relative">
                <label>供應商（輸入關鍵字搜尋）</label>
                <input type="text" id="piVendorSearch" class="form-control" autocomplete="off"
                       placeholder="輸入廠商名稱、統編搜尋..."
                       value="<?= e($prefVendorName) ?>"
                       oninput="searchPiVendor(this)">
                <div id="piVendorDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid var(--gray-200);border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.15)"></div>
            </div>
            <div class="form-group">
                <label>賣方統一編號 *</label>
                <input type="text" name="vendor_tax_id" id="fldVendorTaxId" class="form-control"
                       value="<?= e($prefVendorTaxId) ?>"
                       maxlength="8" placeholder="8碼統編" required>
            </div>
            <div class="form-group">
                <label>賣方名稱 *</label>
                <input type="text" name="vendor_name" id="fldVendorName" class="form-control"
                       value="<?= e($prefVendorName) ?>" required>
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

    <input type="hidden" name="deduction_type" value="deductible">

    <!-- 關聯 -->
    <div class="card">
        <div class="card-header">關聯單據</div>
        <div class="form-row">
            <div class="form-group">
                <label>關聯單據類型</label>
                <select name="reference_type" class="form-control">
                    <?php foreach ($refOptions as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= ($isEdit && (!empty($record['reference_type']) ? $record['reference_type'] : '') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關聯單據編號</label>
                <?php
                $refId = $isEdit && !empty($record['reference_id']) ? $record['reference_id'] : '';
                $refType = $isEdit && !empty($record['reference_type']) ? $record['reference_type'] : '';
                // 根據類型解析連結
                $refLink = '';
                if ($refType === 'payable' && $refId) {
                    $pidStmt = Database::getInstance()->prepare("SELECT id FROM payables WHERE payable_number = ? LIMIT 1");
                    $pidStmt->execute(array($refId));
                    $pid = (int)$pidStmt->fetchColumn();
                    if ($pid > 0) $refLink = '/payables.php?action=edit&id=' . $pid;
                } elseif ($refType === 'purchase' && $refId) {
                    $gidStmt = Database::getInstance()->prepare("SELECT id FROM goods_receipts WHERE gr_number = ? LIMIT 1");
                    $gidStmt->execute(array($refId));
                    $gid = (int)$gidStmt->fetchColumn();
                    if ($gid > 0) $refLink = '/goods_receipts.php?action=view&id=' . $gid;
                }
                ?>
                <div style="position:relative">
                    <input type="text" name="reference_id" class="form-control"
                           value="<?= e($refId) ?>"
                           placeholder="選填"
                           <?= $refLink ? 'style="padding-right:40px"' : '' ?>>
                    <?php if ($refLink): ?>
                    <a href="<?= e($refLink) ?>" target="_blank" title="開啟關聯單據"
                       style="position:absolute;right:8px;top:50%;transform:translateY(-50%);color:var(--primary);text-decoration:none;font-size:1.1rem">🔗</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 登記資訊 -->
    <div class="card">
        <div class="card-header">登記資訊</div>
        <div class="form-row">
            <div class="form-group">
                <label>登記者</label>
                <input type="text" class="form-control" value="<?= e($isEdit && !empty($record['created_by_name']) ? $record['created_by_name'] : (Auth::user()['real_name'] ?? '')) ?>" readonly style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>登記時間</label>
                <input type="text" class="form-control" value="<?= e($isEdit && !empty($record['created_at']) ? $record['created_at'] : date('Y-m-d H:i')) ?>" readonly style="background:#f5f5f5">
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

    <?php if ($isVoided): ?>
    </fieldset>
    <?php endif; ?>

    <?php $isConfirmed = $isEdit && !empty($record['status']) && $record['status'] === 'confirmed'; ?>
    <?php if ($isVoided): ?>
    <!-- 作廢狀態：只顯示刪除 / 返回 -->
    <div class="d-flex justify-between mt-2">
        <a href="/purchase_invoices.php" class="btn btn-outline">返回列表</a>
        <?php if (Auth::hasPermission('accounting.manage')): ?>
        <button type="button" class="btn btn-outline" style="color:#c62828;border-color:#c62828" onclick="if(confirm('此發票將永久刪除，確定要繼續？'))document.getElementById('piDeleteForm').submit()">刪除</button>
        <?php endif; ?>
    </div>
    <?php elseif (!$isConfirmed): ?>
    <div class="d-flex justify-between mt-2">
        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '新增進項發票' ?></button>
            <a href="/purchase_invoices.php" class="btn btn-outline">取消</a>
        </div>
        <?php if ($isEdit && Auth::hasPermission('accounting.manage')): ?>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-danger" onclick="if(confirm('確定要作廢此發票？'))document.getElementById('piVoidForm').submit()">作廢此發票</button>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="mt-2">
        <?= back_button('/purchase_invoices.php') ?>
    </div>
    <?php endif; ?>
</form>

<?php if ($isEdit && Auth::hasPermission('accounting.manage')): ?>
    <?php if (!empty($record['status']) && $record['status'] !== 'voided'): ?>
    <form id="piVoidForm" method="POST" action="/purchase_invoices.php?action=void" style="display:none">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $record['id'] ?>">
    </form>
    <?php endif; ?>
    <?php if ($isVoided): ?>
    <form id="piDeleteForm" method="POST" action="/purchase_invoices.php?action=delete" style="display:none">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $record['id'] ?>">
    </form>
    <?php endif; ?>
<?php endif; ?>
<?php if ($isConfirmed && Auth::hasPermission('accounting.manage')): ?>
<div class="d-flex justify-end gap-1 mt-1">
    <form method="POST" action="/purchase_invoices.php?action=unconfirm" onsubmit="return confirm('確定要取消確認？取消後可編輯或刪除。')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $record['id'] ?>">
        <button type="submit" class="btn btn-warning">取消確認</button>
    </form>
    <form method="POST" action="/purchase_invoices.php?action=void" onsubmit="return confirm('確定要作廢此發票？')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $record['id'] ?>">
        <button type="submit" class="btn btn-danger">作廢此發票</button>
    </form>
</div>
<?php endif; ?>
<?php if ($isConfirmed): ?>
<style>
form#purchaseInvoiceForm input:not([type="hidden"]), form#purchaseInvoiceForm select, form#purchaseInvoiceForm textarea { pointer-events:none; background:#f5f5f5 !important; color:#757575 !important; }
</style>
<?php endif; ?>

<script>
// ---- 供應商關鍵字搜尋 ----
var piVendorTimer = null;
function searchPiVendor(inp) {
    clearTimeout(piVendorTimer);
    var q = inp.value.trim();
    var dd = document.getElementById('piVendorDropdown');
    if (q.length < 1) { dd.style.display = 'none'; return; }
    piVendorTimer = setTimeout(function(){
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/payments_out.php?action=ajax_vendor_search&q=' + encodeURIComponent(q));
        xhr.onload = function(){
            try { var list = JSON.parse(xhr.responseText); } catch(e) { return; }
            if (!list.length) { dd.innerHTML = '<div style="padding:8px;color:#999;font-size:.85rem">無符合廠商，可手動輸入統編與名稱</div>'; dd.style.display = 'block'; return; }
            var html = '';
            for (var i = 0; i < list.length; i++) {
                html += '<div style="padding:6px 10px;cursor:pointer;font-size:.85rem;border-bottom:1px solid #eee" ' +
                    'data-id="' + (list[i].id||'') + '" ' +
                    'data-name="' + (list[i].name||'').replace(/"/g,'&quot;') + '" ' +
                    'data-taxid="' + (list[i].tax_id||'').replace(/"/g,'&quot;') + '" ' +
                    'onmouseover="this.style.background=\'#f0f7ff\'" onmouseout="this.style.background=\'\'">' +
                    '<div style="font-weight:600">' + (list[i].name||'') + '</div>' +
                    '<div style="font-size:.75rem;color:#888">' +
                    (list[i].tax_id ? '統編: ' + list[i].tax_id + ' | ' : '') +
                    (list[i].contact_person ? list[i].contact_person : '') +
                    (list[i].phone ? ' | ' + list[i].phone : '') +
                    '</div></div>';
            }
            dd.innerHTML = html;
            dd.style.display = 'block';
        };
        xhr.send();
    }, 300);
}
document.getElementById('piVendorDropdown').addEventListener('click', function(e){
    var item = e.target.closest('div[data-name]');
    if (item) {
        document.getElementById('piVendorSearch').value = item.dataset.name;
        document.getElementById('fldVendorId').value = item.dataset.id || '';
        document.getElementById('fldVendorTaxId').value = item.dataset.taxid || '';
        document.getElementById('fldVendorName').value = item.dataset.name || '';
        document.getElementById('piVendorDropdown').style.display = 'none';
    }
});
document.addEventListener('click', function(e){
    if (e.target.id !== 'piVendorSearch' && !e.target.closest('#piVendorDropdown')) {
        document.getElementById('piVendorDropdown').style.display = 'none';
    }
});

function onTypeChange() {
    var type = document.getElementById('fldInvoiceType').value;
    if (type === '免稅' || type === '零稅率') {
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
