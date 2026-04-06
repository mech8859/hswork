<?php $isEdit = !empty($record); ?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2><?= $isEdit ? '編輯應付帳款單 - ' . e($record['payable_number']) : '新增應付帳款單' ?></h2>
        <?php if ($isEdit && !empty($record['updated_at'])): ?>
        <?php
        $updaterName = '';
        if (!empty($record['updated_by'])) {
            $uStmt = Database::getInstance()->prepare("SELECT real_name FROM users WHERE id = ?");
            $uStmt->execute(array($record['updated_by']));
            $updaterName = $uStmt->fetchColumn() ?: '';
        }
        ?>
        <small class="text-muted">最後修改 <?= e($record['updated_at']) ?><?= $updaterName ? ' / ' . e($updaterName) : '' ?></small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <?php if ($isEdit && (Auth::hasPermission('finance.delete') || Auth::hasPermission('all'))): ?>
        <button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('確定刪除？'))document.getElementById('deleteForm').submit()">刪除</button>
        <?php endif; ?>
        <?= back_button('/payables.php') ?>
    </div>
</div>
<?php if ($isEdit): ?>
<form id="deleteForm" method="POST" action="/payables.php?action=delete&id=<?= $record['id'] ?>" style="display:none"><?= csrf_field() ?></form>
<?php endif; ?>

<form method="POST" class="mt-2" id="payableForm">
    <?= csrf_field() ?>

    <!-- 基本資訊 -->
    <div class="card">
        <div class="card-header">基本資訊</div>
        <div class="form-row">
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>應付單號</label>
                <input type="text" class="form-control" value="<?= e($isEdit ? $record['payable_number'] : peek_next_doc_number('payables')) ?>" readonly style="background:#f0f7ff;font-weight:600;color:var(--primary)">
            </div>
            <div class="form-group" style="flex:0 0 auto;min-width:200px">
                <label>傳票號碼</label>
                <input type="text" name="voucher_number" class="form-control" value="<?= e($record['voucher_number'] ?? '') ?>" placeholder="AP1-...">
            </div>
            <div class="form-group">
                <label>建立日期 *</label>
                <input type="date" max="2099-12-31" name="create_date" class="form-control"
                       value="<?= e($isEdit && !empty($record['create_date']) ? $record['create_date'] : date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group" style="position:relative">
                <label>廠商名稱 *</label>
                <input type="text" name="vendor_name" id="vendorNameInput" class="form-control"
                       value="<?= e($isEdit && !empty($record['vendor_name']) ? $record['vendor_name'] : '') ?>" required autocomplete="off">
                <div id="vendorDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:#fff;border:1px solid #ddd;border-top:none;border-radius:0 0 6px 6px;max-height:240px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.12)"></div>
            </div>
            <div class="form-group">
                <label>廠商編號</label>
                <input type="text" name="vendor_code" id="vendorCodeInput" class="form-control" value="<?= e($record['vendor_code'] ?? '') ?>">
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
            <div class="form-group">
                <label>登記人</label>
                <?php $regName = ($isEdit && !empty($record['registrar'])) ? $record['registrar'] : (Session::getUser()['real_name'] ?? ''); ?>
                <input type="text" class="form-control" value="<?= e($regName) ?>" readonly style="background:#f5f5f5">
                <input type="hidden" name="registrar" value="<?= e($regName) ?>">
                <small class="text-muted"><?= $isEdit && !empty($record['created_at']) ? date('Y/m/d H:i', strtotime($record['created_at'])) : date('Y/m/d H:i') ?></small>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>付款期間</label>
                <?php
                $ppYear = ''; $ppMonth = '';
                if ($isEdit && !empty($record['payment_period'])) {
                    $ppParts = explode('-', $record['payment_period']);
                    if (count($ppParts) >= 2) { $ppYear = $ppParts[0]; $ppMonth = ltrim($ppParts[1], '0') ?: $ppParts[1]; }
                }
                ?>
                <div style="display:flex;gap:6px;align-items:center">
                    <select id="ppYear" style="flex:1" class="form-control" onchange="updatePP()">
                        <option value="">年</option>
                        <?php for ($y = 2024; $y <= 2030; $y++): ?>
                        <option value="<?= $y ?>" <?= $ppYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <span>年</span>
                    <select id="ppMonth" style="flex:1" class="form-control" onchange="updatePP()">
                        <option value="">月</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= sprintf('%02d', $m) ?>" <?= $ppMonth == $m ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                        <?php endfor; ?>
                    </select>
                    <span>月</span>
                    <input type="hidden" name="payment_period" id="ppValue" value="<?= e($isEdit && !empty($record['payment_period']) ? $record['payment_period'] : '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label>付款條件</label>
                <input type="text" name="payment_terms" class="form-control" value="<?= e($record['payment_terms'] ?? '') ?>" placeholder="例：30天、月結30天" list="termsOptions">
                <datalist id="termsOptions">
                    <option value="30天">
                    <option value="45天">
                    <option value="月結30天">
                    <option value="月結60天">
                    <option value="月結90天">
                    <option value="貨到付款">
                    <option value="預付">
                </datalist>
            </div>
        </div>
    </div>

    <!-- 金額 -->
    <div class="card">
        <div class="card-header">金額</div>
        <div class="form-row">
            <div class="form-group">
                <label>未稅總額 *</label>
                <input type="number" name="subtotal" id="fldSubtotal" class="form-control" step="1" min="0"
                       value="<?= $isEdit && !empty($record['subtotal']) ? (int)$record['subtotal'] : 0 ?>"
                       oninput="calcAmounts()">
            </div>
            <div class="form-group">
                <label>稅金 (5%)</label>
                <input type="number" name="tax" id="fldTax" class="form-control" step="1" min="0"
                       value="<?= $isEdit && !empty($record['tax']) ? (int)$record['tax'] : 0 ?>" readonly>
            </div>
            <div class="form-group">
                <label>總計</label>
                <input type="number" name="total_amount" id="fldTotal" class="form-control" step="1" min="0"
                       value="<?= $isEdit && !empty($record['total_amount']) ? (int)$record['total_amount'] : 0 ?>" readonly>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>預付款</label>
                <input type="number" name="prepaid" id="fldPrepaid" class="form-control" step="1" min="0"
                       value="<?= $isEdit && !empty($record['prepaid']) ? (int)$record['prepaid'] : 0 ?>"
                       oninput="calcAmounts()">
            </div>
            <div class="form-group">
                <label>應付總額</label>
                <input type="number" name="payable_amount" id="fldPayable" class="form-control" step="1" min="0"
                       value="<?= $isEdit && !empty($record['payable_amount']) ? (int)$record['payable_amount'] : 0 ?>" readonly>
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
                        <?php foreach ($branchItems as $idx => $bi): ?>
                        <tr>
                            <td>
                                <select name="branches[<?= $idx ?>][branch_id]" class="form-control">
                                    <option value="">請選擇</option>
                                    <?php foreach ($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= (!empty($bi['branch_id']) ? $bi['branch_id'] : '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="branches[<?= $idx ?>][amount]" class="form-control" step="1" min="0" value="<?= !empty($bi['amount']) ? (int)$bi['amount'] : 0 ?>"></td>
                            <td><input type="text" name="branches[<?= $idx ?>][note]" class="form-control" value="<?= e(!empty($bi['note']) ? $bi['note'] : '') ?>"></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td>
                                <select name="branches[0][branch_id]" class="form-control">
                                    <option value="">請選擇</option>
                                    <?php foreach ($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="branches[0][amount]" class="form-control" step="1" min="0" value="0"></td>
                            <td><input type="text" name="branches[0][note]" class="form-control" value=""></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 進貨明細 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>進貨明細</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addPurchaseDetailRow()">+ 新增</button>
        </div>
        <input type="hidden" name="pd_rendered" value="1">
        <div id="purchaseDetailBody">
            <?php if (!empty($record['purchase_details'])):
                foreach ($record['purchase_details'] as $pi => $pd): ?>
            <div class="pd-item detail-item" style="padding:8px 0">
                <div class="form-row" style="align-items:flex-end">
                    <div class="form-group"><label>進貨日期</label><input type="date" name="pd[<?= $pi ?>][purchase_date]" class="form-control" value="<?= e(!empty($pd['purchase_date']) ? $pd['purchase_date'] : '') ?>"></div>
                    <div class="form-group"><label>進貨單號</label><input type="text" name="pd[<?= $pi ?>][purchase_number]" class="form-control" value="<?= e(!empty($pd['purchase_number']) ? $pd['purchase_number'] : '') ?>"></div>
                    <div class="form-group"><label>分公司</label><input type="text" name="pd[<?= $pi ?>][branch_name]" class="form-control" value="<?= e(!empty($pd['branch_name']) ? $pd['branch_name'] : '') ?>"></div>
                    <div class="form-group"><label>未稅金額</label><input type="number" name="pd[<?= $pi ?>][amount_untaxed]" class="form-control" step="1" value="<?= !empty($pd['amount_untaxed']) ? (int)$pd['amount_untaxed'] : 0 ?>"></div>
                    <div class="form-group"><label>發票字軌</label><input type="text" name="pd[<?= $pi ?>][invoice_track]" class="form-control" value="<?= e(!empty($pd['invoice_track']) ? $pd['invoice_track'] : '') ?>"></div>
                    <div style="flex:0;display:flex;gap:4px;padding-bottom:2px">
                        <button type="button" class="detail-toggle detail-toggle-open" onclick="toggleRows(this)">+</button>
                        <button type="button" class="detail-toggle detail-toggle-close" onclick="this.closest('.detail-item').remove()">×</button>
                    </div>
                </div>
                <div class="detail-extra" style="display:none">
                    <div class="form-row">
                        <div class="form-group"><label>對帳月份</label><input type="month" name="pd[<?= $pi ?>][check_month]" class="form-control" value="<?= e(!empty($pd['check_month']) ? $pd['check_month'] : '') ?>"></div>
                        <div class="form-group"><label>廠商名稱</label><input type="text" name="pd[<?= $pi ?>][vendor_name]" class="form-control" value="<?= e(!empty($pd['vendor_name']) ? $pd['vendor_name'] : '') ?>"></div>
                        <div class="form-group"><label>稅額</label><input type="number" name="pd[<?= $pi ?>][tax_amount]" class="form-control" step="1" value="<?= !empty($pd['tax_amount']) ? (int)$pd['tax_amount'] : 0 ?>"></div>
                        <div class="form-group"><label>含稅金額</label><input type="number" name="pd[<?= $pi ?>][total_amount]" class="form-control" step="1" value="<?= !empty($pd['total_amount']) ? (int)$pd['total_amount'] : 0 ?>"></div>
                        <div class="form-group"><label>已付金額</label><input type="number" name="pd[<?= $pi ?>][paid_amount]" class="form-control" step="1" value="<?= !empty($pd['paid_amount']) ? (int)$pd['paid_amount'] : 0 ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>付款日期</label><input type="date" name="pd[<?= $pi ?>][payment_date]" class="form-control" value="<?= e(!empty($pd['payment_date']) ? $pd['payment_date'] : '') ?>"></div>
                        <div class="form-group"><label>發票日期</label><input type="date" name="pd[<?= $pi ?>][invoice_date]" class="form-control" value="<?= e(!empty($pd['invoice_date']) ? $pd['invoice_date'] : '') ?>"></div>
                        <div class="form-group"><label>發票金額</label><input type="number" name="pd[<?= $pi ?>][invoice_amount]" class="form-control" step="1" value="<?= !empty($pd['invoice_amount']) ? (int)$pd['invoice_amount'] : 0 ?>"></div>
                        <div class="form-group"><label>月結核對</label><input type="text" name="pd[<?= $pi ?>][monthly_check]" class="form-control" value="<?= e(!empty($pd['monthly_check']) ? $pd['monthly_check'] : '') ?>"></div>
                        <div class="form-group"><label>備註</label><input type="text" name="pd[<?= $pi ?>][note]" class="form-control" value="<?= e(!empty($pd['note']) ? $pd['note'] : '') ?>"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- 進退明細 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>進退明細</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addReturnDetailRow()">+ 新增</button>
        </div>
        <input type="hidden" name="rd_rendered" value="1">
        <div id="returnDetailBody">
            <?php if (!empty($record['return_details'])):
                foreach ($record['return_details'] as $ri => $rd): ?>
            <div class="rd-item detail-item" style="padding:8px 0">
                <div class="form-row" style="align-items:flex-end">
                    <div class="form-group"><label>退貨日期</label><input type="date" name="rd[<?= $ri ?>][return_date]" class="form-control" value="<?= e(!empty($rd['return_date']) ? $rd['return_date'] : '') ?>"></div>
                    <div class="form-group"><label>來源退回單號</label><input type="text" name="rd[<?= $ri ?>][return_number]" class="form-control" value="<?= e(!empty($rd['return_number']) ? $rd['return_number'] : '') ?>"></div>
                    <div class="form-group"><label>所屬分公司</label><input type="text" name="rd[<?= $ri ?>][branch_name]" class="form-control" value="<?= e(!empty($rd['branch_name']) ? $rd['branch_name'] : '') ?>"></div>
                    <div class="form-group"><label>退款金額</label><input type="number" name="rd[<?= $ri ?>][refund_amount]" class="form-control" step="1" value="<?= !empty($rd['refund_amount']) ? (int)$rd['refund_amount'] : 0 ?>"></div>
                    <div style="flex:0;display:flex;gap:4px;padding-bottom:2px">
                        <button type="button" class="detail-toggle detail-toggle-open" onclick="toggleRows(this)">+</button>
                        <button type="button" class="detail-toggle detail-toggle-close" onclick="this.closest('.detail-item').remove()">×</button>
                    </div>
                </div>
                <div class="detail-extra" style="display:none">
                    <div class="form-row">
                        <div class="form-group"><label>來源進貨單號</label><input type="text" name="rd[<?= $ri ?>][purchase_number]" class="form-control" value="<?= e(!empty($rd['purchase_number']) ? $rd['purchase_number'] : '') ?>"></div>
                        <div class="form-group"><label>廠商名稱</label><input type="text" name="rd[<?= $ri ?>][vendor_name]" class="form-control" value="<?= e(!empty($rd['vendor_name']) ? $rd['vendor_name'] : '') ?>"></div>
                        <div class="form-group"><label>單據狀態</label><input type="text" name="rd[<?= $ri ?>][doc_status]" class="form-control" value="<?= e(!empty($rd['doc_status']) ? $rd['doc_status'] : '') ?>"></div>
                        <div class="form-group"><label>倉庫名稱</label><input type="text" name="rd[<?= $ri ?>][warehouse_name]" class="form-control" value="<?= e(!empty($rd['warehouse_name']) ? $rd['warehouse_name'] : '') ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>退回原因</label><input type="text" name="rd[<?= $ri ?>][return_reason]" class="form-control" value="<?= e(!empty($rd['return_reason']) ? $rd['return_reason'] : '') ?>"></div>
                        <div class="form-group"><label>會計處理方式</label><input type="text" name="rd[<?= $ri ?>][accounting_method]" class="form-control" value="<?= e(!empty($rd['accounting_method']) ? $rd['accounting_method'] : '') ?>"></div>
                        <div class="form-group"><label>折讓單據</label><input type="text" name="rd[<?= $ri ?>][allowance_doc]" class="form-control" value="<?= e(!empty($rd['allowance_doc']) ? $rd['allowance_doc'] : '') ?>"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- 發票明細 -->
    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <span>發票明細</span>
            <button type="button" class="btn btn-primary btn-sm" onclick="addInvoiceRow()">+ 新增</button>
        </div>
        <div class="table-responsive">
            <table class="table" id="invoiceTable">
                <thead>
                    <tr>
                        <th>發票日期</th>
                        <th>發票號碼</th>
                        <th>統一編號</th>
                        <th style="width:120px">未稅金額</th>
                        <th style="width:100px">稅金</th>
                        <th style="width:120px">小計</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="invoiceBody">
                    <?php if (!empty($invoiceItems)): ?>
                        <?php foreach ($invoiceItems as $idx => $inv): ?>
                        <tr>
                            <td><input type="date" max="2099-12-31" name="invoices[<?= $idx ?>][invoice_date]" class="form-control" value="<?= e(!empty($inv['invoice_date']) ? $inv['invoice_date'] : '') ?>"></td>
                            <td><input type="text" name="invoices[<?= $idx ?>][invoice_number]" class="form-control" value="<?= e(!empty($inv['invoice_number']) ? $inv['invoice_number'] : '') ?>"></td>
                            <td><input type="text" name="invoices[<?= $idx ?>][tax_id]" class="form-control" value="<?= e(!empty($inv['tax_id']) ? $inv['tax_id'] : '') ?>"></td>
                            <td><input type="number" name="invoices[<?= $idx ?>][amount_untaxed]" class="form-control inv-untaxed" step="1" min="0" value="<?= !empty($inv['amount_untaxed']) ? (int)$inv['amount_untaxed'] : 0 ?>" oninput="calcInvRow(this)"></td>
                            <td><input type="number" name="invoices[<?= $idx ?>][tax]" class="form-control inv-tax" step="1" min="0" value="<?= !empty($inv['tax']) ? (int)$inv['tax'] : 0 ?>" readonly></td>
                            <td><input type="number" name="invoices[<?= $idx ?>][subtotal]" class="form-control inv-subtotal" step="1" min="0" value="<?= !empty($inv['subtotal']) ? (int)$inv['subtotal'] : 0 ?>" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td><input type="date" max="2099-12-31" name="invoices[0][invoice_date]" class="form-control" value=""></td>
                            <td><input type="text" name="invoices[0][invoice_number]" class="form-control" value="" placeholder="發票號碼" maxlength="10" oninput="formatInvoiceNumber(this)" onblur="validateInvoiceNumber(this)" style="text-transform:uppercase"></td>
                            <td><input type="text" name="invoices[0][tax_id]" class="form-control" value="" placeholder="統一編號"></td>
                            <td><input type="number" name="invoices[0][amount_untaxed]" class="form-control inv-untaxed" step="1" min="0" value="0" oninput="calcInvRow(this)"></td>
                            <td><input type="number" name="invoices[0][tax]" class="form-control inv-tax" step="1" min="0" value="0" readonly></td>
                            <td><input type="number" name="invoices[0][subtotal]" class="form-control inv-subtotal" step="1" min="0" value="0" readonly></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 發票明細 -->
    <?php if ($isEdit):
        $invStmt = Database::getInstance()->prepare("SELECT * FROM payable_invoices WHERE payable_id = ? ORDER BY id");
        $invStmt->execute(array($record['id']));
        $invoiceItems = $invStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <?php if (!empty($invoiceItems)): ?>
    <div class="card">
        <div class="card-header">發票明細</div>
        <div class="table-responsive">
            <table class="table" style="font-size:.9rem">
                <thead><tr><th>日期</th><th>發票號碼</th><th>統一編號</th><th class="text-right">未稅</th><th class="text-right">稅額</th><th class="text-right">小計</th></tr></thead>
                <tbody>
                    <?php foreach ($invoiceItems as $inv): ?>
                    <tr>
                        <td><?= e($inv['invoice_date'] ?: '-') ?></td>
                        <td><?= e($inv['invoice_number'] ?: '-') ?></td>
                        <td><?= e($inv['tax_id'] ?: '-') ?></td>
                        <td class="text-right">$<?= number_format($inv['amount_untaxed']) ?></td>
                        <td class="text-right">$<?= number_format($inv['tax']) ?></td>
                        <td class="text-right">$<?= number_format($inv['subtotal']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- 備註 -->
    <div class="card">
        <div class="card-header">備註</div>
        <div class="form-group">
            <textarea name="note" class="form-control" rows="3"><?= e($isEdit && !empty($record['note']) ? $record['note'] : '') ?></textarea>
        </div>
    </div>

    <div class="d-flex gap-1 mt-2">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? '儲存變更' : '建立應付帳款單' ?></button>
        <a href="/payables.php" class="btn btn-outline">取消</a>
    </div>
</form>

<script>
// ---- 金額自動計算 ----
function calcAmounts() {
    var subtotal = parseInt(document.getElementById('fldSubtotal').value) || 0;
    var tax = Math.round(subtotal * 0.05);
    var total = subtotal + tax;
    var prepaid = parseInt(document.getElementById('fldPrepaid').value) || 0;
    var payable = total - prepaid;

    document.getElementById('fldTax').value = tax;
    document.getElementById('fldTotal').value = total;
    document.getElementById('fldPayable').value = payable;
}

// ---- 分公司拆帳 動態列 ----
var branchIdx = <?= !empty($branchItems) ? count($branchItems) : 1 ?>;
function addBranchRow() {
    var tbody = document.getElementById('branchBody');
    var tr = document.createElement('tr');
    var opts = '';
    <?php foreach ($branches as $b): ?>
    opts += '<option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>';
    <?php endforeach; ?>
    tr.innerHTML = '<td><select name="branches['+branchIdx+'][branch_id]" class="form-control"><option value="">請選擇</option>'+opts+'</select></td>'
        + '<td><input type="number" name="branches['+branchIdx+'][amount]" class="form-control" step="1" min="0" value="0"></td>'
        + '<td><input type="text" name="branches['+branchIdx+'][note]" class="form-control" value=""></td>'
        + '<td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove()">X</button></td>';
    tbody.appendChild(tr);
    branchIdx++;
}

// ---- 發票明細 動態列 ----
var invIdx = <?= !empty($invoiceItems) ? count($invoiceItems) : 1 ?>;
function addInvoiceRow() {
    var tbody = document.getElementById('invoiceBody');
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="date" max="2099-12-31" name="invoices['+invIdx+'][invoice_date]" class="form-control" value=""></td>'
        + '<td><input type="text" name="invoices['+invIdx+'][invoice_number]" class="form-control" value="" placeholder="發票號碼" maxlength="10" oninput="formatInvoiceNumber(this)" onblur="validateInvoiceNumber(this)" style="text-transform:uppercase"></td>'
        + '<td><input type="text" name="invoices['+invIdx+'][tax_id]" class="form-control" value="" placeholder="統一編號"></td>'
        + '<td><input type="number" name="invoices['+invIdx+'][amount_untaxed]" class="form-control inv-untaxed" step="1" min="0" value="0" oninput="calcInvRow(this)"></td>'
        + '<td><input type="number" name="invoices['+invIdx+'][tax]" class="form-control inv-tax" step="1" min="0" value="0" readonly></td>'
        + '<td><input type="number" name="invoices['+invIdx+'][subtotal]" class="form-control inv-subtotal" step="1" min="0" value="0" readonly></td>'
        + '<td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove()">X</button></td>';
    tbody.appendChild(tr);
    invIdx++;
}

// ---- 發票小計自動計算 ----
function calcInvRow(el) {
    var tr = el.closest('tr');
    var untaxed = parseInt(tr.querySelector('.inv-untaxed').value) || 0;
    var tax = Math.round(untaxed * 0.05);
    var subtotal = untaxed + tax;
    tr.querySelector('.inv-tax').value = tax;
    tr.querySelector('.inv-subtotal').value = subtotal;
}

// 頁面載入時初始計算
calcAmounts();

// ---- 展開/收合第二三排 ----
function toggleRows(btn) {
    var item = btn.closest('.detail-item');
    var extra = item.querySelector('.detail-extra');
    if (extra.style.display === 'none') {
        extra.style.display = '';
        btn.textContent = '−';
        btn.className = 'detail-toggle detail-toggle-close';
    } else {
        extra.style.display = 'none';
        btn.textContent = '+';
        btn.className = 'detail-toggle detail-toggle-open';
    }
}

// ---- 進貨明細 動態列 ----
var pdIdx = document.querySelectorAll('#purchaseDetailBody .pd-item').length;
function addPurchaseDetailRow() {
    var container = document.getElementById('purchaseDetailBody');
    var div = document.createElement('div');
    div.className = 'pd-item detail-item';
    div.style.cssText = 'padding:8px 0';
    var i = pdIdx++;
    div.innerHTML =
        '<div class="form-row" style="align-items:flex-end">' +
            '<div class="form-group"><label>進貨日期</label><input type="date" name="pd['+i+'][purchase_date]" class="form-control"></div>' +
            '<div class="form-group"><label>進貨單號</label><input type="text" name="pd['+i+'][purchase_number]" class="form-control"></div>' +
            '<div class="form-group"><label>分公司</label><input type="text" name="pd['+i+'][branch_name]" class="form-control"></div>' +
            '<div class="form-group"><label>未稅金額</label><input type="number" name="pd['+i+'][amount_untaxed]" class="form-control" step="1" value="0"></div>' +
            '<div class="form-group"><label>發票字軌</label><input type="text" name="pd['+i+'][invoice_track]" class="form-control"></div>' +
            '<div style="flex:0;display:flex;gap:4px;padding-bottom:2px">' +
                '<button type="button" class="detail-toggle detail-toggle-open" onclick="toggleRows(this)">+</button>' +
                '<button type="button" class="detail-toggle detail-toggle-close" onclick="this.closest(\'.detail-item\').remove()">×</button>' +
            '</div>' +
        '</div>' +
        '<div class="detail-extra" style="display:none">' +
            '<div class="form-row">' +
                '<div class="form-group"><label>對帳月份</label><input type="month" name="pd['+i+'][check_month]" class="form-control"></div>' +
                '<div class="form-group"><label>廠商名稱</label><input type="text" name="pd['+i+'][vendor_name]" class="form-control"></div>' +
                '<div class="form-group"><label>稅額</label><input type="number" name="pd['+i+'][tax_amount]" class="form-control" step="1" value="0"></div>' +
                '<div class="form-group"><label>含稅金額</label><input type="number" name="pd['+i+'][total_amount]" class="form-control" step="1" value="0"></div>' +
                '<div class="form-group"><label>已付金額</label><input type="number" name="pd['+i+'][paid_amount]" class="form-control" step="1" value="0"></div>' +
            '</div>' +
            '<div class="form-row">' +
                '<div class="form-group"><label>付款日期</label><input type="date" name="pd['+i+'][payment_date]" class="form-control"></div>' +
                '<div class="form-group"><label>發票日期</label><input type="date" name="pd['+i+'][invoice_date]" class="form-control"></div>' +
                '<div class="form-group"><label>發票金額</label><input type="number" name="pd['+i+'][invoice_amount]" class="form-control" step="1" value="0"></div>' +
                '<div class="form-group"><label>月結核對</label><input type="text" name="pd['+i+'][monthly_check]" class="form-control"></div>' +
                '<div class="form-group"><label>備註</label><input type="text" name="pd['+i+'][note]" class="form-control"></div>' +
            '</div>' +
        '</div>';
    container.appendChild(div);
}

// ---- 進退明細 動態列 ----
var rdIdx = document.querySelectorAll('#returnDetailBody .rd-item').length;
function addReturnDetailRow() {
    var container = document.getElementById('returnDetailBody');
    var div = document.createElement('div');
    div.className = 'rd-item detail-item';
    div.style.cssText = 'padding:8px 0';
    var i = rdIdx++;
    div.innerHTML =
        '<div class="form-row" style="align-items:flex-end">' +
            '<div class="form-group"><label>退貨日期</label><input type="date" name="rd['+i+'][return_date]" class="form-control"></div>' +
            '<div class="form-group"><label>來源退回單號</label><input type="text" name="rd['+i+'][return_number]" class="form-control"></div>' +
            '<div class="form-group"><label>所屬分公司</label><input type="text" name="rd['+i+'][branch_name]" class="form-control"></div>' +
            '<div class="form-group"><label>退款金額</label><input type="number" name="rd['+i+'][refund_amount]" class="form-control" step="1" value="0"></div>' +
            '<div style="flex:0;display:flex;gap:4px;padding-bottom:2px">' +
                '<button type="button" class="detail-toggle detail-toggle-open" onclick="toggleRows(this)">+</button>' +
                '<button type="button" class="detail-toggle detail-toggle-close" onclick="this.closest(\'.detail-item\').remove()">×</button>' +
            '</div>' +
        '</div>' +
        '<div class="detail-extra" style="display:none">' +
            '<div class="form-row">' +
                '<div class="form-group"><label>來源進貨單號</label><input type="text" name="rd['+i+'][purchase_number]" class="form-control"></div>' +
                '<div class="form-group"><label>廠商名稱</label><input type="text" name="rd['+i+'][vendor_name]" class="form-control"></div>' +
                '<div class="form-group"><label>單據狀態</label><input type="text" name="rd['+i+'][doc_status]" class="form-control"></div>' +
                '<div class="form-group"><label>倉庫名稱</label><input type="text" name="rd['+i+'][warehouse_name]" class="form-control"></div>' +
            '</div>' +
            '<div class="form-row">' +
                '<div class="form-group"><label>退回原因</label><input type="text" name="rd['+i+'][return_reason]" class="form-control"></div>' +
                '<div class="form-group"><label>會計處理方式</label><input type="text" name="rd['+i+'][accounting_method]" class="form-control"></div>' +
                '<div class="form-group"><label>折讓單據</label><input type="text" name="rd['+i+'][allowance_doc]" class="form-control"></div>' +
            '</div>' +
        '</div>';
    container.appendChild(div);
}
</script>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.detail-item { border-bottom: 1px solid #1565c0; }
.detail-summary { display:flex; gap:12px; padding:10px 12px; cursor:pointer; align-items:center; font-size:.88rem; transition:background .15s; }
.detail-summary:hover { background:#f0f7ff; }
.detail-summary span { white-space:nowrap; }
.detail-toggle { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:50%; font-size:1.1rem; font-weight:bold; line-height:1; cursor:pointer; border:none; flex-shrink:0; }
.detail-toggle-open { background:#1565c0; color:#fff; }
.detail-toggle-close { background:#e53935; color:#fff; }
.detail-full { padding:12px 12px 12px 48px; background:#fafbfc; border-radius:0 0 6px 6px; }
.detail-full .form-row { margin-bottom:10px; }
</style>
<script>
function updatePP() {
    var y = document.getElementById('ppYear').value;
    var m = document.getElementById('ppMonth').value;
    document.getElementById('ppValue').value = (y && m) ? y + '-' + m : '';
}

// ===== 廠商即時搜尋 =====
(function() {
    var input = document.getElementById('vendorNameInput');
    var dropdown = document.getElementById('vendorDropdown');
    var codeInput = document.getElementById('vendorCodeInput');
    var timer = null;

    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 1) { dropdown.style.display = 'none'; return; }
        timer = setTimeout(function() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/payables.php?action=ajax_search_vendor&q=' + encodeURIComponent(q));
            xhr.onload = function() {
                var list = JSON.parse(xhr.responseText);
                if (!list.length) { dropdown.style.display = 'none'; return; }
                var html = '';
                for (var i = 0; i < list.length; i++) {
                    var v = list[i];
                    var label = v.name + (v.vendor_code ? ' (' + v.vendor_code + ')' : '');
                    html += '<div class="vendor-item" data-idx="' + i + '" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:.9rem" '
                         + 'onclick=\'selectVendor(' + JSON.stringify(v).replace(/'/g, "&#39;") + ')\'>'
                         + '<div style="font-weight:500">' + escVendorHtml(v.name) + '</div>'
                         + '<div style="font-size:.78rem;color:#888">'
                         + (v.vendor_code ? v.vendor_code + ' · ' : '')
                         + (v.contact_person || '') + (v.phone ? ' ' + v.phone : '')
                         + '</div></div>';
                }
                dropdown.innerHTML = html;
                dropdown.style.display = 'block';
            };
            xhr.send();
        }, 250);
    });

    // hover 效果
    dropdown.addEventListener('mouseover', function(e) {
        var item = e.target.closest('.vendor-item');
        if (item) item.style.background = '#f0f7ff';
    });
    dropdown.addEventListener('mouseout', function(e) {
        var item = e.target.closest('.vendor-item');
        if (item) item.style.background = '';
    });

    // 點外面關閉
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#vendorNameInput') && !e.target.closest('#vendorDropdown')) {
            dropdown.style.display = 'none';
        }
    });
})();

function selectVendor(v) {
    document.getElementById('vendorNameInput').value = v.name;
    document.getElementById('vendorCodeInput').value = v.vendor_code || '';
    document.getElementById('vendorDropdown').style.display = 'none';
}

function escVendorHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
</script>
