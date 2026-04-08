<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>進項發票管理 <span style="font-size:.7em;color:var(--gray-500);font-weight:normal">(共 <?= number_format($result['total']) ?> 筆)</span></h2>
    <?php if ($canManage): ?>
    <a href="/purchase_invoices.php?action=create" class="btn btn-primary btn-sm">+ 新增進項發票</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="GET" action="/purchase_invoices.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>期間</label>
                <select name="period" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($periodOptions as $p): ?>
                    <option value="<?= e($p) ?>" <?= (!empty($filters['period']) && $filters['period'] === $p) ? 'selected' : '' ?>><?= e(substr($p, 0, 4) . '/' . substr($p, 4, 2)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>供應商</label>
                <input type="text" name="vendor" class="form-control" value="<?= e(!empty($filters['vendor']) ? $filters['vendor'] : '') ?>" placeholder="名稱/統編">
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (InvoiceModel::invoiceStatusOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($filters['status']) && $filters['status'] === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>發票類型</label>
                <select name="invoice_type" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (InvoiceModel::purchaseInvoiceTypeOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($filters['invoice_type']) && $filters['invoice_type'] === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="發票號碼/備註">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/purchase_invoices.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無進項發票資料</p>
    <?php else: ?>
    <!-- 手機版卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" onclick="location.href='/purchase_invoices.php?action=edit&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['invoice_number']) ? $r['invoice_number'] : '-') ?></strong>
                <span class="badge badge-<?= $r['status'] === 'voided' ? 'danger' : ($r['status'] === 'confirmed' ? 'success' : 'warning') ?>" style="font-size:.75rem">
                    <?= e(InvoiceModel::invoiceStatusOptions()[$r['status']]) ?>
                </span>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($r['invoice_date']) ? $r['invoice_date'] : '') ?></span>
                <span><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></span>
            </div>
            <div class="staff-card-meta">
                <span>未稅 $<?= number_format(!empty($r['amount_untaxed']) ? $r['amount_untaxed'] : 0) ?></span>
                <span>稅 $<?= number_format(!empty($r['tax_amount']) ? $r['tax_amount'] : 0) ?></span>
                <span>含稅 $<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>發票號碼</th>
                    <th>日期</th>
                    <th>供應商</th>
                    <th>統編</th>
                    <th class="text-right">未稅金額</th>
                    <th class="text-right">稅額</th>
                    <th class="text-right">含稅金額</th>
                    <th>類型</th>
                    <th>扣抵別</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr<?= $r['status'] === 'voided' ? ' style="opacity:.5;text-decoration:line-through"' : '' ?>>
                    <td><a href="/purchase_invoices.php?action=edit&id=<?= $r['id'] ?>"><?= e(!empty($r['invoice_number']) ? $r['invoice_number'] : '-') ?></a></td>
                    <td><?= e(!empty($r['invoice_date']) ? $r['invoice_date'] : '') ?></td>
                    <td><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></td>
                    <td><?= e(!empty($r['vendor_tax_id']) ? $r['vendor_tax_id'] : '-') ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['amount_untaxed']) ? $r['amount_untaxed'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['tax_amount']) ? $r['tax_amount'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td><?= e(!empty($r['invoice_type']) ? $r['invoice_type'] : '-') ?></td>
                    <td><?php
                        $dcLabels = array(
                            'deductible_purchase' => '<span style="color:var(--success)">進項之費用</span>',
                            'deductible_asset'    => '<span style="color:#1565c0">固定資產</span>',
                            'non_deductible'      => '<span style="color:#999">不可扣抵</span>',
                        );
                        echo !empty($r['deduction_category']) && isset($dcLabels[$r['deduction_category']])
                            ? $dcLabels[$r['deduction_category']]
                            : '';
                    ?></td>
                    <td>
                        <span class="badge badge-<?= $r['status'] === 'voided' ? 'danger' : ($r['status'] === 'confirmed' ? 'success' : 'warning') ?>">
                            <?= e(InvoiceModel::invoiceStatusOptions()[$r['status']]) ?>
                        </span>
                    </td>
                    <td>
                        <a href="/purchase_invoices.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../layouts/pagination.php'; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: box-shadow .15s; }
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
.badge-danger { background: var(--danger); color: #fff; }
.badge-success { background: var(--success); color: #fff; }
.badge-warning { background: var(--warning); color: #fff; }
.badge { padding: 2px 8px; border-radius: 4px; font-size: .8rem; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
