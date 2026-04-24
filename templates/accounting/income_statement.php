<div class="page-sticky-head">
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>損益表</h1>
    <div style="display:flex;gap:8px">
        <a href="/accounting.php?action=balance_sheet" class="btn btn-secondary">資產負債表</a>
        <a href="/accounting.php?action=trial_balance" class="btn btn-secondary">試算表</a>
        <a href="/accounting.php?action=reconciliation" class="btn btn-secondary">銀行對帳</a>
    </div>
</div>

<!-- Query Form -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <form method="get" action="/accounting.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="action" value="income_statement">
        <div>
            <label style="font-size:0.85em">起始日期</label>
            <input type="date" name="start_date" value="<?= e($startDate) ?>" class="form-control" style="width:160px" required>
        </div>
        <div>
            <label style="font-size:0.85em">結束日期</label>
            <input type="date" name="end_date" value="<?= e($endDate) ?>" class="form-control" style="width:160px" required>
        </div>
        <div>
            <label style="font-size:0.85em">成本中心</label>
            <select name="cost_center_id" class="form-control" style="width:150px">
                <option value="">全部</option>
                <?php foreach ($costCenters as $cc): ?>
                <option value="<?= $cc['id'] ?>" <?= $costCenterId == $cc['id'] ? 'selected' : '' ?>><?= e($cc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">查詢</button>
        <button type="button" class="btn btn-secondary" onclick="window.print()">列印</button>
    </form>
</div>

<div class="card" style="padding:12px;margin-bottom:16px;background:#f8f9fa">
    <strong>損益表</strong>
    期間: <?= e(format_date($report['start_date'])) ?> ~ <?= e(format_date($report['end_date'])) ?>
    <?php if ($costCenterId): ?> | 成本中心篩選<?php endif; ?>
</div>
</div><!-- /.page-sticky-head -->

<!-- Income Statement -->
<div class="card" style="overflow-x:auto">
    <table class="data-table" style="width:100%">
        <!-- Revenue Section -->
        <thead>
            <tr style="background:#e8f5e9">
                <th colspan="2" style="font-size:1.1em">營業收入</th>
                <th style="width:150px;text-align:right">金額</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($report['revenue'])): ?>
            <tr><td colspan="3" style="text-align:center;color:#999">無收入紀錄</td></tr>
        <?php else: ?>
            <?php foreach ($report['revenue'] as $item): ?>
            <tr>
                <td style="width:100px"><?= e($item['code']) ?></td>
                <td><?= e($item['name']) ?></td>
                <td style="text-align:right;color:#388e3c"><?= number_format($item['amount']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr style="background:#c8e6c9;font-weight:bold">
            <td colspan="2" style="text-align:right">營業收入合計</td>
            <td style="text-align:right"><?= number_format($report['total_revenue']) ?></td>
        </tr>
        </tbody>

        <!-- Expense Section -->
        <thead>
            <tr style="background:#ffebee">
                <th colspan="2" style="font-size:1.1em">營業費用</th>
                <th style="text-align:right">金額</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($report['expense'])): ?>
            <tr><td colspan="3" style="text-align:center;color:#999">無費用紀錄</td></tr>
        <?php else: ?>
            <?php foreach ($report['expense'] as $item): ?>
            <tr>
                <td><?= e($item['code']) ?></td>
                <td><?= e($item['name']) ?></td>
                <td style="text-align:right;color:#d32f2f"><?= number_format($item['amount']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        <tr style="background:#ffcdd2;font-weight:bold">
            <td colspan="2" style="text-align:right">營業費用合計</td>
            <td style="text-align:right"><?= number_format($report['total_expense']) ?></td>
        </tr>
        </tbody>

        <!-- Net Income -->
        <tfoot>
            <tr style="background:<?= $report['net_income'] >= 0 ? '#1b5e20' : '#b71c1c' ?>;color:#fff;font-size:1.1em">
                <td colspan="2" style="text-align:right;font-weight:bold;padding:12px">本期淨利（損）</td>
                <td style="text-align:right;font-weight:bold;padding:12px"><?= number_format($report['net_income']) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
