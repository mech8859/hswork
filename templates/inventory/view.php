<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>庫存明細 - <?= e(!empty($product['product_name']) ? $product['product_name'] : '-') ?></h2>
    <div class="d-flex gap-1">
        <a href="/products.php?action=view&id=<?= e($product['product_id']) ?>" class="btn btn-outline btn-sm">產品詳情</a>
        <a href="javascript:history.back()" class="btn btn-outline btn-sm">返回</a>
        <?php if ($canManage): ?>
        <a href="/inventory.php?action=adjust" class="btn btn-primary btn-sm">入庫/出庫</a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-2">
    <h3 style="margin-bottom:12px">商品資訊</h3>
    <div class="product-info-grid">
        <div class="product-info-item">
            <span class="product-info-label">商品名稱</span>
            <span><?= e(!empty($product['product_name']) ? $product['product_name'] : '-') ?></span>
        </div>
        <div class="product-info-item">
            <span class="product-info-label">型號</span>
            <span><?= e(!empty($product['product_model']) ? $product['product_model'] : '-') ?></span>
        </div>
        <div class="product-info-item">
            <span class="product-info-label">主分類</span>
            <span><?php
                if (!empty($product['cat_grandparent_name'])) {
                    echo e($product['cat_grandparent_name']);
                } elseif (!empty($product['cat_parent_name'])) {
                    echo e($product['cat_parent_name']);
                } elseif (!empty($product['category_name'])) {
                    echo e($product['category_name']);
                } else {
                    echo '-';
                }
            ?></span>
        </div>
        <div class="product-info-item">
            <span class="product-info-label">子分類</span>
            <span><?php
                if (!empty($product['cat_grandparent_name'])) {
                    echo e($product['cat_parent_name']);
                } elseif (!empty($product['cat_parent_name'])) {
                    echo e($product['category_name']);
                } else {
                    echo '-';
                }
            ?></span>
        </div>
        <div class="product-info-item">
            <span class="product-info-label">細分類</span>
            <span><?= (!empty($product['cat_grandparent_name']) && !empty($product['category_name'])) ? e($product['category_name']) : '-' ?></span>
        </div>
        <div class="product-info-item">
            <span class="product-info-label">單位</span>
            <span><?= e(!empty($product['unit']) ? $product['unit'] : '-') ?></span>
        </div>
        <div class="product-info-item">
            <span class="product-info-label">成本</span>
            <span><?= !empty($product['cost']) ? '$' . number_format($product['cost']) : '-' ?></span>
        </div>
        <div class="product-info-item">
            <span class="product-info-label">售價</span>
            <span><?= !empty($product['sell_price']) ? '$' . number_format($product['sell_price']) : '-' ?></span>
        </div>
    </div>
</div>

<div class="card mb-2">
    <h3 style="margin-bottom:12px">各倉庫庫存</h3>
    <?php if (empty($inventoryRows)): ?>
        <p class="text-muted text-center">尚無庫存資料</p>
    <?php else: ?>
    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($inventoryRows as $row): ?>
        <?php $isLow = (!empty($row['min_qty']) && $row['min_qty'] > 0 && (int)$row['stock_qty'] <= (int)$row['min_qty']); ?>
        <div class="staff-card" style="<?= $isLow ? 'border-left:3px solid var(--danger)' : '' ?>">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($row['warehouse_name']) ? $row['warehouse_name'] : '-') ?></strong>
                <div class="d-flex gap-1 align-center">
                    <?php if (!empty($row['locked'])): ?>
                    <span class="badge badge-danger">已鎖定</span>
                    <?php endif; ?>
                    <?php if ($isLow): ?>
                    <span class="badge badge-danger">低庫存</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="staff-card-meta" style="flex-wrap:wrap">
                <span>可用 <strong style="color:<?= (!empty($row['available_qty']) && $row['available_qty'] > 0) ? 'var(--success)' : 'var(--gray-400)' ?>"><?= (int)(!empty($row['available_qty']) ? $row['available_qty'] : 0) ?></strong></span>
                <span>庫存 <strong style="color:<?= $isLow ? 'var(--danger)' : '' ?>"><?= (int)(!empty($row['stock_qty']) ? $row['stock_qty'] : 0) ?></strong></span>
                <span>安全庫存 <strong><?= (int)(!empty($row['min_qty']) ? $row['min_qty'] : 0) ?></strong></span>
                <span>已備貨 <strong><?= (int)(!empty($row['reserved_qty']) ? $row['reserved_qty'] : 0) ?></strong></span>
                <span>借出 <strong><?= (int)(!empty($row['loaned_qty']) ? $row['loaned_qty'] : 0) ?></strong></span>
                <span>展示 <strong><?= (int)(!empty($row['display_qty']) ? $row['display_qty'] : 0) ?></strong></span>
            </div>
            <?php if ($canManage): ?>
            <form method="POST" action="/inventory.php?action=update_min_qty" class="d-flex gap-1 align-center" style="margin-top:8px">
                <?= csrf_field() ?>
                <input type="hidden" name="inventory_id" value="<?= e($row['id']) ?>">
                <input type="hidden" name="product_id" value="<?= e($row['product_id']) ?>">
                <span style="font-size:.8rem;white-space:nowrap">安全庫存:</span>
                <input type="number" name="min_qty" value="<?= (int)(!empty($row['min_qty']) ? $row['min_qty'] : 0) ?>" class="form-control" style="width:70px;padding:4px 6px" min="0">
                <button type="submit" class="btn btn-outline btn-sm">更新</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>倉庫</th>
                    <th class="text-right">可用</th>
                    <th class="text-right">庫存</th>
                    <th class="text-right">安全庫存</th>
                    <th class="text-right">已備貨</th>
                    <th class="text-right">借出</th>
                    <th class="text-right">展示</th>
                    <th>狀態</th>
                    <?php if ($canManage): ?><th>安全庫存設定</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventoryRows as $row): ?>
                <?php $isLow = (!empty($row['min_qty']) && $row['min_qty'] > 0 && (int)$row['stock_qty'] <= (int)$row['min_qty']); ?>
                <tr style="<?= $isLow ? 'background:#fff5f5' : '' ?>">
                    <td><?= e(!empty($row['warehouse_name']) ? $row['warehouse_name'] : '-') ?></td>
                    <td class="text-right"><span style="color:<?= (!empty($row['available_qty']) && $row['available_qty'] > 0) ? 'var(--success)' : 'var(--gray-400)' ?>"><?= (int)(!empty($row['available_qty']) ? $row['available_qty'] : 0) ?></span></td>
                    <td class="text-right"><span style="color:<?= $isLow ? 'var(--danger)' : ((!empty($row['stock_qty']) && $row['stock_qty'] > 0) ? 'var(--success)' : 'var(--gray-400)') ?>;font-weight:<?= $isLow ? '700' : 'normal' ?>"><?= (int)(!empty($row['stock_qty']) ? $row['stock_qty'] : 0) ?></span></td>
                    <td class="text-right"><?= (int)(!empty($row['min_qty']) ? $row['min_qty'] : 0) ?></td>
                    <td class="text-right"><?= (int)(!empty($row['reserved_qty']) ? $row['reserved_qty'] : 0) ?></td>
                    <td class="text-right"><?= (int)(!empty($row['loaned_qty']) ? $row['loaned_qty'] : 0) ?></td>
                    <td class="text-right"><?= (int)(!empty($row['display_qty']) ? $row['display_qty'] : 0) ?></td>
                    <td>
                        <?php if (!empty($row['locked'])): ?>
                        <span class="badge badge-danger">已鎖定</span>
                        <?php elseif ($isLow): ?>
                        <span class="badge badge-danger">低庫存</span>
                        <?php else: ?>
                        <span class="badge badge-success">正常</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canManage): ?>
                    <td>
                        <form method="POST" action="/inventory.php?action=update_min_qty" class="d-flex gap-1 align-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="inventory_id" value="<?= e($row['id']) ?>">
                            <input type="hidden" name="product_id" value="<?= e($row['product_id']) ?>">
                            <input type="number" name="min_qty" value="<?= (int)(!empty($row['min_qty']) ? $row['min_qty'] : 0) ?>" class="form-control" style="width:70px;text-align:right;padding:4px 6px" min="0">
                            <button type="submit" class="btn btn-outline btn-sm">更新</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="d-flex justify-between align-center mb-1">
        <h3>異動紀錄</h3>
        <a href="/inventory.php?action=transactions" class="btn btn-outline btn-sm" style="font-size:.8rem">查看全部</a>
    </div>
    <?php if (empty($transactions)): ?>
        <p class="text-muted text-center">尚無異動紀錄</p>
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
                <span class="badge" style="background:#e3f2fd;color:#1565c0"><?= e($typeLabel) ?></span>
                <span class="text-muted" style="font-size:.8rem"><?= e(!empty($t['created_at']) ? substr($t['created_at'], 0, 16) : '-') ?></span>
            </div>
            <div class="staff-card-meta">
                <span><?= e(!empty($t['warehouse_name']) ? $t['warehouse_name'] : '-') ?></span>
                <span style="color:<?= $qtyColor ?>;font-weight:600"><?= $qtyPrefix . $qty ?></span>
                <?php if (isset($t['qty_after'])): ?>
                <span>庫存後 <?= (int)$t['qty_after'] ?></span>
                <?php endif; ?>
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
                    <th>日期</th>
                    <th>類型</th>
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
.product-info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
.product-info-item { display: flex; flex-direction: column; }
.product-info-label { font-size: .75rem; color: var(--gray-500); margin-bottom: 2px; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 600; }
.badge-success { background: #e6f9ee; color: var(--success); }
.badge-danger { background: #fde8e8; color: var(--danger); }
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
