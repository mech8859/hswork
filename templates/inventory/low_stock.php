<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>低庫存警示</h2>
    <?= back_button('/inventory.php') ?>
</div>

<?php if (empty($lowStockItems)): ?>
<div class="card">
    <p class="text-muted text-center mt-2">目前沒有低於安全庫存的品項</p>
</div>
<?php else: ?>
<div class="card">
    <div class="d-flex justify-between align-center mb-1">
        <span class="text-danger" style="font-weight:600"><?= count($lowStockItems) ?> 個品項低於安全庫存</span>
    </div>

    <!-- 手機卡片 -->
    <div class="staff-cards show-mobile">
        <?php foreach ($lowStockItems as $r): ?>
        <?php $shortage = (int)$r['min_qty'] - (int)$r['stock_qty']; ?>
        <div class="staff-card" style="border-left:3px solid var(--danger)">
            <div class="d-flex justify-between align-center">
                <strong><?= e(!empty($r['product_name']) ? $r['product_name'] : '-') ?></strong>
                <a href="/inventory.php?action=view&product_id=<?= e($r['product_id']) ?>" class="btn btn-outline btn-sm">查看</a>
            </div>
            <?php if (!empty($r['product_model'])): ?>
            <div style="font-size:.85rem;color:var(--gray-500)"><?= e($r['product_model']) ?></div>
            <?php endif; ?>
            <div class="staff-card-meta" style="flex-wrap:wrap">
                <span><?= e(!empty($r['warehouse_name']) ? $r['warehouse_name'] : '-') ?></span>
                <span>庫存 <strong style="color:var(--danger)"><?= (int)$r['stock_qty'] ?></strong></span>
                <span>安全庫存 <strong><?= (int)$r['min_qty'] ?></strong></span>
                <span style="color:var(--danger)">缺 <strong><?= $shortage ?></strong></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 桌面表格 -->
    <div class="table-responsive hide-mobile">
        <table class="table">
            <thead>
                <tr>
                    <th>商品名稱</th>
                    <th>型號</th>
                    <th>倉庫</th>
                    <th>單位</th>
                    <th class="text-right">目前庫存</th>
                    <th class="text-right">安全庫存</th>
                    <th class="text-right">缺少數量</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lowStockItems as $r): ?>
                <?php $shortage = (int)$r['min_qty'] - (int)$r['stock_qty']; ?>
                <tr>
                    <td><a href="/inventory.php?action=view&product_id=<?= e($r['product_id']) ?>" style="font-weight:600"><?= e(!empty($r['product_name']) ? $r['product_name'] : '-') ?></a></td>
                    <td style="font-size:.85rem"><?= e(!empty($r['product_model']) ? $r['product_model'] : '-') ?></td>
                    <td><?= e(!empty($r['warehouse_name']) ? $r['warehouse_name'] : '-') ?></td>
                    <td><?= e(!empty($r['unit']) ? $r['unit'] : '-') ?></td>
                    <td class="text-right"><span style="color:var(--danger);font-weight:600"><?= (int)$r['stock_qty'] ?></span></td>
                    <td class="text-right"><?= (int)$r['min_qty'] ?></td>
                    <td class="text-right"><span style="color:var(--danger);font-weight:600"><?= $shortage ?></span></td>
                    <td>
                        <a href="/inventory.php?action=view&product_id=<?= e($r['product_id']) ?>" class="btn btn-outline btn-sm">查看</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
.staff-cards { display: flex; flex-direction: column; gap: 8px; }
.staff-card { border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 12px; }
.staff-card-meta { font-size: .8rem; color: var(--gray-500); display: flex; gap: 8px; margin-top: 4px; }
.show-mobile { display: flex; }
.hide-mobile { display: none; }
@media (min-width: 768px) { .show-mobile { display: none !important; } .hide-mobile { display: block !important; } }
</style>
