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
        <div>
            <label style="font-size:0.85em">科目層級</label>
            <select name="account_view" class="form-control" style="width:140px">
                <option value="all" <?= $frAccountView === 'all' ? 'selected' : '' ?>>全部科目</option>
                <option value="summary" <?= $frAccountView === 'summary' ? 'selected' : '' ?>>僅統馭科目</option>
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
    'balance_sheet_newspaper' => '閱讀式資產負債表',
    'cash_flow' => '現金流量表',
    'is_by_branch' => 'IS 分公司比較',
    'cfs_by_branch' => 'CFS 分公司比較',
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
<?php
// 科目代碼 → 分類帳連結 helper
//   後 3 碼為 000 → 總分類帳（general_ledger）
//   否則 → 明細分類帳（sub_ledger）
if (!function_exists('_frLedgerLink')) {
    function _frLedgerLink($code, $dateFrom, $dateTo, $ccId) {
        $isControl = (strlen($code) >= 3 && substr($code, -3) === '000');
        $tab = $isControl ? 'general_ledger' : 'sub_ledger';
        $url = '/accounting.php?action=journal_reports&tab=' . $tab
             . '&date_from=' . urlencode($dateFrom)
             . '&date_to=' . urlencode($dateTo)
             . '&account_from=' . urlencode($code)
             . '&account_to=' . urlencode($code);
        if ($ccId) $url .= '&cost_center_id=' . (int)$ccId;
        return $url;
    }
}
$_lL = function($code) use ($dateFrom, $dateTo, $frCcId) {
    return _frLedgerLink($code, $dateFrom, $dateTo, $frCcId);
};
?>
<?php if ($frTab === 'income_statement'): ?>
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>損益表</strong> <?= $frYear ?>年 <?= $frMonthFrom ?>月 ~ <?= $frMonthTo ?>月
    <span style="margin-left:12px;color:#666;font-size:.85rem">累計欄 = <?= $frYear ?>/01 ~ <?= $frYear ?>/<?= sprintf('%02d', $frMonthTo) ?>；占比 % 以營業收入為基數</span>
</div>
<div class="card" style="overflow-x:auto">
    <table class="data-table" style="width:100%">
        <thead>
            <tr>
                <th style="width:90px">科目代碼</th>
                <th>科目名稱</th>
                <th style="width:120px;text-align:right">期間金額</th>
                <th style="width:70px;text-align:right">占比 %</th>
                <th style="width:120px;text-align:right">累計</th>
                <th style="width:70px;text-align:right">累計 %</th>
            </tr>
        </thead>
        <tbody>
        <?php
        // 期間 + 累計 雙重資料
        // sections: 4=收入, 5=成本, 6=費用, 7i=營業外收入, 7e=營業外費用, 8=稅
        $sections = array(
            '4'  => array('title' => '營業收入', 'rows' => array()),
            '5'  => array('title' => '營業成本', 'rows' => array()),
            '6'  => array('title' => '營業費用', 'rows' => array()),
            '7i' => array('title' => '營業外收入', 'rows' => array()),
            '7e' => array('title' => '營業外費用', 'rows' => array()),
            '8'  => array('title' => '所得稅費用', 'rows' => array()),
        );
        $signedAmt = function($row) {
            $p = $row['code_prefix'];
            $credit = (float)$row['total_credit'];
            $debit  = (float)$row['total_debit'];
            if ($p === '4' || ($p === '7' && $row['normal_balance'] === 'credit')) return $credit - $debit;
            return $debit - $credit;
        };
        foreach ($plData as $row) {
            $p = $row['code_prefix'];
            $key = $p;
            if ($p === '7') $key = ($row['normal_balance'] === 'credit') ? '7i' : '7e';
            if (!isset($sections[$key])) continue;
            $ytd = isset($plYTDByCode[$row['code']]) ? $signedAmt($plYTDByCode[$row['code']]) : 0;
            $sections[$key]['rows'][] = array(
                'code' => $row['code'], 'name' => $row['name'],
                'amount' => $signedAmt($row), 'ytd' => $ytd,
            );
        }
        // 把僅在 YTD 出現的科目補進去（期間=0）
        foreach ($plYTDByCode as $code => $row) {
            $exists = false;
            foreach ($plData as $r2) { if ($r2['code'] === $code) { $exists = true; break; } }
            if ($exists) continue;
            $p = $row['code_prefix'];
            $key = $p;
            if ($p === '7') $key = ($row['normal_balance'] === 'credit') ? '7i' : '7e';
            if (!isset($sections[$key])) continue;
            $sections[$key]['rows'][] = array(
                'code' => $code, 'name' => $row['name'],
                'amount' => 0, 'ytd' => $signedAmt($row),
            );
        }
        // 渲染前再做一次 code 去重 + 排序（保險，避免任何上游遺漏）
        // 注意：ytd 為 per-code 唯一值，不可累加；只合併 amount
        foreach ($sections as &$s) {
            $_byCode = array();
            foreach ($s['rows'] as $_r) {
                $_k = $_r['code'];
                if (!isset($_byCode[$_k])) {
                    $_byCode[$_k] = $_r;
                } else {
                    $_byCode[$_k]['amount'] += $_r['amount'];
                    // ytd 不累加：所有同 code 列指向同一個 YTD 值
                }
            }
            $s['rows'] = array_values($_byCode);
            usort($s['rows'], function($a, $b) { return strcmp($a['code'], $b['code']); });
        }
        unset($s);

        $rev = max($plSummary['revenue'], 1);
        $revYTD = max($plSummaryYTD['revenue'], 1);
        $pctP = function($v) use ($rev) { return nfmt($v / $rev * 100, 1); };
        $pctY = function($v) use ($revYTD) { return nfmt($v / $revYTD * 100, 1); };

        // 各區段標題顏色（依會計類別）
        $sectionColors = array(
            '營業收入'   => '#cfe2ff', // 藍
            '營業成本'   => '#ffd6cc', // 粉紅
            '營業費用'   => '#ffe5b4', // 橘
            '銷售費用'   => '#ffeacc',
            '管理及總務費用' => '#ffeacc',
            '研究發展費用' => '#ffeacc',
            '其他營業費用' => '#ffeacc',
            '營業外收入' => '#d1ecf1', // 青
            '營業外費用' => '#f8d7da', // 紅
            '所得稅費用' => '#e2d4f0', // 紫
        );
        $renderSection = function($section, $isCost = false) use ($pctP, $pctY, $sectionColors, $_lL) {
            if (empty($section['rows'])) return;
            $bg = isset($sectionColors[$section['title']]) ? $sectionColors[$section['title']] : '#dee2e6';
            echo '<tr style="background:' . $bg . ';font-weight:bold"><td colspan="6">' . e($section['title']) . '</td></tr>';
            foreach ($section['rows'] as $r) {
                echo '<tr>';
                echo '<td><a href="' . e($_lL($r['code'])) . '" style="color:#1565c0;text-decoration:none;font-family:monospace">' . e($r['code']) . '</a></td>';
                echo '<td>' . e($r['name']) . '</td>';
                echo '<td style="text-align:right">' . nfmt($r['amount']) . '</td>';
                echo '<td style="text-align:right;color:#666">' . $pctP($r['amount']) . '</td>';
                echo '<td style="text-align:right">' . nfmt($r['ytd']) . '</td>';
                echo '<td style="text-align:right;color:#666">' . $pctY($r['ytd']) . '</td>';
                echo '</tr>';
            }
        };

        // 小計列底色：依標題對應分類淡色
        $subtotalColors = array(
            '營業收入合計'   => '#e7f0fc',
            '營業成本合計'   => '#fce6df',
            '營業費用合計'   => '#fdf0d9',
            '銷售費用小計'   => '#fdf0d9',
            '管理及總務費用小計' => '#fdf0d9',
            '研究發展費用小計' => '#fdf0d9',
            '其他營業費用小計' => '#fdf0d9',
            '營業外收入合計' => '#e3f1f4',
            '營業外費用合計' => '#fbe7e9',
            '所得稅合計'     => '#ede4f3',
        );
        $renderSubtotal = function($label, $valP, $valY, $bracket = false) use ($pctP, $pctY, $rev, $revYTD, $subtotalColors) {
            $disp = function($v, $br) { return $br ? '(' . nfmt($v) . ')' : nfmt($v); };
            $bg = isset($subtotalColors[$label]) ? $subtotalColors[$label] : '#e9ecef';
            echo '<tr style="background:' . $bg . ';font-weight:bold">';
            echo '<td></td><td>' . e($label) . '</td>';
            echo '<td style="text-align:right">' . $disp($valP, $bracket) . '</td>';
            echo '<td style="text-align:right;color:#666">' . $pctP($valP) . '</td>';
            echo '<td style="text-align:right">' . $disp($valY, $bracket) . '</td>';
            echo '<td style="text-align:right;color:#666">' . $pctY($valY) . '</td>';
            echo '</tr>';
        };

        $renderHighlight = function($label, $valP, $valY, $bg = '#d4edda') use ($pctP, $pctY) {
            echo '<tr style="background:' . $bg . ';font-weight:bold;font-size:1.05em">';
            echo '<td></td><td>' . e($label) . '</td>';
            echo '<td style="text-align:right">' . nfmt($valP) . '</td>';
            echo '<td style="text-align:right">' . $pctP($valP) . '</td>';
            echo '<td style="text-align:right">' . nfmt($valY) . '</td>';
            echo '<td style="text-align:right">' . $pctY($valY) . '</td>';
            echo '</tr>';
        };

        // 1. 營業收入
        $renderSection($sections['4']);
        $renderSubtotal('營業收入合計', $plSummary['revenue'], $plSummaryYTD['revenue']);
        // 2. 營業成本
        $renderSection($sections['5'], true);
        $renderSubtotal('營業成本合計', $plSummary['cost'], $plSummaryYTD['cost'], true);
        // 3. 營業毛利
        $renderHighlight('營業毛利', $plSummary['gross_profit'], $plSummaryYTD['gross_profit']);
        // 4. 營業費用 — 依 2 位前綴拆組（61 銷售費用 / 62 管理及總務費用 / 63 研發費用 / 69 其他）
        $expSubGroups = array(
            '61' => array('title' => '銷售費用', 'rows' => array(), 'sumP' => 0, 'sumY' => 0),
            '62' => array('title' => '管理及總務費用', 'rows' => array(), 'sumP' => 0, 'sumY' => 0),
            '63' => array('title' => '研究發展費用', 'rows' => array(), 'sumP' => 0, 'sumY' => 0),
            '69' => array('title' => '其他營業費用', 'rows' => array(), 'sumP' => 0, 'sumY' => 0),
        );
        foreach ($sections['6']['rows'] as $r) {
            $sub = substr($r['code'], 0, 2);
            if (!isset($expSubGroups[$sub])) $sub = '69';
            $expSubGroups[$sub]['rows'][] = $r;
            $expSubGroups[$sub]['sumP'] += $r['amount'];
            $expSubGroups[$sub]['sumY'] += $r['ytd'];
        }
        $hasAnyExp = false;
        foreach ($expSubGroups as $g) { if (!empty($g['rows'])) { $hasAnyExp = true; break; } }
        if ($hasAnyExp) {
            echo '<tr style="background:#ffe5b4;font-weight:bold"><td colspan="6">營業費用</td></tr>';
            foreach ($expSubGroups as $sub => $g) {
                if (empty($g['rows'])) continue;
                echo '<tr style="background:#fdf0d9;font-weight:600"><td colspan="6" style="padding-left:12px">' . e($g['title']) . '</td></tr>';
                foreach ($g['rows'] as $r) {
                    echo '<tr>';
                    echo '<td><a href="' . e($_lL($r['code'])) . '" style="color:#1565c0;text-decoration:none;font-family:monospace">' . e($r['code']) . '</a></td>';
                    echo '<td>' . e($r['name']) . '</td>';
                    echo '<td style="text-align:right">' . nfmt($r['amount']) . '</td>';
                    echo '<td style="text-align:right;color:#666">' . $pctP($r['amount']) . '</td>';
                    echo '<td style="text-align:right">' . nfmt($r['ytd']) . '</td>';
                    echo '<td style="text-align:right;color:#666">' . $pctY($r['ytd']) . '</td>';
                    echo '</tr>';
                }
                $renderSubtotal($g['title'] . '小計', $g['sumP'], $g['sumY'], true);
            }
        }
        $renderSubtotal('營業費用合計', $plSummary['expense'], $plSummaryYTD['expense'], true);
        // 5. 營業淨利
        $renderHighlight('營業淨利', $plSummary['operating_profit'], $plSummaryYTD['operating_profit']);
        // 6. 營業外收入
        if (!empty($sections['7i']['rows'])) {
            $renderSection($sections['7i']);
            $renderSubtotal('營業外收入合計', $plSummary['other_income'], $plSummaryYTD['other_income']);
        }
        // 7. 營業外費用
        if (!empty($sections['7e']['rows'])) {
            $renderSection($sections['7e']);
            $renderSubtotal('營業外費用合計', $plSummary['other_expense'], $plSummaryYTD['other_expense'], true);
        }
        // 8. 稅前淨利
        $renderHighlight('稅前淨利', $plSummary['pre_tax_income'], $plSummaryYTD['pre_tax_income']);
        // 9. 所得稅
        if (!empty($sections['8']['rows'])) {
            $renderSection($sections['8'], true);
            $renderSubtotal('所得稅合計', $plSummary['tax'], $plSummaryYTD['tax'], true);
        }
        // 10. 本期淨利
        $renderHighlight('本期淨利', $plSummary['net_income'], $plSummaryYTD['net_income'], '#c3e6cb');
        ?>
        </tbody>
    </table>
</div>

<!-- ============================== -->
<!-- Tab 2: 實際損益圖 -->
<!-- ============================== -->
<?php elseif ($frTab === 'actual_chart'): ?>
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
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
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
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
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
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
            <td style="text-align:right"><?= nfmt($ci['budget']) ?></td>
            <td style="text-align:right"><?= nfmt($ci['actual']) ?></td>
            <td style="text-align:right;color:<?= $diff >= 0 ? 'green' : 'red' ?>"><?= nfmt($diff) ?></td>
            <td style="text-align:right"><?= $ci['budget'] != 0 ? nfmt($rate, 1) . '%' : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ============================== -->
<!-- Tab 5: 多期損益比較 -->
<!-- ============================== -->
<?php elseif ($frTab === 'multi_period'): ?>
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>多會計期間損益比較表</strong> <?= $frYear ?>年
</div>
<div class="card" style="overflow-x:auto">
    <table class="data-table" style="width:100%">
        <?php
        // 月份配色：奇數月淡藍、偶數月淡綠
        $monthHeaderBg = function($m) {
            return ($m % 2 === 1) ? '#bbdefb' : '#c8e6c9'; // 淡藍 / 淡綠
        };
        $monthCellBg = function($m) {
            return ($m % 2 === 1) ? '#e3f2fd' : '#dcedc8'; // 更淡的藍 / 更淡的綠
        };
        ?>
        <thead>
            <tr>
                <th style="width:80px">科目代碼</th>
                <th style="width:140px">科目名稱</th>
                <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++):
                    $_hb = $monthHeaderBg($m);
                ?>
                <th style="text-align:right;background:<?= $_hb ?>;border-left:2px solid #c8d1da;padding-left:10px"><?= $m ?>月</th>
                <th style="text-align:right;width:55px;background:#fff;color:#666"><?= $m ?>月 %</th>
                <?php endfor; ?>
                <th style="text-align:right;font-weight:bold;border-left:2px solid #999">合計</th>
                <th style="text-align:right;width:65px">占比 %</th>
            </tr>
        </thead>
        <tbody>
        <?php
        // 占比 % 用期間營業收入合計為基數
        $_mpRevBase = max((float)$plSummary['revenue'], 1);
        $mpPct = function($v) use ($_mpRevBase) { return nfmt($v / $_mpRevBase * 100, 1); };
        // 每月占比 % 以該月營業收入為基數
        $_mpMonRev = array();
        for ($_mm = $frMonthFrom; $_mm <= $frMonthTo; $_mm++) {
            $_mpMonRev[$_mm] = max((float)$monthlySum[$_mm]['revenue'], 1);
        }
        $mpMonPct = function($val, $month) use ($_mpMonRev) {
            return nfmt($val / $_mpMonRev[$month] * 100, 1);
        };

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
            '4' => array('title' => '營業收入',     'codes' => array(), 'bg' => '#cfe2ff'),
            '5' => array('title' => '營業成本',     'codes' => array(), 'bg' => '#ffd6cc'),
            '6' => array('title' => '營業費用',     'codes' => array(), 'bg' => '#ffe5b4'),
            '7' => array('title' => '營業外收支',   'codes' => array(), 'bg' => '#d1ecf1'),
            '8' => array('title' => '所得稅費用',   'codes' => array(), 'bg' => '#e2d4f0'),
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
        <tr style="background:<?= $group['bg'] ?>;font-weight:bold">
            <td colspan="<?= ($frMonthTo - $frMonthFrom + 1) * 2 + 4 ?>"><?= $group['title'] ?></td>
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
            <td><a href="<?= e($_lL($code)) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($code) ?></a></td>
            <td><?= e($data['_name']) ?></td>
            <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++):
                $val = isset($data[$m]) ? $data[$m] : 0;
                $rowTotal += $val;
                $groupMonthTotals[$m] += $val;
                $_tint = $monthCellBg($m);
            ?>
            <td style="text-align:right;background:<?= $_tint ?>;border-left:2px solid #c8d1da;padding-left:10px;color:#2563eb"><?= $val != 0 ? nfmt($val) : '' ?></td>
            <td style="text-align:right;color:#888;font-size:.9em;background:#fff"><?= $val != 0 ? $mpMonPct($val, $m) : '' ?></td>
            <?php endfor; ?>
            <td style="text-align:right;font-weight:bold;border-left:2px solid #999;padding-left:10px;color:#2563eb"><?= nfmt($rowTotal) ?></td>
            <td style="text-align:right;color:#666"><?= $mpPct($rowTotal) ?></td>
        </tr>
        <?php endforeach;
            $groupGrandTotal = array_sum($groupMonthTotals);
        ?>
        <?php
        // 小計列底色：用該分類的淡色版
        $_subBg = array(
            '營業收入' => '#e7f0fc', '營業成本' => '#fce6df', '營業費用' => '#fdf0d9',
            '營業外收支' => '#e3f1f4', '所得稅費用' => '#ede4f3',
        );
        $_mpSubBg = isset($_subBg[$group['title']]) ? $_subBg[$group['title']] : '#fafafa';
        ?>
        <tr style="background:<?= $_mpSubBg ?>;font-weight:bold">
            <td></td>
            <td><?= $group['title'] ?>小計</td>
            <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++): ?>
            <td style="text-align:right;color:#2563eb"><?= nfmt($groupMonthTotals[$m]) ?></td>
            <td style="text-align:right;color:#666;font-size:.9em"><?= $mpMonPct($groupMonthTotals[$m], $m) ?></td>
            <?php endfor; ?>
            <td style="text-align:right;color:#2563eb"><?= nfmt($groupGrandTotal) ?></td>
            <td style="text-align:right"><?= $mpPct($groupGrandTotal) ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- 毛利行 -->
        <tr style="background:#d4edda;font-weight:bold">
            <td></td><td>營業毛利</td>
            <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++):
                $_v = $monthlySum[$m]['revenue'] - $monthlySum[$m]['cost'];
            ?>
            <td style="text-align:right;color:#2563eb"><?= nfmt($_v) ?></td>
            <td style="text-align:right;font-size:.9em"><?= $mpMonPct($_v, $m) ?></td>
            <?php endfor; ?>
            <td style="text-align:right;color:#2563eb"><?= nfmt($plSummary['gross_profit']) ?></td>
            <td style="text-align:right"><?= $mpPct($plSummary['gross_profit']) ?></td>
        </tr>
        <!-- 營業淨利行 -->
        <tr style="background:#d4edda;font-weight:bold">
            <td></td><td>營業淨利</td>
            <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++):
                $_v = $monthlySum[$m]['revenue'] - $monthlySum[$m]['cost'] - $monthlySum[$m]['expense'];
            ?>
            <td style="text-align:right;color:#2563eb"><?= nfmt($_v) ?></td>
            <td style="text-align:right;font-size:.9em"><?= $mpMonPct($_v, $m) ?></td>
            <?php endfor; ?>
            <td style="text-align:right;color:#2563eb"><?= nfmt($plSummary['operating_profit']) ?></td>
            <td style="text-align:right"><?= $mpPct($plSummary['operating_profit']) ?></td>
        </tr>
        <!-- 本期淨利行 -->
        <tr style="background:#c3e6cb;font-weight:bold;font-size:1.05em">
            <td></td><td>本期淨利</td>
            <?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++): ?>
            <td style="text-align:right;color:#2563eb"><?= nfmt($monthlySum[$m]['net']) ?></td>
            <td style="text-align:right;font-size:.9em"><?= $mpMonPct($monthlySum[$m]['net'], $m) ?></td>
            <?php endfor; ?>
            <td style="text-align:right;color:#2563eb"><?= nfmt($plSummary['net_income']) ?></td>
            <td style="text-align:right"><?= $mpPct($plSummary['net_income']) ?></td>
        </tr>
        </tbody>
    </table>
</div>

<!-- ============================== -->
<!-- Tab 6: 月份損益圖 -->
<!-- ============================== -->
<?php elseif ($frTab === 'monthly_chart'): ?>
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
<div class="card" style="padding:16px;margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
        <h3 style="margin:0">月份損益圖 - <?= $frYear ?>年（<?= $frMonthFrom ?>月 ~ <?= $frMonthTo ?>月）</h3>
        <div>
            <button type="button" class="btn btn-sm btn-primary" id="btnChartBar">長條圖</button>
            <button type="button" class="btn btn-sm btn-outline" id="btnChartLine">折線圖</button>
        </div>
    </div>
    <div style="width:95%;margin:0 auto">
        <canvas id="monthlyChart" style="width:100%;max-height:520px"></canvas>
    </div>
</div>
<div style="display:flex;gap:16px;flex-wrap:wrap">
    <div class="card" style="flex:1;min-width:340px;padding:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="margin:0">年度收入結構</h3>
            <button type="button" class="btn btn-sm btn-outline chart-toggle" data-target="pieRevenue" data-mode="pie">切換長條圖</button>
        </div>
        <div style="max-width:420px;margin:0 auto"><canvas id="pieRevenue"></canvas></div>
    </div>
    <div class="card" style="flex:1;min-width:340px;padding:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="margin:0">年度支出結構</h3>
            <button type="button" class="btn btn-sm btn-outline chart-toggle" data-target="pieExpense" data-mode="pie">切換長條圖</button>
        </div>
        <div style="max-width:420px;margin:0 auto"><canvas id="pieExpense"></canvas></div>
    </div>
    <div class="card" style="flex:1;min-width:340px;padding:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="margin:0">收入 vs 成本+費用 占比</h3>
            <button type="button" class="btn btn-sm btn-outline chart-toggle" data-target="pieRevExp" data-mode="doughnut">切換長條圖</button>
        </div>
        <div style="max-width:420px;margin:0 auto"><canvas id="pieRevExp"></canvas></div>
    </div>
</div>

<!-- ============================== -->
<!-- Tab 7: 閱報式試算表 -->
<!-- ============================== -->
<?php elseif ($frTab === 'trial_newspaper'): ?>
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>閱報式試算表</strong> 截止日期: <?= e($dateTo) ?>
</div>
<div style="display:flex;gap:16px;flex-wrap:wrap">
    <!-- 借方（資產+費用） -->
    <div class="card" style="flex:1;min-width:400px;overflow-x:auto">
        <h3 style="padding:12px;background:#cfe2ff;margin:0">借方科目（資產 + 費用）</h3>
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
            // 試算表正確算法：每個科目以淨餘額自動歸屬借/貸方
            //   net = total_debit - total_credit
            //   net > 0 → 借方餘額；net < 0 → 貸方餘額
            $debitTotal = 0;
            $debitItems = array();
            $creditTotal = 0;
            $creditItems = array();
            if (is_array($trialData)) {
                foreach ($trialData as $t) {
                    $code = isset($t['code']) ? $t['code'] : (isset($t['account_code']) ? $t['account_code'] : '');
                    $name = isset($t['name']) ? $t['name'] : (isset($t['account_name']) ? $t['account_name'] : '');
                    $td = (float)(isset($t['total_debit']) ? $t['total_debit'] : 0);
                    $tc = (float)(isset($t['total_credit']) ? $t['total_credit'] : 0);
                    $net = $td - $tc;
                    if ($net > 0) {
                        $debitItems[] = array('code' => $code, 'name' => $name, 'balance' => $net);
                        $debitTotal += $net;
                    } elseif ($net < 0) {
                        $creditItems[] = array('code' => $code, 'name' => $name, 'balance' => -$net);
                        $creditTotal += -$net;
                    }
                }
            }
            foreach ($debitItems as $di):
            ?>
            <tr>
                <td><a href="<?= e($_lL($di['code'])) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($di['code']) ?></a></td>
                <td><?= e($di['name']) ?></td>
                <td style="text-align:right"><?= nfmt($di['balance']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;background:#cfe2ff">
                    <td colspan="2">借方合計</td>
                    <td style="text-align:right"><?= nfmt($debitTotal) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- 貸方（負債+權益+收入） -->
    <div class="card" style="flex:1;min-width:400px;overflow-x:auto">
        <h3 style="padding:12px;background:#f8d7da;margin:0">貸方科目（負債 + 權益 + 收入）</h3>
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
            // creditItems / creditTotal 已在借方面板的同一個 foreach 迴圈中計算
            foreach ($creditItems as $ci):
            ?>
            <tr>
                <td><a href="<?= e($_lL($ci['code'])) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($ci['code']) ?></a></td>
                <td><?= e($ci['name']) ?></td>
                <td style="text-align:right"><?= nfmt($ci['balance']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;background:#f8d7da">
                    <td colspan="2">貸方合計</td>
                    <td style="text-align:right"><?= nfmt($creditTotal) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php if (abs($debitTotal - $creditTotal) > 0.01): ?>
<div class="alert alert-error" style="margin-top:8px">借貸不平衡！差額: <?= nfmt($debitTotal - $creditTotal, 2) ?></div>
<?php else: ?>
<div class="alert alert-success" style="margin-top:8px">借貸平衡</div>
<?php endif; ?>

<!-- ============================== -->
<!-- Tab 8: 資產負債表 -->
<!-- ============================== -->
<?php elseif ($frTab === 'balance_sheet'): ?>
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
<?php
// 預先計算總額供占比 % 用
$_assetTotal = 0; $_liabTotal = 0; $_equityTotal = 0;
foreach ($bsData as $_r) {
    $p = $_r['code_prefix'];
    if ($p === '1') $_assetTotal += (float)$_r['total_debit'] - (float)$_r['total_credit'];
    elseif ($p === '2') $_liabTotal += (float)$_r['total_credit'] - (float)$_r['total_debit'];
    elseif ($p === '3') $_equityTotal += (float)$_r['total_credit'] - (float)$_r['total_debit'];
}
// 用「全期間 4-8 累計」確保 BS 平衡（不受 IS 期間設定影響）
$_bsNi = isset($bsNetIncome) ? (float)$bsNetIncome : (float)$plSummaryYTD['net_income'];
$_equityTotal += $_bsNi;
$_totalLE = $_liabTotal + $_equityTotal;
$_baseAsset = max($_assetTotal, 1);
$_baseLE = max($_totalLE, 1);
$bsPct = function($v, $base) { return nfmt($v / max($base, 1) * 100, 1); };
?>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>資產負債表</strong> 截止日期: <?= e($dateTo) ?>
    <?php $_isBalanced = abs($_assetTotal - $_totalLE) < 1; ?>
    <span style="margin-left:12px;font-weight:700;color:<?= $_isBalanced ? '#16a34a' : '#dc2626' ?>"><?= $_isBalanced ? '✓ 平衡' : '✗ 不平衡（差 ' . nfmt($_assetTotal - $_totalLE) . '）' ?></span>
    <span style="margin-left:12px;color:#666;font-size:.85rem">占比 % = 各科目 ÷ 資產總計（資產側）/ 負債權益總計（右側）</span>
</div>
<!-- 平衡式：資產 = 負債 + 權益 -->
<div class="card" style="padding:12px 16px;margin-bottom:12px;background:linear-gradient(90deg,#cfe2ff 0%,#cfe2ff 33.33%,#f8d7da 33.33%,#f8d7da 66.66%,#e2d4f0 66.66%,#e2d4f0 100%);font-size:1.05rem">
    <div style="display:flex;justify-content:space-around;align-items:center;flex-wrap:wrap;gap:8px">
        <div style="text-align:center"><div style="font-size:.85rem;color:#333">資產合計</div><div style="font-weight:700;font-size:1.2rem"><?= nfmt($_assetTotal) ?></div></div>
        <div style="font-size:1.5rem;font-weight:700">=</div>
        <div style="text-align:center"><div style="font-size:.85rem;color:#333">負債合計</div><div style="font-weight:700;font-size:1.2rem"><?= nfmt($_liabTotal) ?></div></div>
        <div style="font-size:1.5rem;font-weight:700">+</div>
        <div style="text-align:center"><div style="font-size:.85rem;color:#333">權益合計</div><div style="font-weight:700;font-size:1.2rem"><?= nfmt($_equityTotal) ?></div></div>
    </div>
</div>
<div style="display:flex;gap:16px;flex-wrap:wrap">
    <!-- 資產 -->
    <div class="card" style="flex:1;min-width:480px;overflow-x:auto">
        <h3 style="padding:12px;background:#cfe2ff;margin:0">資產</h3>
        <table class="data-table" style="width:100%">
            <thead>
                <tr>
                    <th style="width:80px">代碼</th>
                    <th>科目</th>
                    <th style="width:130px;text-align:right">餘額</th>
                    <th style="width:70px;text-align:right">占比 %</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // 流動 (11) / 非流動 (12-19) 拆組
            $assetCurrent = array(); $assetNonCurrent = array();
            foreach ($bsData as $row) {
                if ($row['code_prefix'] !== '1') continue;
                $bal = (float)$row['total_debit'] - (float)$row['total_credit'];
                $code2 = substr($row['code'], 0, 2);
                if ($code2 === '11') $assetCurrent[] = array('row' => $row, 'bal' => $bal);
                else $assetNonCurrent[] = array('row' => $row, 'bal' => $bal);
            }
            $sumCur = 0; foreach ($assetCurrent as $a) $sumCur += $a['bal'];
            $sumNon = 0; foreach ($assetNonCurrent as $a) $sumNon += $a['bal'];
            ?>
            <?php if (!empty($assetCurrent)): ?>
            <tr style="background:#e3f2fd;font-weight:bold"><td colspan="4">流動資產</td></tr>
            <?php foreach ($assetCurrent as $a): ?>
            <tr>
                <td><a href="<?= e($_lL($a['row']['code'])) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($a['row']['code']) ?></a></td>
                <td><?= e($a['row']['name']) ?></td>
                <td style="text-align:right"><?= nfmt($a['bal']) ?></td>
                <td style="text-align:right;color:#666"><?= $bsPct($a['bal'], $_baseAsset) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:#e7f0fc;font-weight:bold"><td></td><td>流動資產小計</td><td style="text-align:right"><?= nfmt($sumCur) ?></td><td style="text-align:right;color:#666"><?= $bsPct($sumCur, $_baseAsset) ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($assetNonCurrent)): ?>
            <tr style="background:#e3f2fd;font-weight:bold"><td colspan="4">非流動資產</td></tr>
            <?php foreach ($assetNonCurrent as $a): ?>
            <tr>
                <td><a href="<?= e($_lL($a['row']['code'])) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($a['row']['code']) ?></a></td>
                <td><?= e($a['row']['name']) ?></td>
                <td style="text-align:right"><?= nfmt($a['bal']) ?></td>
                <td style="text-align:right;color:#666"><?= $bsPct($a['bal'], $_baseAsset) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:#e7f0fc;font-weight:bold"><td></td><td>非流動資產小計</td><td style="text-align:right"><?= nfmt($sumNon) ?></td><td style="text-align:right;color:#666"><?= $bsPct($sumNon, $_baseAsset) ?></td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;background:#d4edda">
                    <td colspan="2">資產合計</td>
                    <td style="text-align:right"><?= nfmt($_assetTotal) ?></td>
                    <td style="text-align:right">100.0</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- 負債 + 權益 -->
    <div class="card" style="flex:1;min-width:480px;overflow-x:auto">
        <h3 style="padding:12px;background:#f8d7da;margin:0">負債及權益</h3>
        <table class="data-table" style="width:100%">
            <thead>
                <tr>
                    <th style="width:80px">代碼</th>
                    <th>科目</th>
                    <th style="width:130px;text-align:right">餘額</th>
                    <th style="width:70px;text-align:right">占比 %</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // 流動負債 (21) / 非流動負債 (22-29)
            $liabCurrent = array(); $liabNonCurrent = array();
            foreach ($bsData as $row) {
                if ($row['code_prefix'] !== '2') continue;
                $bal = (float)$row['total_credit'] - (float)$row['total_debit'];
                $code2 = substr($row['code'], 0, 2);
                if ($code2 === '21') $liabCurrent[] = array('row' => $row, 'bal' => $bal);
                else $liabNonCurrent[] = array('row' => $row, 'bal' => $bal);
            }
            $sumLC = 0; foreach ($liabCurrent as $a) $sumLC += $a['bal'];
            $sumLN = 0; foreach ($liabNonCurrent as $a) $sumLN += $a['bal'];
            ?>
            <tr style="background:#f8d7da;font-weight:bold"><td colspan="4">負債</td></tr>
            <?php if (!empty($liabCurrent)): ?>
            <tr style="background:#fff3e0;font-weight:bold"><td colspan="4">流動負債</td></tr>
            <?php foreach ($liabCurrent as $a): ?>
            <tr>
                <td><a href="<?= e($_lL($a['row']['code'])) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($a['row']['code']) ?></a></td>
                <td><?= e($a['row']['name']) ?></td>
                <td style="text-align:right"><?= nfmt($a['bal']) ?></td>
                <td style="text-align:right;color:#666"><?= $bsPct($a['bal'], $_baseLE) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:#fbe7e9;font-weight:bold"><td></td><td>流動負債小計</td><td style="text-align:right"><?= nfmt($sumLC) ?></td><td style="text-align:right;color:#666"><?= $bsPct($sumLC, $_baseLE) ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($liabNonCurrent)): ?>
            <tr style="background:#fff3e0;font-weight:bold"><td colspan="4">非流動負債</td></tr>
            <?php foreach ($liabNonCurrent as $a): ?>
            <tr>
                <td><a href="<?= e($_lL($a['row']['code'])) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($a['row']['code']) ?></a></td>
                <td><?= e($a['row']['name']) ?></td>
                <td style="text-align:right"><?= nfmt($a['bal']) ?></td>
                <td style="text-align:right;color:#666"><?= $bsPct($a['bal'], $_baseLE) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:#fbe7e9;font-weight:bold"><td></td><td>非流動負債小計</td><td style="text-align:right"><?= nfmt($sumLN) ?></td><td style="text-align:right;color:#666"><?= $bsPct($sumLN, $_baseLE) ?></td></tr>
            <?php endif; ?>
            <tr style="background:#fbe7e9;font-weight:bold"><td></td><td>負債合計</td><td style="text-align:right"><?= nfmt($_liabTotal) ?></td><td style="text-align:right;color:#666"><?= $bsPct($_liabTotal, $_baseLE) ?></td></tr>

            <tr style="background:#e2d4f0;font-weight:bold"><td colspan="4">權益</td></tr>
            <?php foreach ($bsData as $row):
                if ($row['code_prefix'] !== '3') continue;
                $bal = (float)$row['total_credit'] - (float)$row['total_debit'];
            ?>
            <tr>
                <td><a href="<?= e($_lL($row['code'])) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($row['code']) ?></a></td>
                <td><?= e($row['name']) ?></td>
                <td style="text-align:right"><?= nfmt($bal) ?></td>
                <td style="text-align:right;color:#666"><?= $bsPct($bal, $_baseLE) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="font-style:italic">
                <td></td>
                <td>本期淨利（損益結轉）</td>
                <td style="text-align:right"><?= nfmt($_bsNi) ?></td>
                <td style="text-align:right;color:#666"><?= $bsPct($_bsNi, $_baseLE) ?></td>
            </tr>
            <tr style="background:#ede4f3;font-weight:bold"><td></td><td>權益合計</td><td style="text-align:right"><?= nfmt($_equityTotal) ?></td><td style="text-align:right;color:#666"><?= $bsPct($_equityTotal, $_baseLE) ?></td></tr>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;background:#d4edda">
                    <td colspan="2">負債及權益合計</td>
                    <td style="text-align:right"><?= nfmt($_totalLE) ?></td>
                    <td style="text-align:right">100.0</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php if (abs($assetTotal - ($liabilityTotal + $equityTotal)) > 0.01): ?>
<div class="alert alert-error" style="margin-top:8px">資產負債不平衡！差額: <?= nfmt($assetTotal - $liabilityTotal - $equityTotal, 2) ?></div>
<?php else: ?>
<div class="alert alert-success" style="margin-top:8px">資產 = 負債 + 權益，平衡</div>
<?php endif; ?>

<!-- ============================== -->
<!-- Tab 9: 閱讀式資產負債表 -->
<!-- ============================== -->
<?php elseif ($frTab === 'balance_sheet_newspaper'): ?>
<?php
// 預先計算總額
$_assetTotal = 0; $_liabTotal = 0; $_equityTotal = 0;
foreach ($bsData as $_r) {
    $p = $_r['code_prefix'];
    if ($p === '1') $_assetTotal += (float)$_r['total_debit'] - (float)$_r['total_credit'];
    elseif ($p === '2') $_liabTotal += (float)$_r['total_credit'] - (float)$_r['total_debit'];
    elseif ($p === '3') $_equityTotal += (float)$_r['total_credit'] - (float)$_r['total_debit'];
}
$_bsNi = isset($bsNetIncome) ? (float)$bsNetIncome : (float)$plSummaryYTD['net_income'];
$_equityTotal += $_bsNi;
$_totalLE = $_liabTotal + $_equityTotal;
$_isBalanced = abs($_assetTotal - $_totalLE) < 1;
?>
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>閱讀式資產負債表</strong> 截止日期: <?= e($dateTo) ?>
    <span style="margin-left:12px;font-weight:700;color:<?= $_isBalanced ? '#16a34a' : '#dc2626' ?>"><?= $_isBalanced ? '✓ 平衡' : '✗ 不平衡（差 ' . nfmt($_assetTotal - $_totalLE) . '）' ?></span>
</div>
<div style="display:flex;gap:16px;flex-wrap:wrap">
    <!-- 資產 -->
    <div class="card" style="flex:1;min-width:400px;overflow-x:auto">
        <h3 style="padding:12px;background:#cfe2ff;margin:0">資產</h3>
        <table class="data-table" style="width:100%">
            <thead>
                <tr>
                    <th style="width:90px">代碼</th>
                    <th>科目</th>
                    <th style="width:140px;text-align:right">餘額</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bsData as $row):
                if ($row['code_prefix'] !== '1') continue;
                $bal = (float)$row['total_debit'] - (float)$row['total_credit'];
            ?>
            <tr>
                <td><a href="<?= e($_lL($row['code'])) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($row['code']) ?></a></td>
                <td><?= e($row['name']) ?></td>
                <td style="text-align:right"><?= nfmt($bal) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;background:#cfe2ff">
                    <td colspan="2">資產合計</td>
                    <td style="text-align:right"><?= nfmt($_assetTotal) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <!-- 負債 + 權益 -->
    <div class="card" style="flex:1;min-width:400px;overflow-x:auto">
        <h3 style="padding:12px;background:#f8d7da;margin:0">負債及權益</h3>
        <table class="data-table" style="width:100%">
            <thead>
                <tr>
                    <th style="width:90px">代碼</th>
                    <th>科目</th>
                    <th style="width:140px;text-align:right">餘額</th>
                </tr>
            </thead>
            <tbody>
            <tr style="background:#fbe7e9;font-weight:600"><td colspan="3">負債</td></tr>
            <?php foreach ($bsData as $row):
                if ($row['code_prefix'] !== '2') continue;
                $bal = (float)$row['total_credit'] - (float)$row['total_debit'];
            ?>
            <tr>
                <td><a href="<?= e($_lL($row['code'])) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($row['code']) ?></a></td>
                <td><?= e($row['name']) ?></td>
                <td style="text-align:right"><?= nfmt($bal) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:#fbe7e9;font-weight:bold">
                <td colspan="2">負債合計</td>
                <td style="text-align:right"><?= nfmt($_liabTotal) ?></td>
            </tr>

            <tr style="background:#ede4f3;font-weight:600"><td colspan="3">權益</td></tr>
            <?php foreach ($bsData as $row):
                if ($row['code_prefix'] !== '3') continue;
                $bal = (float)$row['total_credit'] - (float)$row['total_debit'];
            ?>
            <tr>
                <td><a href="<?= e($_lL($row['code'])) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($row['code']) ?></a></td>
                <td><?= e($row['name']) ?></td>
                <td style="text-align:right"><?= nfmt($bal) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="font-style:italic">
                <td></td>
                <td>本期淨利（損益結轉）</td>
                <td style="text-align:right"><?= nfmt($_bsNi) ?></td>
            </tr>
            <tr style="background:#ede4f3;font-weight:bold">
                <td colspan="2">權益合計</td>
                <td style="text-align:right"><?= nfmt($_equityTotal) ?></td>
            </tr>
            </tbody>
            <tfoot>
                <tr style="font-weight:bold;background:#d4edda">
                    <td colspan="2">負債及權益合計</td>
                    <td style="text-align:right"><?= nfmt($_totalLE) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- ============================== -->
<!-- Tab 10: 現金流量表（直接法）-->
<!-- ============================== -->
<?php elseif ($frTab === 'cash_flow'): ?>
<?php
$_cfsSecLabels = array(
    'operating' => array('營運活動現金流量', '#cfe2ff'),
    'investing' => array('投資活動現金流量', '#e2d4f0'),
    'financing' => array('籌資活動現金流量', '#fdf0d9'),
);
$_cfsNetByCat = array();
foreach ($cfsData['sections'] as $catKey => $sec) {
    $_cfsNetByCat[$catKey] = $sec['inflow'] - $sec['outflow'];
}
$_cfsTotalNet = array_sum($_cfsNetByCat);
?>
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>現金流量表（直接法）</strong>
    期間: <?= e($dateFrom) ?> ~ <?= e($dateTo) ?>
    <span style="margin-left:12px;color:#666;font-size:.85rem">現金科目：1111* 現金、1112* 零用金/備用金、1113* 銀行存款</span>
</div>

<div class="card" style="overflow-x:auto">
    <table class="data-table" style="width:100%">
        <thead>
            <tr style="background:#f5f5f5">
                <th style="width:90px">代碼</th>
                <th>科目</th>
                <th style="width:130px;text-align:right">現金流入</th>
                <th style="width:130px;text-align:right">現金流出</th>
                <th style="width:130px;text-align:right">淨額</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($_cfsSecLabels as $catKey => $sl):
            $sec = $cfsData['sections'][$catKey];
            $secNet = $sec['inflow'] - $sec['outflow'];
        ?>
        <tr style="background:<?= $sl[1] ?>;font-weight:bold">
            <td colspan="5"><?= e($sl[0]) ?></td>
        </tr>
        <?php if (empty($sec['items'])): ?>
        <tr><td colspan="5" style="text-align:center;color:#999;padding:8px">本期間無資料</td></tr>
        <?php else: ?>
        <?php foreach ($sec['items'] as $it):
            $itNet = $it['inflow'] - $it['outflow'];
        ?>
        <tr>
            <td><a href="<?= e($_lL($it['code'])) ?>" style="color:#1565c0;text-decoration:none;font-family:monospace"><?= e($it['code']) ?></a></td>
            <td><?= e($it['name']) ?></td>
            <td style="text-align:right;color:#16a34a"><?= $it['inflow'] > 0 ? nfmt($it['inflow']) : '' ?></td>
            <td style="text-align:right;color:#dc2626"><?= $it['outflow'] > 0 ? '(' . nfmt($it['outflow']) . ')' : '' ?></td>
            <td style="text-align:right;font-weight:600"><?= nfmt($itNet) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        <tr style="background:#e9ecef;font-weight:bold">
            <td colspan="2" style="text-align:right"><?= e($sl[0]) ?> 小計</td>
            <td style="text-align:right;color:#16a34a"><?= nfmt($sec['inflow']) ?></td>
            <td style="text-align:right;color:#dc2626"><?= '(' . nfmt($sec['outflow']) . ')' ?></td>
            <td style="text-align:right;font-weight:bold;color:<?= $secNet >= 0 ? '#16a34a' : '#dc2626' ?>"><?= nfmt($secNet) ?></td>
        </tr>
        <?php endforeach; ?>
        <!-- 本期現金及約當現金增減 -->
        <tr style="background:#d4edda;font-weight:bold;font-size:1.05em">
            <td colspan="4" style="text-align:right">本期現金及約當現金淨增（減）</td>
            <td style="text-align:right;color:<?= $_cfsTotalNet >= 0 ? '#16a34a' : '#dc2626' ?>"><?= nfmt($_cfsTotalNet) ?></td>
        </tr>
        <tr>
            <td colspan="4" style="text-align:right">期初現金及約當現金</td>
            <td style="text-align:right"><?= nfmt($cfsData['start_cash']) ?></td>
        </tr>
        <tr style="background:#c3e6cb;font-weight:bold;font-size:1.1em">
            <td colspan="4" style="text-align:right">期末現金及約當現金</td>
            <td style="text-align:right"><?= nfmt($cfsData['end_cash']) ?></td>
        </tr>
        </tbody>
    </table>
</div>

<?php
$_cfsCheck = $cfsData['start_cash'] + $_cfsTotalNet;
$_cfsDiff = $_cfsCheck - $cfsData['end_cash'];
?>
<?php if (abs($_cfsDiff) > 1): ?>
<div class="alert alert-error" style="margin-top:8px">
    現金流量表不平衡（差 <?= nfmt($_cfsDiff) ?>）— 期初+淨增 ≠ 期末。
    可能因內部現金科目間轉帳（如 銀行 → 零用金）也被計入。
</div>
<?php else: ?>
<div class="alert alert-success" style="margin-top:8px">✓ 期初 + 本期淨增（減）= 期末，現金流量表平衡</div>
<?php endif; ?>

<!-- ============================== -->
<!-- Tab 11: IS 分公司比較 -->
<!-- ============================== -->
<?php elseif ($frTab === 'is_by_branch'): ?>
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>損益表 — 分公司（成本中心）比較</strong>
    期間: <?= e($dateFrom) ?> ~ <?= e($dateTo) ?>
</div>
<!-- 分析圖 -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <h3 style="margin-bottom:12px">分公司損益分析圖</h3>
    <div style="width:95%;margin:0 auto">
        <canvas id="isBranchChart" style="width:100%;height:560px"></canvas>
    </div>
</div>
<div class="card" style="overflow-x:auto">
    <table class="data-table" style="width:100%">
        <thead>
            <tr style="background:#f5f5f5">
                <th style="width:160px">項目</th>
                <?php foreach ($branchCompare as $b): ?>
                <th style="text-align:right"><?= e($b['name']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php
        $isRows = array(
            array('label' => '營業收入', 'key' => 'revenue', 'highlight' => false),
            array('label' => '營業成本', 'key' => 'cost', 'highlight' => false, 'bracket' => true),
            array('label' => '營業毛利', 'key' => 'gross_profit', 'highlight' => true),
            array('label' => '營業費用', 'key' => 'expense', 'highlight' => false, 'bracket' => true),
            array('label' => '營業淨利', 'key' => 'operating_profit', 'highlight' => true),
            array('label' => '營業外收入', 'key' => 'other_income', 'highlight' => false),
            array('label' => '營業外費用', 'key' => 'other_expense', 'highlight' => false, 'bracket' => true),
            array('label' => '稅前淨利', 'key' => 'pre_tax_income', 'highlight' => true),
            array('label' => '所得稅', 'key' => 'tax', 'highlight' => false, 'bracket' => true),
            array('label' => '本期淨利', 'key' => 'net_income', 'highlight' => true, 'big' => true),
        );
        foreach ($isRows as $row):
            $bg = '';
            if (!empty($row['big'])) $bg = 'background:#c3e6cb;font-weight:bold;font-size:1.05em';
            elseif (!empty($row['highlight'])) $bg = 'background:#d4edda;font-weight:bold';
        ?>
        <tr style="<?= $bg ?>">
            <td><?= e($row['label']) ?></td>
            <?php foreach ($branchCompare as $b):
                $v = isset($b['is'][$row['key']]) ? (float)$b['is'][$row['key']] : 0;
            ?>
            <td style="text-align:right"><?= !empty($row['bracket']) && $v > 0 ? '(' . nfmt($v) . ')' : nfmt($v) ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ============================== -->
<!-- Tab 12: CFS 分公司比較 -->
<!-- ============================== -->
<?php elseif ($frTab === 'cfs_by_branch'): ?>
<div style="text-align:center;font-size:1.4rem;font-weight:700;margin:8px 0 4px">禾順監視數位科技有限公司</div>
<div class="card" style="padding:12px;margin-bottom:8px;background:#f8f9fa">
    <strong>現金流量表 — 分公司（成本中心）比較</strong>
    期間: <?= e($dateFrom) ?> ~ <?= e($dateTo) ?>
</div>
<!-- 分析圖 -->
<div class="card" style="padding:16px;margin-bottom:16px">
    <h3 style="margin-bottom:12px">分公司現金流量分析圖</h3>
    <div style="width:95%;margin:0 auto">
        <canvas id="cfsBranchChart" style="width:100%;height:560px"></canvas>
    </div>
</div>
<div class="card" style="overflow-x:auto">
    <table class="data-table" style="width:100%">
        <thead>
            <tr style="background:#f5f5f5">
                <th style="width:200px">項目</th>
                <?php foreach ($branchCompare as $b): ?>
                <th style="text-align:right"><?= e($b['name']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php
        $cfsRows = array(
            array('label' => '營運活動 — 流入', 'cat' => 'operating', 'side' => 'inflow', 'color' => '#16a34a'),
            array('label' => '營運活動 — 流出', 'cat' => 'operating', 'side' => 'outflow', 'color' => '#dc2626', 'bracket' => true),
            array('label' => '營運活動淨額', 'cat' => 'operating', 'side' => 'net', 'highlight' => '#cfe2ff'),
            array('label' => '投資活動 — 流入', 'cat' => 'investing', 'side' => 'inflow', 'color' => '#16a34a'),
            array('label' => '投資活動 — 流出', 'cat' => 'investing', 'side' => 'outflow', 'color' => '#dc2626', 'bracket' => true),
            array('label' => '投資活動淨額', 'cat' => 'investing', 'side' => 'net', 'highlight' => '#e2d4f0'),
            array('label' => '籌資活動 — 流入', 'cat' => 'financing', 'side' => 'inflow', 'color' => '#16a34a'),
            array('label' => '籌資活動 — 流出', 'cat' => 'financing', 'side' => 'outflow', 'color' => '#dc2626', 'bracket' => true),
            array('label' => '籌資活動淨額', 'cat' => 'financing', 'side' => 'net', 'highlight' => '#fdf0d9'),
            array('label' => '本期現金淨增（減）', 'cat' => '_total_net', 'side' => '', 'highlight' => '#d4edda', 'bold' => true),
            array('label' => '期初現金', 'cat' => '_start_cash', 'side' => ''),
            array('label' => '期末現金', 'cat' => '_end_cash', 'side' => '', 'highlight' => '#c3e6cb', 'bold' => true),
        );
        foreach ($cfsRows as $row):
            $bg = '';
            if (!empty($row['highlight'])) $bg = 'background:' . $row['highlight'] . ';';
            if (!empty($row['bold'])) $bg .= 'font-weight:bold;font-size:1.05em';
        ?>
        <tr style="<?= $bg ?>">
            <td><?= e($row['label']) ?></td>
            <?php foreach ($branchCompare as $b):
                $cfs = isset($b['cfs']) ? $b['cfs'] : null;
                $v = 0;
                if ($cfs) {
                    if ($row['cat'] === '_total_net') {
                        $v = ($cfs['sections']['operating']['inflow'] - $cfs['sections']['operating']['outflow'])
                           + ($cfs['sections']['investing']['inflow'] - $cfs['sections']['investing']['outflow'])
                           + ($cfs['sections']['financing']['inflow'] - $cfs['sections']['financing']['outflow']);
                    } elseif ($row['cat'] === '_start_cash') {
                        $v = $cfs['start_cash'];
                    } elseif ($row['cat'] === '_end_cash') {
                        $v = $cfs['end_cash'];
                    } elseif ($row['side'] === 'net') {
                        $v = $cfs['sections'][$row['cat']]['inflow'] - $cfs['sections'][$row['cat']]['outflow'];
                    } else {
                        $v = $cfs['sections'][$row['cat']][$row['side']];
                    }
                }
                $color = isset($row['color']) ? $row['color'] : '';
            ?>
            <td style="text-align:right;<?= $color ? 'color:' . $color : '' ?>">
                <?= !empty($row['bracket']) && $v > 0 ? '(' . nfmt($v) . ')' : nfmt($v) ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<!-- ============================== -->
<!-- Chart.js & Tab Script -->
<!-- ============================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>if (window.Chart && window.ChartDataLabels) Chart.register(ChartDataLabels);</script>
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
// 月份圖（依篩選月份自動縮放，全頁 95% 寬，可切換長條/折線）
window._monthlyChartConfig = {
    type: 'bar',
    data: {
        labels: [<?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++) echo "'" . $m . "月',"; ?>],
        datasets: [
            { label: '營業收入', data: [<?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++) echo $monthlySum[$m]['revenue'] . ','; ?>], backgroundColor: '#00C853' },
            { label: '營業成本', data: [<?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++) echo $monthlySum[$m]['cost'] . ','; ?>], backgroundColor: '#FF4081' },
            { label: '營業費用', data: [<?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++) echo $monthlySum[$m]['expense'] . ','; ?>], backgroundColor: '#FFEA00' },
            { label: '本期淨利', data: [<?php for ($m = $frMonthFrom; $m <= $frMonthTo; $m++) echo $monthlySum[$m]['net'] . ','; ?>], backgroundColor: '#2962FF' }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: { font: { size: 14, weight: 'bold' }, boxWidth: 18, padding: 14 }
            },
            datalabels: {
                anchor: 'end', align: 'top',
                color: function(ctx) { return ctx.dataset.data[ctx.dataIndex] < 0 ? '#dc2626' : '#333'; },
                font: { size: 13, weight: 'bold' },
                formatter: function(v) { return v ? v.toLocaleString() : ''; }
            }
        },
        scales: {
            x: { ticks: { font: { size: 13 } } },
            y: { ticks: { font: { size: 13 }, callback: function(v) { return v.toLocaleString(); } } }
        }
    }
};
window._monthlyChart = new Chart(document.getElementById('monthlyChart'), window._monthlyChartConfig);

// 切換長條/折線
function _switchMonthlyChart(type) {
    var cfg = JSON.parse(JSON.stringify(window._monthlyChartConfig));
    cfg.type = type;
    if (type === 'line') {
        cfg.data.datasets.forEach(function(ds) {
            ds.borderColor = ds.backgroundColor;
            ds.backgroundColor = 'transparent';
            ds.borderWidth = 3;
            ds.tension = 0.3;
            ds.fill = false;
            ds.pointRadius = 5;
        });
    }
    // 重新註冊 datalabels（JSON.stringify 會丟掉 function）
    cfg.options.plugins.datalabels.formatter = function(v) { return v ? v.toLocaleString() : ''; };
    cfg.options.scales.y.ticks.callback = function(v) { return v.toLocaleString(); };
    window._monthlyChart.destroy();
    window._monthlyChart = new Chart(document.getElementById('monthlyChart'), cfg);
}
document.getElementById('btnChartBar').addEventListener('click', function() {
    _switchMonthlyChart('bar');
    this.classList.remove('btn-outline'); this.classList.add('btn-primary');
    var b = document.getElementById('btnChartLine');
    b.classList.remove('btn-primary'); b.classList.add('btn-outline');
});
document.getElementById('btnChartLine').addEventListener('click', function() {
    _switchMonthlyChart('line');
    this.classList.remove('btn-outline'); this.classList.add('btn-primary');
    var b = document.getElementById('btnChartBar');
    b.classList.remove('btn-primary'); b.classList.add('btn-outline');
});

// 年度收入結構（4xxx 各統馭科目占比）
<?php
$pieRevenue = array();
foreach ($plDataYTD as $r) {
    if ($r['code_prefix'] !== '4') continue;
    $amt = (float)$r['total_credit'] - (float)$r['total_debit'];
    if ($amt <= 0) continue;
    $pieRevenue[$r['name']] = (isset($pieRevenue[$r['name']]) ? $pieRevenue[$r['name']] : 0) + $amt;
}
arsort($pieRevenue);
$pieRevenue = array_slice($pieRevenue, 0, 8, true); // 只取前 8 個避免過多
?>
window._pieCharts = window._pieCharts || {};
window._pieCharts.pieRevenue = new Chart(document.getElementById('pieRevenue'), {
    type: 'pie',
    data: {
        labels: [<?php foreach ($pieRevenue as $k => $v) echo "'" . addslashes($k) . "',"; ?>],
        datasets: [{
            data: [<?php foreach ($pieRevenue as $v) echo $v . ','; ?>],
            backgroundColor: ['#00E676','#2979FF','#FFD600','#FF1744','#D500F9','#FF9100','#00E5FF','#FF4081']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        layout: { padding: 8 },
        plugins: {
            legend: { position: 'right', labels: { boxWidth: 14, font: { size: 11 } } },
            datalabels: {
                color: '#fff', font: { weight: 'bold', size: 10 },
                textStrokeColor: 'rgba(0,0,0,.65)', textStrokeWidth: 3,
                anchor: 'center', align: 'center',
                formatter: function(v, ctx) {
                    var sum = ctx.chart.data.datasets[0].data.reduce(function(a, b) { return a + b; }, 0);
                    var pct = sum > 0 ? (v / sum * 100).toFixed(1) : 0;
                    if (pct < 5) return ''; // 小於 5% 不顯示，避免重疊
                    return pct + '%';
                }
            },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        var sum = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                        var pct = sum > 0 ? (ctx.parsed / sum * 100).toFixed(1) : 0;
                        return ctx.label + '：' + ctx.parsed.toLocaleString() + '（' + pct + '%）';
                    }
                }
            }
        }
    }
});

// 年度支出結構（5xxx + 6xxx 各統馭科目占比）
<?php
$pieExpense = array();
foreach ($plDataYTD as $r) {
    if ($r['code_prefix'] !== '5' && $r['code_prefix'] !== '6') continue;
    $amt = (float)$r['total_debit'] - (float)$r['total_credit'];
    if ($amt <= 0) continue;
    $pieExpense[$r['name']] = (isset($pieExpense[$r['name']]) ? $pieExpense[$r['name']] : 0) + $amt;
}
arsort($pieExpense);
$pieExpense = array_slice($pieExpense, 0, 8, true);
?>
window._pieCharts.pieExpense = new Chart(document.getElementById('pieExpense'), {
    type: 'pie',
    data: {
        labels: [<?php foreach ($pieExpense as $k => $v) echo "'" . addslashes($k) . "',"; ?>],
        datasets: [{
            data: [<?php foreach ($pieExpense as $v) echo $v . ','; ?>],
            backgroundColor: ['#FF1744','#FF9100','#FFD600','#D500F9','#00E676','#00E5FF','#FF4081','#76FF03']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        layout: { padding: 8 },
        plugins: {
            legend: { position: 'right', labels: { boxWidth: 14, font: { size: 11 } } },
            datalabels: {
                color: '#fff', font: { weight: 'bold', size: 10 },
                textStrokeColor: 'rgba(0,0,0,.65)', textStrokeWidth: 3,
                anchor: 'center', align: 'center',
                formatter: function(v, ctx) {
                    var sum = ctx.chart.data.datasets[0].data.reduce(function(a, b) { return a + b; }, 0);
                    var pct = sum > 0 ? (v / sum * 100).toFixed(1) : 0;
                    if (pct < 5) return ''; // 小於 5% 不顯示，避免重疊
                    return pct + '%';
                }
            },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        var sum = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                        var pct = sum > 0 ? (ctx.parsed / sum * 100).toFixed(1) : 0;
                        return ctx.label + '：' + ctx.parsed.toLocaleString() + '（' + pct + '%）';
                    }
                }
            }
        }
    }
});

// 收入 vs 成本+費用 圓餅
window._pieCharts.pieRevExp = new Chart(document.getElementById('pieRevExp'), {
    type: 'doughnut',
    data: {
        labels: ['營業收入','營業成本','營業費用','其他/淨利'],
        datasets: [{
            data: [
                <?= max(0, $plSummaryYTD['revenue']) ?>,
                <?= max(0, $plSummaryYTD['cost']) ?>,
                <?= max(0, $plSummaryYTD['expense']) ?>,
                <?= max(0, $plSummaryYTD['net_income']) ?>
            ],
            backgroundColor: ['#00C853','#FF4081','#FFEA00','#2962FF']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        layout: { padding: 8 },
        plugins: {
            legend: { position: 'right', labels: { boxWidth: 14, font: { size: 11 } } },
            datalabels: {
                color: '#fff', font: { weight: 'bold', size: 10 },
                textStrokeColor: 'rgba(0,0,0,.65)', textStrokeWidth: 3,
                anchor: 'center', align: 'center',
                formatter: function(v, ctx) {
                    var sum = ctx.chart.data.datasets[0].data.reduce(function(a, b) { return a + b; }, 0);
                    var pct = sum > 0 ? (v / sum * 100).toFixed(1) : 0;
                    if (pct < 5) return ''; // 小於 5% 不顯示，避免重疊
                    return pct + '%';
                }
            },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        var sum = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                        var pct = sum > 0 ? (ctx.parsed / sum * 100).toFixed(1) : 0;
                        return ctx.label + '：' + ctx.parsed.toLocaleString() + '（' + pct + '%）';
                    }
                }
            }
        }
    }
});

// 圓餅 ↔ 長條 切換按鈕
document.querySelectorAll('.chart-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var key = btn.getAttribute('data-target');
        var orig = btn.getAttribute('data-mode');
        var chart = window._pieCharts[key];
        if (!chart) return;
        var isPie = (chart.config.type === 'pie' || chart.config.type === 'doughnut');
        var newType = isPie ? 'bar' : orig;
        var labels = chart.data.labels.slice();
        var data = chart.data.datasets[0].data.slice();
        var bg = chart.data.datasets[0].backgroundColor.slice();
        chart.destroy();
        if (newType === 'bar') {
            window._pieCharts[key] = new Chart(document.getElementById(key), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{ data: data, backgroundColor: bg }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            anchor: 'end', align: 'end', color: '#333', font: { weight: 'bold', size: 11 },
                            formatter: function(v) { return v ? v.toLocaleString() : ''; }
                        }
                    },
                    scales: { x: { ticks: { callback: function(v) { return v.toLocaleString(); } } } }
                }
            });
            btn.textContent = '切換圓餅圖';
        } else {
            // 切回 pie/doughnut
            var sumFn = function(arr) { return arr.reduce(function(a, b) { return a + b; }, 0); };
            window._pieCharts[key] = new Chart(document.getElementById(key), {
                type: newType,
                data: {
                    labels: labels,
                    datasets: [{ data: data, backgroundColor: bg }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    layout: { padding: 8 },
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 14, font: { size: 11 } } },
                        datalabels: {
                            color: '#fff', font: { weight: 'bold', size: 10 },
                            textStrokeColor: 'rgba(0,0,0,.65)', textStrokeWidth: 3,
                            anchor: 'center', align: 'center',
                            formatter: function(v, ctx) {
                                var s = sumFn(ctx.chart.data.datasets[0].data);
                                var p = s > 0 ? (v / s * 100).toFixed(1) : 0;
                                return p < 5 ? '' : (p + '%');
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var s = sumFn(ctx.dataset.data);
                                    var p = s > 0 ? (ctx.parsed / s * 100).toFixed(1) : 0;
                                    return ctx.label + '：' + ctx.parsed.toLocaleString() + '（' + p + '%）';
                                }
                            }
                        }
                    }
                }
            });
            btn.textContent = '切換長條圖';
        }
    });
});
<?php endif; ?>

<?php if ($frTab === 'is_by_branch'): ?>
// IS 分公司比較長條圖
new Chart(document.getElementById('isBranchChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($branchCompare as $b) echo "'" . addslashes($b['name']) . "',"; ?>],
        datasets: [
            { label: '營業收入',  data: [<?php foreach ($branchCompare as $b) echo (float)($b['is']['revenue'] ?? 0) . ','; ?>], backgroundColor: '#00C853' },
            { label: '營業成本',  data: [<?php foreach ($branchCompare as $b) echo (float)($b['is']['cost'] ?? 0) . ','; ?>], backgroundColor: '#FF4081' },
            { label: '營業費用',  data: [<?php foreach ($branchCompare as $b) echo (float)($b['is']['expense'] ?? 0) . ','; ?>], backgroundColor: '#FFEA00' },
            { label: '營業淨利',  data: [<?php foreach ($branchCompare as $b) echo (float)($b['is']['operating_profit'] ?? 0) . ','; ?>], backgroundColor: '#2962FF' },
            { label: '本期淨利',  data: [<?php foreach ($branchCompare as $b) echo (float)($b['is']['net_income'] ?? 0) . ','; ?>], backgroundColor: '#7E57C2' }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        layout: { padding: { top: 60, bottom: 40, left: 8, right: 8 } },
        plugins: {
            legend: { position: 'top', labels: { font: { size: 15, weight: 'bold' }, boxWidth: 48, boxHeight: 18, padding: 16 } },
            datalabels: {
                anchor: 'end',
                align: function(ctx) { return ctx.dataset.data[ctx.dataIndex] < 0 ? 'bottom' : 'top'; },
                color: function(ctx) { return ctx.dataset.data[ctx.dataIndex] < 0 ? '#dc2626' : '#333'; },
                font: { size: 14, weight: 'bold' },
                clip: false,
                offset: function(ctx) {
                    // 不同 dataset 用不同 offset，避免相鄰 dataset 同高的標籤撞在一起
                    return 4 + (ctx.datasetIndex % 2) * 18;
                },
                formatter: function(v, ctx) {
                    if (!v) return '';
                    var maxAbs = 0;
                    ctx.dataset.data.forEach(function(d) { if (Math.abs(d) > maxAbs) maxAbs = Math.abs(d); });
                    if (maxAbs > 0 && Math.abs(v) / maxAbs < 0.05) return '';
                    return v.toLocaleString();
                }
            }
        },
        scales: {
            x: { ticks: { font: { size: 15, weight: 'bold' } } },
            y: { ticks: { font: { size: 13 }, callback: function(v) { return v.toLocaleString(); } } }
        }
    }
});
<?php endif; ?>

<?php if ($frTab === 'cfs_by_branch'): ?>
// CFS 分公司比較長條圖
new Chart(document.getElementById('cfsBranchChart'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($branchCompare as $b) echo "'" . addslashes($b['name']) . "',"; ?>],
        datasets: [
            { label: '營運活動淨額',  data: [<?php foreach ($branchCompare as $b) {
                $c = $b['cfs'] ?? null; $v = $c ? ($c['sections']['operating']['inflow'] - $c['sections']['operating']['outflow']) : 0; echo $v . ',';
            } ?>], backgroundColor: '#2962FF' },
            { label: '投資活動淨額',  data: [<?php foreach ($branchCompare as $b) {
                $c = $b['cfs'] ?? null; $v = $c ? ($c['sections']['investing']['inflow'] - $c['sections']['investing']['outflow']) : 0; echo $v . ',';
            } ?>], backgroundColor: '#7E57C2' },
            { label: '籌資活動淨額',  data: [<?php foreach ($branchCompare as $b) {
                $c = $b['cfs'] ?? null; $v = $c ? ($c['sections']['financing']['inflow'] - $c['sections']['financing']['outflow']) : 0; echo $v . ',';
            } ?>], backgroundColor: '#FF9100' },
            { label: '期末現金',     data: [<?php foreach ($branchCompare as $b) {
                $c = $b['cfs'] ?? null; echo ($c ? (float)$c['end_cash'] : 0) . ',';
            } ?>], backgroundColor: '#00C853' }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        layout: { padding: { top: 60, bottom: 40, left: 8, right: 8 } },
        plugins: {
            legend: { position: 'top', labels: { font: { size: 15, weight: 'bold' }, boxWidth: 48, boxHeight: 18, padding: 16 } },
            datalabels: {
                anchor: 'end',
                align: function(ctx) { return ctx.dataset.data[ctx.dataIndex] < 0 ? 'bottom' : 'top'; },
                color: function(ctx) { return ctx.dataset.data[ctx.dataIndex] < 0 ? '#dc2626' : '#333'; },
                font: { size: 14, weight: 'bold' },
                clip: false,
                offset: function(ctx) {
                    // 不同 dataset 用不同 offset，避免相鄰 dataset 同高的標籤撞在一起
                    return 4 + (ctx.datasetIndex % 2) * 18;
                },
                formatter: function(v, ctx) {
                    if (!v) return '';
                    var maxAbs = 0;
                    ctx.dataset.data.forEach(function(d) { if (Math.abs(d) > maxAbs) maxAbs = Math.abs(d); });
                    if (maxAbs > 0 && Math.abs(v) / maxAbs < 0.05) return '';
                    return v.toLocaleString();
                }
            }
        },
        scales: {
            x: { ticks: { font: { size: 15, weight: 'bold' } } },
            y: { ticks: { font: { size: 13 }, callback: function(v) { return v.toLocaleString(); } } }
        }
    }
});
<?php endif; ?>
</script>
