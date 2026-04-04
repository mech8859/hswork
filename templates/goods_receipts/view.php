<?php
function grViewStatusBadge($status) {
    $color = GoodsReceiptModel::statusBadgeColor($status);
    return '<span class="badge badge-' . $color . '">' . e($status) . '</span>';
}
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <div>
        <h2 style="margin-bottom:2px">進貨單 <?= e($record['gr_number']) ?> <?= grViewStatusBadge($record['status']) ?></h2>
        <?php if (!empty($record['updated_at'])): ?>
        <small class="text-muted">最後修改 <?= e($record['updated_at']) ?><?php
            if (!empty($record['updated_by'])) {
                $updater = Database::getInstance()->prepare('SELECT real_name FROM users WHERE id = ?');
                $updater->execute(array($record['updated_by']));
                $un = $updater->fetchColumn();
                if ($un) echo ' / ' . e($un);
            }
        ?></small>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-1">
        <?php if ($record['status'] === '草稿' || $record['status'] === '待確認'): ?>
        <a href="/goods_receipts.php?action=edit&id=<?= $record['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <?php endif; ?>
        <?php if ($record['status'] !== '已確認' && $record['status'] !== '已取消'): ?>
        <a href="/goods_receipts.php?action=confirm&id=<?= $record['id'] ?>" class="btn btn-sm" style="background:#2e7d32;color:#fff" onclick="return confirm('確認進貨？確認後將自動建立入庫單並更新庫存。')">確認進貨</a>
        <?php endif; ?>
        <a href="/goods_receipts.php" class="btn btn-outline btn-sm">返回列表</a>
    </div>
</div>

<div class="card">
    <div class="card-header">基本資訊</div>
    <div class="form-row">
        <div class="form-group">
            <label>進貨單號</label>
            <div class="form-value"><?= e($record['gr_number']) ?></div>
        </div>
        <div class="form-group">
            <label>進貨日期</label>
            <div class="form-value"><?= e(!empty($record['gr_date']) ? $record['gr_date'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>狀態</label>
            <div class="form-value"><?= grViewStatusBadge($record['status']) ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>關聯採購單</label>
            <div class="form-value">
                <?php if (!empty($record['po_number'])): ?>
                <a href="/purchase_orders.php?action=edit&id=<?= $record['po_id'] ?>"><?= e($record['po_number']) ?></a>
                <?php else: ?>
                -
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group">
            <label>廠商</label>
            <div class="form-value"><?= e(!empty($record['vendor_name']) ? $record['vendor_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>倉庫</label>
            <div class="form-value"><?= e(!empty($record['warehouse_name']) ? $record['warehouse_name'] : '-') ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>收貨人</label>
            <div class="form-value"><?= e(!empty($record['receiver_name']) ? $record['receiver_name'] : '-') ?></div>
        </div>
        <div class="form-group">
            <label>建立者</label>
            <div class="form-value"><?= e(!empty($record['created_by_name']) ? $record['created_by_name'] : '-') ?></div>
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
    <div class="card-header">進貨明細</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>型號</th>
                    <th>品名</th>
                    <th>規格</th>
                    <th>單位</th>
                    <th class="text-right">採購數量</th>
                    <th class="text-right">收貨數量</th>
                    <th class="text-right">單價</th>
                    <th class="text-right">金額</th>
                </tr>
            </thead>
            <tbody>
                <?php $totalQty = 0; $totalAmt = 0; ?>
                <?php foreach ($items as $idx => $item): ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= e(!empty($item['model']) ? $item['model'] : '-') ?></td>
                    <td><?= e(!empty($item['product_name']) ? $item['product_name'] : '-') ?></td>
                    <td><?= e(!empty($item['spec']) ? $item['spec'] : '') ?></td>
                    <td><?= e(!empty($item['unit']) ? $item['unit'] : '') ?></td>
                    <td class="text-right"><?= number_format(!empty($item['po_qty']) ? $item['po_qty'] : 0) ?></td>
                    <td class="text-right"><?= number_format(!empty($item['received_qty']) ? $item['received_qty'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($item['unit_price']) ? $item['unit_price'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($item['amount']) ? $item['amount'] : 0) ?></td>
                </tr>
                <?php $totalQty += (!empty($item['received_qty']) ? $item['received_qty'] : 0); ?>
                <?php $totalAmt += (!empty($item['amount']) ? $item['amount'] : 0); ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold">
                    <td colspan="6" class="text-right">合計</td>
                    <td class="text-right"><?= number_format($totalQty) ?></td>
                    <td></td>
                    <td class="text-right">$<?= number_format($totalAmt) ?></td>
                </tr>
                <?php $taxAmt = round($totalAmt * 0.05); ?>
                <tr style="font-size:.85rem;color:var(--gray-500)">
                    <td colspan="8" class="text-right">未稅金額</td>
                    <td class="text-right">$<?= number_format($totalAmt) ?></td>
                </tr>
                <tr style="font-size:.85rem;color:var(--gray-500)">
                    <td colspan="8" class="text-right">稅額 (5%)</td>
                    <td class="text-right">$<?= number_format($taxAmt) ?></td>
                </tr>
                <tr style="font-weight:bold;font-size:1.05rem;color:var(--primary)">
                    <td colspan="8" class="text-right">總金額（含稅）</td>
                    <td class="text-right">$<?= number_format($totalAmt + $taxAmt) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- 付款資訊 -->
<div class="card">
    <div class="card-header">付款資訊</div>
    <div class="form-row">
        <div class="form-group">
            <label>已付金額</label>
            <div class="form-value" style="<?= !empty($record['paid_amount']) ? 'color:#2e7d32;font-weight:700' : '' ?>"><?= !empty($record['paid_amount']) ? '$' . number_format($record['paid_amount']) : '-' ?></div>
        </div>
        <div class="form-group">
            <label>付款日</label>
            <div class="form-value"><?= !empty($record['paid_date']) ? e($record['paid_date']) : '-' ?></div>
        </div>
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
