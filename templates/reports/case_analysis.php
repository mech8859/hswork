<?php
$months = isset($analysis['months']) ? $analysis['months'] : array();
$nm = count($months);
$closedStatuses = array('已成交', '跨月成交', '現簽', '電話報價成交');
$progressLabels = CaseModel::progressOptions();

// Helper: pivot 表行合計
function pivotRowTotal($data, $months) {
    $t = 0;
    foreach ($months as $m) { $t += isset($data[$m]) ? $data[$m] : 0; }
    return $t;
}
?>

<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>📊 案件綜合分析</h2>
    <form method="GET" class="d-flex gap-1 align-center">
        <input type="hidden" name="action" value="case_analysis">
        <select name="year" class="form-control" style="width:auto" onchange="this.form.submit()">
            <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
            <option value="<?= $y ?>" <?= $analysis['year'] == $y ? 'selected' : '' ?>><?= ($y - 1911) ?>年 (<?= $y ?>)</option>
            <?php endfor; ?>
        </select>
        <a href="/reports.php" class="btn btn-outline btn-sm">返回報表</a>
    </form>
</div>

<?php if (empty($months)): ?>
<div class="card"><p class="text-muted text-center mt-2">該年度無案件資料</p></div>
<?php else: ?>

<div class="analysis-summary mb-1">
    <span>資料來源：案件管理</span>
    <span>總案件：<b><?= number_format($analysis['total_cases']) ?></b> 筆</span>
    <span>分析月份：<?= $months[0] ?> ～ <?= $months[$nm-1] ?></span>
</div>

<!-- 一、新案 各月數量統計 -->
<?php
$caseTypeLabels = CaseModel::caseTypeOptions();
$caseTypeMonthly = isset($analysis['case_type_monthly']) ? $analysis['case_type_monthly'] : array();
$caseTypeClosedMonthly = isset($analysis['case_type_closed_monthly']) ? $analysis['case_type_closed_monthly'] : array();
$otherTypes = array('addition','old_repair','new_repair','maintenance');
$niData = isset($caseTypeMonthly['new_install']) ? $caseTypeMonthly['new_install'] : array();
$niClosed = isset($caseTypeClosedMonthly['new_install']) ? $caseTypeClosedMonthly['new_install'] : array();
$newEntry = 0; $newClosed = 0;
?>
<div class="card">
    <div class="card-header analysis-header">一、新案 各月數量統計</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>統計項目</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <tr>
                    <td>進件數</td>
                    <?php foreach ($months as $m): $v = isset($niData[$m]) ? $niData[$m] : 0; $newEntry += $v; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($newEntry) ?></td>
                </tr>
                <tr>
                    <td>成交數</td>
                    <?php foreach ($months as $m): $v = isset($niClosed[$m]) ? $niClosed[$m] : 0; $newClosed += $v; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($newClosed) ?></td>
                </tr>
                <tr class="row-highlight">
                    <td>成交率</td>
                    <?php foreach ($months as $m):
                        $e = isset($niData[$m]) ? $niData[$m] : 0;
                        $c = isset($niClosed[$m]) ? $niClosed[$m] : 0;
                    ?>
                    <td><?= $e > 0 ? round($c / $e * 100, 1) . '%' : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $newEntry > 0 ? round($newClosed / $newEntry * 100, 1) . '%' : '' ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 一之二、未完工與完工未收款 未收餘額月份統計 -->
<?php
$wipDb = Database::getInstance();
$wipYear = $analysis['year'];
$wipBranches = implode(',', array_map('intval', $branchIds));

// 未完工：依成交月份統計未收餘額（用 balance_amount，不限年份）
$wipMonthly = array();
$unpaidMonthly = array();
foreach ($months as $m) {
    $wipMonthly[$m] = array('cnt' => 0, 'balance' => 0);
    $unpaidMonthly[$m] = array('cnt' => 0, 'balance' => 0);
}
// 去年以前的歸入「以前」
$wipBefore = array('cnt' => 0, 'balance' => 0);
$unpaidBefore = array('cnt' => 0, 'balance' => 0);

$wipMStmt = $wipDb->query("
    SELECT DATE_FORMAT(deal_date, '%Y-%m') as ym, COUNT(*) as cnt,
           COALESCE(SUM(balance_amount), 0) as balance
    FROM cases WHERE status = 'incomplete' AND branch_id IN ({$wipBranches})
    AND deal_date IS NOT NULL
    GROUP BY ym
");
foreach ($wipMStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($wipMonthly[$r['ym']])) {
        $wipMonthly[$r['ym']] = array('cnt' => (int)$r['cnt'], 'balance' => (int)$r['balance']);
    } elseif ($r['ym'] < $months[0]) {
        $wipBefore['cnt'] += (int)$r['cnt'];
        $wipBefore['balance'] += (int)$r['balance'];
    }
}

$unpaidMStmt = $wipDb->query("
    SELECT DATE_FORMAT(deal_date, '%Y-%m') as ym, COUNT(*) as cnt,
           COALESCE(SUM(balance_amount), 0) as balance
    FROM cases WHERE status = 'unpaid' AND branch_id IN ({$wipBranches})
    AND deal_date IS NOT NULL
    GROUP BY ym
");
foreach ($unpaidMStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($unpaidMonthly[$r['ym']])) {
        $unpaidMonthly[$r['ym']] = array('cnt' => (int)$r['cnt'], 'balance' => (int)$r['balance']);
    } elseif ($r['ym'] < $months[0]) {
        $unpaidBefore['cnt'] += (int)$r['cnt'];
        $unpaidBefore['balance'] += (int)$r['balance'];
    }
}

// 無成交日期的
$wipNoDate = $wipDb->query("SELECT COUNT(*) as cnt, COALESCE(SUM(balance_amount), 0) as balance FROM cases WHERE status = 'incomplete' AND branch_id IN ({$wipBranches}) AND (deal_date IS NULL OR deal_date = '')")->fetch(PDO::FETCH_ASSOC);
$unpaidNoDate = $wipDb->query("SELECT COUNT(*) as cnt, COALESCE(SUM(balance_amount), 0) as balance FROM cases WHERE status = 'unpaid' AND branch_id IN ({$wipBranches}) AND (deal_date IS NULL OR deal_date = '')")->fetch(PDO::FETCH_ASSOC);
?>
<div class="card">
    <div class="card-header analysis-header">未完工 與 完工未收款 未收餘額</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <?php
            // 當前月份（還沒到的月份不顯示）
            $currentMonth = date('Y-m');
            ?>
            <thead><tr>
                <th>統計項目</th>
                <?php foreach ($months as $m): ?><th><?= (int)substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php
                // 未完工累加
                $wipAcc = $wipBefore['balance'] + (int)$wipNoDate['balance'];
                $wipFinal = $wipAcc;
                ?>
                <tr>
                    <td style="font-weight:600">未完工</td>
                    <?php foreach ($months as $m):
                        $wipAcc += $wipMonthly[$m]['balance'];
                        if ($m <= $currentMonth):
                            $wipFinal = $wipAcc;
                    ?>
                    <td style="<?= $wipAcc > 0 ? 'color:#e53935' : '' ?>"><?= $wipAcc != 0 ? number_format($wipAcc) : '0' ?></td>
                    <?php else: ?>
                    <td></td>
                    <?php endif; endforeach; ?>
                    <td class="col-total" style="color:#e53935;font-weight:600">$<?= number_format($wipFinal) ?></td>
                </tr>
                <?php
                // 完工未收款累加
                $unpaidAcc = $unpaidBefore['balance'] + (int)$unpaidNoDate['balance'];
                $unpaidFinal = $unpaidAcc;
                ?>
                <tr>
                    <td style="font-weight:600">完工未收款</td>
                    <?php foreach ($months as $m):
                        $unpaidAcc += $unpaidMonthly[$m]['balance'];
                        if ($m <= $currentMonth):
                            $unpaidFinal = $unpaidAcc;
                    ?>
                    <td style="<?= $unpaidAcc > 0 ? 'color:#e53935' : '' ?>"><?= $unpaidAcc != 0 ? number_format($unpaidAcc) : '0' ?></td>
                    <?php else: ?>
                    <td></td>
                    <?php endif; endforeach; ?>
                    <td class="col-total" style="color:#e53935;font-weight:600">$<?= number_format($unpaidFinal) ?></td>
                </tr>
                <?php
                // 合計累加
                $totalAcc = $wipBefore['balance'] + $unpaidBefore['balance'] + (int)$wipNoDate['balance'] + (int)$unpaidNoDate['balance'];
                $totalFinal = $totalAcc;
                ?>
                <tr class="row-total">
                    <td>合計</td>
                    <?php foreach ($months as $m):
                        $totalAcc += $wipMonthly[$m]['balance'] + $unpaidMonthly[$m]['balance'];
                        if ($m <= $currentMonth):
                            $totalFinal = $totalAcc;
                    ?>
                    <td style="color:#e53935;font-weight:600"><?= $totalAcc != 0 ? number_format($totalAcc) : '0' ?></td>
                    <?php else: ?>
                    <td></td>
                    <?php endif; endforeach; ?>
                    <td class="col-total" style="color:#e53935;font-weight:600">$<?= number_format($totalFinal) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 二、各案別 各月數量統計 -->
<?php
$allTypes = array_merge(array('new_install'), $otherTypes);
$allEntry = 0; $allClosed = 0;
// 預先計算各案別總計
$typeTotalsEntry = array();
$typeTotalsClosed = array();
foreach ($allTypes as $ct) {
    $typeTotalsEntry[$ct] = 0;
    $typeTotalsClosed[$ct] = 0;
    foreach ($months as $m) {
        $typeTotalsEntry[$ct] += isset($caseTypeMonthly[$ct][$m]) ? $caseTypeMonthly[$ct][$m] : 0;
        $typeTotalsClosed[$ct] += isset($caseTypeClosedMonthly[$ct][$m]) ? $caseTypeClosedMonthly[$ct][$m] : 0;
    }
}
$grandEntry = array_sum($typeTotalsEntry);
$grandClosed = array_sum($typeTotalsClosed);
?>
<div class="card">
    <div class="card-header analysis-header">二、各案別 各月數量統計</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>統計項目</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
                <th class="col-total">比例</th>
            </tr></thead>
            <tbody>
                <!-- 進件合計 -->
                <?php $otherEntry = 0; ?>
                <tr style="font-weight:bold; background:#f8f9fa;">
                    <td>進件數（合計）</td>
                    <?php foreach ($months as $m):
                        $v = 0;
                        foreach ($allTypes as $ct) { $v += isset($caseTypeMonthly[$ct][$m]) ? $caseTypeMonthly[$ct][$m] : 0; }
                        $allEntry += $v;
                    ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($allEntry) ?></td>
                    <td class="col-total">100%</td>
                </tr>
                <?php foreach ($allTypes as $ct):
                    $label = isset($caseTypeLabels[$ct]) ? $caseTypeLabels[$ct] : $ct;
                    $data = isset($caseTypeMonthly[$ct]) ? $caseTypeMonthly[$ct] : array();
                    $rowTotal = $typeTotalsEntry[$ct];
                ?>
                <tr>
                    <td style="padding-left:24px; color:#666;">└ <?= e($label) ?></td>
                    <?php foreach ($months as $m): $v = isset($data[$m]) ? $data[$m] : 0; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $rowTotal ?: '' ?></td>
                    <td class="col-total"><?= $grandEntry > 0 ? round($rowTotal / $grandEntry * 100, 1) . '%' : '' ?></td>
                </tr>
                <?php endforeach; ?>

                <!-- 成交合計 -->
                <?php $allClosed = 0; ?>
                <tr style="font-weight:bold; background:#f8f9fa;">
                    <td>成交數（合計）</td>
                    <?php foreach ($months as $m):
                        $v = 0;
                        foreach ($allTypes as $ct) { $v += isset($caseTypeClosedMonthly[$ct][$m]) ? $caseTypeClosedMonthly[$ct][$m] : 0; }
                        $allClosed += $v;
                    ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($allClosed) ?></td>
                    <td class="col-total">100%</td>
                </tr>
                <?php foreach ($allTypes as $ct):
                    $label = isset($caseTypeLabels[$ct]) ? $caseTypeLabels[$ct] : $ct;
                    $data = isset($caseTypeClosedMonthly[$ct]) ? $caseTypeClosedMonthly[$ct] : array();
                    $rowTotal = $typeTotalsClosed[$ct];
                ?>
                <tr>
                    <td style="padding-left:24px; color:#666;">└ <?= e($label) ?></td>
                    <?php foreach ($months as $m): $v = isset($data[$m]) ? $data[$m] : 0; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $rowTotal ?: '' ?></td>
                    <td class="col-total"><?= $grandClosed > 0 ? round($rowTotal / $grandClosed * 100, 1) . '%' : '' ?></td>
                </tr>
                <?php endforeach; ?>

                <tr class="row-highlight">
                    <td>成交率</td>
                    <?php foreach ($months as $m):
                        $mE = 0; $mC = 0;
                        foreach ($allTypes as $ct) {
                            $mE += isset($caseTypeMonthly[$ct][$m]) ? $caseTypeMonthly[$ct][$m] : 0;
                            $mC += isset($caseTypeClosedMonthly[$ct][$m]) ? $caseTypeClosedMonthly[$ct][$m] : 0;
                        }
                    ?>
                    <td><?= $mE > 0 ? round($mC / $mE * 100, 1) . '%' : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $grandEntry > 0 ? round($grandClosed / $grandEntry * 100, 1) . '%' : '' ?></td>
                    <td class="col-total"></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 三、全部案件 各月數量統計 -->
<div class="card">
    <div class="card-header analysis-header">三、全部案件 各月數量統計</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>統計項目</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <tr>
                    <td>進件數</td>
                    <?php $totalEntry = 0; foreach ($months as $m): $v = isset($analysis['monthly_entry'][$m]) ? $analysis['monthly_entry'][$m] : 0; $totalEntry += $v; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($totalEntry) ?></td>
                </tr>
                <tr>
                    <td>成交數</td>
                    <?php $totalClosed = 0; foreach ($months as $m): $v = isset($analysis['monthly_closed'][$m]) ? $analysis['monthly_closed'][$m] : 0; $totalClosed += $v; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($totalClosed) ?></td>
                </tr>
                <tr class="row-highlight">
                    <td>成交率</td>
                    <?php foreach ($months as $m):
                        $e = isset($analysis['monthly_entry'][$m]) ? $analysis['monthly_entry'][$m] : 0;
                        $c = isset($analysis['monthly_closed'][$m]) ? $analysis['monthly_closed'][$m] : 0;
                    ?>
                    <td><?= $e > 0 ? round($c / $e * 100, 1) . '%' : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $totalEntry > 0 ? round($totalClosed / $totalEntry * 100, 1) . '%' : '' ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 二、各月金額統計 -->
<div class="card">
    <div class="card-header analysis-header">四、各月金額統計（元）</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>統計項目</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php
                $amtLabels = array(
                    'total_amount' => '含稅成交金額',
                    'quote_amount' => '報價金額',
                    'deposit_amount' => '訂金金額',
                    'completion_amount' => '完工金額(含稅)',
                    'balance_amount' => '尾款',
                    'total_collected' => '總收款金額',
                );
                foreach ($amtLabels as $key => $label):
                    $rowTotal = 0;
                ?>
                <tr>
                    <td><?= $label ?></td>
                    <?php foreach ($months as $m):
                        $v = isset($analysis['monthly_amounts'][$m][$key]) ? $analysis['monthly_amounts'][$m][$key] : 0;
                        $rowTotal += $v;
                    ?>
                    <td><?= $v > 0 ? number_format($v) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= $rowTotal > 0 ? number_format($rowTotal) : '' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 三、分公司 × 月份 -->
<?php if (!empty($analysis['branch_monthly'])): ?>
<div class="card">
    <div class="card-header analysis-header">五、分公司 × 月份 進件數</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>分公司</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
                <th>佔比</th>
            </tr></thead>
            <tbody>
                <?php
                $grandTotal = $analysis['total_cases'];
                foreach ($analysis['branch_monthly'] as $name => $mdata):
                    $rt = pivotRowTotal($mdata, $months);
                ?>
                <tr>
                    <td><?= e($name) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($rt) ?></td>
                    <td><?= $grandTotal > 0 ? round($rt / $grandTotal * 100, 1) . '%' : '' ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="row-total">
                    <td>合計</td>
                    <?php foreach ($months as $m): $ct = 0; foreach ($analysis['branch_monthly'] as $d) { $ct += isset($d[$m]) ? $d[$m] : 0; } ?>
                    <td><?= $ct ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($grandTotal) ?></td>
                    <td>100%</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 三b、分公司 × 月份 成交金額 -->
<?php if (!empty($analysis['branch_monthly_amounts'])): ?>
<div class="card">
    <div class="card-header analysis-header">五b、分公司 × 月份 成交金額（元）</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>分公司</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php
                $amtGrandTotals = array();
                foreach ($analysis['branch_monthly_amounts'] as $name => $mdata):
                    $rt = 0;
                    foreach ($months as $m) { $v = isset($mdata[$m]) ? $mdata[$m] : 0; $rt += $v; if (!isset($amtGrandTotals[$m])) $amtGrandTotals[$m] = 0; $amtGrandTotals[$m] += $v; }
                    if ($rt == 0) continue; // 無成交金額的分公司不顯示
                ?>
                <tr>
                    <td><?= e($name) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td><?= $v > 0 ? number_format($v) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($rt) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="row-total">
                    <td>合計</td>
                    <?php $amtGrand = 0; foreach ($months as $m): $ct = isset($amtGrandTotals[$m]) ? $amtGrandTotals[$m] : 0; $amtGrand += $ct; ?>
                    <td><?= $ct > 0 ? number_format($ct) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($amtGrand) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 四、案件狀態(sub_status) × 月份 -->
<?php if (!empty($analysis['status_monthly'])): ?>
<div class="card">
    <div class="card-header analysis-header">六、案件狀態 × 月份</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>狀態</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
                <th>佔比</th>
            </tr></thead>
            <tbody>
                <?php
                $statusGrand = 0;
                foreach ($analysis['status_monthly'] as $d) { foreach ($months as $m) { $statusGrand += isset($d[$m]) ? $d[$m] : 0; } }
                foreach ($analysis['status_monthly'] as $name => $mdata):
                    $rt = pivotRowTotal($mdata, $months);
                    $rowClass = in_array($name, $closedStatuses) ? 'row-ok' : (in_array($name, array('無效','客戶毀約','已報價無意願')) ? 'row-bad' : '');
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><?= e($name) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td class="<?= $v ? 'drillable' : '' ?>" <?= $v ? 'onclick="drillDown(\'status\',\'' . $m . '\',\'\',\'' . e($name) . ' ' . (int)substr($m,5) . '月 案件明細\',\'' . e($name) . '\')"' : '' ?>><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total <?= $rt ? 'drillable' : '' ?>" <?= $rt ? 'onclick="drillDown(\'status\',\'\',\'\',\'' . e($name) . ' 年度案件明細\',\'' . e($name) . '\')"' : '' ?>><?= number_format($rt) ?></td>
                    <td><?= $statusGrand > 0 ? round($rt / $statusGrand * 100, 1) . '%' : '' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 五、案件進度 × 月份 -->
<?php if (!empty($analysis['progress_monthly'])): ?>
<div class="card">
    <div class="card-header analysis-header">七、案件進度 × 月份</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>進度</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
                <th>佔比</th>
            </tr></thead>
            <tbody>
                <?php
                foreach ($analysis['progress_monthly'] as $key => $mdata):
                    $rt = pivotRowTotal($mdata, $months);
                    $label = isset($progressLabels[$key]) ? $progressLabels[$key] : $key;
                ?>
                <tr>
                    <td><?= e($label) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($rt) ?></td>
                    <td><?= $grandTotal > 0 ? round($rt / $grandTotal * 100, 1) . '%' : '' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 六、案件來源 × 月份 -->
<?php if (!empty($analysis['source_monthly'])): ?>
<div class="card">
    <div class="card-header analysis-header">八、案件來源 × 月份</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>來源</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php foreach ($analysis['source_monthly'] as $name => $mdata): $rt = pivotRowTotal($mdata, $months); ?>
                <tr>
                    <td><?= e($name) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($rt) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 七、業務進件數 × 月份 -->
<?php if (!empty($analysis['sales_cases'])): ?>
<div class="card">
    <div class="card-header analysis-header">九、業務人員 × 月份 進件數</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>業務</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php foreach ($analysis['sales_cases'] as $name => $mdata): $rt = pivotRowTotal($mdata, $months); ?>
                <tr>
                    <td><?= e($name) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td class="<?= $v ? 'drillable' : '' ?>" <?= $v ? 'onclick="drillDown(\'entry\',\'' . $m . '\',\'' . e($name) . '\',\'' . e($name) . ' ' . (int)substr($m,5) . '月 進件明細\')"' : '' ?>><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total <?= $rt ? 'drillable' : '' ?>" <?= $rt ? 'onclick="drillDown(\'entry\',\'\',\'' . e($name) . '\',\'' . e($name) . ' 年度進件明細\')"' : '' ?>><?= number_format($rt) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 八、業務成交金額 × 月份 -->
<?php if (!empty($analysis['sales_amount'])): ?>
<div class="card">
    <div class="card-header analysis-header">十、業務人員 × 月份 含稅成交金額（元）</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>業務</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php foreach ($analysis['sales_amount'] as $name => $mdata): $rt = pivotRowTotal($mdata, $months); ?>
                <tr>
                    <td><?= e($name) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td class="<?= $v > 0 ? 'drillable' : '' ?>" <?= $v > 0 ? 'onclick="drillDown(\'deal_amount\',\'' . $m . '\',\'' . e($name) . '\',\'' . e($name) . ' ' . (int)substr($m,5) . '月 成交金額明細\')"' : '' ?>><?= $v > 0 ? number_format($v) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total <?= $rt > 0 ? 'drillable' : '' ?>" <?= $rt > 0 ? 'onclick="drillDown(\'deal_amount\',\'\',\'' . e($name) . '\',\'' . e($name) . ' 年度成交金額明細\')"' : '' ?>><?= number_format($rt) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 九、業務成交件數 × 月份 -->
<?php if (!empty($analysis['sales_closed'])): ?>
<div class="card">
    <div class="card-header analysis-header">十一、業務人員 × 月份 成交案件數</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>業務</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php foreach ($analysis['sales_closed'] as $name => $mdata): $rt = pivotRowTotal($mdata, $months); ?>
                <tr>
                    <td><?= e($name) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td class="<?= $v ? 'drillable' : '' ?>" <?= $v ? 'onclick="drillDown(\'closed\',\'' . $m . '\',\'' . e($name) . '\',\'' . e($name) . ' ' . (int)substr($m,5) . '月 成交案件明細\')"' : '' ?>><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total <?= $rt ? 'drillable' : '' ?>" <?= $rt ? 'onclick="drillDown(\'closed\',\'\',\'' . e($name) . '\',\'' . e($name) . ' 年度成交案件明細\')"' : '' ?>><?= number_format($rt) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 十、業務績效排名 -->
<?php if (!empty($analysis['sales_ranking'])): ?>
<div class="card">
    <div class="card-header analysis-header">十二、業務人員績效排名</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>排名</th><th>業務</th><th>分公司</th><th>進件數</th><th>成交數</th><th>成交率</th><th>含稅金額合計</th><th>平均成交金額</th>
            </tr></thead>
            <tbody>
                <?php
                $rank = 0;
                $gtTotal = 0; $gtClosed = 0; $gtAmt = 0;
                foreach ($analysis['sales_ranking'] as $name => $s):
                    $rank++;
                    $rate = $s['total'] > 0 ? round($s['closed'] / $s['total'] * 100, 1) : 0;
                    $avg = $s['closed'] > 0 ? round($s['amount'] / $s['closed']) : 0;
                    $medalClass = $rank <= 3 ? 'rank-' . $rank : '';
                    $gtTotal += $s['total']; $gtClosed += $s['closed']; $gtAmt += $s['amount'];
                ?>
                <tr class="<?= $medalClass ?>">
                    <td><?= $rank ?></td>
                    <td><?= e($name) ?></td>
                    <td><?= e($s['branch'] ?: '') ?></td>
                    <td><?= number_format($s['total']) ?></td>
                    <td><?= number_format($s['closed']) ?></td>
                    <td><?= $rate ?>%</td>
                    <td><?= number_format($s['amount']) ?></td>
                    <td><?= number_format($avg) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="row-total">
                    <td></td><td>合計</td><td></td>
                    <td><?= number_format($gtTotal) ?></td>
                    <td><?= number_format($gtClosed) ?></td>
                    <td><?= $gtTotal > 0 ? round($gtClosed / $gtTotal * 100, 1) : 0 ?>%</td>
                    <td><?= number_format($gtAmt) ?></td>
                    <td><?= $gtClosed > 0 ? number_format(round($gtAmt / $gtClosed)) : '' ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 十一、業務各月收款金額（收款單） -->
<?php if (!empty($analysis['sales_receipt'])): ?>
<div class="card">
    <div class="card-header analysis-header">十三、業務人員 × 月份 收款金額（元）<small style="opacity:.8;margin-left:8px">資料來源：收款單</small></div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>業務</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php
                $receiptGrandTotals = array();
                foreach ($analysis['sales_receipt'] as $name => $mdata):
                    $rt = pivotRowTotal($mdata, $months);
                    foreach ($months as $m) {
                        $v = isset($mdata[$m]) ? $mdata[$m] : 0;
                        if (!isset($receiptGrandTotals[$m])) $receiptGrandTotals[$m] = 0;
                        $receiptGrandTotals[$m] += $v;
                    }
                ?>
                <tr>
                    <td><?= e($name) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td class="<?= $v > 0 ? 'drillable' : '' ?>" <?= $v > 0 ? 'onclick="drillDown(\'receipt\',\'' . $m . '\',\'' . e($name) . '\',\'' . e($name) . ' ' . (int)substr($m,5) . '月 收款明細\')"' : '' ?>><?= $v > 0 ? number_format($v) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total <?= $rt > 0 ? 'drillable' : '' ?>" <?= $rt > 0 ? 'onclick="drillDown(\'receipt\',\'\',\'' . e($name) . '\',\'' . e($name) . ' 年度收款明細\')"' : '' ?>><?= number_format($rt) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="row-total">
                    <td>合計</td>
                    <?php $receiptGrand = 0; foreach ($months as $m): $ct = isset($receiptGrandTotals[$m]) ? $receiptGrandTotals[$m] : 0; $receiptGrand += $ct; ?>
                    <td><?= $ct > 0 ? number_format($ct) : '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($receiptGrand) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 十二、業務各月收款筆數（收款單） -->
<?php if (!empty($analysis['sales_receipt_count'])): ?>
<div class="card">
    <div class="card-header analysis-header">十四、業務人員 × 月份 收款筆數<small style="opacity:.8;margin-left:8px">資料來源：收款單</small></div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>業務</th>
                <?php foreach ($months as $m): ?><th><?= substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
                <?php
                $cntGrandTotals = array();
                foreach ($analysis['sales_receipt_count'] as $name => $mdata):
                    $rt = pivotRowTotal($mdata, $months);
                    foreach ($months as $m) {
                        $v = isset($mdata[$m]) ? $mdata[$m] : 0;
                        if (!isset($cntGrandTotals[$m])) $cntGrandTotals[$m] = 0;
                        $cntGrandTotals[$m] += $v;
                    }
                ?>
                <tr>
                    <td><?= e($name) ?></td>
                    <?php foreach ($months as $m): $v = isset($mdata[$m]) ? $mdata[$m] : 0; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($rt) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="row-total">
                    <td>合計</td>
                    <?php $cntGrand = 0; foreach ($months as $m): $ct = isset($cntGrandTotals[$m]) ? $cntGrandTotals[$m] : 0; $cntGrand += $ct; ?>
                    <td><?= $ct ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($cntGrand) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- 鑽取明細（內嵌在卡片下方）-->
<div id="drillInline" style="display:none">
    <div class="card" style="margin-top:-8px;border-top:3px solid var(--primary);background:#fafbff">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #e0e0e0">
            <strong id="drillInlineTitle" style="font-size:.9rem;color:var(--primary)">明細</strong>
            <button class="btn btn-sm" style="padding:2px 10px;font-size:1.1rem;line-height:1;background:none;color:#999" onclick="closeDrillInline()">&times;</button>
        </div>
        <div style="padding:8px 12px;overflow-x:auto">
            <div id="drillInlineLoading" style="text-align:center;padding:16px;display:none">載入中...</div>
            <div id="drillInlineResult"></div>
        </div>
    </div>
</div>

<style>
.analysis-summary { font-size: .85rem; color: var(--gray-500); display: flex; gap: 16px; flex-wrap: wrap; }
.analysis-header { background: var(--primary); color: #fff; font-weight: 600; }
.analysis-table { font-size: .8rem; white-space: nowrap; }
.analysis-table th { background: var(--gray-100); font-weight: 600; text-align: center; padding: 6px 8px; }
.analysis-table td { text-align: center; padding: 4px 8px; }
.analysis-table td:first-child, .analysis-table th:first-child { text-align: left; position: sticky; left: 0; background: #fff; z-index: 1; }
.analysis-table thead th:first-child { background: var(--gray-100); z-index: 2; }
.col-total { font-weight: 600; background: #fef3e2 !important; }
.row-total td { font-weight: 600; background: #fef3e2 !important; border-top: 2px solid var(--gray-300); }
.row-highlight td { background: #e8f5e9; font-weight: 600; }
.row-ok td { background: #e8f5e9; }
.row-bad td { background: #fce4ec; }
.rank-1 td { background: #fff8e1; }
.rank-2 td { background: #f5f5f5; }
.rank-3 td { background: #fbe9e7; }
td.drillable { cursor: pointer; position: relative; }
td.drillable:hover { background: #e3f2fd !important; text-decoration: underline; }
</style>

<script>
var drillYear = <?= json_encode($analysis['year']) ?>;

function drillDown(type, month, salesName, label, statusVal) {
    var inline = document.getElementById('drillInline');
    var title = document.getElementById('drillInlineTitle');
    var loading = document.getElementById('drillInlineLoading');
    var result = document.getElementById('drillInlineResult');

    // 找到點擊的 td 所在的 card
    var clickedTd = event.target.closest('td');
    var parentCard = clickedTd ? clickedTd.closest('.card') : null;

    title.textContent = label;
    loading.style.display = '';
    result.innerHTML = '';

    // 將 drillInline 插入到該 card 下方
    if (parentCard && parentCard.parentNode) {
        parentCard.parentNode.insertBefore(inline, parentCard.nextSibling);
    }
    inline.style.display = '';
    inline.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    var url = '/reports.php?action=drill_down&year=' + drillYear + '&type=' + encodeURIComponent(type);
    if (month) url += '&month=' + encodeURIComponent(month);
    if (salesName) url += '&sales_name=' + encodeURIComponent(salesName);
    if (statusVal) url += '&status_val=' + encodeURIComponent(statusVal);

    var xhr = new XMLHttpRequest();
    xhr.open('GET', url);
    xhr.onload = function() {
        loading.style.display = 'none';
        try {
            var data = JSON.parse(xhr.responseText);
            var cases = data.cases || [];
            if (cases.length === 0) {
                result.innerHTML = '<p class="text-muted text-center">無明細資料</p>';
                inline.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                return;
            }
            var isReceipt = (type === 'receipt');
            var html = '<table class="table table-sm" style="font-size:.8rem"><thead><tr>';
            html += '<th>編號</th><th>' + (isReceipt ? '客戶' : '案件名稱') + '</th>';
            if (!isReceipt) html += '<th>客戶</th>';
            html += '<th>業務</th><th>' + (type === 'entry' || type === 'status' ? '進件日' : '成交日') + '</th>';
            html += '<th style="text-align:right">金額</th><th>狀態</th></tr></thead><tbody>';
            var totalAmt = 0;
            for (var i = 0; i < cases.length; i++) {
                var c = cases[i];
                var amt = parseInt(c.total_amount) || 0;
                totalAmt += amt;
                var dateVal = type === 'entry' ? (c.created_date || '') : (c.deal_date || '');
                var link = isReceipt ? '/receipts.php?action=edit&id=' + c.id : '/cases.php?action=edit&id=' + c.id;
                html += '<tr>';
                html += '<td><a href="' + link + '" target="_blank">' + escHtml(c.case_number || '-') + '</a></td>';
                html += '<td>' + escHtml(c.title || '-') + '</td>';
                if (!isReceipt) html += '<td>' + escHtml(c.customer_name || '-') + '</td>';
                html += '<td>' + escHtml(c.sales_name || '-') + '</td>';
                html += '<td>' + escHtml(dateVal) + '</td>';
                html += '<td style="text-align:right;font-weight:600">' + (amt > 0 ? '$' + amt.toLocaleString() : '') + '</td>';
                html += '<td>' + escHtml(c.sub_status || '-') + '</td>';
                html += '</tr>';
            }
            html += '</tbody><tfoot><tr><td colspan="' + (isReceipt ? 4 : 5) + '" style="text-align:right;font-weight:bold">合計 (' + cases.length + '筆)</td>';
            html += '<td style="text-align:right;font-weight:bold;color:var(--primary)">$' + totalAmt.toLocaleString() + '</td><td></td></tr></tfoot></table>';
            result.innerHTML = html;
            inline.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) {
            result.innerHTML = '<p class="text-danger">載入失敗</p>';
        }
    };
    xhr.onerror = function() {
        loading.style.display = 'none';
        result.innerHTML = '<p class="text-danger">網路錯誤</p>';
    };
    xhr.send();
}

function closeDrillInline() {
    var inline = document.getElementById('drillInline');
    inline.style.display = 'none';
    inline.querySelector('#drillInlineResult').innerHTML = '';
}

function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// 拖曳
(function() {
    var dragEl = null, startX = 0, startY = 0, origX = 0, origY = 0;
    var header = document.getElementById('drillModalHeader');
    var modal = document.getElementById('drillModalContent');
    if (!header) return;
    header.addEventListener('mousedown', function(e) {
        if (e.target.closest('.modal-close')) return;
        dragEl = modal;
        startX = e.clientX; startY = e.clientY;
        var t = modal.style.transform;
        var m = t.match(/translate\((-?\d+)px,\s*(-?\d+)px\)/);
        origX = m ? parseInt(m[1]) : 0;
        origY = m ? parseInt(m[2]) : 0;
        e.preventDefault();
    });
    document.addEventListener('mousemove', function(e) {
        if (!dragEl) return;
        dragEl.style.transform = 'translate(' + (origX + e.clientX - startX) + 'px,' + (origY + e.clientY - startY) + 'px)';
    });
    document.addEventListener('mouseup', function() { dragEl = null; });
})();
</script>
