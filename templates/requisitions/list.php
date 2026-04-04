<?php
$statusOptions = ProcurementModel::requisitionStatusOptions();
$statusBadgeMap = array(
    '草稿'     => 'badge-secondary',
    '簽核中'   => 'badge-primary',
    '已核准'   => 'badge-info',
    '簽核完成' => 'badge-success',
    '退回'     => 'badge-danger',
    '已轉採購' => 'badge-purple',
    '取消'     => 'badge-secondary',
);
?>
<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>請購單 <span class="text-muted" style="font-size:.9rem">(<?= count($records) ?>)</span></h2>
    <a href="/requisitions.php?action=create" class="btn btn-primary btn-sm">+ 新增請購單</a>
</div>

<!-- 篩選 -->
<div class="card">
    <form method="GET" action="/requisitions.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <select name="branch_id" class="form-control">
                    <option value="">全部分公司</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= (!empty($filters['branch_id']) && $filters['branch_id'] == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">全部狀態</option>
                    <?php foreach ($statusOptions as $sv => $sl): ?>
                    <option value="<?= e($sv) ?>" <?= (!empty($filters['status']) && $filters['status'] === $sv) ? 'selected' : '' ?>><?= e($sl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:2">
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="搜尋單號/請購人/案名/廠商..." autocomplete="off">
            </div>
            <div class="form-group">
                <input type="date" max="2099-12-31" name="date_from" class="form-control" value="<?= e(!empty($filters['date_from']) ? $filters['date_from'] : '') ?>" placeholder="起始日期">
            </div>
            <div class="form-group">
                <input type="date" max="2099-12-31" name="date_to" class="form-control" value="<?= e(!empty($filters['date_to']) ? $filters['date_to'] : '') ?>" placeholder="結束日期">
            </div>
            <div class="form-group" style="align-self:flex-end;flex:0;display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/requisitions.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<!-- 列表 -->
<div class="card">
    <?php if (empty($records)): ?>
        <p class="text-muted text-center mt-2">目前無請購單資料</p>
    <?php else: ?>

    <!-- 手機版卡片 -->
    <div class="requisition-cards show-mobile">
        <?php foreach ($records as $row): ?>
        <div class="requisition-card" onclick="location.href='/requisitions.php?action=edit&id=<?= $row['id'] ?>'">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($row['requisition_number']) ? $row['requisition_number'] : '') ?></strong>
                <?php $badgeCls = !empty($statusBadgeMap[$row['status']]) ? $statusBadgeMap[$row['status']] : 'badge-secondary'; ?>
                <span class="badge <?= $badgeCls ?>"><?= e(!empty($row['status']) ? $row['status'] : '-') ?></span>
            </div>
            <div class="requisition-card-title"><?= e(!empty($row['case_name']) ? $row['case_name'] : '-') ?></div>
            <div class="requisition-card-meta">
                <span><?= !empty($row['requisition_date']) && $row['requisition_date'] !== '0000-00-00' ? date('Y/m/d', strtotime($row['requisition_date'])) : '-' ?></span>
                <span><?= e(!empty($row['requester_name']) ? $row['requester_name'] : '') ?></span>
                <span><?= e(!empty($row['branch_name']) ? $row['branch_name'] : '') ?></span>
                <?php if (!empty($row['urgency'])): ?><span><?= e($row['urgency']) ?></span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>單號</th>
                    <th>日期</th>
                    <th>請購人</th>
                    <th>分公司</th>
                    <th>案名</th>
                    <th>廠商</th>
                    <th>緊急程度</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                <tr>
                    <td><a href="/requisitions.php?action=edit&id=<?= $row['id'] ?>"><?= e(!empty($row['requisition_number']) ? $row['requisition_number'] : '') ?></a></td>
                    <td><?= !empty($row['requisition_date']) && $row['requisition_date'] !== '0000-00-00' ? date('Y/m/d', strtotime($row['requisition_date'])) : '-' ?></td>
                    <td><?= e(!empty($row['requester_name']) ? $row['requester_name'] : '-') ?></td>
                    <td><?= e(!empty($row['branch_name']) ? $row['branch_name'] : '-') ?></td>
                    <td><?= e(!empty($row['case_name']) ? $row['case_name'] : '-') ?></td>
                    <td title="<?= e(!empty($row['vendor_name']) ? $row['vendor_name'] : '') ?>"><?= !empty($row['vendor_name']) ? e(mb_substr($row['vendor_name'], 0, 2)) : '-' ?></td>
                    <td><?= e(!empty($row['urgency']) ? $row['urgency'] : '-') ?></td>
                    <td>
                        <?php $badgeCls = !empty($statusBadgeMap[$row['status']]) ? $statusBadgeMap[$row['status']] : 'badge-secondary'; ?>
                        <span class="badge <?= $badgeCls ?>"><?= e(!empty($row['status']) ? $row['status'] : '-') ?></span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/requisitions.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                            <?php if (!empty($row['status']) && in_array($row['status'], array('已核准', '簽核完成'))): ?>
                            <a href="/purchase_orders.php?action=create&from_requisition=<?= $row['id'] ?>" class="btn btn-success btn-sm">轉採購</a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('確定要刪除此請購單？'))location.href='/requisitions.php?action=delete&id=<?= $row['id'] ?>'">刪除</button>
                        </div>
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
.filter-row .form-group { flex: 1; min-width: 140px; margin-bottom: 0; }
.requisition-cards { display: flex; flex-direction: column; gap: 8px; }
.requisition-card {
    border: 1px solid var(--gray-200); border-radius: var(--radius);
    padding: 12px; cursor: pointer; transition: box-shadow .15s;
}
.requisition-card:hover { box-shadow: var(--shadow); }
.requisition-card-title { font-weight: 500; margin: 4px 0; }
.requisition-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; flex-wrap: wrap; }
.badge-purple { background: #7c3aed; color: #fff; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) {
    .show-mobile { display: none !important; }
    .hide-mobile { display: block !important; }
}
</style>
