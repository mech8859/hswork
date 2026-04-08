<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>加班單管理 <small class="text-muted">(<?= count($records) ?>)</small></h2>
    <div class="d-flex gap-1">
        <a href="/overtimes.php?action=monthly_report&month=<?= e($filters['month']) ?>" class="btn btn-outline btn-sm">📊 月結報表</a>
        <a href="/overtimes.php?action=create" class="btn btn-primary btn-sm">+ 申請加班</a>
    </div>
</div>

<!-- 篩選 -->
<div class="card">
    <form method="GET" action="/overtimes.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>月份</label>
                <input type="month" name="month" class="form-control" value="<?= e($filters['month']) ?>">
            </div>
            <?php if ($canView && !empty($users)): ?>
            <div class="form-group">
                <label>人員</label>
                <select name="user_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filters['user_id'] == $u['id'] ? 'selected' : '' ?>><?= e($u['real_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($canView && !empty($branches)): ?>
            <div class="form-group">
                <label>分公司</label>
                <select name="branch_id" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $filters['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>待核准</option>
                    <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>已核准</option>
                    <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>已駁回</option>
                </select>
            </div>
            <div class="form-group">
                <label>類別</label>
                <select name="overtime_type" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (OvertimeModel::typeOptions() as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $filters['overtime_type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/overtimes.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<?php
// 計算總時數
$totalHours = 0;
foreach ($records as $r) $totalHours += (float)$r['hours'];
?>

<div class="card">
    <div style="padding:8px 12px;border-bottom:1px solid var(--gray-200);background:#f9f9f9;font-size:.85rem">
        <strong>本次篩選結果合計：</strong> <?= count($records) ?> 筆 / 共 <span style="color:#1565c0;font-weight:600"><?= number_format($totalHours, 2) ?></span> 小時
    </div>
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無加班記錄</p>
    <?php else: ?>

    <!-- 手機版 -->
    <div class="overtime-cards show-mobile">
        <?php foreach ($records as $r): ?>
        <div class="staff-card" onclick="location.href='/overtimes.php?action=view&id=<?= $r['id'] ?>'" style="cursor:pointer">
            <div class="d-flex justify-between align-center">
                <strong><?= e($r['real_name']) ?></strong>
                <?php if ($r['status'] === 'pending'): ?>
                    <span class="badge badge-warning">待核准</span>
                <?php elseif ($r['status'] === 'approved'): ?>
                    <span class="badge badge-success">已核准</span>
                <?php else: ?>
                    <span class="badge badge-danger">已駁回</span>
                <?php endif; ?>
            </div>
            <div class="staff-card-meta">
                <span><?= e($r['overtime_date']) ?></span>
                <span><?= e(substr($r['start_time'], 0, 5)) ?>~<?= e(substr($r['end_time'], 0, 5)) ?></span>
                <span style="color:#1565c0;font-weight:600"><?= number_format($r['hours'], 2) ?>h</span>
                <span><?= e(OvertimeModel::typeLabel($r['overtime_type'])) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>加班日期</th>
                    <th>申請人</th>
                    <th>分公司</th>
                    <th>類別</th>
                    <th>時間</th>
                    <th class="text-right">時數</th>
                    <th>事由</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><?= e($r['overtime_date']) ?></td>
                    <td><?= e($r['real_name']) ?></td>
                    <td><?= e($r['branch_name']) ?></td>
                    <td><?= e(OvertimeModel::typeLabel($r['overtime_type'])) ?></td>
                    <td><?= e(substr($r['start_time'], 0, 5)) ?> ~ <?= e(substr($r['end_time'], 0, 5)) ?></td>
                    <td class="text-right" style="font-weight:600;color:#1565c0"><?= number_format($r['hours'], 2) ?></td>
                    <td><?= e(mb_substr($r['reason'] ?: '-', 0, 30)) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                            <span class="badge badge-warning">待核准</span>
                        <?php elseif ($r['status'] === 'approved'): ?>
                            <span class="badge badge-success">已核准</span>
                        <?php else: ?>
                            <span class="badge badge-danger">已駁回</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/overtimes.php?action=view&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">檢視</a>
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
.overtime-cards { display: flex; flex-direction: column; gap: 8px; padding: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; transition: box-shadow .15s; }
.staff-card:hover { box-shadow: var(--shadow); }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; flex-wrap: wrap; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 500; }
.badge-warning { background: #fff3e0; color: #e65100; }
.badge-success { background: #e8f5e9; color: #2e7d32; }
.badge-danger { background: #ffebee; color: #c62828; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) {
    .show-mobile { display: none !important; }
    .hide-mobile { display: block !important; }
}
</style>
