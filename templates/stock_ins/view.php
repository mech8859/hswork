<?php
function siViewStatusBadge($status) {
    $color = StockModel::statusBadgeColor($status);
    return '<span class="badge badge-' . $color . '">' . e($status) . '</span>';
}
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2 style="margin-bottom:2px">入庫單 <?= e($record['si_number']) ?> <?= siViewStatusBadge($record['status']) ?></h2>
        <?php if (!empty($record['updated_at'])): ?>
        <small class="text-muted">最後修改 <?= e($record['updated_at']) ?><?php
            if (!empty($record['updated_by'])) {
                $updater = Database::getInstance()->prepare('SELECT real_name FROM users WHERE id = ?');
                $updater->execute(array($record['updated_by']));
                $updaterName = $updater->fetchColumn();
                if ($updaterName) echo ' / ' . e($updaterName);
            }
        ?></small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <?php if ($record['status'] === '待確認'): ?>
        <a href="/stock_ins.php?action=confirm&id=<?= $record['id'] ?>" class="btn btn-sm" style="background:#2e7d32;color:#fff" onclick="return confirm('確認入庫？確認後將更新庫存數量。')">確認入庫</a>
        <?php endif; ?>
        <a href="/stock_ins.php" class="btn btn-outline btn-sm">返回列表</a>
    </div>
</div>

<div class="card">
    <div class="card-header">基本資訊</div>
    <div class="form-row">
        <div class="form-group">
            <label>入庫單號</label>
            <div class="form-value"><?= e($record['si_number']) ?></div>
        </div>
        <div class="form-group">
            <label>入庫日期</label>
            <div class="form-value"><?= e(!empty($record['si_date']) ? $record['si_date'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>狀態</label>
            <div class="form-value"><?= siViewStatusBadge($record['status']) ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>來源類型</label>
            <div class="form-value"><?= e(StockModel::sourceTypeLabel(!empty($record['source_type']) ? $record['source_type'] : '')) ?></div>
        </div>
        <div class="form-group">
            <label>來源單號</label>
            <div class="form-value">
                <?php
                $srcNum = !empty($record['source_number']) ? $record['source_number'] : '';
                $srcLinked = false;
                if ($srcNum) {
                    // S/D 開頭 → 出庫單
                    if (strpos($srcNum, 'S/D-') === 0) {
                        $srcRow = Database::getInstance()->prepare("SELECT id FROM stock_outs WHERE so_number = ?")->execute(array($srcNum));
                        $srcRow = Database::getInstance()->prepare("SELECT id FROM stock_outs WHERE so_number = ?");
                        $srcRow->execute(array($srcNum));
                        $srcId = $srcRow->fetchColumn();
                        if ($srcId) { echo '<a href="/stock_outs.php?action=view&id=' . $srcId . '">' . e($srcNum) . '</a>'; $srcLinked = true; }
                    }
                    // GR 開頭 → 進貨單
                    elseif (strpos($srcNum, 'GR-') === 0) {
                        $srcRow = Database::getInstance()->prepare("SELECT id FROM goods_receipts WHERE gr_number = ?");
                        $srcRow->execute(array($srcNum));
                        $srcId = $srcRow->fetchColumn();
                        if ($srcId) { echo '<a href="/goods_receipts.php?action=view&id=' . $srcId . '">' . e($srcNum) . '</a>'; $srcLinked = true; }
                    }
                    if (!$srcLinked) echo e($srcNum);
                } else {
                    echo '-';
                }
                ?>
            </div>
        </div>
        <div class="form-group">
            <label>分公司</label>
            <div class="form-value"><?= e(!empty($record['branch_name']) ? $record['branch_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>倉庫</label>
            <div class="form-value"><?= e(!empty($record['warehouse_name']) ? $record['warehouse_name'] : '-') ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>客戶名稱</label>
            <div class="form-value"><?= e(!empty($record['customer_name']) ? $record['customer_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>廠商名稱</label>
            <div class="form-value"><?= e(!empty($record['vendor_name']) ? $record['vendor_name'] : '-') ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>建立者</label>
            <div class="form-value"><?= e(!empty($record['created_by_name']) ? $record['created_by_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>建立時間</label>
            <div class="form-value"><?= e(!empty($record['created_at']) ? $record['created_at'] : '-') ?></div>
        </div>
        <?php if ($record['status'] === '已確認'): ?>
        <div class="form-group">
            <label>確認者</label>
            <div class="form-value"><?= e(!empty($record['confirmed_by_name']) ? $record['confirmed_by_name'] : '-') ?> (<?= e(!empty($record['confirmed_at']) ? $record['confirmed_at'] : '') ?>)</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 明細 -->
<div class="card">
    <div class="card-header">入庫明細</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>型號</th>
                    <th>品名</th>
                    <th>規格</th>
                    <th>單位</th>
                    <th class="text-right">數量</th>
                    <th class="text-right">單價</th>
                </tr>
            </thead>
            <tbody>
                <?php $totalQty = 0; ?>
                <?php foreach ($items as $idx => $item): ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= e(!empty($item['model']) ? $item['model'] : '-') ?></td>
                    <td><?= e(!empty($item['product_name']) ? $item['product_name'] : '-') ?></td>
                    <td><?= e(!empty($item['spec']) ? $item['spec'] : '') ?></td>
                    <td><?= e(!empty($item['unit']) ? $item['unit'] : '') ?></td>
                    <td class="text-right"><?= number_format(!empty($item['quantity']) ? $item['quantity'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($item['unit_price']) ? $item['unit_price'] : 0) ?></td>
                </tr>
                <?php $totalQty += (!empty($item['quantity']) ? $item['quantity'] : 0); ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold">
                    <td colspan="5" class="text-right">合計</td>
                    <td class="text-right"><?= number_format($totalQty) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if (!empty($record['note'])): ?>
<div class="card">
    <div class="card-header">備註</div>
    <p><?= nl2br(e($record['note'])) ?></p>
</div>
<?php endif; ?>

<style>
.form-row { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row .form-group { flex: 1; min-width: 150px; }
.form-value { padding: 6px 0; font-weight: 500; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: .75rem; font-weight: 500; }
.badge-orange { background: #fff3e0; color: #e65100; }
.badge-green { background: #e8f5e9; color: #2e7d32; }
.badge-gray { background: #f5f5f5; color: #757575; }
</style>
