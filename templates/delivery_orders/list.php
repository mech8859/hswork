<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>出貨單管理</h2>
    <?php if ($canManage): ?>
    <a href="/delivery_orders.php?action=create" class="btn btn-primary btn-sm">+ 新增出貨單</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="GET" action="/delivery_orders.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>月份</label>
                <input type="month" name="month" class="form-control" value="<?= e($filters['month']) ?>">
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (DeliveryModel::statusOptions() as $k => $v): ?>
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
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword']) ?>" placeholder="單號/案件/收貨人">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/delivery_orders.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($orders)): ?>
        <p class="text-muted text-center mt-2">目前無出貨單</p>
    <?php else: ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($orders as $o): ?>
        <div class="staff-card" onclick="location.href='/delivery_orders.php?action=view&id=<?= $o['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e($o['do_number']) ?></strong>
                <span class="badge badge-<?= DeliveryModel::statusBadge($o['status']) ?>"><?= e(DeliveryModel::statusLabel($o['status'])) ?></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e($o['do_date']) ?></span>
                <span><?= e(!empty($o['case_name']) ? $o['case_name'] : '-') ?></span>
                <span><?= e(isset($o['warehouse_name']) ? $o['warehouse_name'] : '-') ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead><tr>
                <th>單號</th><th>日期</th><th>案件/客戶</th><th>收貨人</th><th>倉庫</th><th>狀態</th><th>建立者</th><th>操作</th>
            </tr></thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="/delivery_orders.php?action=view&id=<?= $o['id'] ?>"><?= e($o['do_number']) ?></a></td>
                    <td><?= e($o['do_date']) ?></td>
                    <td><?= e(!empty($o['case_name']) ? $o['case_name'] : '-') ?></td>
                    <td><?= e(!empty($o['receiver_name']) ? $o['receiver_name'] : '-') ?></td>
                    <td><?= e(isset($o['warehouse_name']) ? $o['warehouse_name'] : '-') ?></td>
                    <td><span class="badge badge-<?= DeliveryModel::statusBadge($o['status']) ?>"><?= e(DeliveryModel::statusLabel($o['status'])) ?></span></td>
                    <td><?= e(isset($o['created_by_name']) ? $o['created_by_name'] : '-') ?></td>
                    <td>
                        <a href="/delivery_orders.php?action=view&id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">檢視</a>
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
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
