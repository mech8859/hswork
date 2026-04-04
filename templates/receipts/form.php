<?php $isEdit = !empty($record); ?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= $isEdit ? '編輯收款單 - ' . e($record['receipt_number']) : '新增收款單' ?></h2>
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
        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteReceipt(<?= $record['id'] ?>, '<?= e($record['receipt_number']) ?>')">刪除</button>
        <?php endif; ?>
        <a href="/receipts.php" class="btn btn-outline btn-sm">返回列表</a>
    </div>
</div>

<form method="POST" class="mt-2" id="receiptForm">
    <?= csrf_field() ?>

    <!-- 收款資訊 -->
    <div class="card">
        <div class="card-header">收款資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>收款單號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? $record['receipt_number'] : peek_next_doc_number('receipts')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>傳票號碼</label>
                <input type="text" name="voucher_number" class="form-control" value="<?= e($record['voucher_number'] ?? '') ?>" placeholder="AR2-...">
            </div>
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>請款單號</label>
                <input type="text" name="billing_number" class="form-control" value="<?= e($record['billing_number'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>登記日期 *</label>
                <input type="date" max="2099-12-31" name="register_date" class="form-control" required
                       value="<?= e($isEdit && !empty($record['register_date']) ? $record['register_date'] : date('Y-m-d')) ?>">
            </div>
            <div class="form-group">
                <label>入帳日期</label>
                <input type="date" max="2099-12-31" name="deposit_date" class="form-control"
                       value="<?= e($isEdit && !empty($record['deposit_date']) ? $record['deposit_date'] : '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>客戶名稱</label>
                <input type="text" name="customer_name" class="form-control" placeholder="客戶名稱"
                       value="<?= e($isEdit && !empty($record['customer_name']) ? $record['customer_name'] : '') ?>">
            </div>
            <div class="form-group">
                <label>進件編號</label>
                <input type="text" name="case_number" class="form-control" placeholder="例：2026-0028"
                       value="<?= e($record['case_number'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>客戶編號</label>
                <input type="text" name="customer_no" class="form-control" placeholder="客戶編號"
                       value="<?= e($record['customer_no'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>業務人員</label>
                <select name="sales_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($salesUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($isEdit && !empty($record['sales_id']) && $record['sales_id'] == $u['id']) ? 'selected' : '' ?>>
                        <?= e($u['real_name']) ?><?= !empty($u['branch_name']) ? ' (' . e($u['branch_name']) . ')' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($isEdit && !empty($record['branch_id']) && $record['branch_id'] == $b['id']) ? 'selected' : '' ?>>
                        <?= e($b['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>收款方式</label>
                <select name="receipt_method" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach (FinanceModel::paymentMethodOptions() as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= ($isEdit && !empty($record['receipt_method']) && $record['receipt_method'] === $val) ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>發票類別</label>
                <select name="invoice_category" class="form-control">
                    <option value="">請選擇</option>
                    <?php foreach (FinanceModel::invoiceCategoryOptions() as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= ($isEdit && !empty($record['invoice_category']) && $record['invoice_category'] === $val) ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <?php foreach (FinanceModel::receiptStatusOptions() as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= ($isEdit && !empty($record['status']) && $record['status'] === $val) ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>銀行明細上傳編號</label>
                <input type="text" name="bank_ref" class="form-control" placeholder="CC-2026-..."
                       value="<?= e($isEdit && !empty($record['bank_ref']) ? $record['bank_ref'] : '') ?>">
            </div>
            <div class="form-group">
                <label>登記人</label>
                <?php
                $registrarName = '';
                if ($isEdit && !empty($record['registrar'])) {
                    $registrarName = $record['registrar'];
                } else {
                    $registrarName = Session::getUser()['real_name'] ?? '';
                }
                ?>
                <input type="text" class="form-control" value="<?= e($registrarName) ?>" readonly style="background:#f5f5f5">
                <input type="hidden" name="registrar" value="<?= e($registrarName) ?>">
                <small class="text-muted"><?= $isEdit && !empty($record['created_at']) ? date('Y/m/d H:i', strtotime($record['created_at'])) : date('Y/m/d H:i') ?></small>
            </div>
        </div>
    </div>

    <!-- 金額 -->
    <div class="card">
        <div class="card-header">金額</div>
        <div class="form-row">
            <div class="form-group">
                <label>小計 (未稅)</label>
                <input type="number" name="subtotal" id="f_subtotal" class="form-control calc-trigger" step="1" min="0"
                       value="<?= $isEdit && !empty($record['subtotal']) ? (int)$record['subtotal'] : 0 ?>">
            </div>
            <div class="form-group">
                <label>稅額</label>
                <input type="number" name="tax" id="f_tax" class="form-control calc-trigger" step="1" min="0"
                       value="<?= $isEdit && !empty($record['tax']) ? (int)$record['tax'] : 0 ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>折讓/匯費</label>
                <input type="number" name="discount" id="f_discount" class="form-control calc-trigger" step="1" min="0"
                       value="<?= $isEdit && !empty($record['discount']) ? (int)$record['discount'] : 0 ?>">
            </div>
            <div class="form-group">
                <label>收款總計</label>
                <input type="number" name="total_amount" id="f_total" class="form-control" step="1" readonly
                       value="<?= $isEdit && !empty($record['total_amount']) ? (int)$record['total_amount'] : 0 ?>"
                       style="font-weight:bold; font-size:1.1em; background:#f0f0f0;">
            </div>
        </div>
    </div>

    <!-- 合併請款案件 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>合併請款案件</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addItemRow()">+ 新增列</button>
        </div>
        <div class="table-responsive">
            <table class="table" id="itemsTable">
                <thead>
                    <tr>
                        <th>主進件編號</th>
                        <th>併案編號</th>
                        <th class="text-right">金額</th>
                        <th>備註</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $idx => $item): ?>
                        <tr>
                            <td><input type="text" name="items[<?= $idx ?>][main_case_number]" class="form-control" value="<?= e(!empty($item['main_case_number']) ? $item['main_case_number'] : '') ?>"></td>
                            <td><input type="text" name="items[<?= $idx ?>][merge_case_number]" class="form-control" value="<?= e(!empty($item['merge_case_number']) ? $item['merge_case_number'] : '') ?>"></td>
                            <td><input type="number" name="items[<?= $idx ?>][amount]" class="form-control text-right" step="1" min="0" value="<?= !empty($item['amount']) ? (int)$item['amount'] : 0 ?>"></td>
                            <td><input type="text" name="items[<?= $idx ?>][note]" class="form-control" value="<?= e(!empty($item['note']) ? $item['note'] : '') ?>"></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td><input type="text" name="items[0][main_case_number]" class="form-control"></td>
                            <td><input type="text" name="items[0][merge_case_number]" class="form-control"></td>
                            <td><input type="number" name="items[0][amount]" class="form-control text-right" step="1" min="0" value="0"></td>
                            <td><input type="text" name="items[0][note]" class="form-control"></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">&times;</button></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 備註 -->
    <div class="card">
        <div class="card-header">備註</div>
        <div class="form-group">
            <textarea name="note" class="form-control" rows="3" placeholder="備註說明"><?= e($isEdit && !empty($record['note']) ? $record['note'] : '') ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '更新收款單' : '建立收款單' ?></button>
        <a href="/receipts.php" class="btn btn-outline">取消</a>
    </div>
</form>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
#itemsTable .form-control { padding: 4px 8px; font-size: 0.9em; }
</style>

<script>
function confirmDeleteReceipt(id, number) {
    if (!confirm('確定要刪除收款單 ' + number + '？\n\n此操作無法復原。')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/receipts.php?action=delete&id=' + id;
    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = 'csrf_token';
    csrf.value = document.querySelector('input[name="csrf_token"]').value;
    form.appendChild(csrf);
    document.body.appendChild(form);
    form.submit();
}

var itemIndex = <?= !empty($items) ? count($items) : 1 ?>;

function addItemRow() {
    var tbody = document.getElementById('itemsBody');
    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td><input type="text" name="items[' + itemIndex + '][main_case_number]" class="form-control"></td>' +
        '<td><input type="text" name="items[' + itemIndex + '][merge_case_number]" class="form-control"></td>' +
        '<td><input type="number" name="items[' + itemIndex + '][amount]" class="form-control text-right" step="1" min="0" value="0"></td>' +
        '<td><input type="text" name="items[' + itemIndex + '][note]" class="form-control"></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">&times;</button></td>';
    tbody.appendChild(tr);
    itemIndex++;
}

function removeRow(btn) {
    var tbody = document.getElementById('itemsBody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
    }
}

// 自動計算總計 = 小計 + 稅額 - 折讓/匯費
function calcTotal() {
    var subtotal = parseInt(document.getElementById('f_subtotal').value) || 0;
    var tax = parseInt(document.getElementById('f_tax').value) || 0;
    var discount = parseInt(document.getElementById('f_discount').value) || 0;
    document.getElementById('f_total').value = subtotal + tax - discount;
}

var triggers = document.querySelectorAll('.calc-trigger');
for (var i = 0; i < triggers.length; i++) {
    triggers[i].addEventListener('input', calcTotal);
}
</script>
