<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>盤點管理</h2>
    <div class="d-flex gap-1">
        <?= back_button('/inventory.php') ?>
        <?php if ($canManage): ?>
        <a href="/inventory.php?action=stocktake_create" class="btn btn-primary btn-sm">+ 建立盤點</a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-2">
    <form method="GET" action="/inventory.php" class="filter-form">
        <input type="hidden" name="action" value="stocktake_list">
        <div class="filter-row">
            <div class="form-group">
                <label>倉庫</label>
                <select name="warehouse_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($warehouses as $w): ?>
                    <option value="<?= e($w['id']) ?>" <?= (!empty($filters['warehouse_id']) && $filters['warehouse_id'] == $w['id']) ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <option value="盤點中" <?= (!empty($filters['status']) && $filters['status'] === '盤點中') ? 'selected' : '' ?>>盤點中</option>
                    <option value="已完成" <?= (!empty($filters['status']) && $filters['status'] === '已完成') ? 'selected' : '' ?>>已完成</option>
                    <option value="已取消" <?= (!empty($filters['status']) && $filters['status'] === '已取消') ? 'selected' : '' ?>>已取消</option>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($stocktakes)): ?>
        <p class="text-muted text-center mt-2">尚無盤點記錄</p>
    <?php else: ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($stocktakes as $s): ?>
        <?php
            $statusClass = ($s['status'] === '盤點中') ? 'badge-warning' : (($s['status'] === '已完成') ? 'badge-success' : 'badge-muted');
        ?>
        <div class="staff-card">
            <div class="d-flex justify-between align-center">
                <strong><?= e($s['stocktake_number']) ?></strong>
                <span class="badge <?= $statusClass ?>"><?= e($s['status']) ?></span>
            </div>
            <div class="staff-card-meta" style="flex-wrap:wrap">
                <span><?= e(!empty($s['warehouse_name']) ? $s['warehouse_name'] : '-') ?></span>
                <span><?= e($s['stocktake_date']) ?></span>
                <span>品項 <?= (int)$s['item_count'] ?></span>
                <span>已盤 <?= (int)$s['counted_count'] ?></span>
                <?php if ((int)$s['diff_count'] > 0): ?>
                <span style="color:var(--danger)">差異 <?= (int)$s['diff_count'] ?></span>
                <?php endif; ?>
            </div>
            <div style="margin-top:8px">
                <a href="/inventory.php?action=stocktake_edit&id=<?= e($s['id']) ?>" class="btn btn-outline btn-sm"><?= $s['status'] === '盤點中' ? '編輯' : '查看' ?></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>盤點單號</th>
                    <th>日期</th>
                    <th>倉庫</th>
                    <th class="text-right">品項</th>
                    <th class="text-right">已盤</th>
                    <th class="text-right">差異</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stocktakes as $s): ?>
                <?php
                    $statusClass = ($s['status'] === '盤點中') ? 'badge-warning' : (($s['status'] === '已完成') ? 'badge-success' : 'badge-muted');
                ?>
                <tr>
                    <td><strong><?= e($s['stocktake_number']) ?></strong></td>
                    <td><?= e($s['stocktake_date']) ?></td>
                    <td><?= e(!empty($s['warehouse_name']) ? $s['warehouse_name'] : '-') ?></td>
                    <td class="text-right"><?= (int)$s['item_count'] ?></td>
                    <td class="text-right"><?= (int)$s['counted_count'] ?></td>
                    <td class="text-right"><?= ((int)$s['diff_count'] > 0) ? '<span style="color:var(--danger);font-weight:600">' . (int)$s['diff_count'] . '</span>' : '0' ?></td>
                    <td><span class="badge <?= $statusClass ?>"><?= e($s['status']) ?></span></td>
                    <td>
                        <a href="/inventory.php?action=stocktake_edit&id=<?= e($s['id']) ?>" class="btn btn-outline btn-sm"><?= $s['status'] === '盤點中' ? '編輯' : '查看' ?></a>
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
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 600; }
.badge-success { background: #e6f9ee; color: var(--success); }
.badge-warning { background: #fff8e1; color: #f57f17; }
.badge-muted { background: var(--gray-100); color: var(--gray-500); }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
