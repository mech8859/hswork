<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>進貨單 <small class="text-muted">(<?= $pagination['total'] ?> 筆<?= $pagination['totalPages'] > 1 ? '，第' . $pagination['page'] . '/' . $pagination['totalPages'] . '頁' : '' ?>)</small></h2>
    <div class="d-flex gap-1">
        <a href="/goods_receipts.php?action=create" class="btn btn-primary btn-sm">+ 新增進貨單</a>
    </div>
</div>

<div class="card">
    <form method="GET" action="/goods_receipts.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (GoodsReceiptModel::statusOptions() as $k => $v): ?>
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
                <label>廠商</label>
                <input type="text" name="vendor_name" class="form-control" value="<?= e(!empty($filters['vendor_name']) ? $filters['vendor_name'] : '') ?>" placeholder="廠商名稱">
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="單號/廠商">
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
                <a href="/goods_receipts.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<?php
function grStatusBadge($status) {
    $color = GoodsReceiptModel::statusBadgeColor($status);
    return '<span class="badge badge-' . $color . '">' . e($status) . '</span>';
}
?>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無進貨單</p>
    <?php else: ?>
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" onclick="location.href='/goods_receipts.php?action=view&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['gr_number']) ? $r['gr_number'] : '-') ?></strong>
                <?= grStatusBadge(!empty($r['status']) ? $r['status'] : '') ?>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($r['gr_date']) ? $r['gr_date'] : '') ?></span>
                <span><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></span>
                <span><?= e(!empty($r['warehouse_name']) ? $r['warehouse_name'] : '') ?></span>
            </div>
            <div class="staff-card-meta">
                <span>數量 <?= number_format(!empty($r['total_qty']) ? $r['total_qty'] : 0) ?></span>
                <span>金額 $<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>進貨單號</th>
                    <th>日期</th>
                    <th>狀態</th>
                    <th>採購單號</th>
                    <th>廠商</th>
                    <th>倉庫</th>
                    <th class="text-right">數量</th>
                    <th class="text-right">金額</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><a href="/goods_receipts.php?action=view&id=<?= $r['id'] ?>"><?= e(!empty($r['gr_number']) ? $r['gr_number'] : '') ?></a></td>
                    <td><?= e(!empty($r['gr_date']) ? $r['gr_date'] : '') ?></td>
                    <td><?= grStatusBadge(!empty($r['status']) ? $r['status'] : '') ?></td>
                    <td><?= e(!empty($r['po_number']) ? $r['po_number'] : '-') ?></td>
                    <td><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></td>
                    <td><?= e(!empty($r['warehouse_name']) ? $r['warehouse_name'] : '') ?></td>
                    <td class="text-right"><?= number_format(!empty($r['total_qty']) ? $r['total_qty'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td>
                        <a href="/goods_receipts.php?action=view&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">檢視</a>
                        <?php if ($r['status'] === '草稿' || $r['status'] === '待確認'): ?>
                        <a href="/goods_receipts.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                        <?php endif; ?>
                        <?php if ($r['status'] === '草稿'): ?>
                        <a href="/goods_receipts.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('確定要刪除此進貨單？')">刪除</a>
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
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
.justify-center { justify-content: center; }
</style>

<?php if ($pagination['totalPages'] > 1): ?>
<div class="d-flex justify-center gap-1 mt-2" style="flex-wrap:wrap">
    <?php
    $qp = $_GET; unset($qp['page']);
    $qs = http_build_query($qp);
    $base = '/goods_receipts.php?' . ($qs ? $qs . '&' : '');
    ?>
    <?php if ($pagination['page'] > 1): ?>
    <a href="<?= $base ?>page=<?= $pagination['page'] - 1 ?>" class="btn btn-outline btn-sm">&laquo; 上一頁</a>
    <?php endif; ?>
    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
    <a href="<?= $base ?>page=<?= $p ?>" class="btn btn-sm <?= $p == $pagination['page'] ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($pagination['page'] < $pagination['totalPages']): ?>
    <a href="<?= $base ?>page=<?= $pagination['page'] + 1 ?>" class="btn btn-outline btn-sm">下一頁 &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
