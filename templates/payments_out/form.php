<?php $isEdit = !empty($record); ?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= $isEdit ? '編輯付款單 - ' . e(!empty($record['payment_number']) ? $record['payment_number'] : $record['id']) : '新增付款單' ?></h2>
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
        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeletePayment(<?= $record['id'] ?>, '<?= e($record['payment_number']) ?>')">刪除</button>
        <?php endif; ?>
        <?= back_button('/payments_out.php') ?>
    </div>
</div>

<form method="POST" class="mt-2" id="paymentOutForm">
    <?= csrf_field() ?>

    <!-- 基本資訊 -->
    <div class="card">
        <div class="card-header">基本資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>付款單號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? $record['payment_number'] : peek_next_doc_number('payments_out')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group">
                <label>建立日期 *</label>
                <input type="date" max="2099-12-31" name="create_date" class="form-control" value="<?= e(!empty($record['create_date']) ? $record['create_date'] : date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>付款日期</label>
                <input type="date" max="2099-12-31" name="payment_date" class="form-control" value="<?= e(!empty($record['payment_date']) ? $record['payment_date'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>進件編號</label>
                <input type="text" name="case_number" class="form-control" value="<?= e($record['case_number'] ?? '') ?>" placeholder="例：2026-0028">
            </div>
            <div class="form-group">
                <label>客戶編號</label>
                <input type="text" name="customer_no" class="form-control" value="<?= e($record['customer_no'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>應付帳款單號</label>
                <div style="position:relative">
                    <input type="text" id="payableSearch" class="form-control" value="<?= e(!empty($record['payable_id']) ? ($record['payable_number'] ?? '') : '') ?>" placeholder="輸入搜尋應付帳款..." autocomplete="off">
                    <input type="hidden" name="payable_id" id="payableId" value="<?= e($record['payable_id'] ?? '') ?>">
                    <div id="payableSuggestions" class="autocomplete-list"></div>
                </div>
            </div>
            <div class="form-group">
                <label>廠商編號</label>
                <input type="text" name="vendor_code" id="vendorCode" class="form-control" value="<?= e($record['vendor_code'] ?? '') ?>" readonly style="background:#f5f5f5">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>廠商名稱 *</label>
                <div style="position:relative">
                    <input type="text" name="vendor_name" id="vendorName" class="form-control" value="<?= e(!empty($record['vendor_name']) ? $record['vendor_name'] : '') ?>" placeholder="輸入廠商名稱搜尋..." autocomplete="off" required>
                    <div id="vendorSuggestions" class="autocomplete-list"></div>
                </div>
            </div>
            <div class="form-group">
                <label>付款方式 *</label>
                <select name="payment_method" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach (FinanceModel::paymentOutMethodOptions() as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= (!empty($record['payment_method']) ? $record['payment_method'] : '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>付款類別 *</label>
                <select name="payment_type" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach (FinanceModel::paymentOutCategoryOptions() as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= (!empty($record['payment_type']) ? $record['payment_type'] : '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>付款條件</label>
                <select name="payment_terms" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach (FinanceModel::paymentTermsOptions() as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= (!empty($record['payment_terms']) ? $record['payment_terms'] : '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <?php foreach (FinanceModel::paymentOutStatusOptions() as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= ((!empty($record['status']) ? $record['status'] : '待付款') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>登記人</label>
                <?php
                $registrarName = '';
                if ($isEdit) {
                    if (!empty($record['registrar'])) {
                        $registrarName = $record['registrar'];
                    } elseif (!empty($record['created_by'])) {
                        $cuStmt = Database::getInstance()->prepare('SELECT real_name FROM users WHERE id = ?');
                        $cuStmt->execute(array($record['created_by']));
                        $registrarName = $cuStmt->fetchColumn() ?: '';
                    }
                } else {
                    $registrarName = Session::getUser()['real_name'] ?? '';
                }
                ?>
                <input type="text" class="form-control" value="<?= e($registrarName) ?>" readonly style="background:#f5f5f5">
                <small class="text-muted"><?= $isEdit && !empty($record['created_at']) ? date('Y/m/d H:i', strtotime($record['created_at'])) : date('Y/m/d H:i') ?></small>
            </div>
        </div>
    </div>

    <!-- 分類 -->
    <div class="card">
        <div class="card-header">分類</div>
        <div class="form-row">
            <div class="form-group">
                <label>主分類</label>
                <select name="main_category" id="mainCategory" class="form-control" onchange="updateSubCategory()">
                    <option value="">請選擇</option>
                    <?php foreach (FinanceModel::mainCategoryOptions() as $label): ?>
                    <option value="<?= e($label) ?>" <?= (!empty($record['main_category']) && $record['main_category'] === $label) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>子分類</label>
                <select name="sub_category" id="subCategory" class="form-control">
                    <option value="">請先選擇主分類</option>
                </select>
            </div>
        </div>
    </div>

    <!-- 金額 -->
    <div class="card">
        <div class="card-header">金額</div>
        <div class="form-row">
            <div class="form-group">
                <label>小計</label>
                <input type="number" name="subtotal" id="subtotal" class="form-control" value="<?= !empty($record['subtotal']) ? (int)$record['subtotal'] : 0 ?>" min="0" onchange="calcAmounts()">
            </div>
            <div class="form-group">
                <label>稅額 (5%)</label>
                <input type="number" name="tax" id="tax" class="form-control" value="<?= !empty($record['tax']) ? (int)$record['tax'] : 0 ?>" min="0" oninput="onTaxManual()" title="預設依小計×5%自動帶入，可手動修改">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>匯費</label>
                <input type="number" name="remittance_fee" id="remittanceFee" class="form-control" value="<?= !empty($record['remittance_fee']) ? (int)$record['remittance_fee'] : 0 ?>" min="0" onchange="calcAmounts()">
            </div>
            <div class="form-group">
                <label>總金額</label>
                <input type="number" name="total_amount" id="totalAmount" class="form-control" value="<?= !empty($record['total_amount']) ? (int)$record['total_amount'] : 0 ?>" readonly>
            </div>
        </div>
    </div>

    <!-- 分公司拆帳 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>分公司拆帳</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addBranchRow()">+ 新增</button>
        </div>
        <div class="table-responsive">
            <table class="table" id="branchTable">
                <thead>
                    <tr>
                        <th>分公司</th>
                        <th style="width:140px">金額</th>
                        <th>備註</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="branchBody">
                    <?php if (!empty($branchItems)): ?>
                        <?php foreach ($branchItems as $idx => $item): ?>
                        <tr>
                            <td>
                                <select name="branches[<?= $idx ?>][branch_id]" class="form-control">
                                    <option value="">請選擇</option>
                                    <?php foreach ($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= (!empty($item['branch_id']) ? $item['branch_id'] : '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="branches[<?= $idx ?>][amount]" class="form-control" value="<?= !empty($item['amount']) ? (int)$item['amount'] : 0 ?>" min="0"></td>
                            <td><input type="text" name="branches[<?= $idx ?>][note]" class="form-control" value="<?= e(!empty($item['note']) ? $item['note'] : '') ?>"></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 憑證明細 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>憑證明細</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addVoucherRow()">+ 新增</button>
        </div>
        <div class="table-responsive">
            <table class="table" id="voucherTable">
                <thead>
                    <tr>
                        <th>憑證類型</th>
                        <th style="width:140px">金額</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="voucherBody">
                    <?php if (!empty($voucherItems)): ?>
                        <?php foreach ($voucherItems as $idx => $item): ?>
                        <tr>
                            <td><input type="text" name="vouchers[<?= $idx ?>][voucher_type]" class="form-control" value="<?= e(!empty($item['voucher_type']) ? $item['voucher_type'] : '') ?>"></td>
                            <td><input type="number" name="vouchers[<?= $idx ?>][amount]" class="form-control voucher-amount" value="<?= !empty($item['amount']) ? (int)$item['amount'] : 0 ?>" min="0" onchange="calcVoucherTotal()"></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();calcVoucherTotal()">X</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight:600; background:var(--gray-100);">
                        <td class="text-right">合計</td>
                        <td id="voucherTotal" class="text-right">$0</td>
                        <td></td>
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

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立付款單' ?></button>
        <a href="/payments_out.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
function confirmDeletePayment(id, number) {
    if (!confirm('確定要刪除付款單 ' + number + '？\n\n此操作無法復原。')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/payments_out.php?action=delete&id=' + id;
    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = 'csrf_token';
    csrf.value = document.querySelector('input[name="csrf_token"]').value;
    form.appendChild(csrf);
    document.body.appendChild(form);
    form.submit();
}

// ---- 主分類 / 子分類 連動 ----
var subCategoryMap = <?= json_encode(FinanceModel::subCategoryMap(), JSON_UNESCAPED_UNICODE) ?>;
var currentSubCategory = <?= json_encode(!empty($record['sub_category']) ? $record['sub_category'] : '') ?>;

function updateSubCategory() {
    var mainVal = document.getElementById('mainCategory').value;
    var subSelect = document.getElementById('subCategory');
    subSelect.innerHTML = '<option value="">請選擇</option>';
    if (mainVal && subCategoryMap[mainVal]) {
        var subs = subCategoryMap[mainVal];
        for (var i = 0; i < subs.length; i++) {
            var opt = document.createElement('option');
            opt.value = subs[i];
            opt.textContent = subs[i];
            if (subs[i] === currentSubCategory) {
                opt.selected = true;
            }
            subSelect.appendChild(opt);
        }
    }
}
// 初始載入時觸發
updateSubCategory();

// ---- 金額自動計算 ----
// 稅額預設依小計×5%自動帶入；使用者手動修改後不再覆寫
var taxManualEdited = <?= ($isEdit && !empty($record['subtotal']) && !empty($record['tax']) && (int)$record['tax'] !== (int)round((int)$record['subtotal'] * 0.05)) ? 'true' : 'false' ?>;
function onTaxManual() {
    taxManualEdited = true;
    recalcTotal();
}
function calcAmounts() {
    var subtotal = parseInt(document.getElementById('subtotal').value) || 0;
    if (!taxManualEdited) {
        document.getElementById('tax').value = Math.round(subtotal * 0.05);
    }
    recalcTotal();
}
function recalcTotal() {
    var subtotal = parseInt(document.getElementById('subtotal').value) || 0;
    var tax = parseInt(document.getElementById('tax').value) || 0;
    var remittanceFee = parseInt(document.getElementById('remittanceFee').value) || 0;
    document.getElementById('totalAmount').value = subtotal + tax + remittanceFee;
}
calcAmounts();

// ---- 分公司拆帳動態列 ----
var branchIdx = <?= !empty($branchItems) ? count($branchItems) : 0 ?>;
var branchOptions = '';
<?php foreach ($branches as $b): ?>
branchOptions += '<option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>';
<?php endforeach; ?>

function addBranchRow() {
    var tbody = document.getElementById('branchBody');
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><select name="branches[' + branchIdx + '][branch_id]" class="form-control"><option value="">請選擇</option>' + branchOptions + '</select></td>'
        + '<td><input type="number" name="branches[' + branchIdx + '][amount]" class="form-control" value="0" min="0"></td>'
        + '<td><input type="text" name="branches[' + branchIdx + '][note]" class="form-control"></td>'
        + '<td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove()">X</button></td>';
    tbody.appendChild(tr);
    branchIdx++;
}

// ---- 憑證明細動態列 ----
var voucherIdx = <?= !empty($voucherItems) ? count($voucherItems) : 0 ?>;

function addVoucherRow() {
    var tbody = document.getElementById('voucherBody');
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="text" name="vouchers[' + voucherIdx + '][voucher_type]" class="form-control" placeholder="憑證類型"></td>'
        + '<td><input type="number" name="vouchers[' + voucherIdx + '][amount]" class="form-control voucher-amount" value="0" min="0" onchange="calcVoucherTotal()"></td>'
        + '<td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove();calcVoucherTotal()">X</button></td>';
    tbody.appendChild(tr);
    voucherIdx++;
}

function calcVoucherTotal() {
    var total = 0;
    var amounts = document.querySelectorAll('#voucherBody .voucher-amount');
    for (var i = 0; i < amounts.length; i++) {
        total += parseInt(amounts[i].value) || 0;
    }
    document.getElementById('voucherTotal').textContent = '$' + total.toLocaleString();
}
calcVoucherTotal();
</script>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.autocomplete-list { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid var(--gray-300); border-top:none; border-radius:0 0 6px 6px; max-height:240px; overflow-y:auto; z-index:100; display:none; box-shadow:0 4px 12px rgba(0,0,0,.1); }
.autocomplete-list.show { display:block; }
.autocomplete-item { padding:8px 12px; cursor:pointer; font-size:.9rem; border-bottom:1px solid var(--gray-100); }
.autocomplete-item:hover { background:var(--gray-50); }
.autocomplete-item .ac-main { font-weight:600; }
.autocomplete-item .ac-sub { font-size:.8rem; color:var(--gray-500); }
</style>

<script>
// ---- 廠商即時搜尋 ----
(function() {
    var input = document.getElementById('vendorName');
    var list = document.getElementById('vendorSuggestions');
    var codeInput = document.getElementById('vendorCode');
    var timer = null;

    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 1) { list.classList.remove('show'); return; }
        timer = setTimeout(function() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/payments_out.php?action=ajax_vendor_search&q=' + encodeURIComponent(q));
            xhr.onload = function() {
                var data = JSON.parse(xhr.responseText);
                if (data.length === 0) { list.classList.remove('show'); return; }
                var html = '';
                for (var i = 0; i < data.length; i++) {
                    html += '<div class="autocomplete-item" data-name="' + (data[i].name||'').replace(/"/g,'&quot;') + '" data-code="' + (data[i].vendor_code||'') + '">';
                    html += '<div class="ac-main">' + (data[i].name||'') + '</div>';
                    html += '<div class="ac-sub">' + (data[i].vendor_code ? '編號:' + data[i].vendor_code + ' | ' : '') + (data[i].contact_person||'') + ' ' + (data[i].phone||'') + '</div>';
                    html += '</div>';
                }
                list.innerHTML = html;
                list.classList.add('show');
                // 點擊選項
                list.querySelectorAll('.autocomplete-item').forEach(function(el) {
                    el.addEventListener('click', function() {
                        input.value = this.getAttribute('data-name');
                        codeInput.value = this.getAttribute('data-code');
                        list.classList.remove('show');
                    });
                });
            };
            xhr.send();
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !list.contains(e.target)) list.classList.remove('show');
    });
})();

// ---- 應付帳款搜尋 ----
(function() {
    var input = document.getElementById('payableSearch');
    var list = document.getElementById('payableSuggestions');
    var hiddenId = document.getElementById('payableId');
    var timer = null;

    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 1) { list.classList.remove('show'); hiddenId.value = ''; return; }
        timer = setTimeout(function() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/payments_out.php?action=ajax_payable_search&q=' + encodeURIComponent(q));
            xhr.onload = function() {
                var data = JSON.parse(xhr.responseText);
                if (data.length === 0) { list.classList.remove('show'); return; }
                var html = '';
                for (var i = 0; i < data.length; i++) {
                    html += '<div class="autocomplete-item" data-id="' + data[i].id + '" data-number="' + (data[i].payable_number||'') + '">';
                    html += '<div class="ac-main">' + (data[i].payable_number||'') + '</div>';
                    html += '<div class="ac-sub">' + (data[i].vendor_name||'') + ' | $' + Number(data[i].total_amount||0).toLocaleString() + ' | ' + (data[i].status||'') + '</div>';
                    html += '</div>';
                }
                list.innerHTML = html;
                list.classList.add('show');
                list.querySelectorAll('.autocomplete-item').forEach(function(el) {
                    el.addEventListener('click', function() {
                        input.value = this.getAttribute('data-number');
                        hiddenId.value = this.getAttribute('data-id');
                        list.classList.remove('show');
                    });
                });
            };
            xhr.send();
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !list.contains(e.target)) list.classList.remove('show');
    });
})();
</script>
