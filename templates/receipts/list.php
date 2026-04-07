<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>收款單 <span style="font-size:.6em;color:#888;font-weight:normal">共 <?= number_format($result['total']) ?> 筆</span> <span style="font-size:.55em;color:var(--primary);font-weight:600">合計 $<?= number_format($result['sum_amount']) ?></span></h2>
    <a href="/receipts.php?action=create" class="btn btn-primary btn-sm">+ 新增收款單</a>
</div>

<!-- 篩選 -->
<div class="card">
    <form method="GET" action="/receipts.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>狀態</label>
                <select name="status" class="form-control">
                    <option value="">全部</option>
                    <?php foreach (FinanceModel::receiptStatusOptions() as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= (!empty($filters['status']) && $filters['status'] === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" placeholder="收款單號/客戶名稱" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>">
            </div>
            <div class="form-group">
                <label>起始日期</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>結束日期</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>排序</label>
                <select name="sort" class="form-control">
                    <option value="desc" <?= ($filters['sort'] ?? 'desc') === 'desc' ? 'selected' : '' ?>>由新到舊</option>
                    <option value="asc" <?= ($filters['sort'] ?? '') === 'asc' ? 'selected' : '' ?>>由舊到新</option>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/receipts.php" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($receipts)): ?>
        <p class="text-muted text-center mt-2">目前無收款單記錄</p>
    <?php else: ?>

    <!-- 手機版 -->
    <div class="receipt-cards show-mobile">
        <?php foreach ($receipts as $row): ?>
        <div class="staff-card">
            <div class="d-flex justify-between align-center">
                <a href="/receipts.php?action=edit&id=<?= $row['id'] ?>"><strong><?= e($row['receipt_number']) ?></strong></a>
                <?php if ($row['status'] === '待收款'): ?>
                    <span class="badge badge-info">待收款</span>
                <?php elseif ($row['status'] === '拋轉待確認'): ?>
                    <span class="badge badge-warning">拋轉待確認</span>
                <?php elseif ($row['status'] === '已入帳'): ?>
                    <span class="badge badge-success">已入帳</span>
                <?php elseif ($row['status'] === '退款'): ?>
                    <span class="badge badge-warning">退款</span>
                <?php else: ?>
                    <span class="badge badge-secondary"><?= e($row['status']) ?></span>
                <?php endif; ?>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($row['customer_name']) ? $row['customer_name'] : '-') ?></span>
                <span>登記: <?= e(!empty($row['register_date']) ? $row['register_date'] : '-') ?></span>
                <span>$<?= number_format(!empty($row['total_amount']) ? $row['total_amount'] : 0) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面版 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>收款單號</th>
                    <th>登記日期</th>
                    <th>入帳日期</th>
                    <th>客戶名稱</th>
                    <th>分公司</th>
                    <th>業務</th>
                    <th class="text-right">收款總計</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receipts as $row): ?>
                <tr>
                    <td><a href="/receipts.php?action=edit&id=<?= $row['id'] ?>"><?= e($row['receipt_number']) ?></a></td>
                    <td><?= e(!empty($row['register_date']) ? $row['register_date'] : '-') ?></td>
                    <td><?= e(!empty($row['deposit_date']) ? $row['deposit_date'] : '-') ?></td>
                    <td><?= e(!empty($row['customer_name']) ? $row['customer_name'] : '-') ?></td>
                    <td><?= e(!empty($row['branch_name']) ? $row['branch_name'] : '-') ?></td>
                    <td><?= e(!empty($row['sales_name']) ? $row['sales_name'] : '-') ?></td>
                    <td class="text-right">$<?= number_format(!empty($row['total_amount']) ? $row['total_amount'] : 0) ?></td>
                    <td>
                        <?php if ($row['status'] === '待收款'): ?>
                            <span class="badge badge-info">待收款</span>
                        <?php elseif ($row['status'] === '拋轉待確認'): ?>
                            <span class="badge badge-warning">拋轉待確認</span>
                        <?php elseif ($row['status'] === '已入帳'): ?>
                            <span class="badge badge-success">已入帳</span>
                        <?php elseif ($row['status'] === '退款'): ?>
                            <span class="badge badge-warning">退款</span>
                        <?php else: ?>
                            <span class="badge badge-secondary"><?= e($row['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/receipts.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../layouts/pagination.php'; ?>
</div>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 130px; margin-bottom: 0; }
.receipt-cards { display: flex; flex-direction: column; gap: 8px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) {
    .show-mobile { display: none !important; }
    .hide-mobile { display: block !important; }
}
</style>
