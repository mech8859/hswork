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
        <input type="hidden" name="perf_month" value="<?= e(isset($_GET['perf_month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['perf_month']) ? $_GET['perf_month'] : date('Y-m')) ?>" id="topPerfMonth">
        <select name="year" class="form-control" style="width:auto" onchange="
            var y = parseInt(this.value);
            var pm = document.getElementById('topPerfMonth');
            if (pm && parseInt(pm.value.substring(0,4)) !== y) {
                var t = new Date();
                var snap = (y === t.getFullYear()) ? ('0'+(t.getMonth()+1)).slice(-2) : '12';
                pm.value = y + '-' + snap;
            }
            this.form.submit();
        ">
            <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
            <option value="<?= $y ?>" <?= $analysis['year'] == $y ? 'selected' : '' ?>><?= ($y - 1911) ?>年 (<?= $y ?>)</option>
            <?php endfor; ?>
        </select>
        <?= back_button('/reports.php') ?>
    </form>
</div>

<!-- ★ 本月業務新案 / 老客戶追加 績效（依分公司、業務人員）-->
<?php
$cmLabel = isset($_GET['perf_month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['perf_month']) ? $_GET['perf_month'] : date('Y-m');
$cmStart = $cmLabel . '-01';
$cmEnd   = date('Y-m-t', strtotime($cmStart));
// 產生最近 24 個月的選項
$cmMonthOptions = array();
for ($i = 0; $i < 24; $i++) {
    $cmMonthOptions[] = date('Y-m', strtotime("-{$i} months"));
}
$cmBranchPh = !empty($branchIds) ? implode(',', array_map('intval', $branchIds)) : '0';
$cmDb = Database::getInstance();
$cmStmt = $cmDb->query("
    SELECT c.branch_id,
           b.name AS branch_name,
           c.sales_id,
           u.real_name AS sales_name,
           u.role AS sales_role,
           c.case_type,
           c.sub_status
    FROM cases c
    JOIN branches b ON c.branch_id = b.id
    LEFT JOIN users u ON c.sales_id = u.id
    WHERE c.branch_id IN ({$cmBranchPh})
      AND c.case_type IN ('new_install','addition')
      AND c.created_at BETWEEN '{$cmStart} 00:00:00' AND '{$cmEnd} 23:59:59'
");
$cmRows = $cmStmt->fetchAll(PDO::FETCH_ASSOC);
$cmClosed = array('已成交','跨月成交','現簽','電話報價成交');

$cmBranchAgg = array();   // [branch_name => [new_entry,new_closed,add_entry,add_closed]]
$cmSalesAgg  = array();   // [branch_name => [sales_name => [...,role]]]
foreach ($cmRows as $r) {
    $bn = $r['branch_name'] ?: '(未設定)';
    $sn = $r['sales_name'] ?: '(未指定)';
    $role = $r['sales_role'] ?: '';
    $isAdd = ($r['case_type'] === 'addition');
    $isClosed = in_array($r['sub_status'], $cmClosed);

    if (!isset($cmBranchAgg[$bn])) {
        $cmBranchAgg[$bn] = array('new_entry'=>0,'new_closed'=>0,'add_entry'=>0,'add_closed'=>0);
    }
    if ($isAdd) { $cmBranchAgg[$bn]['add_entry']++; if ($isClosed) $cmBranchAgg[$bn]['add_closed']++; }
    else        { $cmBranchAgg[$bn]['new_entry']++; if ($isClosed) $cmBranchAgg[$bn]['new_closed']++; }

    if (!in_array($role, array('sales_manager','sales'))) continue;
    if (!isset($cmSalesAgg[$bn])) $cmSalesAgg[$bn] = array();
    if (!isset($cmSalesAgg[$bn][$sn])) {
        $cmSalesAgg[$bn][$sn] = array('role'=>$role,'new_entry'=>0,'new_closed'=>0,'add_entry'=>0,'add_closed'=>0);
    }
    if ($isAdd) { $cmSalesAgg[$bn][$sn]['add_entry']++; if ($isClosed) $cmSalesAgg[$bn][$sn]['add_closed']++; }
    else        { $cmSalesAgg[$bn][$sn]['new_entry']++; if ($isClosed) $cmSalesAgg[$bn][$sn]['new_closed']++; }
}
ksort($cmBranchAgg);
ksort($cmSalesAgg);
$cmRate = function($num, $den) { return $den > 0 ? round($num / $den * 100, 1) . '%' : '-'; };
?>
<div class="card">
    <div class="card-header analysis-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <span>★ <?= ($cmLabel === date('Y-m')) ? '本月' : '指定月份' ?>（<?= e($cmLabel) ?>）業務新案 / 老客戶追加 績效</span>
        <form method="GET" style="margin:0;display:flex;align-items:center;gap:6px">
            <input type="hidden" name="action" value="case_analysis">
            <input type="hidden" name="year" value="<?= (int)$analysis['year'] ?>">
            <label style="color:#fff;font-weight:normal;font-size:13px">月份</label>
            <select name="perf_month" class="form-control" style="width:auto;height:28px;padding:2px 6px;font-size:13px" onchange="
                var y = this.value.substring(0,4);
                var yi = this.form.querySelector('input[name=year]');
                if (yi) yi.value = y;
                this.form.submit();
            ">
                <?php foreach ($cmMonthOptions as $opt): ?>
                <option value="<?= e($opt) ?>" <?= $opt === $cmLabel ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div style="padding:6px 10px;font-size:13px;color:#666">
        統計區間：<?= e($cmStart) ?> ~ <?= e($cmEnd) ?>　|
        資料源：案件管理（case_type = new_install / addition）　|
        成交定義：sub_status ∈ <?= e(implode('、', $cmClosed)) ?>
    </div>

    <!-- 分公司彙整 -->
    <div class="table-responsive" style="margin-bottom:8px">
        <table class="table table-sm analysis-table">
            <thead>
                <tr>
                    <th rowspan="2" style="vertical-align:middle">分公司</th>
                    <th colspan="3" style="text-align:center;background:#e3f2fd">新案</th>
                    <th colspan="3" style="text-align:center;background:#fff3e0">老客戶追加</th>
                    <th colspan="3" style="text-align:center;background:#e8f5e9">本月合計</th>
                </tr>
                <tr>
                    <th>進件</th><th>成交</th><th>成交率</th>
                    <th>進件</th><th>成交</th><th>成交率</th>
                    <th>進件</th><th>成交</th><th>成交率</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($cmBranchAgg)):
                ?>
                <tr><td colspan="10" class="text-muted text-center"><?= e($cmLabel) ?> 尚無新案 / 老客戶追加進件</td></tr>
                <?php
                else:
                    $gNE=0;$gNC=0;$gAE=0;$gAC=0;
                    foreach ($cmBranchAgg as $name => $d):
                        $tE = $d['new_entry'] + $d['add_entry'];
                        $tC = $d['new_closed'] + $d['add_closed'];
                        $gNE += $d['new_entry']; $gNC += $d['new_closed'];
                        $gAE += $d['add_entry']; $gAC += $d['add_closed'];
                ?>
                <tr>
                    <td><?= e($name) ?></td>
                    <td><?= $d['new_entry'] ?: '' ?></td>
                    <td><?= $d['new_closed'] ?: '' ?></td>
                    <td><?= $cmRate($d['new_closed'], $d['new_entry']) ?></td>
                    <td><?= $d['add_entry'] ?: '' ?></td>
                    <td><?= $d['add_closed'] ?: '' ?></td>
                    <td><?= $cmRate($d['add_closed'], $d['add_entry']) ?></td>
                    <td class="col-total"><?= $tE ?: '' ?></td>
                    <td class="col-total"><?= $tC ?: '' ?></td>
                    <td class="col-total"><?= $cmRate($tC, $tE) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="row-total">
                    <td>合計</td>
                    <td><?= $gNE ?: '' ?></td>
                    <td><?= $gNC ?: '' ?></td>
                    <td><?= $cmRate($gNC, $gNE) ?></td>
                    <td><?= $gAE ?: '' ?></td>
                    <td><?= $gAC ?: '' ?></td>
                    <td><?= $cmRate($gAC, $gAE) ?></td>
                    <td class="col-total"><?= ($gNE+$gAE) ?: '' ?></td>
                    <td class="col-total"><?= ($gNC+$gAC) ?: '' ?></td>
                    <td class="col-total"><?= $cmRate($gNC+$gAC, $gNE+$gAE) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 業務人員明細 -->
    <div style="padding:4px 10px;font-size:13px;color:#555;background:#fafafa;border-top:1px solid #eee">
        業務人員明細（限 <b>業務主管</b>、<b>業務</b> 角色；其他角色或未指派者不列入此表，但仍計入上方分公司總計）
    </div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead>
                <tr>
                    <th rowspan="2" style="vertical-align:middle">分公司</th>
                    <th rowspan="2" style="vertical-align:middle">業務人員</th>
                    <th rowspan="2" style="vertical-align:middle">職務</th>
                    <th colspan="3" style="text-align:center;background:#e3f2fd">新案</th>
                    <th colspan="3" style="text-align:center;background:#fff3e0">老客戶追加</th>
                </tr>
                <tr>
                    <th>進件</th><th>成交</th><th>成交率</th>
                    <th>進件</th><th>成交</th><th>成交率</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cmSalesAgg)): ?>
                <tr><td colspan="9" class="text-muted text-center"><?= e($cmLabel) ?> 無業務主管／業務承辦的新案 / 老客戶追加</td></tr>
                <?php else: foreach ($cmSalesAgg as $bn => $sales): ?>
                <?php foreach ($sales as $sn => $d): ?>
                <tr>
                    <td><?= e($bn) ?></td>
                    <td><?= e($sn) ?></td>
                    <td><?= $d['role'] === 'sales_manager' ? '業務主管' : '業務' ?></td>
                    <td><?= $d['new_entry'] ?: '' ?></td>
                    <td><?= $d['new_closed'] ?: '' ?></td>
                    <td><?= $cmRate($d['new_closed'], $d['new_entry']) ?></td>
                    <td><?= $d['add_entry'] ?: '' ?></td>
                    <td><?= $d['add_closed'] ?: '' ?></td>
                    <td><?= $cmRate($d['add_closed'], $d['add_entry']) ?></td>
                </tr>
                <?php endforeach; endforeach; endif; ?>
            </tbody>
        </table>
    </div>
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

<!-- 一之1、依進件公司統計 進件數 -->
<?php
$compDb = Database::getInstance();
$compBranches = implode(',', array_map('intval', $branchIds));
$compStmt = $compDb->query("
    SELECT COALESCE(NULLIF(company,''), '未填') AS comp,
           DATE_FORMAT(created_at, '%Y-%m') AS ym,
           COUNT(*) AS cnt
    FROM cases
    WHERE branch_id IN ({$compBranches})
    AND case_type = 'new_install'
    AND DATE_FORMAT(created_at, '%Y-%m') BETWEEN '{$months[0]}' AND '{$months[$nm-1]}'
    GROUP BY comp, ym
    ORDER BY comp, ym
");
$compData = array();
$compTotals = array();
foreach ($compStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $compData[$r['comp']][$r['ym']] = (int)$r['cnt'];
    if (!isset($compTotals[$r['comp']])) $compTotals[$r['comp']] = 0;
    $compTotals[$r['comp']] += (int)$r['cnt'];
}
arsort($compTotals);
?>
<div class="card">
    <div class="card-header analysis-header">依進件公司統計 進件數</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>公司</th>
                <?php foreach ($months as $m): ?><th><?= (int)substr($m, 5) ?>月</th><?php endforeach; ?>
                <th class="col-total">合計</th>
            </tr></thead>
            <tbody>
            <?php
            $compGrandTotal = 0;
            foreach ($compTotals as $comp => $total):
                $compGrandTotal += $total;
            ?>
                <tr>
                    <td><?= e($comp) ?></td>
                    <?php foreach ($months as $m): $v = isset($compData[$comp][$m]) ? $compData[$comp][$m] : 0; ?>
                    <td><?= $v ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($total) ?></td>
                </tr>
            <?php endforeach; ?>
                <tr class="row-highlight">
                    <td>合計</td>
                    <?php foreach ($months as $m):
                        $mTotal = 0;
                        foreach ($compData as $cd) { $mTotal += isset($cd[$m]) ? $cd[$m] : 0; }
                    ?>
                    <td><?= $mTotal ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="col-total"><?= number_format($compGrandTotal) ?></td>
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

// 未完工：依成交月份統計未收餘額（即時算：含稅金額或成交金額 - total_collected，與 updateTotalCollected() 一致）
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
           COALESCE(SUM(GREATEST(COALESCE(CASE WHEN total_amount > 0 THEN total_amount ELSE deal_amount END, 0) - COALESCE(total_collected, 0), 0)), 0) as balance
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
           COALESCE(SUM(GREATEST(COALESCE(CASE WHEN total_amount > 0 THEN total_amount ELSE deal_amount END, 0) - COALESCE(total_collected, 0), 0)), 0) as balance
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
$wipNoDate = $wipDb->query("SELECT COUNT(*) as cnt, COALESCE(SUM(GREATEST(COALESCE(CASE WHEN total_amount > 0 THEN total_amount ELSE deal_amount END, 0) - COALESCE(total_collected, 0), 0)), 0) as balance FROM cases WHERE status = 'incomplete' AND branch_id IN ({$wipBranches}) AND (deal_date IS NULL OR deal_date = '')")->fetch(PDO::FETCH_ASSOC);
$unpaidNoDate = $wipDb->query("SELECT COUNT(*) as cnt, COALESCE(SUM(GREATEST(COALESCE(CASE WHEN total_amount > 0 THEN total_amount ELSE deal_amount END, 0) - COALESCE(total_collected, 0), 0)), 0) as balance FROM cases WHERE status = 'unpaid' AND branch_id IN ({$wipBranches}) AND (deal_date IS NULL OR deal_date = '')")->fetch(PDO::FETCH_ASSOC);
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

<!-- 十五、案件進度交叉分析 -->
<?php if (!empty($analysis['progress_cross'])): ?>
<div class="card">
    <div class="card-header analysis-header">十五、案件進度狀態交叉分析</div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th>進度</th>
                <th>筆數</th>
                <th>有報價金額</th>
                <th>已完工</th>
                <th>已成交</th>
                <th>有完工日期</th>
                <th>有成交金額</th>
                <th>無成交金額</th>
                <th>無尾款</th>
            </tr></thead>
            <tbody>
            <?php foreach ($analysis['progress_cross'] as $pKey => $pc):
                if ($pc['total'] == 0) continue;
            ?>
                <tr>
                    <td style="text-align:left;font-weight:600;"><?= e($pc['label']) ?></td>
                    <td class="drillable" onclick="showProgressCases('<?= $pKey ?>','cases_total','<?= e($pc['label']) ?> - 全部')"><?= $pc['total'] ?></td>
                    <td class="drillable" onclick="showProgressCases('<?= $pKey ?>','cases_has_quote','<?= e($pc['label']) ?> - 有報價金額')"><?= $pc['has_quote'] ?></td>
                    <td class="drillable" onclick="showProgressCases('<?= $pKey ?>','cases_is_completed','<?= e($pc['label']) ?> - 已完工')"><?= $pc['is_completed'] ?></td>
                    <td class="drillable" onclick="showProgressCases('<?= $pKey ?>','cases_is_closed_deal','<?= e($pc['label']) ?> - 已成交')"><?= $pc['is_closed_deal'] ?></td>
                    <td class="drillable" onclick="showProgressCases('<?= $pKey ?>','cases_has_completion_date','<?= e($pc['label']) ?> - 有完工日期')"><?= $pc['has_completion_date'] ?></td>
                    <td class="drillable" onclick="showProgressCases('<?= $pKey ?>','cases_has_deal_amount','<?= e($pc['label']) ?> - 有成交金額')"><?= $pc['has_deal_amount'] ?></td>
                    <td class="drillable" onclick="showProgressCases('<?= $pKey ?>','cases_no_deal_amount','<?= e($pc['label']) ?> - 無成交金額')"><?= $pc['no_deal_amount'] ?></td>
                    <td class="drillable" onclick="showProgressCases('<?= $pKey ?>','cases_no_balance','<?= e($pc['label']) ?> - 無尾款')"><?= $pc['no_balance'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="padding:8px 16px;font-size:.75rem;color:#888;">* 點擊數字可查看該批案件明細</div>
</div>

<?php
// ===== 業務 × 狀態 / 業務 × 進度 即時分析 =====
$_db = Database::getInstance();
$_bph = implode(',', array_fill(0, count($branchIds), '?'));

// 業務 × 狀態(sub_status) 即時
$_ssStmt = $_db->prepare("
    SELECT u.real_name AS sales_name, c.sub_status, COUNT(*) AS cnt
    FROM cases c
    LEFT JOIN users u ON c.sales_id = u.id
    WHERE c.branch_id IN ({$_bph})
      AND c.sub_status IS NOT NULL AND c.sub_status != ''
      AND c.status NOT IN ('closed','cancelled','已完工結案','客戶取消')
    GROUP BY c.sales_id, c.sub_status
    ORDER BY u.real_name, c.sub_status
");
$_ssStmt->execute($branchIds);
$_ssRows = $_ssStmt->fetchAll(PDO::FETCH_ASSOC);

// 整理成 [業務][狀態] = 數量
$_ssBySales = array();
$_ssAllStatuses = array();
foreach ($_ssRows as $_r) {
    $name = $_r['sales_name'] ?: '(未指派)';
    $ss = $_r['sub_status'];
    if (!isset($_ssBySales[$name])) $_ssBySales[$name] = array();
    $_ssBySales[$name][$ss] = (int)$_r['cnt'];
    $_ssAllStatuses[$ss] = true;
}
$_ssAllStatuses = array_keys($_ssAllStatuses);
sort($_ssAllStatuses);

// 業務 × 進度(status) 即時
$_pgStmt = $_db->prepare("
    SELECT u.real_name AS sales_name, c.status, COUNT(*) AS cnt
    FROM cases c
    LEFT JOIN users u ON c.sales_id = u.id
    WHERE c.branch_id IN ({$_bph})
      AND c.status IS NOT NULL AND c.status != ''
      AND c.status NOT IN ('closed','cancelled','已完工結案','客戶取消')
    GROUP BY c.sales_id, c.status
    ORDER BY u.real_name, c.status
");
$_pgStmt->execute($branchIds);
$_pgRows = $_pgStmt->fetchAll(PDO::FETCH_ASSOC);

$_pgLabelMap = array(
    'tracking'=>'待追蹤','incomplete'=>'未完工','unpaid'=>'完工未收款',
    'completed_pending'=>'已完工待簽核','closed'=>'已完工結案','lost'=>'未成交',
    'maint_case'=>'保養案件','breach'=>'毀約','scheduled'=>'已排工/已排行事曆',
    'needs_reschedule'=>'已進場/需再安排','awaiting_dispatch'=>'待安排派工查修',
    'customer_cancel'=>'客戶取消','maintenance_case'=>'保養案件',
);
$_pgBySales = array();
$_pgAllProgress = array();
foreach ($_pgRows as $_r) {
    $name = $_r['sales_name'] ?: '(未指派)';
    $pg = $_r['status'];
    $pgLabel = isset($_pgLabelMap[$pg]) ? $_pgLabelMap[$pg] : $pg;
    if (!isset($_pgBySales[$name])) $_pgBySales[$name] = array();
    if (!isset($_pgBySales[$name][$pgLabel])) $_pgBySales[$name][$pgLabel] = 0;
    $_pgBySales[$name][$pgLabel] += (int)$_r['cnt'];
    $_pgAllProgress[$pgLabel] = true;
}
$_pgAllProgress = array_keys($_pgAllProgress);
sort($_pgAllProgress);
?>

<!-- 十六、業務 × 狀態 即時統計 -->
<div class="card">
    <div class="card-header analysis-header">十六、業務 × 案件狀態 即時統計<small style="opacity:.7;margin-left:8px">（排除已完工結案/客戶取消）</small></div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th style="text-align:left;min-width:80px">業務</th>
                <?php foreach ($_ssAllStatuses as $_s): ?>
                <th style="font-size:.7rem;white-space:nowrap;padding:4px 6px;text-align:center"><?= e($_s) ?></th>
                <?php endforeach; ?>
                <th style="font-weight:700">合計</th>
            </tr></thead>
            <tbody>
            <?php foreach ($_ssBySales as $_name => $_data):
                $_total = array_sum($_data);
            ?>
                <tr>
                    <td style="text-align:left;font-weight:600;white-space:nowrap"><?= e($_name) ?></td>
                    <?php foreach ($_ssAllStatuses as $_s): ?>
                    <td><?php if (isset($_data[$_s]) && $_data[$_s] > 0): ?><span class="drillable" onclick="drillDown('realtime_status','','<?= e($_name) ?>','<?= e($_name) ?> - <?= e($_s) ?>','<?= e($_s) ?>')"><?= $_data[$_s] ?></span><?php endif; ?></td>
                    <?php endforeach; ?>
                    <td style="font-weight:700"><span class="drillable" onclick="drillDown('realtime_status','','<?= e($_name) ?>','<?= e($_name) ?> - 全部','')"><?= $_total ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 十七、業務 × 案件進度 即時統計 -->
<div class="card">
    <div class="card-header analysis-header">十七、業務 × 案件進度 即時統計<small style="opacity:.7;margin-left:8px">（排除已完工結案/客戶取消）</small></div>
    <div class="table-responsive">
        <table class="table table-sm analysis-table">
            <thead><tr>
                <th style="text-align:left;min-width:80px">業務</th>
                <?php foreach ($_pgAllProgress as $_p): ?>
                <th style="font-size:.7rem;white-space:nowrap;padding:4px 6px;text-align:center"><?= e($_p) ?></th>
                <?php endforeach; ?>
                <th style="font-weight:700">合計</th>
            </tr></thead>
            <tbody>
            <?php foreach ($_pgBySales as $_name => $_data):
                $_total = array_sum($_data);
            ?>
                <tr>
                    <td style="text-align:left;font-weight:600;white-space:nowrap"><?= e($_name) ?></td>
                    <?php foreach ($_pgAllProgress as $_p): ?>
                    <td><?php if (isset($_data[$_p]) && $_data[$_p] > 0): ?><span class="drillable" onclick="drillDown('realtime_progress','','<?= e($_name) ?>','<?= e($_name) ?> - <?= e($_p) ?>','<?= e($_p) ?>')"><?= $_data[$_p] ?></span><?php endif; ?></td>
                    <?php endforeach; ?>
                    <td style="font-weight:700"><span class="drillable" onclick="drillDown('realtime_progress','','<?= e($_name) ?>','<?= e($_name) ?> - 全部','')"><?= $_total ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 進度交叉分析明細 -->
<div id="progressDrillPanel" style="display:none">
    <div class="card" style="margin-top:-8px;border-top:3px solid var(--primary);background:#fafbff">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid #e0e0e0">
            <strong id="progressDrillTitle" style="font-size:.9rem;color:var(--primary)">明細</strong>
            <button class="btn btn-sm" style="padding:2px 10px;font-size:1.1rem;line-height:1;background:none;color:#999" onclick="document.getElementById('progressDrillPanel').style.display='none'">&times;</button>
        </div>
        <div style="padding:8px 12px;overflow-x:auto">
            <div id="progressDrillResult"></div>
        </div>
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
td.drillable, .drillable { cursor: pointer; position: relative; }
td.drillable:hover, td:has(> .drillable):hover { background: #e3f2fd !important; }
.drillable:hover { text-decoration: underline; color: var(--primary); }
</style>

<script>
var drillYear = <?= json_encode($analysis['year']) ?>;

// 十五、進度交叉分析資料
var progressCrossData = <?= json_encode(isset($analysis['progress_cross']) ? $analysis['progress_cross'] : array()) ?>;

function showProgressCases(progressKey, caseKey, title) {
    var panel = document.getElementById('progressDrillPanel');
    var titleEl = document.getElementById('progressDrillTitle');
    var resultEl = document.getElementById('progressDrillResult');
    if (!panel) return;
    titleEl.textContent = title;
    var data = progressCrossData[progressKey];
    if (!data || !data[caseKey] || data[caseKey].length === 0) {
        resultEl.innerHTML = '<p style="color:#999;text-align:center;padding:16px">無資料</p>';
        panel.style.display = '';
        panel.scrollIntoView({behavior:'smooth', block:'start'});
        return;
    }
    var cases = data[caseKey];
    var html = '<table class="table table-sm" style="font-size:.8rem;"><thead><tr>';
    html += '<th>案件編號</th><th>客戶名稱</th><th>業務</th><th>報價金額</th><th>成交金額</th><th>尾款</th><th>狀態</th><th>完工日期</th>';
    html += '</tr></thead><tbody>';
    for (var i = 0; i < cases.length; i++) {
        var c = cases[i];
        html += '<tr>';
        html += '<td><a href="/cases.php?action=view&id=' + c.id + '" target="_blank" style="color:var(--primary);text-decoration:underline;">' + escHtml(c.case_number || '-') + '</a></td>';
        html += '<td>' + escHtml(c.customer_name || '-') + '</td>';
        html += '<td>' + escHtml(c.sales_name || '-') + '</td>';
        html += '<td style="text-align:right">' + (c.quote_amount > 0 ? Number(c.quote_amount).toLocaleString() : '-') + '</td>';
        html += '<td style="text-align:right">' + (c.deal_amount > 0 ? Number(c.deal_amount).toLocaleString() : '-') + '</td>';
        html += '<td style="text-align:right">' + (c.balance_amount > 0 ? Number(c.balance_amount).toLocaleString() : '-') + '</td>';
        html += '<td>' + escHtml(c.sub_status || '-') + '</td>';
        html += '<td>' + escHtml(c.completion_date || '-') + '</td>';
        html += '</tr>';
    }
    html += '</tbody></table>';
    resultEl.innerHTML = html;
    panel.style.display = '';
    panel.scrollIntoView({behavior:'smooth', block:'start'});
}

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
