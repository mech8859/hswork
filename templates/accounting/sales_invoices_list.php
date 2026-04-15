<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>銷項發票管理 <span style="font-size:.7em;color:var(--gray-500);font-weight:normal">(共 <?= number_format($result['total']) ?> 筆)</span></h2>
    <?php if ($canManage): ?>
    <a href="/sales_invoices.php?action=create" class="btn btn-primary btn-sm">+ 新增銷項發票</a>
    <?php endif; ?>
</div>

<div class="card" style="padding:12px">
    <form method="GET" action="/sales_invoices.php" class="d-flex flex-wrap gap-1 align-center">
        <select name="period" class="form-control" style="width:auto;min-width:100px">
            <option value="">全部期間</option>
            <?php foreach ($periodOptions as $p): ?>
            <option value="<?= e($p) ?>" <?= (!empty($filters['period']) && $filters['period'] === $p) ? 'selected' : '' ?>><?= e(substr($p, 0, 4) . '/' . substr($p, 4, 2)) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="customer" class="form-control" style="width:auto;min-width:120px" value="<?= e(!empty($filters['customer']) ? $filters['customer'] : '') ?>" placeholder="客戶名稱/統編">
        <select name="status" class="form-control" style="width:auto;min-width:80px">
            <option value="">全部狀態</option>
            <?php foreach (InvoiceModel::invoiceStatusOptions() as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= (!empty($filters['status']) && $filters['status'] === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="invoice_type" class="form-control" style="width:auto;min-width:80px">
            <option value="">全部類型</option>
            <?php foreach (InvoiceModel::salesInvoiceTypeOptions() as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= (!empty($filters['invoice_type']) && $filters['invoice_type'] === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="keyword" class="form-control" style="width:auto;min-width:140px" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="發票號碼/備註">
        <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
        <a href="/sales_invoices.php" class="btn btn-outline btn-sm">清除</a>
    </form>
</div>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無銷項發票資料</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:32px"></th>
                    <th>發票號碼</th>
                    <th>日期</th>
                    <th>客戶名稱</th>
                    <th>統編</th>
                    <th class="text-right">未稅金額</th>
                    <th class="text-right">稅額</th>
                    <th class="text-right">含稅金額</th>
                    <th>類型</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): $isStar = !empty($r['is_starred']); ?>
                <tr<?= $r['status'] === 'voided' ? ' style="opacity:.5;text-decoration:line-through"' : '' ?>>
                    <td class="text-center"><span class="star-toggle <?= $isStar ? 'is-on' : '' ?>" data-id="<?= (int)$r['id'] ?>" onclick="toggleStarSalesInvoice(this)" title="標記">&#9733;</span></td>
                    <td><a href="/sales_invoices.php?action=edit&id=<?= $r['id'] ?>"><?= e(!empty($r['invoice_number']) ? $r['invoice_number'] : '-') ?></a></td>
                    <td><?= e(!empty($r['invoice_date']) ? $r['invoice_date'] : '') ?></td>
                    <td><?= e(!empty($r['customer_name']) ? $r['customer_name'] : '-') ?></td>
                    <td><?= e(!empty($r['customer_tax_id']) ? $r['customer_tax_id'] : '-') ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['amount_untaxed']) ? $r['amount_untaxed'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['tax_amount']) ? $r['tax_amount'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td><?= e(!empty($r['invoice_type']) ? $r['invoice_type'] : '-') ?></td>
                    <td>
                        <span class="badge badge-<?= $r['status'] === 'voided' ? 'danger' : ($r['status'] === 'confirmed' ? 'success' : 'warning') ?>">
                            <?php $sopts = InvoiceModel::invoiceStatusOptions(); echo e(isset($sopts[$r['status']]) ? $sopts[$r['status']] : $r['status']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="/sales_invoices.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
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
.star-toggle { display:inline-block; cursor:pointer; font-size:1.2rem; color:#d0d0d0; transition:color .15s,transform .15s; user-select:none; line-height:1; }
.star-toggle:hover { color:#f1c40f; transform:scale(1.15); }
.star-toggle.is-on { color:#f1c40f; }
.star-toggle.saving { opacity:.5; pointer-events:none; }
</style>
<script>
function toggleStarSalesInvoice(el) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id'); if (!id) return;
    el.classList.add('saving');
    var fd = new FormData(); fd.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/sales_invoices.php?action=toggle_star');
    xhr.onload = function() { el.classList.remove('saving'); try { var res = JSON.parse(xhr.responseText); if (res.error) { alert(res.error); return; } el.classList.toggle('is-on', !!res.starred); } catch (e) { alert('回應錯誤'); } };
    xhr.onerror = function() { el.classList.remove('saving'); alert('網路錯誤'); };
    xhr.send(fd);
}
</script>
