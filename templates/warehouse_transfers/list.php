<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>倉庫調撥 <small class="text-muted">(<?= count($records) ?>)</small></h2>
    <a href="/warehouse_transfers.php?action=create" class="btn btn-primary btn-sm">+ 新增調撥單</a>
</div>

<div class="card">
    <form method="GET" action="/warehouse_transfers.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>調出倉庫</label>
                <select name="from_warehouse_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= $w['id'] ?>" <?= (!empty($filters['from_warehouse_id']) ? $filters['from_warehouse_id'] : '') == $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>調進倉庫</label>
                <select name="to_warehouse_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= $w['id'] ?>" <?= (!empty($filters['to_warehouse_id']) ? $filters['to_warehouse_id'] : '') == $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (ProcurementModel::transferStatusOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= (!empty($filters['status']) ? $filters['status'] : '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="單號/備註">
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
                <a href="/warehouse_transfers.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<?php
function transferStatusBadge($status) {
    $map = array(
        '待出貨' => 'orange',
        '已出貨' => 'blue',
        '已到貨' => 'purple',
        '完成'   => 'green',
        '取消'   => 'gray'
    );
    $color = !empty($map[$status]) ? $map[$status] : 'gray';
    return '<span class="badge badge-' . $color . '">' . e($status) . '</span>';
}
?>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無調撥單</p>
    <?php else: ?>
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" onclick="location.href='/warehouse_transfers.php?action=edit&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['transfer_number']) ? $r['transfer_number'] : '-') ?></strong>
                <?= transferStatusBadge(!empty($r['status']) ? $r['status'] : '') ?>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($r['transfer_date']) ? $r['transfer_date'] : '') ?></span>
                <span><?= e(!empty($r['from_warehouse_name']) ? $r['from_warehouse_name'] : '') ?> → <?= e(!empty($r['to_warehouse_name']) ? $r['to_warehouse_name'] : '') ?></span>
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
                    <th>調出倉庫</th>
                    <th>調進倉庫</th>
                    <th>狀態</th>
                    <th>出貨人</th>
                    <th>進貨人</th>
                    <th class="text-right">合計</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><a href="/warehouse_transfers.php?action=edit&id=<?= $r['id'] ?>"><?= e(!empty($r['transfer_number']) ? $r['transfer_number'] : '') ?></a></td>
                    <td><?= e(!empty($r['transfer_date']) ? $r['transfer_date'] : '') ?></td>
                    <td><?= e(!empty($r['from_warehouse_name']) ? $r['from_warehouse_name'] : '') ?></td>
                    <td><?= e(!empty($r['to_warehouse_name']) ? $r['to_warehouse_name'] : '') ?></td>
                    <td><?= transferStatusBadge(!empty($r['status']) ? $r['status'] : '') ?></td>
                    <td><?= e(!empty($r['shipper_name']) ? $r['shipper_name'] : '') ?></td>
                    <td><?= e(!empty($r['receiver_name']) ? $r['receiver_name'] : '') ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td>
                        <a href="/warehouse_transfers.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                        <a href="/warehouse_transfers.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('確定要刪除此調撥單？')">刪除</a>
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
