<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>退貨單 <small class="text-muted">(<?= count($records) ?>)</small></h2>
    <a href="/returns.php?action=create" class="btn btn-primary btn-sm">+ 新增退貨單</a>
</div>

<div class="card">
    <form method="GET" action="/returns.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>退貨類型</label>
                <select name="return_type" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (ReturnModel::returnTypeOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($filters['return_type']) ? $filters['return_type'] : '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (ReturnModel::statusOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($filters['status']) ? $filters['status'] : '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>倉庫</label>
                <select name="warehouse_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($warehouses as $wh): ?>
                    <option value="<?= $wh['id'] ?>" <?= (!empty($filters['warehouse_id']) ? $filters['warehouse_id'] : '') == $wh['id'] ? 'selected' : '' ?>><?= e($wh['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="單號/客戶/廠商">
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
                <a href="/returns.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<?php
function returnStatusBadge($status) {
    $map = array(
        'draft'     => 'orange',
        'confirmed' => 'green',
        'cancelled' => 'gray',
    );
    $color = !empty($map[$status]) ? $map[$status] : 'gray';
    return '<span class="badge badge-' . $color . '">' . e(ReturnModel::statusLabel($status)) . '</span>';
}

function returnTypeBadge($type) {
    $map = array(
        'customer_return' => 'blue',
        'vendor_return'   => 'purple',
    );
    $color = !empty($map[$type]) ? $map[$type] : 'gray';
    return '<span class="badge badge-' . $color . '">' . e(ReturnModel::returnTypeLabel($type)) . '</span>';
}
?>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無退貨單</p>
    <?php else: ?>
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" onclick="location.href='/returns.php?action=view&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['return_number']) ? $r['return_number'] : '-') ?></strong>
                <div><?= returnTypeBadge(!empty($r['return_type']) ? $r['return_type'] : '') ?> <?= returnStatusBadge(!empty($r['status']) ? $r['status'] : '') ?></div>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($r['return_date']) ? $r['return_date'] : '') ?></span>
                <?php if (!empty($r['customer_name'])): ?><span><?= e($r['customer_name']) ?></span><?php endif; ?>
                <?php if (!empty($r['vendor_name'])): ?><span><?= e($r['vendor_name']) ?></span><?php endif; ?>
            </div>
            <div class="staff-card-meta">
                <span>合計 $<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></span>
                <span><?= e(!empty($r['warehouse_name']) ? $r['warehouse_name'] : '') ?></span>
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
                    <th>類型</th>
                    <th>狀態</th>
                    <th>客戶/廠商</th>
                    <th>分公司</th>
                    <th>倉庫</th>
                    <th class="text-right">合計金額</th>
                    <th>建立人</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><a href="/returns.php?action=view&id=<?= $r['id'] ?>"><?= e(!empty($r['return_number']) ? $r['return_number'] : '') ?></a></td>
                    <td><?= e(!empty($r['return_date']) ? $r['return_date'] : '') ?></td>
                    <td><?= returnTypeBadge(!empty($r['return_type']) ? $r['return_type'] : '') ?></td>
                    <td><?= returnStatusBadge(!empty($r['status']) ? $r['status'] : '') ?></td>
                    <td><?= e($r['return_type'] === 'customer_return' ? (!empty($r['customer_name']) ? $r['customer_name'] : '-') : (!empty($r['vendor_name']) ? $r['vendor_name'] : '-')) ?></td>
                    <td><?= e(!empty($r['branch_name']) ? $r['branch_name'] : '-') ?></td>
                    <td><?= e(!empty($r['warehouse_name']) ? $r['warehouse_name'] : '-') ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td><?= e(!empty($r['created_by_name']) ? $r['created_by_name'] : '') ?></td>
                    <td>
                        <a href="/returns.php?action=view&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">檢視</a>
                        <?php if ($r['status'] === 'draft'): ?>
                        <a href="/returns.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                        <a href="/returns.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('確定要刪除此退貨單？')">刪除</a>
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
