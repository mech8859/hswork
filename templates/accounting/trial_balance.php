<div class="page-sticky-head">
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>試算表</h1>
    <div style="display:flex;gap:8px">
        <a href="/accounting.php?action=journals" class="btn btn-secondary">傳票管理</a>
        <a href="/accounting.php?action=ledger" class="btn btn-secondary">總帳查詢</a>
    </div>
</div>

<!-- Query Form -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <form method="get" action="/accounting.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="action" value="trial_balance">
        <div>
            <label style="font-size:0.85em">截止日期</label>
            <input type="date" name="as_of_date" value="<?= e($asOfDate) ?>" class="form-control" style="width:160px" required>
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
    </form>
</div>

<div class="card" style="padding:12px;margin-bottom:16px;background:#f8f9fa">
    <strong>試算表</strong> 截止日期: <?= e(format_date($asOfDate)) ?>
    <?php if ($costCenterId): ?>
    | 成本中心篩選
    <?php endif; ?>
</div>
</div><!-- /.page-sticky-head -->

<!-- Trial Balance Table -->
<div class="card" style="overflow:visible">
    <table class="data-table" style="width:100%">
        <thead class="sticky-thead">
            <tr>
                <th style="width:100px">科目編號</th>
                <th>科目名稱</th>
                <th style="width:80px">類型</th>
                <th style="width:130px;text-align:right">借方合計</th>
                <th style="width:130px;text-align:right">貸方合計</th>
                <th style="width:130px;text-align:right">借方餘額</th>
                <th style="width:130px;text-align:right">貸方餘額</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $typeLabels = AccountingModel::accountTypeOptions();
            $grandDebit = 0;
            $grandCredit = 0;
            $balDebit = 0;
            $balCredit = 0;
            $currentType = '';
            foreach ($trialBalance as $tb):
                $d = (float)$tb['total_debit'];
                $c = (float)$tb['total_credit'];
                $grandDebit += $d;
                $grandCredit += $c;

                // Calculate balance
                $debitBal = 0;
                $creditBal = 0;
                if ($tb['normal_balance'] === 'debit') {
                    $bal = $d - $c;
                    if ($bal >= 0) { $debitBal = $bal; } else { $creditBal = abs($bal); }
                } else {
                    $bal = $c - $d;
                    if ($bal >= 0) { $creditBal = $bal; } else { $debitBal = abs($bal); }
                }
                $balDebit += $debitBal;
                $balCredit += $creditBal;

                // Section header
                if ($tb['account_type'] !== $currentType):
                    $currentType = $tb['account_type'];
                    $_typeBg = array(
                        'asset' => '#cfe2ff', 'liability' => '#f8d7da', 'equity' => '#e2d4f0',
                        'revenue' => '#d1ecf1', 'cost' => '#ffd6cc', 'expense' => '#ffe5b4',
                    );
                    $_tbg = isset($_typeBg[$currentType]) ? $_typeBg[$currentType] : '#dee2e6';
            ?>
            <tr style="background:<?= $_tbg ?>;font-weight:bold">
                <td colspan="7"><?= isset($typeLabels[$currentType]) ? e($typeLabels[$currentType]) : e($currentType) ?></td>
            </tr>
            <?php endif; ?>
            <?php
            $_tbIsControl = (strlen($tb['code']) >= 3 && substr($tb['code'], -3) === '000');
            $_tbTab = $_tbIsControl ? 'general_ledger' : 'sub_ledger';
            $_tbDateFrom = (int)substr($asOfDate, 0, 4) . '-01-01';
            $_tbUrl = '/accounting.php?action=journal_reports&tab=' . $_tbTab
                    . '&date_from=' . urlencode($_tbDateFrom)
                    . '&date_to=' . urlencode($asOfDate)
                    . '&account_from=' . urlencode($tb['code'])
                    . '&account_to=' . urlencode($tb['code']);
            if ($costCenterId) $_tbUrl .= '&cost_center_id=' . (int)$costCenterId;
            ?>
            <tr>
                <td><a href="<?= e($_tbUrl) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($tb['code']) ?></a></td>
                <td><?= e($tb['name']) ?></td>
                <td style="font-size:0.85em;color:#666"><?= isset($typeLabels[$tb['account_type']]) ? e($typeLabels[$tb['account_type']]) : '' ?></td>
                <td style="text-align:right"><?= $d > 0 ? number_format($d, 2) : '' ?></td>
                <td style="text-align:right"><?= $c > 0 ? number_format($c, 2) : '' ?></td>
                <td style="text-align:right"><?= $debitBal > 0 ? number_format($debitBal, 2) : '' ?></td>
                <td style="text-align:right"><?= $creditBal > 0 ? number_format($creditBal, 2) : '' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($trialBalance)): ?>
            <tr><td colspan="7" style="text-align:center;padding:20px;color:#999">尚無過帳資料</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;background:#f8f9fa;font-size:1.05em">
                <td colspan="3" style="text-align:right">合計</td>
                <td style="text-align:right"><?= number_format($grandDebit, 2) ?></td>
                <td style="text-align:right"><?= number_format($grandCredit, 2) ?></td>
                <td style="text-align:right"><?= number_format($balDebit, 2) ?></td>
                <td style="text-align:right"><?= number_format($balCredit, 2) ?></td>
            </tr>
            <tr>
                <td colspan="7" style="text-align:center;padding:8px">
                    <?php if (abs($grandDebit - $grandCredit) < 0.01): ?>
                    <span style="color:green;font-weight:bold">借貸平衡</span>
                    <?php else: ?>
                    <span style="color:red;font-weight:bold">借貸不平衡！差額: <?= number_format(abs($grandDebit - $grandCredit), 2) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
