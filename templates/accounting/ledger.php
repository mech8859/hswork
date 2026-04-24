<div class="page-sticky-head">
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>總帳查詢</h1>
    <div style="display:flex;gap:8px">
        <a href="/accounting.php?action=journals" class="btn btn-secondary">傳票管理</a>
        <a href="/accounting.php?action=trial_balance" class="btn btn-secondary">試算表</a>
    </div>
</div>

<!-- Query Form -->
<?php $codeFrom = isset($_GET['code_from']) ? $_GET['code_from'] : ''; $codeTo = isset($_GET['code_to']) ? $_GET['code_to'] : ''; ?>
<div class="card" style="padding:16px;margin-bottom:16px">
    <form method="get" action="/accounting.php">
        <input type="hidden" name="action" value="ledger">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
            <div>
                <label style="font-size:0.85em">會計科目</label>
                <select name="account_id" id="ledgerAccountSel" class="form-control" style="width:250px">
                    <option value="">全部科目</option>
                    <?php foreach ($accounts as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $accountId == $a['id'] ? 'selected' : '' ?>><?= e($a['code']) ?> <?= e($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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
            <a href="/accounting.php?action=ledger" class="btn btn-outline">清除</a>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:8px;padding-top:8px;border-top:1px dashed #ddd">
            <div>
                <label style="font-size:0.85em">科目區間（從）</label>
                <select name="code_from" class="form-control" style="width:220px">
                    <option value="">全部</option>
                    <?php foreach ($accounts as $a): ?>
                    <option value="<?= e($a['code']) ?>" <?= $codeFrom === $a['code'] ? 'selected' : '' ?>><?= e($a['code']) ?> <?= e($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:0.85em">科目區間（到）</label>
                <select name="code_to" class="form-control" style="width:220px">
                    <option value="">全部</option>
                    <?php foreach ($accounts as $a): ?>
                    <option value="<?= e($a['code']) ?>" <?= $codeTo === $a['code'] ? 'selected' : '' ?>><?= e($a['code']) ?> <?= e($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="font-size:.8rem;color:#999;padding-bottom:8px">填寫科目區間時，上方單一科目可留空</div>
        </div>
    </form>
</div>

<?php if ($accountId && $selectedAccount): ?>
<!-- Account Info -->
<div class="card" style="padding:12px;margin-bottom:16px;background:#f8f9fa">
    <strong><?= e($selectedAccount['code']) ?> <?= e($selectedAccount['name']) ?></strong>
    <span style="margin-left:12px;color:#666">
        正常餘額: <?= $selectedAccount['normal_balance'] === 'debit' ? '借方' : '貸方' ?>
    </span>
    <span style="margin-left:12px;color:#666">
        期間: <?= e(format_date($startDate)) ?> ~ <?= e(format_date($endDate)) ?>
    </span>
</div>
<?php endif; ?>
<?php if (!empty($rangeAccounts)): ?>
<!-- Range Query Stats -->
<div class="card" style="padding:12px;margin-bottom:16px;background:#f8f9fa">
    <strong>科目區間: <?= e($codeFrom) ?> ~ <?= e($codeTo) ?></strong>
    <span style="margin-left:12px;color:#666">共 <?= count($rangeAccounts) ?> 個科目，<?= count($ledgerEntries) ?> 筆分錄</span>
    <span style="margin-left:12px;color:#666">期間: <?= e(format_date($startDate)) ?> ~ <?= e(format_date($endDate)) ?></span>
</div>
<?php endif; ?>
</div><!-- /.page-sticky-head -->

<?php if ($accountId && $selectedAccount): ?>
<!-- Ledger Table -->
<div class="card" style="overflow:visible">
    <table class="data-table" style="width:100%">
        <thead class="sticky-thead">
            <tr>
                <th style="width:100px">日期</th>
                <th style="width:120px">傳票號碼</th>
                <th>摘要</th>
                <th>成本中心</th>
                <th style="width:110px;text-align:right">借方</th>
                <th style="width:110px;text-align:right">貸方</th>
                <th style="width:120px;text-align:right">餘額</th>
            </tr>
        </thead>
        <tbody>
            <!-- Opening Balance -->
            <tr style="background:#f0f0f0;font-weight:bold">
                <td colspan="4">期初餘額</td>
                <td style="text-align:right"></td>
                <td style="text-align:right"></td>
                <td style="text-align:right"><?= number_format($openingBalance) ?></td>
            </tr>
            <?php
            $runningBalance = $openingBalance;
            $periodDebit = 0;
            $periodCredit = 0;
            foreach ($ledgerEntries as $le):
                $debit = (float)$le['debit_amount'];
                $credit = (float)$le['credit_amount'];
                $periodDebit += $debit;
                $periodCredit += $credit;
                if ($selectedAccount['normal_balance'] === 'debit') {
                    $runningBalance += $debit - $credit;
                } else {
                    $runningBalance += $credit - $debit;
                }
            ?>
            <tr>
                <td><?= e(format_date($le['voucher_date'])) ?></td>
                <td><a href="/accounting.php?action=journal_view&id=<?= $le['journal_entry_id'] ?>&ref=ledger"><?= e($le['voucher_number']) ?></a></td>
                <td><?= e($le['description'] ?: $le['je_description']) ?></td>
                <td style="font-size:0.9em"><?= e($le['cost_center_name']) ?></td>
                <td style="text-align:right"><?= $debit > 0 ? number_format($debit) : '' ?></td>
                <td style="text-align:right"><?= $credit > 0 ? number_format($credit) : '' ?></td>
                <td style="text-align:right;font-weight:bold;<?= $runningBalance < 0 ? 'color:red' : '' ?>"><?= number_format($runningBalance) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($ledgerEntries)): ?>
            <tr><td colspan="7" style="text-align:center;padding:20px;color:#999">此期間無異動記錄</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;background:#f8f9fa">
                <td colspan="4" style="text-align:right">本期合計</td>
                <td style="text-align:right"><?= number_format($periodDebit) ?></td>
                <td style="text-align:right"><?= number_format($periodCredit) ?></td>
                <td style="text-align:right;<?= $runningBalance < 0 ? 'color:red' : '' ?>"><?= number_format($runningBalance) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php elseif (!empty($rangeAccounts)): ?>
<!-- Range Query Table -->
<div class="card" style="overflow:visible">
    <table class="data-table" style="width:100%">
        <thead class="sticky-thead">
            <tr>
                <th style="width:100px">日期</th>
                <th style="width:120px">傳票號碼</th>
                <th style="width:100px">科目編號</th>
                <th>科目名稱</th>
                <th>摘要</th>
                <th>成本中心</th>
                <th style="width:100px;text-align:right">借方</th>
                <th style="width:100px;text-align:right">貸方</th>
                <th style="width:50px;text-align:center">借貸</th>
                <th style="width:110px;text-align:right">餘額</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $rDebit = 0; $rCredit = 0;
            foreach ($ledgerEntries as $le):
                $debit = (float)$le['debit_amount'];
                $credit = (float)$le['credit_amount'];
                $rDebit += $debit;
                $rCredit += $credit;
                $rBalance = $rDebit - $rCredit;
                $rDir = $rBalance >= 0 ? '借' : '貸';
            ?>
            <tr>
                <td><?= e(format_date($le['voucher_date'])) ?></td>
                <td><a href="/accounting.php?action=journal_view&id=<?= $le['journal_entry_id'] ?>&ref=ledger"><?= e($le['voucher_number']) ?></a></td>
                <td style="font-family:monospace"><?= e($le['_account_code'] ?? '') ?></td>
                <td><?= e($le['_account_name'] ?? '') ?></td>
                <td><?= e($le['description'] ?: ($le['je_description'] ?? '')) ?></td>
                <td style="font-size:0.9em"><?= e($le['cost_center_name'] ?? '') ?></td>
                <td style="text-align:right"><?= $debit > 0 ? number_format($debit) : '' ?></td>
                <td style="text-align:right"><?= $credit > 0 ? number_format($credit) : '' ?></td>
                <td style="text-align:center"><?= $rDir ?></td>
                <td style="text-align:right;font-weight:bold"><?= number_format(abs($rBalance)) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($ledgerEntries)): ?>
            <tr><td colspan="10" style="text-align:center;padding:20px;color:#999">此期間無異動記錄</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;background:#f8f9fa">
                <td colspan="6" style="text-align:right">合計</td>
                <td style="text-align:right"><?= number_format($rDebit) ?></td>
                <td style="text-align:right"><?= number_format($rCredit) ?></td>
                <td style="text-align:center"><?= ($rDebit - $rCredit) >= 0 ? '借' : '貸' ?></td>
                <td style="text-align:right"><?= number_format(abs($rDebit - $rCredit)) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php elseif ($accountId): ?>
<div class="card" style="padding:20px;text-align:center;color:#999">找不到該科目</div>
<?php endif; ?>
