<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>採購單 <small class="text-muted">(<?= count($records) ?>)</small></h2>
    <a href="/purchase_orders.php?action=create" class="btn btn-primary btn-sm">+ 新增採購單</a>
</div>

<div class="card">
    <form method="GET" action="/purchase_orders.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= (!empty($filters['branch_id']) ? $filters['branch_id'] : '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (ProcurementModel::poStatusOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($filters['status']) ? $filters['status'] : '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>廠商名稱</label>
                <input type="text" name="vendor_name" class="form-control" value="<?= e(!empty($filters['vendor_name']) ? $filters['vendor_name'] : '') ?>" placeholder="廠商名稱">
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="單號/品名">
            </div>
            <div class="form-group">
                <label>日期起</label>
                <input type="date" max="2099-12-31" name="date_from" class="form-control" value="<?= e(!empty($filters['date_from']) ? $filters['date_from'] : '') ?>">
            </div>
            <div class="form-group">
                <label>日期迄</label>
                <input type="date" max="2099-12-31" name="date_to" class="form-control" value="<?= e(!empty($filters['date_to']) ? $filters['date_to'] : '') ?>">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/purchase_orders.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<?php
function poStatusBadge($status) {
    $map = array(
        '尚未進貨'   => 'orange',
        '確認進貨'   => 'blue',
        '已轉進貨單' => 'purple',
        '確認付款'   => 'green',
        '取消'       => 'gray'
    );
    $color = !empty($map[$status]) ? $map[$status] : 'gray';
    return '<span class="badge badge-' . $color . '">' . e($status) . '</span>';
}
?>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無採購單</p>
    <?php else: ?>
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" onclick="location.href='/purchase_orders.php?action=edit&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['po_number']) ? $r['po_number'] : '-') ?></strong>
                <?= poStatusBadge(!empty($r['status']) ? $r['status'] : '') ?>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($r['po_date']) ? $r['po_date'] : '') ?></span>
                <span><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></span>
                <span><?= e(!empty($r['purchaser_name']) ? $r['purchaser_name'] : '') ?></span>
            </div>
            <div class="staff-card-meta">
                <span>合計 $<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>單號</th>
                    <th>日期</th>
                    <th>狀態</th>
                    <th>廠商</th>
                    <th>採購人</th>
                    <th>分公司</th>
                    <th>案件名稱</th>
                    <th class="text-right">合計金額</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><a href="/purchase_orders.php?action=edit&id=<?= $r['id'] ?>"><?= e(!empty($r['po_number']) ? $r['po_number'] : '') ?></a></td>
                    <td><?= e(!empty($r['po_date']) ? $r['po_date'] : '') ?></td>
                    <td><?= poStatusBadge(!empty($r['status']) ? $r['status'] : '') ?></td>
                    <td><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></td>
                    <td><?= e(!empty($r['purchaser_name']) ? $r['purchaser_name'] : '') ?></td>
                    <td><?= e(!empty($r['branch_name']) ? $r['branch_name'] : '') ?></td>
                    <td><?= e(!empty($r['case_name']) ? $r['case_name'] : '-') ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td>
                        <a href="/purchase_orders.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                        <?php if (!empty($r['status']) && $r['status'] === '確認進貨'): ?>
                        <a href="/goods_receipts.php?action=create_from_po&po_id=<?= $r['id'] ?>" class="btn btn-success btn-sm">轉進貨單</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: box-shadow .15s; }
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 500; }
.badge-orange { background: #fff3e0; color: #e65100; }
.badge-blue { background: #e3f2fd; color: #1565c0; }
.badge-green { background: #e8f5e9; color: #2e7d32; }
.badge-gray { background: #f5f5f5; color: #757575; }
.badge-purple { background: #f3e5f5; color: #7b1fa2; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
