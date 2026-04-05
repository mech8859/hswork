<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>庫存異動記錄</h2>
    <?= back_button('/inventory.php') ?>
</div>

<div class="card mb-2">
    <form method="GET" action="/inventory.php" class="filter-form">
        <input type="hidden" name="action" value="transactions">
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
                <label>異動類型</label>
                <select name="type" class="form-control">
                    <option value="">全部</option>
                    <?php foreach ($typeOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= (!empty($filters['type']) && $filters['type'] === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>關鍵字</label>
                <input type="text" name="keyword" class="form-control" value="<?= e(!empty($filters['keyword']) ? $filters['keyword'] : '') ?>" placeholder="商品名稱/型號">
            </div>
            <div class="form-group">
                <label>起始日期</label>
                <input type="date" name="date_from" class="form-control" value="<?= e(!empty($filters['date_from']) ? $filters['date_from'] : '') ?>">
            </div>
            <div class="form-group">
                <label>結束日期</label>
                <input type="date" name="date_to" class="form-control" value="<?= e(!empty($filters['date_to']) ? $filters['date_to'] : '') ?>">
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">搜尋</button>
                <a href="/inventory.php?action=transactions" class="btn btn-outline btn-sm">清除</a>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="d-flex justify-between align-center mb-1">
        <span class="text-muted"><?= count($transactions) ?> 筆</span>
    </div>
    <?php if (empty($transactions)): ?>
        <p class="text-muted text-center mt-2">目前無異動記錄</p>
    <?php else: ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($transactions as $t): ?>
        <?php
            $qty = isset($t['quantity']) ? (int)$t['quantity'] : 0;
            $qtyColor = ($qty > 0) ? 'var(--success)' : (($qty < 0) ? 'var(--danger)' : 'var(--gray-400)');
            $qtyPrefix = ($qty > 0) ? '+' : '';
            $typeLabel = InventoryModel::transactionTypeLabel(!empty($t['type']) ? $t['type'] : '');
        ?>
        <div class="staff-card">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($t['product_name']) ? $t['product_name'] : '-') ?></strong>
                <span class="badge" style="background:#e3f2fd;color:#1565c0"><?= e($typeLabel) ?></span>
            </div>
            <?php if (!empty($t['product_model'])): ?>
            <div style="font-size:.85rem;color:var(--gray-500)"><?= e($t['product_model']) ?></div>
            <?php endif; ?>
            <div class="staff-card-meta">
                <span><?= e(!empty($t['warehouse_name']) ? $t['warehouse_name'] : '-') ?></span>
                <span style="color:<?= $qtyColor ?>;font-weight:600"><?= $qtyPrefix . $qty ?></span>
                <span><?= e(!empty($t['created_at']) ? substr($t['created_at'], 0, 16) : '-') ?></span>
            </div>
            <?php if (!empty($t['note'])): ?>
            <div style="font-size:.8rem;color:var(--gray-500);margin-top:4px"><?= e($t['note']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>日期時間</th>
                    <th>類型</th>
                    <th>商品名稱</th>
                    <th>型號</th>
                    <th>倉庫</th>
                    <th class="text-right">數量</th>
                    <th class="text-right">庫存後</th>
                    <th>參考</th>
                    <th>備註</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <?php
                    $qty = isset($t['quantity']) ? (int)$t['quantity'] : 0;
                    $qtyColor = ($qty > 0) ? 'var(--success)' : (($qty < 0) ? 'var(--danger)' : 'var(--gray-400)');
                    $qtyPrefix = ($qty > 0) ? '+' : '';
                    $typeLabel = InventoryModel::transactionTypeLabel(!empty($t['type']) ? $t['type'] : '');
                ?>
                <tr>
                    <td style="white-space:nowrap"><?= e(!empty($t['created_at']) ? substr($t['created_at'], 0, 16) : '-') ?></td>
                    <td><span class="badge" style="background:#e3f2fd;color:#1565c0;font-size:.75rem"><?= e($typeLabel) ?></span></td>
                    <td><a href="/inventory.php?action=view&product_id=<?= e($t['product_id']) ?>"><?= e(!empty($t['product_name']) ? $t['product_name'] : '-') ?></a></td>
                    <td style="font-size:.85rem"><?= e(!empty($t['product_model']) ? $t['product_model'] : '-') ?></td>
                    <td><?= e(!empty($t['warehouse_name']) ? $t['warehouse_name'] : '-') ?></td>
                    <td class="text-right"><span style="color:<?= $qtyColor ?>;font-weight:600"><?= $qtyPrefix . $qty ?></span></td>
                    <td class="text-right"><?= isset($t['qty_after']) ? (int)$t['qty_after'] : '-' ?></td>
                    <td style="font-size:.8rem"><?= e(!empty($t['reference_type']) ? $t['reference_type'] : '-') ?><?= !empty($t['reference_id']) ? ' #' . e($t['reference_id']) : '' ?></td>
                    <td style="font-size:.85rem"><?= e(!empty($t['note']) ? $t['note'] : '-') ?></td>
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
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
