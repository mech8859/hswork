<div class="page-sticky-head">
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>資產負債表</h1>
    <div style="display:flex;gap:8px">
        <a href="/accounting.php?action=income_statement" class="btn btn-secondary">損益表</a>
        <a href="/accounting.php?action=trial_balance" class="btn btn-secondary">試算表</a>
        <a href="/accounting.php?action=reconciliation" class="btn btn-secondary">銀行對帳</a>
    </div>
</div>

<!-- Query Form -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <form method="get" action="/accounting.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="action" value="balance_sheet">
        <div>
            <label style="font-size:0.85em">截止日期</label>
            <input type="date" name="as_of_date" value="<?= e($report['as_of_date']) ?>" class="form-control" style="width:160px" required>
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
    <strong>資產負債表</strong>
    截止日期: <?= e(format_date($report['as_of_date'])) ?>
    <?php if ($costCenterId): ?> | 成本中心篩選<?php endif; ?>
    <?php if ($report['balanced']): ?>
        <span style="color:#388e3c;margin-left:16px">平衡</span>
    <?php else: ?>
        <span style="color:#d32f2f;margin-left:16px">不平衡 (差異: <?= number_format($report['total_assets'] - $report['total_le']) ?>)</span>
    <?php endif; ?>
</div>
</div><!-- /.page-sticky-head -->

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <!-- Left: Assets -->
    <div class="card" style="overflow-x:auto">
        <table class="data-table" style="width:100%">
            <thead>
                <tr style="background:#e3f2fd">
                    <th colspan="2" style="font-size:1.1em">資產</th>
                    <th style="width:140px;text-align:right">金額</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($report['assets'])): ?>
                <tr><td colspan="3" style="text-align:center;color:#999;padding:24px">無資產紀錄</td></tr>
            <?php else: ?>
                <?php foreach ($report['assets'] as $item): ?>
                <tr>
                    <td style="width:80px"><?= e($item['code']) ?></td>
                    <td><?= e($item['name']) ?></td>
                    <td style="text-align:right"><?= number_format($item['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr style="background:#1565c0;color:#fff;font-weight:bold;font-size:1.05em">
                <td colspan="2" style="text-align:right;padding:10px">資產合計</td>
                <td style="text-align:right;padding:10px"><?= number_format($report['total_assets']) ?></td>
            </tr>
            </tbody>
        </table>
    </div>

    <!-- Right: Liabilities + Equity -->
    <div class="card" style="overflow-x:auto">
        <table class="data-table" style="width:100%">
            <!-- Liabilities -->
            <thead>
                <tr style="background:#fff3e0">
                    <th colspan="2" style="font-size:1.1em">負債</th>
                    <th style="width:140px;text-align:right">金額</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($report['liabilities'])): ?>
                <tr><td colspan="3" style="text-align:center;color:#999">無負債紀錄</td></tr>
            <?php else: ?>
                <?php foreach ($report['liabilities'] as $item): ?>
                <tr>
                    <td style="width:80px"><?= e($item['code']) ?></td>
                    <td><?= e($item['name']) ?></td>
                    <td style="text-align:right"><?= number_format($item['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr style="background:#ef6c00;color:#fff;font-weight:bold">
                <td colspan="2" style="text-align:right;padding:8px">負債小計</td>
                <td style="text-align:right;padding:8px"><?= number_format($report['total_liabilities']) ?></td>
            </tr>

            <!-- Equity -->
            <tr style="background:#e8f5e9">
                <th colspan="2" style="font-size:1.1em">權益</th>
                <th style="text-align:right">金額</th>
            </tr>
            <?php if (empty($report['equity'])): ?>
                <tr><td colspan="3" style="text-align:center;color:#999">無權益紀錄</td></tr>
            <?php else: ?>
                <?php foreach ($report['equity'] as $item): ?>
                <tr>
                    <td><?= e($item['code']) ?></td>
                    <td><?= e($item['name']) ?></td>
                    <td style="text-align:right"><?= number_format($item['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr style="background:#2e7d32;color:#fff;font-weight:bold">
                <td colspan="2" style="text-align:right;padding:8px">權益小計</td>
                <td style="text-align:right;padding:8px"><?= number_format($report['total_equity']) ?></td>
            </tr>

            <!-- Total L+E -->
            <tr style="background:#1565c0;color:#fff;font-weight:bold;font-size:1.05em">
                <td colspan="2" style="text-align:right;padding:10px">負債+權益合計</td>
                <td style="text-align:right;padding:10px"><?= number_format($report['total_le']) ?></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
