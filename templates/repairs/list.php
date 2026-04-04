<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>維修單管理</h2>
    <?php if (Auth::hasPermission('repairs.manage')): ?>
    <a href="/repairs.php?action=create" class="btn btn-primary btn-sm">+ 新增維修單</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="GET" action="/repairs.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>月份</label>
                <input type="month" name="month" class="form-control" value="<?= e($filters['month']) ?>">
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>草稿</option>
                    <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>已完成</option>
                    <option value="invoiced" <?= $filters['status'] === 'invoiced' ? 'selected' : '' ?>>已開票</option>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e($filters['keyword']) ?>" placeholder="客戶名/單號">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/repairs.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($repairs)): ?>
        <p class="text-muted text-center mt-2">目前無維修單</p>
    <?php else: ?>
    <div class="staff-cards show-mobile">
        <?php foreach ($repairs as $r): ?>
        <div class="staff-card" onclick="location.href='/repairs.php?action=view&id=<?= $r['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e($r['customer_name']) ?></strong>
                <span class="badge badge-<?= RepairModel::statusBadge($r['status']) ?>"><?= e(RepairModel::statusLabel($r['status'])) ?></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e($r['repair_number']) ?></span>
                <span><?= e($r['repair_date']) ?></span>
                <span>$<?= number_format($r['total_amount']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead><tr><th>單號</th><th>日期</th><th>客戶</th><th>地址</th><th>工程師</th><th>金額</th><th>狀態</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($repairs as $r): ?>
                <tr>
                    <td><a href="/repairs.php?action=view&id=<?= $r['id'] ?>"><?= e($r['repair_number']) ?></a></td>
                    <td><?= e($r['repair_date']) ?></td>
                    <td><?= e($r['customer_name']) ?></td>
                    <td><?= e(mb_substr($r['customer_address'] ?: '-', 0, 20)) ?></td>
                    <td><?= e($r['engineer_name'] ?: '-') ?></td>
                    <td class="text-right">$<?= number_format($r['total_amount']) ?></td>
                    <td><span class="badge badge-<?= RepairModel::statusBadge($r['status']) ?>"><?= e(RepairModel::statusLabel($r['status'])) ?></span></td>
                    <td>
                        <a href="/repairs.php?action=view&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">檢視</a>
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
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
