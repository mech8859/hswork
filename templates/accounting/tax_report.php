<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>401 營業稅申報</h2>
</div>

<!-- 期間選擇 -->
<div class="card">
    <form method="GET" action="/tax_report.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>申報期間</label>
                <select name="period" class="form-control" onchange="this.form.submit()">
                    <?php foreach ($taxPeriodOptions as $opt): ?>
                    <option value="<?= e($opt['value']) ?>" <?= $period === $opt['value'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">查詢</button>
            </div>
        </div>
    </form>
</div>

<?php if ($summary): ?>
<!-- 彙總卡片 -->
<div class="tax-summary-grid">
    <!-- 銷項 -->
    <div class="card tax-card">
        <div class="card-header" style="background:var(--primary);color:#fff">銷項發票</div>
        <div class="tax-card-body">
            <div class="tax-row">
                <span>應稅銷售額</span>
                <strong>$<?= number_format((int)$summary['sales_taxable_amount']) ?></strong>
            </div>
            <div class="tax-row">
                <span>銷項稅額</span>
                <strong style="color:var(--danger)">$<?= number_format((int)$summary['sales_tax']) ?></strong>
            </div>
            <div class="tax-row">
                <span>免稅銷售額</span>
                <strong>$<?= number_format((int)$summary['sales_exempt_amount']) ?></strong>
            </div>
            <div class="tax-row text-muted" style="font-size:.85rem">
                <span>發票數 <?= (int)$summary['sales_count'] ?> 張</span>
                <span>作廢 <?= (int)$summary['sales_voided_count'] ?> 張</span>
            </div>
        </div>
    </div>

    <!-- 進項 -->
    <div class="card tax-card">
        <div class="card-header" style="background:var(--success);color:#fff">進項發票</div>
        <div class="tax-card-body">
            <div class="tax-row">
                <span>可扣抵進項額</span>
                <strong>$<?= number_format((int)$summary['purchase_deductible_amount']) ?></strong>
            </div>
            <div class="tax-row">
                <span>可扣抵進項稅額</span>
                <strong style="color:var(--success)">$<?= number_format((int)$summary['purchase_deductible_tax']) ?></strong>
            </div>
            <div class="tax-row">
                <span>不可扣抵進項額</span>
                <strong>$<?= number_format((int)$summary['purchase_non_deductible_amount']) ?></strong>
            </div>
            <div class="tax-row text-muted" style="font-size:.85rem">
                <span>發票數 <?= (int)$summary['purchase_count'] ?> 張</span>
                <span>作廢 <?= (int)$summary['purchase_voided_count'] ?> 張</span>
            </div>
        </div>
    </div>

    <!-- 應繳稅額 -->
    <?php $_finalPayable = (int)$summary['tax_payable_final']; $_prevCredit = (int)$summary['prev_credit']; ?>
    <div class="card tax-card">
        <div class="card-header" style="background:<?= $_finalPayable >= 0 ? 'var(--danger)' : 'var(--info, #17a2b8)' ?>;color:#fff">
            <?= $_finalPayable >= 0 ? '應繳稅額' : '可退／留抵稅額' ?>
        </div>
        <div class="tax-card-body">
            <div class="tax-row">
                <span>銷項稅額</span>
                <strong>$<?= number_format((int)$summary['sales_tax']) ?></strong>
            </div>
            <div class="tax-row">
                <span>- 可扣抵進項稅額</span>
                <strong>$<?= number_format((int)$summary['purchase_deductible_tax']) ?></strong>
            </div>
            <?php if ($_prevCredit > 0): ?>
            <div class="tax-row" style="color:#1565c0">
                <span>- 上期累積留抵（格108）</span>
                <strong>$<?= number_format($_prevCredit) ?></strong>
            </div>
            <?php endif; ?>
            <div class="tax-row" style="border-top:2px solid var(--gray-200);padding-top:8px;margin-top:8px">
                <span style="font-size:1.1rem;font-weight:600"><?= $_finalPayable >= 0 ? '應繳營業稅' : '本期留抵稅額' ?></span>
                <strong style="font-size:1.3rem;color:<?= $_finalPayable >= 0 ? 'var(--danger)' : 'var(--success)' ?>">
                    $<?= number_format(abs($_finalPayable)) ?>
                </strong>
            </div>
        </div>
    </div>
</div>

<!-- 401 申報格號對照表 -->
<?php
// 依 format + invoice_type (銷項) 或 format + deduction_category (進項) 分類加總
function _taxFmtSum($rows, $filter)
{
    $sumU = 0; $sumT = 0;
    foreach ($rows as $r) {
        if ($r['status'] === 'voided') continue;
        if (isset($filter['formats']) && !in_array($r['invoice_format'], $filter['formats'], true)) continue;
        if (isset($filter['type']) && ($r['invoice_type'] ?? '') !== $filter['type']) continue;
        if (isset($filter['not_type']) && ($r['invoice_type'] ?? '') === $filter['not_type']) continue;
        if (isset($filter['deduction']) && ($r['deduction_category'] ?? '') !== $filter['deduction']) continue;
        $sumU += (int)($r['amount_untaxed'] ?? 0);
        $sumT += (int)($r['tax_amount'] ?? 0);
    }
    return array('untaxed' => $sumU, 'tax' => $sumT);
}
// 銷項
$_s31T = _taxFmtSum($salesDetail, array('formats' => array('31'), 'type' => '應稅'));
$_s31Z = _taxFmtSum($salesDetail, array('formats' => array('31'), 'type' => '零稅率'));
$_s35T = _taxFmtSum($salesDetail, array('formats' => array('35'), 'type' => '應稅'));
$_s35Z = _taxFmtSum($salesDetail, array('formats' => array('35'), 'type' => '零稅率'));
$_s32T = _taxFmtSum($salesDetail, array('formats' => array('32'), 'type' => '應稅'));
$_s32Z = _taxFmtSum($salesDetail, array('formats' => array('32'), 'type' => '零稅率'));
$_sRet = _taxFmtSum($salesDetail, array('formats' => array('33', '34')));
$_sRetZ = _taxFmtSum($salesDetail, array('formats' => array('33', '34'), 'type' => '零稅率'));
$_sExempt = _taxFmtSum($salesDetail, array('type' => '免稅'));
// 格 1~23
$box1  = $_s31T['untaxed'];  $box2  = $_s31T['tax'] + $_s31Z['tax'];  $box3  = $_s31Z['untaxed'];
$box5  = $_s35T['untaxed'];  $box6  = $_s35T['tax'] + $_s35Z['tax'];  $box7  = $_s35Z['untaxed'];
$box9  = $_s32T['untaxed'];  $box10 = $_s32T['tax'] + $_s32Z['tax'];  $box11 = $_s32Z['untaxed'];
$box17 = $_sRet['untaxed'];  $box18 = $_sRet['tax'];                   $box19 = $_sRetZ['untaxed'];
$box21 = $box1 + $box5 + $box9 - $box17;
$box22 = $box2 + $box6 + $box10 - $box18;
$box23 = $box3 + $box7 + $box11 - $box19;
$box25 = $box21 + $box23;
$box82 = $_sExempt['untaxed'];
// 進項
$_p21P = _taxFmtSum($purchaseDetail, array('formats' => array('21'), 'deduction' => 'deductible_purchase'));
$_p21A = _taxFmtSum($purchaseDetail, array('formats' => array('21'), 'deduction' => 'deductible_asset'));
$_p25P = _taxFmtSum($purchaseDetail, array('formats' => array('25'), 'deduction' => 'deductible_purchase'));
$_p25A = _taxFmtSum($purchaseDetail, array('formats' => array('25'), 'deduction' => 'deductible_asset'));
$_p22P = _taxFmtSum($purchaseDetail, array('formats' => array('22'), 'deduction' => 'deductible_purchase'));
$_p22A = _taxFmtSum($purchaseDetail, array('formats' => array('22'), 'deduction' => 'deductible_asset'));
$_pRetP = _taxFmtSum($purchaseDetail, array('formats' => array('23', '24'), 'deduction' => 'deductible_purchase'));
$_pRetA = _taxFmtSum($purchaseDetail, array('formats' => array('23', '24'), 'deduction' => 'deductible_asset'));
$_pNonDedP = _taxFmtSum($purchaseDetail, array('deduction' => 'non_deductible'));
$box28 = $_p21P['untaxed']; $box29 = $_p21P['tax'];
$box30 = $_p21A['untaxed']; $box31 = $_p21A['tax'];
$box32 = $_p25P['untaxed']; $box33 = $_p25P['tax'];
$box34 = $_p25A['untaxed']; $box35 = $_p25A['tax'];
$box36 = $_p22P['untaxed']; $box37 = $_p22P['tax'];
$box38 = $_p22A['untaxed']; $box39 = $_p22A['tax'];
$box40 = $_pRetP['untaxed']; $box41 = $_pRetP['tax'];
$box42 = $_pRetA['untaxed']; $box43 = $_pRetA['tax'];
$box44 = $box28 + $box32 + $box36 - $box40;
$box45 = $box29 + $box33 + $box37 - $box41;
$box46 = $box30 + $box34 + $box38 - $box42;
$box47 = $box31 + $box35 + $box39 - $box43;
$box48 = $_pNonDedP['untaxed']; $box49 = 0;
// 稅額
$box101 = $box22;
$box107 = $box45 + $box47;
$box108 = isset($prevCredit) ? (int)$prevCredit : 0;
$box110 = $box107 + $box108;
$box111 = max(0, $box101 - $box110);
$box115 = max(0, $box110 - $box101);
?>
<?php $_tax401Open = !empty($_GET['tax401']) && $_GET['tax401'] === '1'; ?>
<div class="card mt-2" id="tax401Card">
    <div class="card-header d-flex justify-between align-center" style="cursor:pointer" onclick="toggleTax401(event)">
        <span style="display:inline-flex;align-items:center;gap:6px">
            <span id="tax401Arrow" style="display:inline-block;transition:transform .2s;<?= $_tax401Open ? 'transform:rotate(90deg)' : '' ?>">▶</span>
            401 申報書格號對照（<?= e($period) ?>）
        </span>
        <form method="GET" style="display:inline-flex;gap:6px;align-items:center;margin:0" onclick="event.stopPropagation()">
            <input type="hidden" name="period" value="<?= e($period) ?>">
            <input type="hidden" name="tax401" value="1">
            <label style="font-size:.85rem;color:#888">上期累積留抵(格108)</label>
            <input type="number" name="prev_credit" value="<?= (int)$box108 ?>" class="form-control" style="width:120px" min="0">
            <button type="submit" class="btn btn-outline btn-sm">套用</button>
        </form>
    </div>
    <div id="tax401Body" style="<?= $_tax401Open ? '' : 'display:none' ?>">
    <div class="table-responsive">
        <table class="table table-sm" style="font-size:.88rem">
            <thead>
                <tr style="background:var(--primary);color:#fff">
                    <th colspan="5">銷項（應稅銷售額 / 稅額 / 零稅率銷售額）</th>
                </tr>
                <tr>
                    <th style="width:60px">格</th>
                    <th>項目</th>
                    <th class="text-right">銷售額</th>
                    <th style="width:60px">格</th>
                    <th class="text-right">稅額</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>1</strong></td><td>三聯式發票、電子計算機發票（31）</td><td class="text-right">$<?= number_format($box1) ?></td><td><strong>2</strong></td><td class="text-right">$<?= number_format($box2) ?></td></tr>
                <tr><td><strong>3</strong></td><td style="color:#888">└ 零稅率銷售額</td><td class="text-right">$<?= number_format($box3) ?></td><td></td><td></td></tr>
                <tr><td><strong>5</strong></td><td>收銀機發票(三聯式)及電子發票（35）</td><td class="text-right">$<?= number_format($box5) ?></td><td><strong>6</strong></td><td class="text-right">$<?= number_format($box6) ?></td></tr>
                <tr><td><strong>7</strong></td><td style="color:#888">└ 零稅率銷售額</td><td class="text-right">$<?= number_format($box7) ?></td><td></td><td></td></tr>
                <tr><td><strong>9</strong></td><td>二聯式發票、收銀機發票(二聯式)（32）</td><td class="text-right">$<?= number_format($box9) ?></td><td><strong>10</strong></td><td class="text-right">$<?= number_format($box10) ?></td></tr>
                <tr><td><strong>11</strong></td><td style="color:#888">└ 零稅率銷售額</td><td class="text-right">$<?= number_format($box11) ?></td><td></td><td></td></tr>
                <tr style="color:var(--danger)"><td><strong>17</strong></td><td>減：退回及折讓（33、34）</td><td class="text-right">-$<?= number_format($box17) ?></td><td><strong>18</strong></td><td class="text-right">-$<?= number_format($box18) ?></td></tr>
                <tr style="color:var(--danger)"><td><strong>19</strong></td><td style="color:#888">└ 零稅率退回折讓</td><td class="text-right">-$<?= number_format($box19) ?></td><td></td><td></td></tr>
                <tr style="font-weight:600;background:var(--gray-50,#f8f9fa)">
                    <td><strong>21</strong></td><td>合計①</td><td class="text-right">$<?= number_format($box21) ?></td><td><strong>22</strong></td><td class="text-right">$<?= number_format($box22) ?></td>
                </tr>
                <tr style="font-weight:600;background:var(--gray-50,#f8f9fa)"><td><strong>23</strong></td><td>零稅率銷售額合計③</td><td class="text-right">$<?= number_format($box23) ?></td><td></td><td></td></tr>
                <tr style="font-weight:700;background:#fff3cd"><td><strong>25</strong></td><td>銷售額總計 ①+③</td><td class="text-right">$<?= number_format($box25) ?></td><td><strong>82</strong></td><td class="text-right" title="免稅銷售額">免稅 $<?= number_format($box82) ?></td></tr>
            </tbody>
            <thead>
                <tr style="background:var(--success);color:#fff">
                    <th colspan="5">進項（得扣抵進項稅額：進貨及費用 / 固定資產）</th>
                </tr>
                <tr>
                    <th>格</th><th>項目</th>
                    <th class="text-right">進貨及費用（金額/稅額）</th>
                    <th class="text-right">固定資產（金額/稅額）</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>28/29/30/31</strong></td><td>統一發票扣抵聯（21）</td>
                    <td class="text-right">$<?= number_format($box28) ?> / $<?= number_format($box29) ?></td>
                    <td class="text-right">$<?= number_format($box30) ?> / $<?= number_format($box31) ?></td>
                    <td></td></tr>
                <tr><td><strong>32/33/34/35</strong></td><td>三聯式收銀機及電子發票扣抵聯（25）</td>
                    <td class="text-right">$<?= number_format($box32) ?> / $<?= number_format($box33) ?></td>
                    <td class="text-right">$<?= number_format($box34) ?> / $<?= number_format($box35) ?></td>
                    <td></td></tr>
                <tr><td><strong>36/37/38/39</strong></td><td>載有稅額之其他憑證（22）</td>
                    <td class="text-right">$<?= number_format($box36) ?> / $<?= number_format($box37) ?></td>
                    <td class="text-right">$<?= number_format($box38) ?> / $<?= number_format($box39) ?></td>
                    <td></td></tr>
                <tr style="color:var(--danger)"><td><strong>40/41/42/43</strong></td><td>減：退出、折讓（23、24）</td>
                    <td class="text-right">-$<?= number_format($box40) ?> / -$<?= number_format($box41) ?></td>
                    <td class="text-right">-$<?= number_format($box42) ?> / -$<?= number_format($box43) ?></td>
                    <td></td></tr>
                <tr style="font-weight:600;background:var(--gray-50,#f8f9fa)">
                    <td><strong>44/45/46/47</strong></td><td>合計</td>
                    <td class="text-right">$<?= number_format($box44) ?> / $<?= number_format($box45) ?></td>
                    <td class="text-right">$<?= number_format($box46) ?> / $<?= number_format($box47) ?></td>
                    <td></td>
                </tr>
                <tr style="color:#888"><td><strong>48/49</strong></td><td>不得扣抵進項金額（憑證及普通收據）</td>
                    <td class="text-right">$<?= number_format($box48) ?></td>
                    <td class="text-right">$<?= number_format($box49) ?></td>
                    <td></td></tr>
            </tbody>
            <thead>
                <tr style="background:var(--danger);color:#fff">
                    <th colspan="5">稅額計算</th>
                </tr>
                <tr><th>格</th><th>項目</th><th class="text-right" colspan="2">金額</th><th></th></tr>
            </thead>
            <tbody>
                <tr><td><strong>101</strong></td><td>本期銷項稅額合計（② = 格22）</td><td colspan="2" class="text-right">$<?= number_format($box101) ?></td><td></td></tr>
                <tr><td><strong>107</strong></td><td>得扣抵進項稅額合計（格45+格47）</td><td colspan="2" class="text-right" style="color:var(--success)">$<?= number_format($box107) ?></td><td></td></tr>
                <tr><td><strong>108</strong></td><td>上期(月)累積留抵稅額（手動輸入）</td><td colspan="2" class="text-right">$<?= number_format($box108) ?></td><td></td></tr>
                <tr><td><strong>110</strong></td><td>小計（7+8 = 格107+格108）</td><td colspan="2" class="text-right">$<?= number_format($box110) ?></td><td></td></tr>
                <tr style="font-weight:700;background:#fff3cd">
                    <td><strong>111</strong></td><td>本期實繳稅額（1-10 = 格101-格110）</td>
                    <td colspan="2" class="text-right" style="color:var(--danger);font-size:1.1rem">$<?= number_format($box111) ?></td>
                    <td></td>
                </tr>
                <tr style="font-weight:700;background:#d4edda">
                    <td><strong>115</strong></td><td>本期累積留抵稅額（10-1）</td>
                    <td colspan="2" class="text-right" style="color:var(--success);font-size:1.1rem">$<?= number_format($box115) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div style="padding:8px 12px;background:#fff3cd;border-top:1px solid #ffeaa7;font-size:.82rem;color:#856404">
        ⚠ 本表僅供申報抄寫參考。下列格位系統尚無資料，需自行填寫：
        格 13-15（免用發票銷售額）、格 73（進口免稅）、格 74（購買國外勞務）、格 78-81（海關代徵營業稅扣抵）、格 49（不得扣抵固定資產）
    </div>
    </div><!-- /tax401Body -->
</div>
<script>
function toggleTax401(e) {
    if (e && e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON' || e.target.tagName === 'LABEL')) return;
    var body = document.getElementById('tax401Body');
    var arrow = document.getElementById('tax401Arrow');
    if (!body) return;
    var open = body.style.display !== 'none';
    body.style.display = open ? 'none' : '';
    if (arrow) arrow.style.transform = open ? '' : 'rotate(90deg)';
}
</script>

<!-- 銷項發票聯式彙總 -->
<?php
$_sfLabels = array(
    '31' => '31：銷項三聯式、電子計算機統一發票',
    '32' => '32：銷項二聯式、二聯式收銀機統一發票',
    '33' => '33：三聯式銷貨退回或折讓證明單',
    '34' => '34：二聯式銷貨退回或折讓證明單',
    '35' => '35：銷項三聯式收銀機統一發票、電子發票',
);
$_sfStats = array();
foreach ($_sfLabels as $_k => $_v) {
    $_sfStats[$_k] = array('count' => 0, 'untaxed' => 0, 'tax' => 0, 'total' => 0);
}
$_sfOther = array('count' => 0, 'untaxed' => 0, 'tax' => 0, 'total' => 0);
foreach ($salesDetail as $_r) {
    if ($_r['status'] === 'voided') continue;
    $_fmt = !empty($_r['invoice_format']) ? $_r['invoice_format'] : '';
    $_target = isset($_sfStats[$_fmt]) ? $_sfStats[$_fmt] : $_sfOther;
    $_target['count']   += 1;
    $_target['untaxed'] += (int)$_r['amount_untaxed'];
    $_target['tax']     += (int)$_r['tax_amount'];
    $_target['total']   += (int)$_r['total_amount'];
    if (isset($_sfStats[$_fmt])) { $_sfStats[$_fmt] = $_target; } else { $_sfOther = $_target; }
}
$_sfTot = array('count' => 0, 'untaxed' => 0, 'tax' => 0, 'total' => 0);
foreach ($_sfStats as $_k => $s) {
    $_sfTot['count'] += $s['count'];
    // 33/34 為銷貨退回或折讓，合計時應扣除
    $sign = in_array((string)$_k, array('33', '34'), true) ? -1 : 1;
    $_sfTot['untaxed'] += $sign * $s['untaxed'];
    $_sfTot['tax']     += $sign * $s['tax'];
    $_sfTot['total']   += $sign * $s['total'];
}
$_sfTot['count']+=$_sfOther['count']; $_sfTot['untaxed']+=$_sfOther['untaxed']; $_sfTot['tax']+=$_sfOther['tax']; $_sfTot['total']+=$_sfOther['total'];
?>
<div class="card mt-2">
    <div class="card-header">銷項發票聯式彙總（不含作廢）</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:80px">代碼</th>
                    <th>說明</th>
                    <th class="text-right" style="width:80px">張數</th>
                    <th class="text-right">未稅金額</th>
                    <th class="text-right">稅額</th>
                    <th class="text-right">含稅金額</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_sfStats as $_k => $_s):
                    $_isRefund = in_array((string)$_k, array('33', '34'), true);
                    $_prefix = $_isRefund && $_s['count'] > 0 ? '-' : '';
                ?>
                <tr<?= $_s['count'] === 0 ? ' style="color:#bbb"' : ($_isRefund ? ' style="color:var(--danger)"' : '') ?>>
                    <td><strong><?= e($_k) ?></strong></td>
                    <td><?= e($_sfLabels[$_k]) ?><?= $_isRefund ? '（退出折讓，扣除）' : '' ?></td>
                    <td class="text-right"><?= number_format($_s['count']) ?></td>
                    <td class="text-right"><?= $_prefix ?>$<?= number_format($_s['untaxed']) ?></td>
                    <td class="text-right"<?= $_isRefund ? '' : ' style="color:var(--danger)"' ?>><?= $_prefix ?>$<?= number_format($_s['tax']) ?></td>
                    <td class="text-right"><?= $_prefix ?>$<?= number_format($_s['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($_sfOther['count'] > 0): ?>
                <tr>
                    <td><strong>-</strong></td>
                    <td style="color:#888">未標註聯式</td>
                    <td class="text-right"><?= number_format($_sfOther['count']) ?></td>
                    <td class="text-right">$<?= number_format($_sfOther['untaxed']) ?></td>
                    <td class="text-right" style="color:var(--danger)">$<?= number_format($_sfOther['tax']) ?></td>
                    <td class="text-right">$<?= number_format($_sfOther['total']) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600;background:var(--gray-50,#f8f9fa)">
                    <td colspan="2">合計</td>
                    <td class="text-right"><?= number_format($_sfTot['count']) ?></td>
                    <td class="text-right">$<?= number_format($_sfTot['untaxed']) ?></td>
                    <td class="text-right" style="color:var(--danger)">$<?= number_format($_sfTot['tax']) ?></td>
                    <td class="text-right">$<?= number_format($_sfTot['total']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- 進項發票聯式彙總 -->
<?php
$_pfLabels = array(
    '21' => '21：進項三聯式、電子計算機統一發票',
    '22' => '22：進項二聯式收銀機統一發票、載有稅額之其他憑證',
    '23' => '23：三聯式進貨退出或折讓證明單',
    '24' => '24：二聯式進貨退出或折讓證明單',
    '25' => '25：進項三聯式收銀機統一發票、公用事業憑證',
);
$_pfStats = array();
foreach ($_pfLabels as $_k => $_v) {
    $_pfStats[$_k] = array('count' => 0, 'untaxed' => 0, 'tax' => 0, 'total' => 0);
}
$_pfOther = array('count' => 0, 'untaxed' => 0, 'tax' => 0, 'total' => 0);
foreach ($purchaseDetail as $_r) {
    if ($_r['status'] === 'voided') continue;
    // 僅計入「可扣抵」的進項發票，與頂部卡片一致
    if (($_r['deduction_type'] ?? '') !== 'deductible') continue;
    $_fmt = !empty($_r['invoice_format']) ? $_r['invoice_format'] : '';
    $_target = isset($_pfStats[$_fmt]) ? $_pfStats[$_fmt] : $_pfOther;
    $_target['count']   += 1;
    $_target['untaxed'] += (int)$_r['amount_untaxed'];
    $_target['tax']     += (int)$_r['tax_amount'];
    $_target['total']   += (int)$_r['total_amount'];
    if (isset($_pfStats[$_fmt])) { $_pfStats[$_fmt] = $_target; } else { $_pfOther = $_target; }
}
$_pfTot = array('count' => 0, 'untaxed' => 0, 'tax' => 0, 'total' => 0);
foreach ($_pfStats as $_k => $s) {
    $_pfTot['count'] += $s['count'];
    // 23/24 為進貨退出或折讓，合計時應扣除
    $sign = in_array((string)$_k, array('23', '24'), true) ? -1 : 1;
    $_pfTot['untaxed'] += $sign * $s['untaxed'];
    $_pfTot['tax']     += $sign * $s['tax'];
    $_pfTot['total']   += $sign * $s['total'];
}
$_pfTot['count']+=$_pfOther['count']; $_pfTot['untaxed']+=$_pfOther['untaxed']; $_pfTot['tax']+=$_pfOther['tax']; $_pfTot['total']+=$_pfOther['total'];
?>
<div class="card mt-2">
    <div class="card-header">進項發票聯式彙總（不含作廢，僅計可扣抵）</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:80px">代碼</th>
                    <th>說明</th>
                    <th class="text-right" style="width:80px">張數</th>
                    <th class="text-right">未稅金額</th>
                    <th class="text-right">稅額</th>
                    <th class="text-right">含稅金額</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_pfStats as $_k => $_s):
                    $_isRefund = in_array((string)$_k, array('23', '24'), true);
                    $_prefix = $_isRefund && $_s['count'] > 0 ? '-' : '';
                ?>
                <tr<?= $_s['count'] === 0 ? ' style="color:#bbb"' : ($_isRefund ? ' style="color:var(--danger)"' : '') ?>>
                    <td><strong><?= e($_k) ?></strong></td>
                    <td><?= e($_pfLabels[$_k]) ?><?= $_isRefund ? '（退出折讓，扣除）' : '' ?></td>
                    <td class="text-right"><?= number_format($_s['count']) ?></td>
                    <td class="text-right"><?= $_prefix ?>$<?= number_format($_s['untaxed']) ?></td>
                    <td class="text-right"<?= $_isRefund ? '' : ' style="color:var(--success)"' ?>><?= $_prefix ?>$<?= number_format($_s['tax']) ?></td>
                    <td class="text-right"><?= $_prefix ?>$<?= number_format($_s['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($_pfOther['count'] > 0): ?>
                <tr>
                    <td><strong>-</strong></td>
                    <td style="color:#888">未標註聯式</td>
                    <td class="text-right"><?= number_format($_pfOther['count']) ?></td>
                    <td class="text-right">$<?= number_format($_pfOther['untaxed']) ?></td>
                    <td class="text-right" style="color:var(--success)">$<?= number_format($_pfOther['tax']) ?></td>
                    <td class="text-right">$<?= number_format($_pfOther['total']) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600;background:var(--gray-50,#f8f9fa)">
                    <td colspan="2">合計</td>
                    <td class="text-right"><?= number_format($_pfTot['count']) ?></td>
                    <td class="text-right">$<?= number_format($_pfTot['untaxed']) ?></td>
                    <td class="text-right" style="color:var(--success)">$<?= number_format($_pfTot['tax']) ?></td>
                    <td class="text-right">$<?= number_format($_pfTot['total']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- 銷項明細 -->
<div class="card mt-2">
    <div class="card-header d-flex justify-between align-center">
        <span>銷項發票明細 (<?= count($salesDetail) ?> 筆)</span>
    </div>
    <?php if (empty($salesDetail)): ?>
        <p class="text-muted text-center mt-2">此期間無銷項發票</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>發票號碼</th>
                    <th>日期</th>
                    <th>客戶</th>
                    <th>統編</th>
                    <th>類型</th>
                    <th class="text-right">未稅金額</th>
                    <th class="text-right">稅額</th>
                    <th class="text-right">含稅金額</th>
                    <th>狀態</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $salesTotalUntaxed = 0;
                $salesTotalTax = 0;
                $salesTotalAmount = 0;
                foreach ($salesDetail as $r):
                    if ($r['status'] !== 'voided') {
                        $salesTotalUntaxed += (int)$r['amount_untaxed'];
                        $salesTotalTax += (int)$r['tax_amount'];
                        $salesTotalAmount += (int)$r['total_amount'];
                    }
                ?>
                <tr<?= $r['status'] === 'voided' ? ' style="opacity:.5;text-decoration:line-through"' : '' ?>>
                    <td><a href="/sales_invoices.php?action=edit&id=<?= $r['id'] ?>"><?= e(!empty($r['invoice_number']) ? $r['invoice_number'] : '-') ?></a></td>
                    <td><?= e(!empty($r['invoice_date']) ? $r['invoice_date'] : '') ?></td>
                    <td><?= e(!empty($r['customer_name']) ? $r['customer_name'] : '-') ?></td>
                    <td><?= e(!empty($r['customer_tax_id']) ? $r['customer_tax_id'] : '-') ?></td>
                    <td><?= e(!empty($r['invoice_type']) ? $r['invoice_type'] : '-') ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['amount_untaxed']) ? $r['amount_untaxed'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['tax_amount']) ? $r['tax_amount'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td>
                        <?php $so = InvoiceModel::invoiceStatusOptions(); ?>
                        <span class="badge badge-<?= $r['status'] === 'voided' ? 'danger' : ($r['status'] === 'confirmed' ? 'success' : 'warning') ?>">
                            <?= e(isset($so[$r['status']]) ? $so[$r['status']] : $r['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600;background:var(--gray-50,#f8f9fa)">
                    <td colspan="5">合計 (不含作廢)</td>
                    <td class="text-right">$<?= number_format($salesTotalUntaxed) ?></td>
                    <td class="text-right">$<?= number_format($salesTotalTax) ?></td>
                    <td class="text-right">$<?= number_format($salesTotalAmount) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- 進項明細 -->
<div class="card mt-2">
    <div class="card-header d-flex justify-between align-center">
        <span>進項發票明細 (<?= count($purchaseDetail) ?> 筆)</span>
    </div>
    <?php if (empty($purchaseDetail)): ?>
        <p class="text-muted text-center mt-2">此期間無進項發票</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>發票號碼</th>
                    <th>日期</th>
                    <th>供應商</th>
                    <th>統編</th>
                    <th>類型</th>
                    <th>扣抵</th>
                    <th class="text-right">未稅金額</th>
                    <th class="text-right">稅額</th>
                    <th class="text-right">含稅金額</th>
                    <th>狀態</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $purchaseTotalUntaxed = 0;
                $purchaseTotalTax = 0;
                $purchaseTotalAmount = 0;
                foreach ($purchaseDetail as $r):
                    if ($r['status'] !== 'voided') {
                        $purchaseTotalUntaxed += (int)$r['amount_untaxed'];
                        $purchaseTotalTax += (int)$r['tax_amount'];
                        $purchaseTotalAmount += (int)$r['total_amount'];
                    }
                ?>
                <tr<?= $r['status'] === 'voided' ? ' style="opacity:.5;text-decoration:line-through"' : '' ?>>
                    <td><a href="/purchase_invoices.php?action=edit&id=<?= $r['id'] ?>"><?= e(!empty($r['invoice_number']) ? $r['invoice_number'] : '-') ?></a></td>
                    <td><?= e(!empty($r['invoice_date']) ? $r['invoice_date'] : '') ?></td>
                    <td><?= e(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') ?></td>
                    <td><?= e(!empty($r['vendor_tax_id']) ? $r['vendor_tax_id'] : '-') ?></td>
                    <td><?= e(!empty($r['invoice_type']) ? $r['invoice_type'] : '-') ?></td>
                    <td><?= (!empty($r['deduction_type']) && $r['deduction_type'] === 'deductible') ? '<span style="color:var(--success)">可扣抵</span>' : '<span style="color:var(--danger)">不可扣抵</span>' ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['amount_untaxed']) ? $r['amount_untaxed'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['tax_amount']) ? $r['tax_amount'] : 0) ?></td>
                    <td class="text-right">$<?= number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) ?></td>
                    <td>
                        <?php $so = InvoiceModel::invoiceStatusOptions(); ?>
                        <span class="badge badge-<?= $r['status'] === 'voided' ? 'danger' : ($r['status'] === 'confirmed' ? 'success' : 'warning') ?>">
                            <?= e(isset($so[$r['status']]) ? $so[$r['status']] : $r['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:600;background:var(--gray-50,#f8f9fa)">
                    <td colspan="6">合計 (不含作廢)</td>
                    <td class="text-right">$<?= number_format($purchaseTotalUntaxed) ?></td>
                    <td class="text-right">$<?= number_format($purchaseTotalTax) ?></td>
                    <td class="text-right">$<?= number_format($purchaseTotalAmount) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
    <p class="text-muted text-center mt-2">請選擇期間查詢</p>
</div>
<?php endif; ?>

<style>
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-row .form-group { flex: 1; min-width: 200px; margin-bottom: 0; }
.tax-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; }
.tax-card { margin-bottom: 0; }
.tax-card-body { padding: 16px; }
.tax-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; }
.badge-danger { background: var(--danger); color: #fff; }
.badge-success { background: var(--success); color: #fff; }
.badge-warning { background: var(--warning); color: #fff; }
.badge { padding: 2px 8px; border-radius: 4px; font-size: .8rem; }
@media (max-width: 767px) {
    .tax-summary-grid { grid-template-columns: 1fr; }
}
</style>
