<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>出庫單管理 <small class="text-muted" style="font-size:.8rem">(<?= $pagination['total'] ?> 筆<?= $pagination['totalPages'] > 1 ? '，第' . $pagination['page'] . '/' . $pagination['totalPages'] . '頁' : '' ?>)</small></h2>
    <?php if ($canManage): ?>
    <a href="/stock_outs.php?action=create" class="btn btn-primary btn-sm">+ 新增出庫單</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="GET" action="/stock_outs.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>月份</label>
                <div style="display:flex;gap:6px;align-items:center">
                    <input type="month" name="month" class="form-control" value="<?= e($filters['month']) ?>">
                    <?php if (!empty($filters['month'])): ?>
                    <a href="/stock_outs.php?month=&status=<?= e($filters['status']) ?>&warehouse_id=<?= e($filters['warehouse_id']) ?>&keyword=<?= e($filters['keyword']) ?>" class="btn btn-outline btn-sm" style="white-space:nowrap">全部</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (StockModel::stockOutStatusOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>倉庫</label>
                <select name="warehouse_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= $w['id'] ?>" <?= $filters['warehouse_id'] == $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword']) ?>" placeholder="出庫單號">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/stock_outs.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無出庫單</p>
    <?php else: ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($records as $r):
            $soNum = isset($r['stockout_number']) ? $r['stockout_number'] : (isset($r['so_number']) ? $r['so_number'] : '-');
            $soDate = isset($r['stockout_date']) ? $r['stockout_date'] : (isset($r['so_date']) ? $r['so_date'] : '-');
            $refType = isset($r['reference_type']) ? $r['reference_type'] : (isset($r['source_type']) ? $r['source_type'] : '');
        ?>
        <div class="staff-card" onclick="location.href='/stock_outs.php?action=view&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e($soNum) ?></strong>
                <span class="badge badge-<?= StockModel::statusBadge($r['status']) ?>"><?= e(StockModel::statusLabel($r['status'])) ?></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e($soDate) ?></span>
                <?php if (!empty($r['customer_name'])): ?><span><?= e($r['customer_name']) ?></span><?php endif; ?>
                <span><?= e(isset($r['warehouse_name']) ? $r['warehouse_name'] : '-') ?></span>
                <?php if ($refType): ?>
                <span><?= e(StockModel::referenceTypeLabel($refType)) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead><tr>
                <th>單號</th><th>日期</th><th>客戶名稱</th><th>倉庫</th><th>來源類型</th><th>狀態</th><th>建立者</th><th>操作</th>
            </tr></thead>
            <tbody>
                <?php foreach ($records as $r):
                    $soNum = isset($r['stockout_number']) ? $r['stockout_number'] : (isset($r['so_number']) ? $r['so_number'] : '-');
                    $soDate = isset($r['stockout_date']) ? $r['stockout_date'] : (isset($r['so_date']) ? $r['so_date'] : '-');
                    $refType = isset($r['reference_type']) ? $r['reference_type'] : (isset($r['source_type']) ? $r['source_type'] : '');
                    $creatorName = isset($r['creator_name']) ? $r['creator_name'] : (isset($r['created_by_name']) ? $r['created_by_name'] : '-');
                ?>
                <tr>
                    <td><a href="/stock_outs.php?action=view&id=<?= $r['id'] ?>"><?= e($soNum) ?></a></td>
                    <td><?= e($soDate) ?></td>
                    <td><?= e(isset($r['customer_name']) && $r['customer_name'] ? $r['customer_name'] : '-') ?></td>
                    <td><?= e(isset($r['warehouse_name']) ? $r['warehouse_name'] : '-') ?></td>
                    <td><?= $refType ? e(StockModel::referenceTypeLabel($refType)) : '-' ?></td>
                    <td><span class="badge badge-<?= StockModel::statusBadge($r['status']) ?>"><?= e(StockModel::statusLabel($r['status'])) ?></span></td>
                    <td><?= e($creatorName) ?></td>
                    <td>
                        <a href="/stock_outs.php?action=view&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">檢視</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($pagination['totalPages'] > 1): ?>
<div class="d-flex justify-center gap-1 mt-2" style="flex-wrap:wrap">
    <?php
    $qp = $_GET; unset($qp['page']);
    $qs = http_build_query($qp);
    $base = '/stock_outs.php?' . ($qs ? $qs . '&' : '');
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

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; cursor: pointer; transition: box-shadow .15s; }
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
.justify-center { justify-content: center; }
</style>
