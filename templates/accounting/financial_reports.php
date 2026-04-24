<div class="page-sticky-head">
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h1>財務報表</h1>
    <div style="display:flex;gap:8px">
        <a href="/accounting.php?action=budget" class="btn btn-secondary">預算編輯</a>
        <a href="/accounting.php?action=journals" class="btn btn-secondary">傳票管理</a>
    </div>
</div>

<!-- 篩選區 -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <form method="get" action="/accounting.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
        <input type="hidden" name="action" value="financial_reports">
        <input type="hidden" name="tab" value="<?= e($frTab) ?>" id="frTabInput">
        <div>
            <label style="font-size:0.85em">年度</label>
            <select name="year" class="form-control" style="width:100px">
                <?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $frYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.85em">起始月</label>
            <select name="month_from" class="form-control" style="width:80px">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $frMonthFrom == $m ? 'selected' : '' ?>><?= $m ?>月</option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.85em">結束月</label>
            <select name="month_to" class="form-control" style="width:80px">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $frMonthTo == $m ? 'selected' : '' ?>><?= $m ?>月</option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.85em">成本中心</label>
            <select name="cost_center_id" class="form-control" style="width:150px">
                <option value="">全部</option>
                <?php foreach ($costCenters as $cc): ?>
                <option value="<?= $cc['id'] ?>" <?= $frCcId == $cc['id'] ? 'selected' : '' ?>><?= e($cc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">查詢</button>
    </form>
</div>

<!-- Tab 選擇 -->
<div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:16px;border-bottom:2px solid var(--gray-200);padding-bottom:0">
<?php
$tabs = array(
    'income_statement' => '損益表',
    'actual_chart' => '實際損益圖',
    'budget_chart' => '預算損益圖',
    'budget_actual_chart' => '預實比較圖',
    'multi_period' => '多期損益比較',
    'monthly_chart' => '月份損益圖',
    'trial_newspaper' => '閱報式試算表',
    'balance_sheet' => '資產負債表',
);
foreach ($tabs as $key => $label): ?>
    <button type="button" class="btn <?= $frTab === $key ? 'btn-primary' : 'btn-secondary' ?>"
            onclick="switchFrTab('<?= $key ?>')"
            style="border-radius:4px 4px 0 0;margin-bottom:-2px;<?= $frTab === $key ? 'border-bottom:2px solid var(--primary)' : '' ?>"><?= $label ?></button>
<?php endforeach; ?>
</div>
</div><!-- /.page-sticky-head -->

<!-- ============================== -->
<!-- Tab 1: 損益表 -->
<!-- ============================== -->
<?php if ($frTab === 'income_statement'): ?>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>損益表</strong> <?= $frYear ?>年 <?= $frMonthFrom ?>月 ~ <?= $frMonthTo ?>月
</div>
<div class="card" style="overflow-x:auto">
    <table class="data-table" style="width:100%">
        <thead>
            <tr>
                <th style="width:100px">科目代碼</th>
                <th>科目名稱</th>
                <th style="width:130px;text-align:right">金額</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $sections = array(
            '4' => array('title' => '營業收入', 'rows' => array(), 'total' => 0),
            '5' => array('title' => '營業成本', 'rows' => array(), 'total' => 0),
            '6' => array('title' => '營業費用', 'rows' => array(), 'total' => 0),
            '7' => array('title' => '營業外收支', 'rows' => array(), 'total' => 0),
            '8' => array('title' => '所得稅費用', 'rows' => array(), 'total' => 0),
        );
        foreach ($plData as $row) {
            $p = $row['code_prefix'];
            if (!isset($sections[$p])) continue;
            if ($p === '4' || ($p === '7' && $row['normal_balance'] === 'credit')) {
                $amt = (float)$row['total_credit'] - (float)$row['total_debit'];
            } else {
                $amt = (float)$row['total_debit'] - (float)$row['total_credit'];
            }
            $sections[$p]['rows'][] = array('code' => $row['code'], 'name' => $row['name'], 'amount' => $amt);
            $sections[$p]['total'] += $amt;
        }
        ?>

        <!-- 營業收入 -->
        <tr style="background:#dee2e6;font-weight:bold"><td colspan="3">營業收入</td></tr>
        <?php foreach ($sections['4']['rows'] as $r): ?>
        <tr><td><?= e($r['code']) ?></td><td><?= e($r['name']) ?></td><td style="text-align:right"><?= number_format($r['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr style="background:#e9ecef;font-weight:bold"><td></td><td>營業收入合計</td><td style="text-align:right"><?= number_format($plSummary['revenue']) ?></td></tr>

        <!-- 營業成本 -->
        <tr style="background:#dee2e6;font-weight:bold"><td colspan="3">營業成本</td></tr>
        <?php foreach ($sections['5']['rows'] as $r): ?>
        <tr><td><?= e($r['code']) ?></td><td><?= e($r['name']) ?></td><td style="text-align:right"><?= number_format($r['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr style="background:#e9ecef;font-weight:bold"><td></td><td>營業成本合計</td><td style="text-align:right">(<?= number_format($plSummary['cost']) ?>)</td></tr>

        <!-- 營業毛利 -->
        <tr style="background:#d4edda;font-weight:bold;font-size:1.05em"><td></td><td>營業毛利</td><td style="text-align:right"><?= number_format($plSummary['gross_profit']) ?></td></tr>

        <!-- 營業費用 -->
        <tr style="background:#dee2e6;font-weight:bold"><td colspan="3">營業費用</td></tr>
        <?php foreach ($sections['6']['rows'] as $r): ?>
        <tr><td><?= e($r['code']) ?></td><td><?= e($r['name']) ?></td><td style="text-align:right"><?= number_format($r['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr style="background:#e9ecef;font-weight:bold"><td></td><td>營業費用合計</td><td style="text-align:right">(<?= number_format($plSummary['expense']) ?>)</td></tr>

        <!-- 營業淨利 -->
        <tr style="background:#d4edda;font-weight:bold;font-size:1.05em"><td></td><td>營業淨利</td><td style="text-align:right"><?= number_format($plSummary['operating_profit']) ?></td></tr>

        <!-- 營業外收支 -->
        <tr style="background:#dee2e6;font-weight:bold"><td colspan="3">營業外收支</td></tr>
        <?php foreach ($sections['7']['rows'] as $r): ?>
        <tr><td><?= e($r['code']) ?></td><td><?= e($r['name']) ?></td><td style="text-align:right"><?= number_format($r['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr style="background:#e9ecef;font-weight:bold"><td></td><td>營業外收支淨額</td><td style="text-align:right"><?= number_format($plSummary['other_income'] - $plSummary['other_expense']) ?></td></tr>

        <!-- 所得稅 -->
        <?php if (!empty($sections['8']['rows'])): ?>
        <tr style="background:#dee2e6;font-weight:bold"><td colspan="3">所得稅費用</td></tr>
        <?php foreach ($sections['8']['rows'] as $r): ?>
        <tr><td><?= e($r['code']) ?></td><td><?= e($r['name']) ?></td><td style="text-align:right"><?= number_format($r['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr style="background:#e9ecef;font-weight:bold"><td></td><td>所得稅合計</td><td style="text-align:right">(<?= number_format($plSummary['tax']) ?>)</td></tr>
        <?php endif; ?>

        <!-- 本期淨利 -->
        <tr style="background:#c3e6cb;font-weight:bold;font-size:1.1em"><td></td><td>本期淨利</td><td style="text-align:right"><?= number_format($plSummary['net_income']) ?></td></tr>
        </tbody>
    </table>
</div>

<!-- ============================== -->
<!-- Tab 2: 實際損益圖 -->
<!-- ============================== -->
<?php elseif ($frTab === 'actual_chart'): ?>
<div class="card" style="padding:16px">
    <h3 style="margin-bottom:16px">實際損益圖 - <?= $frYear ?>年 <?= $frMonthFrom ?>~<?= $frMonthTo ?>月</h3>
    <div style="max-width:800px;margin:0 auto">
        <canvas id="actualChart" height="400"></canvas>
    </div>
</div>

<!-- ============================== -->
<!-- Tab 3: 預算損益圖 -->
<!-- ============================== -->
<?php elseif ($frTab === 'budget_chart'): ?>
<div class="card" style="padding:16px">
    <h3 style="margin-bottom:16px">預算損益圖 - <?= $frYear ?>年 <?= $frMonthFrom ?>~<?= $frMonthTo ?>月</h3>
    <div style="max-width:800px;margin:0 auto">
        <canvas id="budgetChart" height="400"></canvas>
    </div>
</div>

<!-- ============================== -->
<!-- Tab 4: 預實比較圖 -->
<!-- ============================== -->
<?php elseif ($frTab === 'budget_actual_chart'): ?>
<div class="card" style="padding:16px">
    <h3 style="margin-bottom:16px">預算 vs 實際比較 - <?= $frYear ?>年 <?= $frMonthFrom ?>~<?= $frMonthTo ?>月</h3>
    <div style="max-width:900px;margin:0 auto">
        <canvas id="budgetActualChart" height="400"></canvas>
    </div>
</div>

<!-- 達成率表格 -->
<div class="card" style="overflow-x:auto;margin-top:16px">
    <table class="data-table" style="width:100%">
        <thead>
            <tr>
                <th>項目</th>
                <th style="text-align:right">預算</th>
                <th style="text-align:right">實際</th>
                <th style="text-align:right">差異</th>
                <th style="text-align:right">達成率</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $compareItems = array(
            array('label' => '營業收入', 'budget' => $budgetSummary['revenue'], 'actual' => $plSummary['revenue']),
            array('label' => '營業成本', 'budget' => $budgetSummary['cost'], 'actual' => $plSummary['cost']),
            array('label' => '營業費用', 'budget' => $budgetSummary['expense'], 'actual' => $plSummary['expense']),
            array('label' => '營業外收支', 'budget' => $budgetSummary['other'], 'actual' => $plSummary['other_income'] - $plSummary['other_expense']),
            array('label' => '本期淨利', 'budget' => $budgetSummary['revenue'] - $budgetSummary['cost'] - $budgetSummary['expense'] + $budgetSummary['other'] - $budgetSummary['tax'], 'actual' => $plSummary['net_income']),
        );
        foreach ($compareItems as $ci):
            $diff = $ci['actual'] - $ci['budget'];
            $rate = $ci['budget'] != 0 ? ($ci['actual'] / $ci['budget'] * 100) : 0;
        ?>
        <tr>
            <td><strong><?= $ci['label'] ?></strong></td>
            <td style="text-align:right"><?= number_format($ci['budget']) ?></td>
            <td style="text-align:right"><?= number_format($ci['actual']) ?></td>
            <td style="text-align:right;color:<?= $diff >= 0 ? 'green' : 'red' ?>"><?= number_format($diff) ?></td>
            <td style="text-align:right"><?= $ci['budget'] != 0 ? number_format($rate, 1) . '%' : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ============================== -->
<!-- Tab 5: 多期損益比較 -->
<!-- ============================== -->
<?php elseif ($frTab === 'multi_period'): ?>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>多會計期間損益比較表</strong> <?= $frYear ?>年
</div>
<div class="card" style="overflow-x:auto">
    <table class="data-table" style="width:100%;font-size:0.85em">
        <thead>
            <tr>
                <th style="width:80px">科目代碼</th>
                <th style="width:140px">科目名稱</th>
                <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++): ?>
                <th style="text-align:right"><?= $m ?>月</th>
                <?php endfor; ?>
                <th style="text-align:right;font-weight:bold">合計</th>
            </tr>
        </thead>
        <tbody>
        <?php
        // Build monthly map: code => month => amount
        $monthlyMap = array();
        foreach ($monthlyPL as $row) {
            $p = $row['code_prefix'];
            if ($p === '4' || ($p === '7' && $row['normal_balance'] === 'credit')) {
                $amt = (float)$row['total_credit'] - (float)$row['total_debit'];
            } else {
                $amt = (float)$row['total_debit'] - (float)$row['total_credit'];
            }
            $monthlyMap[$row['code']][$row['month']] = $amt;
            if (!isset($monthlyMap[$row['code']]['_name'])) {
                $monthlyMap[$row['code']]['_name'] = $row['name'];
                $monthlyMap[$row['code']]['_prefix'] = $row['code_prefix'];
            }
        }

        $mpGroups = array(
            '4' => array('title' => '營業收入', 'codes' => array()),
            '5' => array('title' => '營業成本', 'codes' => array()),
            '6' => array('title' => '營業費用', 'codes' => array()),
            '7' => array('title' => '營業外收支', 'codes' => array()),
            '8' => array('title' => '所得稅費用', 'codes' => array()),
        );
        foreach ($monthlyMap as $code => $data) {
            $p = $data['_prefix'];
            if (isset($mpGroups[$p])) {
                $mpGroups[$p]['codes'][] = $code;
            }
        }

        foreach ($mpGroups as $gPrefix => $group):
            if (empty($group['codes'])) continue;
        ?>
        <tr style="background:#dee2e6;font-weight:bold">
            <td colspan="<?= ($frMonthTo - $frMonthFrom + 1) + 3 ?>"><?= $group['title'] ?></td>
        </tr>
        <?php
            $groupMonthTotals = array();
            for ($m = $frMonthFrom; $m <= $frMonthTo; $m++) { $groupMonthTotals[$m] = 0; }
            $groupGrandTotal = 0;

            sort($group['codes']);
            foreach ($group['codes'] as $code):
                $data = $monthlyMap[$code];
                $rowTotal = 0;
        ?>
        <tr>
            <td><?= e($code) ?></td>
            <td><?= e($data['_name']) ?></td>
            <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++):
                $val = isset($data[$m]) ? $data[$m] : 0;
                $rowTotal += $val;
                $groupMonthTotals[$m] += $val;
            ?>
            <td style="text-align:right"><?= $val != 0 ? number_format($val) : '' ?></td>
            <?php endfor; ?>
            <td style="text-align:right;font-weight:bold"><?= number_format($rowTotal) ?></td>
        </tr>
        <?php endforeach;
            $groupGrandTotal = array_sum($groupMonthTotals);
        ?>
        <tr style="background:#e9ecef;font-weight:bold">
            <td></td>
            <td><?= $group['title'] ?>小計</td>
            <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++): ?>
            <td style="text-align:right"><?= number_format($groupMonthTotals[$m]) ?></td>
            <?php endfor; ?>
            <td style="text-align:right"><?= number_format($groupGrandTotal) ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- 毛利行 -->
        <tr style="background:#d4edda;font-weight:bold">
            <td></td><td>營業毛利</td>
            <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++): ?>
            <td style="text-align:right"><?= number_format($monthlySum[$m]['revenue'] - $monthlySum[$m]['cost']) ?></td>
            <?php endfor; ?>
            <td style="text-align:right"><?= number_format($plSummary['gross_profit']) ?></td>
        </tr>
        <!-- 營業淨利行 -->
        <tr style="background:#d4edda;font-weight:bold">
            <td></td><td>營業淨利</td>
            <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++): ?>
            <td style="text-align:right"><?= number_format($monthlySum[$m]['revenue'] - $monthlySum[$m]['cost'] - $monthlySum[$m]['expense']) ?></td>
            <?php endfor; ?>
            <td style="text-align:right"><?= number_format($plSummary['operating_profit']) ?></td>
        </tr>
        <!-- 本期淨利行 -->
        <tr style="background:#c3e6cb;font-weight:bold;font-size:1.05em">
            <td></td><td>本期淨利</td>
            <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++): ?>
            <td style="text-align:right"><?= number_format($monthlySum[$m]['net']) ?></td>
            <?php endfor; ?>
            <td style="text-align:right"><?= number_format($plSummary['net_income']) ?></td>
        </tr>
        </tbody>
    </table>
</div>

<!-- ============================== -->
<!-- Tab 6: 月份損益圖 -->
<!-- ============================== -->
<?php elseif ($frTab === 'monthly_chart'): ?>
<div class="card" style="padding:16px">
    <h3 style="margin-bottom:16px">月份損益趨勢圖 - <?= $frYear ?>年</h3>
    <div style="max-width:900px;margin:0 auto">
        <canvas id="monthlyChart" height="400"></canvas>
    </div>
</div>

<!-- ============================== -->
<!-- Tab 7: 閱報式試算表 -->
<!-- ============================== -->
<?php elseif ($frTab === 'trial_newspaper'): ?>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>閱報式試算表</strong> 截止日期: <?= e($dateTo) ?>
</div>
<div style="display:flex;gap:16px;flex-wrap:wrap">
    <!-- 借方（資產+費用） -->
    <div class="card" style="flex:1;min-width:400px;overflow-x:auto">
        <h3 style="padding:12px;background:#dee2e6;margin:0">借方科目（資產 + 費用）</h3>
        <table class="data-table" style="width:100%">
            <thead>
                <tr>
                    <th style="width:80px">代碼</th>
                    <th>科目</th>
                    <th style="width:130px;text-align:right">餘額</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $debitTotal = 0;
            $debitItems = array();
            if (is_array($trialData)) {
                foreach ($trialData as $t) {
                    $code = isset($t['code']) ? $t['code'] : (isset($t['account_code']) ? $t['account_code'] : '');
                    $name = isset($t['name']) ? $t['name'] : (isset($t['account_name']) ? $t['account_name'] : '');
                    $nb = isset($t['normal_balance']) ? $t['normal_balance'] : '';
                    $bal = (float)(isset($t['debit_balance']) ? $t['debit_balance'] : ((float)(isset($t['total_debit']) ? $t['total_debit'] : 0) - (float)(isset($t['total_credit']) ? $t['total_credit'] : 0)));
                    if ($nb === 'debit' || (substr($code, 0, 1) === '1' || substr($code, 0, 1) === '5' || substr($code, 0, 1) === '6')) {
                        if ($bal < 0) $bal = abs($bal);
                        $debitItems[] = array('code' => $code, 'name' => $name, 'balance' => $bal);
                        $debitTotal += $bal;
                    }
                }
            }
            foreach ($debitItems as $di):
            ?>
            <tr>
                <td><?= e($di['code']) ?></td>
                <td><?= e($di['name']) ?></td>
                <td style="text-align:right"><?= number_format($di['balance']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;background:#e9ecef">
                    <td colspan="2">借方合計</td>
                    <td style="text-align:right"><?= number_format($debitTotal) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- 貸方（負債+權益+收入） -->
    <div class="card" style="flex:1;min-width:400px;overflow-x:auto">
        <h3 style="padding:12px;background:#dee2e6;margin:0">貸方科目（負債 + 權益 + 收入）</h3>
        <table class="data-table" style="width:100%">
            <thead>
                <tr>
                    <th style="width:80px">代碼</th>
                    <th>科目</th>
                    <th style="width:130px;text-align:right">餘額</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $creditTotal = 0;
            $creditItems = array();
            if (is_array($trialData)) {
                foreach ($trialData as $t) {
                    $code = isset($t['code']) ? $t['code'] : (isset($t['account_code']) ? $t['account_code'] : '');
                    $name = isset($t['name']) ? $t['name'] : (isset($t['account_name']) ? $t['account_name'] : '');
                    $nb = isset($t['normal_balance']) ? $t['normal_balance'] : '';
                    $bal = (float)(isset($t['credit_balance']) ? $t['credit_balance'] : ((float)(isset($t['total_credit']) ? $t['total_credit'] : 0) - (float)(isset($t['total_debit']) ? $t['total_debit'] : 0)));
                    if ($nb === 'credit' || (substr($code, 0, 1) === '2' || substr($code, 0, 1) === '3' || substr($code, 0, 1) === '4' || substr($code, 0, 1) === '7')) {
                        if ($bal < 0) $bal = abs($bal);
                        $creditItems[] = array('code' => $code, 'name' => $name, 'balance' => $bal);
                        $creditTotal += $bal;
                    }
                }
            }
            foreach ($creditItems as $ci):
            ?>
            <tr>
                <td><?= e($ci['code']) ?></td>
                <td><?= e($ci['name']) ?></td>
                <td style="text-align:right"><?= number_format($ci['balance']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;background:#e9ecef">
                    <td colspan="2">貸方合計</td>
                    <td style="text-align:right"><?= number_format($creditTotal) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php if (abs($debitTotal - $creditTotal) > 0.01): ?>
<div class="alert alert-error" style="margin-top:8px">借貸不平衡！差額: <?= number_format($debitTotal - $creditTotal, 2) ?></div>
<?php else: ?>
<div class="alert alert-success" style="margin-top:8px">借貸平衡</div>
<?php endif; ?>

<!-- ============================== -->
<!-- Tab 8: 資產負債表 -->
<!-- ============================== -->
<?php elseif ($frTab === 'balance_sheet'): ?>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>資產負債表</strong> 截止日期: <?= e($dateTo) ?>
</div>
<div style="display:flex;gap:16px;flex-wrap:wrap">
    <!-- 資產 -->
    <div class="card" style="flex:1;min-width:400px;overflow-x:auto">
        <h3 style="padding:12px;background:#dee2e6;margin:0">資產</h3>
        <table class="data-table" style="width:100%">
            <thead>
                <tr>
                    <th style="width:80px">代碼</th>
                    <th>科目</th>
                    <th style="width:130px;text-align:right">餘額</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $assetTotal = 0;
            foreach ($bsData as $row):
                if ($row['code_prefix'] !== '1') continue;
                $bal = (float)$row['total_debit'] - (float)$row['total_credit'];
                $assetTotal += $bal;
            ?>
            <tr>
                <td><?= e($row['code']) ?></td>
                <td><?= e($row['name']) ?></td>
                <td style="text-align:right"><?= number_format($bal) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;background:#d4edda">
                    <td colspan="2">資產合計</td>
                    <td style="text-align:right"><?= number_format($assetTotal) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- 負債 + 權益 -->
    <div class="card" style="flex:1;min-width:400px;overflow-x:auto">
        <h3 style="padding:12px;background:#dee2e6;margin:0">負債及權益</h3>
        <table class="data-table" style="width:100%">
            <thead>
                <tr>
                    <th style="width:80px">代碼</th>
                    <th>科目</th>
                    <th style="width:130px;text-align:right">餘額</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $liabilityTotal = 0;
            $equityTotal = 0;
            ?>
            <tr style="background:#dee2e6;font-weight:bold"><td colspan="3">負債</td></tr>
            <?php foreach ($bsData as $row):
                if ($row['code_prefix'] !== '2') continue;
                $bal = (float)$row['total_credit'] - (float)$row['total_debit'];
                $liabilityTotal += $bal;
            ?>
            <tr>
                <td><?= e($row['code']) ?></td>
                <td><?= e($row['name']) ?></td>
                <td style="text-align:right"><?= number_format($bal) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:#e9ecef;font-weight:bold"><td></td><td>負債合計</td><td style="text-align:right"><?= number_format($liabilityTotal) ?></td></tr>

            <tr style="background:#dee2e6;font-weight:bold"><td colspan="3">權益</td></tr>
            <?php foreach ($bsData as $row):
                if ($row['code_prefix'] !== '3') continue;
                $bal = (float)$row['total_credit'] - (float)$row['total_debit'];
                $equityTotal += $bal;
            ?>
            <tr>
                <td><?= e($row['code']) ?></td>
                <td><?= e($row['name']) ?></td>
                <td style="text-align:right"><?= number_format($bal) ?></td>
            </tr>
            <?php endforeach; ?>
            <!-- 本期淨利加入權益 -->
            <tr style="font-style:italic">
                <td></td>
                <td>本期淨利（損益結轉）</td>
                <td style="text-align:right"><?= number_format($plSummary['net_income']) ?></td>
            </tr>
            <?php $equityTotal += $plSummary['net_income']; ?>
            <tr style="background:#e9ecef;font-weight:bold"><td></td><td>權益合計</td><td style="text-align:right"><?= number_format($equityTotal) ?></td></tr>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;background:#d4edda">
                    <td colspan="2">負債及權益合計</td>
                    <td style="text-align:right"><?= number_format($liabilityTotal + $equityTotal) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php if (abs($assetTotal - ($liabilityTotal + $equityTotal)) > 0.01): ?>
<div class="alert alert-error" style="margin-top:8px">資產負債不平衡！差額: <?= number_format($assetTotal - $liabilityTotal - $equityTotal, 2) ?></div>
<?php else: ?>
<div class="alert alert-success" style="margin-top:8px">資產 = 負債 + 權益，平衡</div>
<?php endif; ?>

<?php endif; ?>

<!-- ============================== -->
<!-- Chart.js & Tab Script -->
<!-- ============================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function switchFrTab(tab) {
    document.getElementById('frTabInput').value = tab;
    document.querySelector('.card form').submit();
}

<?php if ($frTab === 'actual_chart'): ?>
new Chart(document.getElementById('actualChart'), {
    type: 'bar',
    data: {
        labels: ['營業收入', '營業成本', '營業費用', '營業外收支', '本期淨利'],
        datasets: [{
            label: '實際金額',
            data: [<?= $plSummary['revenue'] ?>, <?= $plSummary['cost'] ?>, <?= $plSummary['expense'] ?>, <?= $plSummary['other_income'] - $plSummary['other_expense'] ?>, <?= $plSummary['net_income'] ?>],
            backgroundColor: ['#28a745', '#dc3545', '#fd7e14', '#17a2b8', <?= $plSummary['net_income'] >= 0 ? "'#007bff'" : "'#dc3545'" ?>]
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return v.toLocaleString(); } } } }
    }
});
<?php endif; ?>

<?php if ($frTab === 'budget_chart'): ?>
(function() {
    var bNet = <?= $budgetSummary['revenue'] ?> - <?= $budgetSummary['cost'] ?> - <?= $budgetSummary['expense'] ?> + <?= $budgetSummary['other'] ?> - <?= $budgetSummary['tax'] ?>;
    new Chart(document.getElementById('budgetChart'), {
        type: 'bar',
        data: {
            labels: ['營業收入', '營業成本', '營業費用', '營業外收支', '本期淨利'],
            datasets: [{
                label: '預算金額',
                data: [<?= $budgetSummary['revenue'] ?>, <?= $budgetSummary['cost'] ?>, <?= $budgetSummary['expense'] ?>, <?= $budgetSummary['other'] ?>, bNet],
                backgroundColor: ['#28a745', '#dc3545', '#fd7e14', '#17a2b8', bNet >= 0 ? '#007bff' : '#dc3545']
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return v.toLocaleString(); } } } }
        }
    });
})();
<?php endif; ?>

<?php if ($frTab === 'budget_actual_chart'): ?>
(function() {
    var bNet = <?= $budgetSummary['revenue'] ?> - <?= $budgetSummary['cost'] ?> - <?= $budgetSummary['expense'] ?> + <?= $budgetSummary['other'] ?> - <?= $budgetSummary['tax'] ?>;
    new Chart(document.getElementById('budgetActualChart'), {
        type: 'bar',
        data: {
            labels: ['營業收入', '營業成本', '營業費用', '營業外收支', '本期淨利'],
            datasets: [
                {
                    label: '預算',
                    data: [<?= $budgetSummary['revenue'] ?>, <?= $budgetSummary['cost'] ?>, <?= $budgetSummary['expense'] ?>, <?= $budgetSummary['other'] ?>, bNet],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: '實際',
                    data: [<?= $plSummary['revenue'] ?>, <?= $plSummary['cost'] ?>, <?= $plSummary['expense'] ?>, <?= $plSummary['other_income'] - $plSummary['other_expense'] ?>, <?= $plSummary['net_income'] ?>],
                    backgroundColor: 'rgba(255, 159, 64, 0.7)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return v.toLocaleString(); } } } }
        }
    });
})();
<?php endif; ?>

<?php if ($frTab === 'monthly_chart'): ?>
new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: [<?php for ($m = 1; $m <= 12; $m++) echo "'" . $m . "月',"; ?>],
        datasets: [
            { label: '營業收入', data: [<?php for ($m = 1; $m <= 12; $m++) echo $monthlySum[$m]['revenue'] . ','; ?>], borderColor: '#28a745', fill: false, tension: 0.3 },
            { label: '營業成本', data: [<?php for ($m = 1; $m <= 12; $m++) echo $monthlySum[$m]['cost'] . ','; ?>], borderColor: '#dc3545', fill: false, tension: 0.3 },
            { label: '營業費用', data: [<?php for ($m = 1; $m <= 12; $m++) echo $monthlySum[$m]['expense'] . ','; ?>], borderColor: '#fd7e14', fill: false, tension: 0.3 },
            { label: '本期淨利', data: [<?php for ($m = 1; $m <= 12; $m++) echo $monthlySum[$m]['net'] . ','; ?>], borderColor: '#007bff', borderWidth: 3, fill: false, tension: 0.3 }
        ]
    },
    options: {
        responsive: true,
        scales: { y: { ticks: { callback: function(v) { return v.toLocaleString(); } } } }
    }
});
<?php endif; ?>
</script>
