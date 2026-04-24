<?php
$voucherTypeLabels = array('general' => '一般傳票', 'receipt' => '收款傳票', 'payment' => '付款傳票', 'transfer' => '轉帳傳票');
// 建立各 tab 的傳票連結 helper
$jrRefBase = 'date_from=' . urlencode($jrDateFrom) . '&date_to=' . urlencode($jrDateTo) . ($jrCostCenterId ? '&cost_center_id=' . $jrCostCenterId : '') . ($jrAccountFrom !== '' ? '&account_from=' . urlencode($jrAccountFrom) : '') . ($jrAccountTo !== '' ? '&account_to=' . urlencode($jrAccountTo) : '');
function jrVoucherLink($id, $number, $tab, $refBase) {
    return '<a href="/accounting.php?action=journal_view&id=' . (int)$id . '&ref=journal_reports&ref_tab=' . $tab . '&ref_params=' . urlencode($refBase) . '">' . e($number) . '</a>';
}
?>
<div class="page-sticky-head">
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>傳票報表</h1>
    <div style="display:flex;gap:8px">
        <a href="/accounting.php?action=journals" class="btn btn-secondary">傳票管理</a>
        <a href="/accounting.php?action=ledger" class="btn btn-secondary">總帳查詢</a>
    </div>
</div>

<!-- 篩選 -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <form method="get" action="/accounting.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="action" value="journal_reports">
        <input type="hidden" name="tab" id="jrHiddenTab" value="<?= e($jrTab) ?>">
        <div>
            <label style="font-size:.85em">起始日期</label>
            <input type="date" name="date_from" value="<?= e($jrDateFrom) ?>" class="form-control" style="width:160px">
        </div>
        <div>
            <label style="font-size:.85em">結束日期</label>
            <input type="date" name="date_to" value="<?= e($jrDateTo) ?>" class="form-control" style="width:160px">
        </div>
        <div>
            <label style="font-size:.85em">成本中心</label>
            <select name="cost_center_id" class="form-control" style="width:130px">
                <option value="">全部</option>
                <?php foreach ($costCenters as $cc): ?>
                <option value="<?= $cc['id'] ?>" <?= $jrCostCenterId == $cc['id'] ? 'selected' : '' ?>><?= e($cc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:.85em">科目起（編號）</label>
            <input type="text" name="account_from" value="<?= e($jrAccountFrom) ?>" class="form-control" style="width:110px" placeholder="例 1111" list="jrAccountList">
        </div>
        <div>
            <label style="font-size:.85em">科目迄</label>
            <input type="text" name="account_to" value="<?= e($jrAccountTo) ?>" class="form-control" style="width:110px" placeholder="例 1119" list="jrAccountList">
        </div>
        <datalist id="jrAccountList">
            <?php
            $accList = Database::getInstance()->query("SELECT code, name FROM chart_of_accounts WHERE is_active = 1 ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($accList as $ac): ?>
            <option value="<?= e($ac['code']) ?>"><?= e($ac['code']) ?> <?= e($ac['name']) ?></option>
            <?php endforeach; ?>
        </datalist>
        <button type="submit" class="btn btn-primary">查詢</button>
        <a href="/accounting.php?action=journal_reports" class="btn btn-outline">清除</a>
    </form>
</div>

<!-- Tab -->
<div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid var(--gray-200);overflow-x:auto">
    <?php
    $tabs = array(
        'daily_voucher' => '傳票日報表',
        'journal' => '日記帳',
        'daily_summary' => '日計表',
        'cash_book' => '現金簿',
        'general_ledger' => '總分類帳',
        'sub_ledger' => '明細分類帳',
    );
    foreach ($tabs as $tk => $tl): ?>
    <button type="button" class="jr-tab <?= $jrTab === $tk ? 'active' : '' ?>" onclick="jrSwitch('<?= $tk ?>')"><?= $tl ?></button>
    <?php endforeach; ?>
</div>
</div><!-- /.page-sticky-head -->

<!-- ========== 1. 傳票日報表 ========== -->
<div id="jr-daily_voucher" class="jr-panel" style="<?= $jrTab !== 'daily_voucher' ? 'display:none' : '' ?>">
<div class="card" style="overflow:visible">
    <table class="data-table" style="width:100%;font-size:.85rem">
        <thead class="sticky-thead"><tr style="background:#f5f5f5">
            <th>日期</th><th>傳票號碼</th><th>成本中心</th><th style="width:80px">科目編號</th><th>科目名稱</th><th>摘要</th>
            <th style="text-align:right">借方金額</th><th style="text-align:right">貸方金額</th><th>建立者</th>
        </tr></thead>
        <tbody>
        <?php
        $dvPrevDate = ''; $dvDayDebit = 0; $dvDayCredit = 0;
        $dvTotalDebit = 0; $dvTotalCredit = 0;
        $dvPrevVoucher = '';
        foreach ($jrLineList as $i => $l):
            $curDate = $l['voucher_date'];
            $d = (float)$l['debit_amount']; $c = (float)$l['credit_amount'];
            // 日期變換時輸出日小計
            if ($dvPrevDate && $dvPrevDate !== $curDate) {
                echo '<tr style="background:#e3f2fd;font-weight:600"><td colspan="6" style="text-align:right">' . e($dvPrevDate) . ' 小計</td>';
                echo '<td style="text-align:right">' . number_format($dvDayDebit) . '</td><td style="text-align:right">' . number_format($dvDayCredit) . '</td><td></td></tr>';
                $dvDayDebit = 0; $dvDayCredit = 0;
            }
            $dvDayDebit += $d; $dvDayCredit += $c;
            $dvTotalDebit += $d; $dvTotalCredit += $c;
            $dvPrevDate = $curDate;
            $showHeader = ($dvPrevVoucher !== $l['voucher_number']);
        ?>
        <tr<?= $showHeader && $i > 0 ? ' style="border-top:2px solid #ddd"' : '' ?>>
            <td><?= $showHeader ? e($l['voucher_date']) : '' ?></td>
            <td><?= $showHeader ? jrVoucherLink($l['journal_entry_id'], $l['voucher_number'], 'daily_voucher', $jrRefBase) : '' ?></td>
            <td><?= e($l['cost_center_name'] ?? '') ?></td>
            <td style="font-family:monospace"><?= e($l['account_code']) ?></td>
            <td><?= e($l['account_name']) ?></td>
            <td style="color:#666"><?= e($l['description'] ?: $l['je_description']) ?></td>
            <td style="text-align:right"><?= $d > 0 ? number_format($d) : '' ?></td>
            <td style="text-align:right"><?= $c > 0 ? number_format($c) : '' ?></td>
            <td><?= $showHeader ? e($l['line_created_by'] ?? '') : '' ?></td>
        </tr>
        <?php $dvPrevVoucher = $l['voucher_number']; endforeach;
        if ($dvPrevDate) {
            echo '<tr style="background:#e3f2fd;font-weight:600"><td colspan="6" style="text-align:right">' . e($dvPrevDate) . ' 小計</td>';
            echo '<td style="text-align:right">' . number_format($dvDayDebit) . '</td><td style="text-align:right">' . number_format($dvDayCredit) . '</td><td></td></tr>';
        }
        if (empty($jrLineList)) echo '<tr><td colspan="9" style="text-align:center;padding:20px;color:#999">無資料</td></tr>';
        ?>
        </tbody>
        <tfoot><tr style="font-weight:bold;background:#f0f0f0">
            <td colspan="6" style="text-align:right">合計</td>
            <td style="text-align:right"><?= number_format($dvTotalDebit) ?></td>
            <td style="text-align:right"><?= number_format($dvTotalCredit) ?></td>
            <td></td>
        </tr></tfoot>
    </table>
</div>
</div>

<!-- ========== 2. 日記帳 ========== -->
<div id="jr-journal" class="jr-panel" style="<?= $jrTab !== 'journal' ? 'display:none' : '' ?>">
<div class="card" style="overflow:visible">
    <table class="data-table" style="width:100%;font-size:.85rem">
        <thead class="sticky-thead"><tr style="background:#f5f5f5">
            <th>日期</th><th>傳票號碼</th><th>成本中心</th><th style="width:80px">科目編號</th><th>科目名稱</th><th>摘要</th>
            <th style="text-align:right">借方金額</th><th style="text-align:right">貸方金額</th><th>建立者</th>
        </tr></thead>
        <tbody>
        <?php
        $jPrevVoucher = ''; $jTotalD = 0; $jTotalC = 0;
        foreach ($jrLineList as $i => $l):
            $d = (float)$l['debit_amount']; $c = (float)$l['credit_amount'];
            $jTotalD += $d; $jTotalC += $c;
            $showHeader = ($jPrevVoucher !== $l['voucher_number']);
        ?>
        <tr<?= $showHeader && $i > 0 ? ' style="border-top:2px solid #ddd"' : '' ?>>
            <td><?= $showHeader ? e($l['voucher_date']) : '' ?></td>
            <td><?= $showHeader ? jrVoucherLink($l['journal_entry_id'], $l['voucher_number'], 'journal', $jrRefBase) : '' ?></td>
            <td><?= e($l['cost_center_name'] ?? '') ?></td>
            <td style="font-family:monospace"><?= e($l['account_code']) ?></td>
            <td><?= e($l['account_name']) ?></td>
            <td style="color:#666"><?= e($l['description'] ?: $l['je_description']) ?></td>
            <td style="text-align:right"><?= $d > 0 ? number_format($d) : '' ?></td>
            <td style="text-align:right"><?= $c > 0 ? number_format($c) : '' ?></td>
            <td><?= $showHeader ? e($l['line_created_by'] ?? '') : '' ?></td>
        </tr>
        <?php $jPrevVoucher = $l['voucher_number']; endforeach;
        if (empty($jrLineList)) echo '<tr><td colspan="9" style="text-align:center;padding:20px;color:#999">無資料</td></tr>';
        ?>
        </tbody>
        <tfoot><tr style="font-weight:bold;background:#f0f0f0">
            <td colspan="6" style="text-align:right">合計</td>
            <td style="text-align:right"><?= number_format($jTotalD) ?></td>
            <td style="text-align:right"><?= number_format($jTotalC) ?></td>
            <td></td>
        </tr></tfoot>
    </table>
</div>
</div>

<!-- ========== 3. 日計表 ========== -->
<div id="jr-daily_summary" class="jr-panel" style="<?= $jrTab !== 'daily_summary' ? 'display:none' : '' ?>">
<div class="card" style="overflow:visible">
    <div style="padding:10px 16px;background:#f8f9fa;border-bottom:1px solid #eee;font-size:.85rem">
        <strong>期間：<?= e($jrDateFrom) ?> ~ <?= e($jrDateTo) ?></strong>
    </div>
    <table class="data-table" style="width:100%;font-size:.82rem">
        <thead class="sticky-thead">
            <tr style="background:#f5f5f5">
                <th rowspan="2">成本中心</th>
                <th rowspan="2" style="width:80px">科目編號</th>
                <th rowspan="2">科目名稱</th>
                <th colspan="2" style="text-align:center;border-bottom:1px solid #ddd">期初餘額</th>
                <th colspan="2" style="text-align:center;border-bottom:1px solid #ddd">本期借方</th>
                <th colspan="2" style="text-align:center;border-bottom:1px solid #ddd">本期貸方</th>
                <th colspan="2" style="text-align:center;border-bottom:1px solid #ddd">期末餘額</th>
            </tr>
            <tr style="background:#f5f5f5">
                <th style="text-align:right">借方</th><th style="text-align:right">貸方</th>
                <th style="text-align:right">金額</th><th style="text-align:center">筆數</th>
                <th style="text-align:right">金額</th><th style="text-align:center">筆數</th>
                <th style="text-align:right">借方</th><th style="text-align:right">貸方</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $dsTotalD = 0; $dsTotalC = 0; $dsTotalDCnt = 0; $dsTotalCCnt = 0;
        if (!empty($jrDailyByAccount)):
        foreach ($jrDailyByAccount as $acctCode => $a):
            $isDebitNormal = ($a['normal_balance'] === 'debit');
            $periodNet = $a['debit'] - $a['credit'];
            // 期初=0（暫不計算跨期），期末=本期淨額
            $endDebit = 0; $endCredit = 0;
            if ($isDebitNormal) {
                if ($periodNet >= 0) $endDebit = $periodNet; else $endCredit = abs($periodNet);
            } else {
                if ($periodNet <= 0) $endCredit = abs($periodNet); else $endDebit = $periodNet;
            }
            $dsTotalD += $a['debit']; $dsTotalC += $a['credit'];
            $dsTotalDCnt += $a['debit_count']; $dsTotalCCnt += $a['credit_count'];
        ?>
        <tr>
            <td></td>
            <td style="font-family:monospace"><?= e($a['code']) ?></td>
            <td><?= e($a['name']) ?></td>
            <td style="text-align:right;color:#999">0</td>
            <td style="text-align:right;color:#999">0</td>
            <td style="text-align:right"><?= $a['debit'] > 0 ? number_format($a['debit']) : '' ?></td>
            <td style="text-align:center"><?= $a['debit_count'] > 0 ? $a['debit_count'] : '' ?></td>
            <td style="text-align:right"><?= $a['credit'] > 0 ? number_format($a['credit']) : '' ?></td>
            <td style="text-align:center"><?= $a['credit_count'] > 0 ? $a['credit_count'] : '' ?></td>
            <td style="text-align:right;font-weight:600"><?= $endDebit > 0 ? number_format($endDebit) : '' ?></td>
            <td style="text-align:right;font-weight:600"><?= $endCredit > 0 ? number_format($endCredit) : '' ?></td>
        </tr>
        <?php endforeach;
        else: echo '<tr><td colspan="11" style="text-align:center;padding:20px;color:#999">無資料</td></tr>';
        endif; ?>
        </tbody>
        <tfoot><tr style="font-weight:bold;background:#f0f0f0">
            <td colspan="3" style="text-align:right">合計</td>
            <td style="text-align:right">0</td><td style="text-align:right">0</td>
            <td style="text-align:right"><?= number_format($dsTotalD) ?></td>
            <td style="text-align:center"><?= $dsTotalDCnt ?></td>
            <td style="text-align:right"><?= number_format($dsTotalC) ?></td>
            <td style="text-align:center"><?= $dsTotalCCnt ?></td>
            <td style="text-align:right"><?= number_format($dsTotalD) ?></td>
            <td style="text-align:right"><?= number_format($dsTotalC) ?></td>
        </tr></tfoot>
    </table>
</div>
</div>

<!-- ========== 4. 現金簿 ========== -->
<div id="jr-cash_book" class="jr-panel" style="<?= $jrTab !== 'cash_book' ? 'display:none' : '' ?>">
<div class="card" style="overflow:visible">
    <table class="data-table" style="width:100%;font-size:.85rem">
        <thead class="sticky-thead"><tr style="background:#f5f5f5">
            <th>日期</th><th>傳票號碼</th><th>成本中心</th><th style="width:80px">科目編號</th><th>科目名稱</th><th>摘要</th>
            <th style="text-align:right">收入(借方)</th><th style="text-align:right">支出(貸方)</th><th style="text-align:right">餘額</th><th>建立者</th>
        </tr></thead>
        <tbody>
        <?php
        $cashBal = 0; $cashTotalD = 0; $cashTotalC = 0;
        foreach ($jrCashLines as $l):
            $d = (float)$l['debit_amount']; $c = (float)$l['credit_amount'];
            $cashBal += $d - $c;
            $cashTotalD += $d; $cashTotalC += $c;
        ?>
        <tr>
            <td><?= e($l['voucher_date']) ?></td>
            <td><?= jrVoucherLink($l['journal_entry_id'], $l['voucher_number'], 'cash_book', $jrRefBase) ?></td>
            <td><?= e($l['cost_center_name'] ?? '') ?></td>
            <td style="font-family:monospace"><?= e($l['account_code']) ?></td>
            <td><?= e($l['account_name']) ?></td>
            <td style="color:#666"><?= e($l['description'] ?: $l['je_description']) ?></td>
            <td style="text-align:right"><?= $d > 0 ? number_format($d) : '' ?></td>
            <td style="text-align:right"><?= $c > 0 ? number_format($c) : '' ?></td>
            <td style="text-align:right;font-weight:bold;<?= $cashBal < 0 ? 'color:red' : '' ?>"><?= number_format($cashBal) ?></td>
            <td><?= e($l['line_created_by'] ?? '') ?></td>
        </tr>
        <?php endforeach;
        if (empty($jrCashLines)) echo '<tr><td colspan="10" style="text-align:center;padding:20px;color:#999">此期間無現金異動</td></tr>';
        ?>
        </tbody>
        <tfoot><tr style="font-weight:bold;background:#f0f0f0">
            <td colspan="6" style="text-align:right">合計</td>
            <td style="text-align:right"><?= number_format($cashTotalD) ?></td>
            <td style="text-align:right"><?= number_format($cashTotalC) ?></td>
            <td style="text-align:right;<?= $cashBal < 0 ? 'color:red' : '' ?>"><?= number_format($cashBal) ?></td>
            <td></td>
        </tr></tfoot>
    </table>
</div>
</div>

<!-- ========== 5. 總分類帳 ========== -->
<div id="jr-general_ledger" class="jr-panel" style="<?= $jrTab !== 'general_ledger' ? 'display:none' : '' ?>">
<?php if (empty($jrGeneralLedger)): ?>
<div class="card" style="padding:20px;text-align:center;color:#999">無資料</div>
<?php else: ?>
<?php foreach ($jrGeneralLedger as $acctCode => $acctData):
    $isDebitNormal = ($acctData['normal_balance'] === 'debit');
?>
<div class="card" style="margin-bottom:16px;overflow:visible">
    <div style="padding:10px 16px;background:#f8f9fa;border-bottom:1px solid #eee">
        <strong style="font-family:monospace"><?= e($acctData['code']) ?></strong> <?= e($acctData['name']) ?>
        <span style="margin-left:12px;color:#666;font-size:.85rem">正常餘額: <?= $isDebitNormal ? '借方' : '貸方' ?></span>
    </div>
    <table class="data-table" style="width:100%;font-size:.82rem">
        <thead class="sticky-thead"><tr style="background:#fafafa">
            <th style="width:90px">日期</th><th style="width:130px">傳票號碼</th><th>成本中心</th><th>摘要</th>
            <th style="width:100px;text-align:right">借方</th><th style="width:100px;text-align:right">貸方</th>
            <th style="width:50px;text-align:center">借貸</th><th style="width:100px;text-align:right">餘額</th>
        </tr></thead>
        <tbody>
        <?php
        // 期初餘額（基於 normal_balance 方向的正值 / 負值）
        $glOpen = isset($jrGlOpeningBalance[$acctCode]) ? (float)$jrGlOpeningBalance[$acctCode]['opening'] : 0;
        $glBal = $glOpen; $glD = 0; $glC = 0;
        ?>
        <?php if (abs($glOpen) > 0.01): ?>
        <tr style="background:#f5f9ff;color:#555">
            <td><?= e($jrDateFrom) ?></td>
            <td></td>
            <td></td>
            <td style="font-style:italic">期初餘額</td>
            <td style="text-align:right"></td>
            <td style="text-align:right"></td>
            <td style="text-align:center"><?= $glBal >= 0 ? ($isDebitNormal ? '借' : '貸') : ($isDebitNormal ? '貸' : '借') ?></td>
            <td style="text-align:right;font-weight:bold"><?= number_format(abs($glBal)) ?></td>
        </tr>
        <?php endif; ?>
        <?php
        foreach ($acctData['lines'] as $l):
            $d = (float)$l['debit_amount']; $c = (float)$l['credit_amount'];
            $glD += $d; $glC += $c;
            if ($isDebitNormal) { $glBal += $d - $c; } else { $glBal += $c - $d; }
        ?>
        <tr>
            <td><?= e($l['voucher_date']) ?></td>
            <td><?= jrVoucherLink($l['journal_entry_id'], $l['voucher_number'], 'general_ledger', $jrRefBase) ?></td>
            <td><?= e($l['cost_center_name'] ?? '') ?></td>
            <td style="color:#666"><?= e($l['description'] ?: $l['je_description']) ?></td>
            <td style="text-align:right"><?= $d > 0 ? number_format($d) : '' ?></td>
            <td style="text-align:right"><?= $c > 0 ? number_format($c) : '' ?></td>
            <td style="text-align:center"><?= $glBal >= 0 ? ($isDebitNormal ? '借' : '貸') : ($isDebitNormal ? '貸' : '借') ?></td>
            <td style="text-align:right;font-weight:bold"><?= number_format(abs($glBal)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <?php if (abs($glOpen) > 0.01): ?>
        <tr style="color:#666;font-size:.78rem">
            <td colspan="4" style="text-align:right">期初餘額</td>
            <td colspan="3" style="text-align:right"></td>
            <td style="text-align:right"><?= ($glOpen >= 0 ? '' : '-') . number_format(abs($glOpen)) ?></td>
        </tr>
        <?php endif; ?>
        <tr style="font-weight:bold;background:#f8f9fa">
            <td colspan="4" style="text-align:right">本期合計</td>
            <td style="text-align:right"><?= number_format($glD) ?></td>
            <td style="text-align:right"><?= number_format($glC) ?></td>
            <td style="text-align:center"><?= $glBal >= 0 ? ($isDebitNormal ? '借' : '貸') : ($isDebitNormal ? '貸' : '借') ?></td>
            <td style="text-align:right"><?= number_format(abs($glBal)) ?></td>
        </tr></tfoot>
    </table>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<!-- ========== 6. 明細分類帳 ========== -->
<div id="jr-sub_ledger" class="jr-panel" style="<?= $jrTab !== 'sub_ledger' ? 'display:none' : '' ?>">
<?php if (empty($jrByAccount)): ?>
<div class="card" style="padding:20px;text-align:center;color:#999">無資料</div>
<?php else: ?>
<?php foreach ($jrByAccount as $acctCode => $acctData):
    $isDebitNormal = ($acctData['normal_balance'] === 'debit');
?>
<div class="card" style="margin-bottom:16px;overflow:visible">
    <div style="padding:10px 16px;background:#f8f9fa;border-bottom:1px solid #eee">
        <strong style="font-family:monospace"><?= e($acctData['code']) ?></strong> <?= e($acctData['name']) ?>
        <span style="margin-left:12px;color:#666;font-size:.85rem">正常餘額: <?= $isDebitNormal ? '借方' : '貸方' ?></span>
    </div>
    <table class="data-table" style="width:100%;font-size:.82rem">
        <thead class="sticky-thead"><tr style="background:#fafafa">
            <th style="width:90px">日期</th><th style="width:130px">傳票號碼</th><th>成本中心</th>
            <th style="width:80px">科目編號</th><th>科目名稱</th><th>摘要</th>
            <th style="width:90px;text-align:right">借方金額</th><th style="width:90px;text-align:right">貸方金額</th>
            <th style="width:50px;text-align:center">借貸</th><th style="width:100px;text-align:right">餘額</th><th>建立者</th>
        </tr></thead>
        <tbody>
        <?php
        // 期初餘額（明細分類帳以科目完整代碼查）
        $slOpen = isset($jrOpeningBalance[$acctCode]) ? (float)$jrOpeningBalance[$acctCode]['opening'] : 0;
        $slBal = $slOpen; $slD = 0; $slC = 0;
        ?>
        <?php if (abs($slOpen) > 0.01): ?>
        <tr style="background:#f5f9ff;color:#555">
            <td><?= e($jrDateFrom) ?></td>
            <td></td>
            <td></td>
            <td style="font-family:monospace"><?= e($acctData['code']) ?></td>
            <td><?= e($acctData['name']) ?></td>
            <td style="font-style:italic">期初餘額</td>
            <td style="text-align:right"></td>
            <td style="text-align:right"></td>
            <td style="text-align:center"><?= $slBal >= 0 ? ($isDebitNormal ? '借' : '貸') : ($isDebitNormal ? '貸' : '借') ?></td>
            <td style="text-align:right;font-weight:bold"><?= number_format(abs($slBal)) ?></td>
            <td></td>
        </tr>
        <?php endif; ?>
        <?php
        foreach ($acctData['lines'] as $l):
            $d = (float)$l['debit_amount']; $c = (float)$l['credit_amount'];
            $slD += $d; $slC += $c;
            if ($isDebitNormal) { $slBal += $d - $c; } else { $slBal += $c - $d; }
        ?>
        <tr>
            <td><?= e($l['voucher_date']) ?></td>
            <td><?= jrVoucherLink($l['journal_entry_id'], $l['voucher_number'], 'sub_ledger', $jrRefBase) ?></td>
            <td><?= e($l['cost_center_name'] ?? '') ?></td>
            <td style="font-family:monospace"><?= e($l['account_code']) ?></td>
            <td><?= e($l['account_name']) ?></td>
            <td style="color:#666"><?= e($l['description'] ?: $l['je_description']) ?></td>
            <td style="text-align:right"><?= $d > 0 ? number_format($d) : '' ?></td>
            <td style="text-align:right"><?= $c > 0 ? number_format($c) : '' ?></td>
            <td style="text-align:center"><?= $slBal >= 0 ? ($isDebitNormal ? '借' : '貸') : ($isDebitNormal ? '貸' : '借') ?></td>
            <td style="text-align:right;font-weight:bold"><?= number_format(abs($slBal)) ?></td>
            <td><?= e($l['line_created_by'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <?php if (abs($slOpen) > 0.01): ?>
        <tr style="color:#666;font-size:.78rem">
            <td colspan="6" style="text-align:right">期初餘額</td>
            <td colspan="3" style="text-align:right"></td>
            <td style="text-align:right"><?= ($slOpen >= 0 ? '' : '-') . number_format(abs($slOpen)) ?></td>
            <td></td>
        </tr>
        <?php endif; ?>
        <tr style="font-weight:600;background:#f8f9fa">
            <td colspan="6" style="text-align:right">本期合計</td>
            <td style="text-align:right"><?= number_format($slD) ?></td>
            <td style="text-align:right"><?= number_format($slC) ?></td>
            <td style="text-align:center"><?= $slBal >= 0 ? ($isDebitNormal ? '借' : '貸') : ($isDebitNormal ? '貸' : '借') ?></td>
            <td style="text-align:right"><?= number_format(abs($slBal)) ?></td>
            <td></td>
        </tr></tfoot>
    </table>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<script>
function jrSwitch(tab) {
    var panels = document.querySelectorAll('.jr-panel');
    var tabs = document.querySelectorAll('.jr-tab');
    for (var i = 0; i < panels.length; i++) panels[i].style.display = 'none';
    for (var i = 0; i < tabs.length; i++) tabs[i].classList.remove('active');
    document.getElementById('jr-' + tab).style.display = '';
    document.getElementById('jrHiddenTab').value = tab;
    event.target.classList.add('active');
}
</script>
<style>
.jr-tab { padding:8px 16px; background:none; border:none; cursor:pointer; font-size:.9rem; border-bottom:3px solid transparent; white-space:nowrap; }
.jr-tab.active { border-bottom-color:var(--primary); color:var(--primary); font-weight:600; }
.jr-tab:hover { background:#f5f5f5; }
</style>
