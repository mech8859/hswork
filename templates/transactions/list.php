<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>非廠商交易管理</h2>
    <div class="d-flex gap-1">
        <a href="/transactions.php?action=create" class="btn btn-primary btn-sm">+ 新增交易</a>
    </div>
</div>

<!-- 篩選 -->
<div class="card mb-2">
    <form method="GET" class="form-row align-center flex-wrap gap-1">
        <div class="form-group">
            <label>交易對象</label>
            <select name="target_type" class="form-control">
                <option value="">全部</option>
                <option value="employee" <?= $filters['target_type'] === 'employee' ? 'selected' : '' ?>>員工</option>
                <option value="partner" <?= $filters['target_type'] === 'partner' ? 'selected' : '' ?>>合作夥伴</option>
            </select>
        </div>
        <div class="form-group">
            <label>結清狀態</label>
            <select name="settled" class="form-control">
                <option value="">全部</option>
                <option value="unsettled" <?= $filters['settled'] === 'unsettled' ? 'selected' : '' ?>>有未收款</option>
                <option value="settled" <?= $filters['settled'] === 'settled' ? 'selected' : '' ?>>已全部結清</option>
            </select>
        </div>
        <div class="form-group">
            <label>姓名/廠商</label>
            <input type="text" name="contact_name" class="form-control" value="<?= e($filters['contact_name']) ?>" placeholder="搜尋...">
        </div>
        <div class="form-group" style="align-self:flex-end">
            <button type="submit" class="btn btn-primary btn-sm">篩選</button>
            <a href="/transactions.php" class="btn btn-outline btn-sm">清除</a>
        </div>
    </form>
</div>

<!-- 桌面版表格 -->
<div class="card hide-mobile">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>姓名/廠商</th>
                    <th>交易對象</th>
                    <th>交易筆數</th>
                    <th>明細筆數</th>
                    <th style="text-align:right">未收金額</th>
                    <th>未結明細</th>
                    <th>最近交易日</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                <tr><td colspan="7" class="text-center text-muted">無記錄</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                    <tr style="cursor:pointer" onclick="location.href='/transactions.php?action=contact&name=<?= urlencode($r['contact_name']) ?>'">
                        <td><a href="/transactions.php?action=contact&name=<?= urlencode($r['contact_name']) ?>" style="font-weight:600"><?= e($r['contact_name']) ?></a></td>
                        <td><?= TransactionModel::targetTypeLabel($r['target_type']) ?></td>
                        <td><?= $r['tx_count'] ?>筆</td>
                        <td><?= $r['item_count'] ?>筆</td>
                        <td style="text-align:right">
                            <?php if ($r['total_unpaid_sum'] > 0): ?>
                            <span style="color:var(--danger);font-weight:700;font-size:1.05rem">$<?= number_format($r['total_unpaid_sum']) ?></span>
                            <?php else: ?>
                            <span class="text-muted">$0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['unsettled_count'] > 0): ?>
                            <span class="badge" style="background:var(--danger);color:#fff"><?= $r['unsettled_count'] ?>筆未結</span>
                            <?php else: ?>
                            <span class="badge badge-success">已結清</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($r['last_date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 手機版卡片 -->
<div class="show-mobile">
    <?php if (empty($records)): ?>
    <div class="card text-center text-muted" style="padding:2rem">無記錄</div>
    <?php else: ?>
        <?php foreach ($records as $r): ?>
        <a href="/transactions.php?action=contact&name=<?= urlencode($r['contact_name']) ?>" class="card mb-1" style="padding:.8rem;display:block;text-decoration:none;color:inherit">
            <div class="d-flex justify-between align-center mb-1">
                <span style="font-weight:600;font-size:1.05rem"><?= e($r['contact_name']) ?></span>
                <span class="badge"><?= TransactionModel::targetTypeLabel($r['target_type']) ?></span>
            </div>
            <div class="d-flex justify-between align-center">
                <span>
                    <?= $r['tx_count'] ?>筆交易 / <?= $r['item_count'] ?>筆明細
                    <?php if ($r['unsettled_count'] > 0): ?>
                    <span class="badge" style="background:var(--danger);color:#fff;font-size:.7rem"><?= $r['unsettled_count'] ?>未結</span>
                    <?php endif; ?>
                </span>
                <?php if ($r['total_unpaid_sum'] > 0): ?>
                <span style="color:var(--danger);font-weight:700">$<?= number_format($r['total_unpaid_sum']) ?></span>
                <?php else: ?>
                <span class="badge badge-success">已結清</span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
