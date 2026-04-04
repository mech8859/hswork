<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2><?= e($contactName) ?> — 交易紀錄</h2>
    <div class="d-flex gap-1">
        <a href="/transactions.php?action=create&contact_name=<?= urlencode($contactName) ?>&target_type=<?= urlencode($contactRecords[0]['target_type']) ?>" class="btn btn-primary btn-sm">+ 新增交易</a>
        <a href="/transactions.php" class="btn btn-outline btn-sm">返回列表</a>
    </div>
</div>

<?php
$totalUnpaid = 0;
$totalItems = 0;
$totalUnsettled = 0;
foreach ($contactRecords as $r) {
    $totalUnpaid += $r['total_unpaid'];
    $totalItems += $r['item_count'];
    $totalUnsettled += $r['unsettled_count'];
}
?>

<!-- 彙總卡片 -->
<div class="card mb-2">
    <div class="d-flex flex-wrap gap-2" style="justify-content:space-around;text-align:center">
        <div>
            <div class="text-muted" style="font-size:.85rem">交易筆數</div>
            <div style="font-size:1.3rem;font-weight:700"><?= count($contactRecords) ?></div>
        </div>
        <div>
            <div class="text-muted" style="font-size:.85rem">明細筆數</div>
            <div style="font-size:1.3rem;font-weight:700"><?= $totalItems ?></div>
        </div>
        <div>
            <div class="text-muted" style="font-size:.85rem">未結明細</div>
            <div style="font-size:1.3rem;font-weight:700;color:<?= $totalUnsettled > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= $totalUnsettled ?></div>
        </div>
        <div>
            <div class="text-muted" style="font-size:.85rem">合計未收金額</div>
            <div style="font-size:1.3rem;font-weight:700;color:<?= $totalUnpaid > 0 ? 'var(--danger)' : 'var(--success)' ?>">$<?= number_format($totalUnpaid) ?></div>
        </div>
    </div>
</div>

<!-- 各筆交易 -->
<?php foreach ($contactRecords as $r):
    $txModel = new TransactionModel();
    $detail = $txModel->getById($r['id']);
?>
<div class="card mb-2">
    <div class="d-flex justify-between align-center mb-1">
        <div>
            <span style="font-weight:600"><?= e($r['register_no']) ?></span>
            <span class="text-muted" style="margin-left:.5rem"><?= e($r['register_date']) ?></span>
            <span class="badge" style="margin-left:.5rem"><?= TransactionModel::categoryLabel($r['category']) ?></span>
        </div>
        <div class="d-flex gap-1">
            <?php if ($r['total_unpaid'] > 0): ?>
            <span style="color:var(--danger);font-weight:700">未收 $<?= number_format($r['total_unpaid']) ?></span>
            <?php else: ?>
            <span class="badge badge-success">已結清</span>
            <?php endif; ?>
            <a href="/transactions.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">編輯</a>
            <a href="/transactions.php?action=delete&id=<?= $r['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
               class="btn btn-danger btn-sm" onclick="return confirm('確定刪除？')">刪除</a>
        </div>
    </div>

    <?php if (!empty($detail['items'])): ?>
    <div class="table-responsive">
        <table class="table" style="font-size:.9rem">
            <thead>
                <tr>
                    <th>交易日期</th>
                    <th>交易內容</th>
                    <th>商品</th>
                    <th style="text-align:right">金額</th>
                    <th>預計付款日</th>
                    <th>狀態</th>
                    <th>備註</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detail['items'] as $itemIdx => $item):
                    $iDate = isset($item['trade_date']) ? $item['trade_date'] : '';
                    $iDesc = isset($item['description']) ? $item['description'] : '';
                    $iProd = isset($item['product']) ? $item['product'] : '';
                    $iAmt = isset($item['amount']) ? (float)$item['amount'] : 0;
                    $iDue = isset($item['due_date']) ? $item['due_date'] : '';
                    $iSettled = !empty($item['is_settled']);
                    $iNote = isset($item['note']) ? $item['note'] : '';
                    $iId = isset($item['id']) ? (int)$item['id'] : 0;
                ?>
                <tr>
                    <td><?= htmlspecialchars($iDate) ?></td>
                    <td><?= htmlspecialchars($iDesc) ?></td>
                    <td><?= htmlspecialchars($iProd) ?></td>
                    <td style="text-align:right;font-weight:600">$<?= number_format($iAmt) ?></td>
                    <td><?= htmlspecialchars($iDue) ?></td>
                    <td>
                        <?php if ($iSettled): ?>
                        <span class="badge badge-success">已結清</span>
                        <?php else: ?>
                        <span class="badge" style="background:var(--danger);color:#fff">未結清</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:150px;white-space:normal"><?= htmlspecialchars($iNote) ?></td>
                    <td>
                        <?php if ($iSettled): ?>
                        <a href="/transactions.php?action=unsettle_item&item_id=<?= $iId ?>&tx_id=<?= $r['id'] ?>&back=<?= urlencode($contactName) ?>&csrf_token=<?= htmlspecialchars(Session::getCsrfToken()) ?>"
                           class="btn btn-outline btn-sm" style="font-size:.75rem" onclick="return confirm('取消結清？')">取消結清</a>
                        <?php else: ?>
                        <a href="/transactions.php?action=settle_item&item_id=<?= $iId ?>&tx_id=<?= $r['id'] ?>&back=<?= urlencode($contactName) ?>&csrf_token=<?= htmlspecialchars(Session::getCsrfToken()) ?>"
                           class="btn btn-primary btn-sm" style="font-size:.75rem" onclick="return confirm('確定結清？')">結清</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
