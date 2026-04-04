<?php
$typeLabels = VehicleModel::typeLabels();
$currentType = $filters['vehicle_type'];
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>車輛管理</h2>
    <?php if (Auth::hasPermission('staff.manage')): ?>
    <a href="/vehicles.php?action=create" class="btn btn-primary btn-sm">+ 新增車輛</a>
    <?php endif; ?>
</div>

<!-- 篩選 -->
<div class="card mb-2">
    <form method="get" class="d-flex gap-1 flex-wrap align-center">
        <input type="text" name="keyword" class="form-control" style="max-width:200px" placeholder="車牌/品牌/型號..." value="<?= e($filters['keyword']) ?>">
        <select name="vehicle_type" class="form-control" style="max-width:140px">
            <option value="">全部類型</option>
            <?php foreach ($typeLabels as $tk => $tl): ?>
            <option value="<?= $tk ?>" <?= $currentType === $tk ? 'selected' : '' ?>><?= $tl ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">搜尋</button>
    </form>
</div>

<!-- 車輛列表 -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>車牌號碼</th>
                <th>類型</th>
                <th>品牌/型號</th>
                <th>保管人</th>
                <th>分公司</th>
                <th>下次保養</th>
                <th style="width:80px">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vehicles)): ?>
            <tr><td colspan="7" class="text-center text-muted">無車輛資料</td></tr>
            <?php else: ?>
            <?php foreach ($vehicles as $v): ?>
            <tr>
                <td><a href="/vehicles.php?action=view&id=<?= $v['id'] ?>" style="font-weight:600"><?= e($v['plate_number']) ?></a></td>
                <td><span class="badge <?= $v['vehicle_type'] === 'truck' ? 'badge-warning' : ($v['vehicle_type'] === 'van' ? 'badge-primary' : 'badge-success') ?>"><?= VehicleModel::typeLabel($v['vehicle_type']) ?></span></td>
                <td><?= e(($v['brand'] ?: '') . ' ' . ($v['model'] ?: '')) ?></td>
                <td><?= e($v['custodian_name'] ?: '-') ?></td>
                <td><?= e($v['branch_name'] ?: '-') ?></td>
                <td>
                    <?php if ($v['next_maintenance_date']): ?>
                        <?php
                        $nextDate = $v['next_maintenance_date'];
                        $isOverdue = $nextDate < date('Y-m-d');
                        $isSoon = !$isOverdue && $nextDate <= date('Y-m-d', strtotime('+14 days'));
                        ?>
                        <span style="color:<?= $isOverdue ? 'var(--danger)' : ($isSoon ? 'var(--warning-dark, #E65100)' : 'inherit') ?>; font-weight:<?= ($isOverdue || $isSoon) ? '600' : 'normal' ?>">
                            <?= e($nextDate) ?>
                            <?php if ($isOverdue): ?> (逾期)<?php elseif ($isSoon): ?> (即將到期)<?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="/vehicles.php?action=view&id=<?= $v['id'] ?>" class="btn btn-outline btn-sm" style="padding:2px 8px">查看</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 手機卡片 -->
<div class="show-mobile">
    <?php foreach ($vehicles as $v): ?>
    <a href="/vehicles.php?action=view&id=<?= $v['id'] ?>" class="card mb-1" style="display:block;text-decoration:none;color:inherit">
        <div class="d-flex justify-between align-center">
            <strong><?= e($v['plate_number']) ?></strong>
            <span class="badge <?= $v['vehicle_type'] === 'truck' ? 'badge-warning' : ($v['vehicle_type'] === 'van' ? 'badge-primary' : 'badge-success') ?>"><?= VehicleModel::typeLabel($v['vehicle_type']) ?></span>
        </div>
        <div class="text-muted" style="font-size:.85rem"><?= e(($v['brand'] ?: '') . ' ' . ($v['model'] ?: '')) ?></div>
        <div class="d-flex justify-between" style="font-size:.85rem;margin-top:4px">
            <span>保管人: <?= e($v['custodian_name'] ?: '-') ?></span>
            <span><?= e($v['branch_name'] ?: '') ?></span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<style>
@media (max-width: 767px) {
    .table-responsive { display: none; }
}
@media (min-width: 768px) {
    .show-mobile { display: none !important; }
}
</style>
