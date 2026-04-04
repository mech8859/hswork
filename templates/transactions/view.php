<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>交易詳情</h2>
    <div class="d-flex gap-1">
        <a href="/transactions.php?action=edit&id=<?= $record['id'] ?>" class="btn btn-primary btn-sm">編輯</a>
        <a href="javascript:history.back()" class="btn btn-outline btn-sm">返回</a>
    </div>
</div>

<div class="card mb-2">
    <h3 class="mb-1">基本資訊</h3>
    <table class="table">
        <tr><td style="width:120px;color:var(--gray-500)">登記編號</td><td style="font-weight:600"><?= e($record['register_no']) ?></td></tr>
        <tr><td style="color:var(--gray-500)">登記日期</td><td><?= e($record['register_date']) ?></td></tr>
        <tr><td style="color:var(--gray-500)">交易對象</td><td><?= TransactionModel::targetTypeLabel($record['target_type']) ?></td></tr>
        <tr><td style="color:var(--gray-500)">交易分類</td><td><?= TransactionModel::categoryLabel($record['category']) ?></td></tr>
        <tr><td style="color:var(--gray-500)">姓名/廠商</td><td style="font-weight:600"><?= e($record['contact_name']) ?></td></tr>
        <tr>
            <td style="color:var(--gray-500)">合計未收金額</td>
            <td>
                <?php if ($record['total_unpaid'] > 0): ?>
                <span style="color:var(--danger);font-size:1.2rem;font-weight:700">$<?= number_format($record['total_unpaid']) ?></span>
                <?php else: ?>
                <span class="badge badge-success">已全部結清</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<div class="card mb-2">
    <h3 class="mb-1">交易明細</h3>
    <?php if (empty($record['items'])): ?>
    <p class="text-center text-muted">尚無明細</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
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
                <?php foreach ($record['items'] as $item): ?>
                <tr>
                    <td><?= e($item['trade_date']) ?></td>
                    <td><?= e($item['description']) ?></td>
                    <td><?= e($item['product']) ?></td>
                    <td style="text-align:right;font-weight:600">$<?= number_format($item['amount']) ?></td>
                    <td><?= e($item['due_date']) ?></td>
                    <td>
                        <?php if ($item['is_settled']): ?>
                        <span class="badge badge-success">已結清</span>
                        <?php else: ?>
                        <span class="badge" style="background:var(--danger);color:#fff">未結清</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($item['note']) ?></td>
                    <td>
                        <?php if ($item['is_settled']): ?>
                        <a href="/transactions.php?action=unsettle_item&item_id=<?= $item['id'] ?>&tx_id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                           class="btn btn-outline btn-sm" onclick="return confirm('確定取消結清？')">取消結清</a>
                        <?php else: ?>
                        <a href="/transactions.php?action=settle_item&item_id=<?= $item['id'] ?>&tx_id=<?= $record['id'] ?>&csrf_token=<?= e(Session::getCsrfToken()) ?>"
                           class="btn btn-primary btn-sm" onclick="return confirm('確定結清？')">結清</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
