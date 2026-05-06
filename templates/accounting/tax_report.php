<div class="d-flex justify-between align-center flex-wrap gap-1 mb-2">
    <h2>401 營業稅申報</h2>
</div>

<!-- 期間選擇 -->
<div class="card">
    <form method="GET" action="/tax_report.php" class="filter-form">
        <div class="filter-row">
            <div class="form-group">
                <label>統一編號</label>
                <select name="company_tax_id" class="form-control" onchange="this.form.submit()">
                    <option value="" <?= $companyTaxId === '' ? 'selected' : '' ?>>全部</option>
                    <option value="94081455" <?= $companyTaxId === '94081455' ? 'selected' : '' ?>>94081455 禾順監視數位科技有限公司</option>
                    <option value="97002927" <?= $companyTaxId === '97002927' ? 'selected' : '' ?>>97002927 政遠企業有限公司</option>
                </select>
            </div>
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
<?php
// 401 報表只列入「開立已確認」的發票，其餘狀態（待處理/作廢/空白/退款）一律排除。
// 保留原始計數以便顯示警告。
$_pendingSales = 0; $_pendingPurchase = 0;
$_voidedSales = 0; $_voidedPurchase = 0;
foreach ($salesDetail as $_r) {
    if ($_r['status'] === 'voided') $_voidedSales++;
    elseif ($_r['status'] !== 'confirmed') $_pendingSales++;
}
foreach ($purchaseDetail as $_r) {
    if ($_r['status'] === 'voided') $_voidedPurchase++;
    elseif ($_r['status'] !== 'confirmed') $_pendingPurchase++;
}
$salesDetail = array_values(array_filter($salesDetail, function($r) { return ($r['status'] ?? '') === 'confirmed'; }));
$purchaseDetail = array_values(array_filter($purchaseDetail, function($r) { return ($r['status'] ?? '') === 'confirmed'; }));
?>
<?php if ($_pendingSales > 0 || $_pendingPurchase > 0): ?>
<div class="card mt-2" style="background:#fff8e1;border-left:4px solid #f57c00;padding:10px 14px;margin-bottom:10px">
    <strong style="color:#e65100">⚠ 401 申報只列入「開立已確認」的發票</strong>
    <div style="font-size:.9rem;color:#5d4037;margin-top:4px">
        <?php if ($_pendingSales > 0): ?>
            銷項有 <strong><?= (int)$_pendingSales ?></strong> 筆待處理（未確認）
        <?php endif; ?>
        <?php if ($_pendingSales > 0 && $_pendingPurchase > 0): ?>，<?php endif; ?>
        <?php if ($_pendingPurchase > 0): ?>
            進項有 <strong><?= (int)$_pendingPurchase ?></strong> 筆待處理（未確認）
        <?php endif; ?>
        — 未列入下列彙總／明細，請至發票管理確認後再申報。
    </div>
</div>
<?php endif; ?>
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
function toggleSection(bodyId, arrowId) {
    var body = document.getElementById(bodyId);
    var arrow = document.getElementById(arrowId);
    if (!body) return;
    var open = body.style.display !== 'none';
    body.style.display = open ? 'none' : '';
    if (arrow) arrow.style.transform = open ? '' : 'rotate(90deg)';
    saveTaxReportState();
}

// 將「展開狀態 + 捲動位置」存到 sessionStorage，重整或重新查詢後復原
var TAX_STATE_KEY = 'taxReportState_v1';
function saveTaxReportState() {
    try {
        var sd = document.getElementById('salesDetailBody');
        var pd = document.getElementById('purchaseDetailBody');
        var t401 = document.getElementById('tax401Body');
        var st = {
            sdOpen: !!(sd && sd.style.display !== 'none'),
            pdOpen: !!(pd && pd.style.display !== 'none'),
            t401Open: !!(t401 && t401.style.display !== 'none'),
            scrollY: window.scrollY || window.pageYOffset || 0,
            ts: Date.now()
        };
        sessionStorage.setItem(TAX_STATE_KEY, JSON.stringify(st));
    } catch (e) { /* sessionStorage 不可用就忽略 */ }
}
function restoreTaxReportState() {
    try {
        var raw = sessionStorage.getItem(TAX_STATE_KEY);
        if (!raw) return;
        var st = JSON.parse(raw);
        if (!st) return;
        function setOpen(bodyId, arrowId, isOpen) {
            var body = document.getElementById(bodyId);
            var arrow = document.getElementById(arrowId);
            if (!body) return;
            body.style.display = isOpen ? '' : 'none';
            if (arrow) arrow.style.transform = isOpen ? 'rotate(90deg)' : '';
        }
        if (typeof st.sdOpen === 'boolean')   setOpen('salesDetailBody', 'salesDetailArrow', st.sdOpen);
        if (typeof st.pdOpen === 'boolean')   setOpen('purchaseDetailBody', 'purchaseDetailArrow', st.pdOpen);
        if (typeof st.t401Open === 'boolean') setOpen('tax401Body', 'tax401Arrow', st.t401Open);
        if (typeof st.scrollY === 'number' && st.scrollY > 0) {
            // 等內容渲染完再捲動
            setTimeout(function() { window.scrollTo(0, st.scrollY); }, 30);
        }
    } catch (e) { /* ignore */ }
}
document.addEventListener('DOMContentLoaded', restoreTaxReportState);
window.addEventListener('beforeunload', saveTaxReportState);
// 捲動時節流寫入
var _taxScrollTimer = null;
window.addEventListener('scroll', function() {
    if (_taxScrollTimer) clearTimeout(_taxScrollTimer);
    _taxScrollTimer = setTimeout(saveTaxReportState, 250);
});
function jumpToFmtGroup(bodyId, arrowId, anchorId) {
    var body = document.getElementById(bodyId);
    var arrow = document.getElementById(arrowId);
    if (body && body.style.display === 'none') {
        body.style.display = '';
        if (arrow) arrow.style.transform = 'rotate(90deg)';
    }
    var target = document.getElementById(anchorId);
    if (!target) return;
    setTimeout(function() {
        target.scrollIntoView({behavior:'smooth', block:'start'});
        // 短暫高亮
        var orig = target.style.outline;
        target.style.outline = '3px solid #fbbf24';
        target.style.outlineOffset = '-2px';
        setTimeout(function() { target.style.outline = orig || ''; target.style.outlineOffset = ''; saveTaxReportState(); }, 1500);
    }, 50);
}
function toggleTax401(e) {
    if (e && e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON' || e.target.tagName === 'LABEL')) return;
    var body = document.getElementById('tax401Body');
    var arrow = document.getElementById('tax401Arrow');
    if (!body) return;
    var open = body.style.display !== 'none';
    body.style.display = open ? 'none' : '';
    if (arrow) arrow.style.transform = open ? '' : 'rotate(90deg)';
    saveTaxReportState();
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
$_sfPendingRefund = 0; // 待處理的折讓 33/34 計數
foreach ($salesDetail as $_r) {
    if ($_r['status'] === 'voided') continue;
    $_fmt = !empty($_r['invoice_format']) ? $_r['invoice_format'] : '';
    // 折讓 33/34 必須開立確認後才計入 401
    if (in_array((string)$_fmt, array('33', '34'), true) && ($_r['status'] ?? '') !== 'confirmed') {
        $_sfPendingRefund++;
        continue;
    }
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
    <div class="card-header">銷項發票聯式彙總（僅計開立已確認）</div>
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
                    $_clickable = $_s['count'] > 0;
                    $_rowStyle = $_s['count'] === 0 ? 'color:#bbb' : ($_isRefund ? 'color:var(--danger)' : '');
                    if ($_clickable) $_rowStyle .= ($_rowStyle ? ';' : '') . 'cursor:pointer';
                    $_onClick = $_clickable ? "onclick=\"jumpToFmtGroup('salesDetailBody','salesDetailArrow','sf-grp-{$_k}')\" title=\"點擊跳到此聯式的明細\"" : '';
                ?>
                <tr style="<?= $_rowStyle ?>" <?= $_onClick ?>>
                    <td><strong><?= e($_k) ?></strong><?= $_clickable ? ' <span style="color:#888;font-size:.75rem">▸</span>' : '' ?></td>
                    <td><?= e($_sfLabels[$_k]) ?><?= $_isRefund ? '（退出折讓，扣除）' : '' ?></td>
                    <td class="text-right"><?= number_format($_s['count']) ?></td>
                    <td class="text-right"><?= $_prefix ?>$<?= number_format($_s['untaxed']) ?></td>
                    <td class="text-right"<?= $_isRefund ? '' : ' style="color:var(--danger)"' ?>><?= $_prefix ?>$<?= number_format($_s['tax']) ?></td>
                    <td class="text-right"><?= $_prefix ?>$<?= number_format($_s['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($_sfOther['count'] > 0): ?>
                <tr style="cursor:pointer" onclick="jumpToFmtGroup('salesDetailBody','salesDetailArrow','sf-grp-other')" title="點擊跳到此分組的明細">
                    <td><strong>-</strong> <span style="color:#888;font-size:.75rem">▸</span></td>
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

<!-- 銷項發票明細（依聯式分組，可收合） -->
<?php
$_sdGroups = array();
foreach ($_sfLabels as $_k => $_v) { $_sdGroups[$_k] = array(); }
$_sdOther = array();
foreach ($salesDetail as $_r) {
    $_fmt = !empty($_r['invoice_format']) ? (string)$_r['invoice_format'] : '';
    if (isset($_sdGroups[$_fmt])) { $_sdGroups[$_fmt][] = $_r; } else { $_sdOther[] = $_r; }
}
$_invSort = function($a, $b) {
    $c = strcmp((string)($a['invoice_date'] ?? ''), (string)($b['invoice_date'] ?? ''));
    if ($c !== 0) return $c;
    return strcmp((string)($a['invoice_number'] ?? ''), (string)($b['invoice_number'] ?? ''));
};
foreach ($_sdGroups as $_k => $_g) { usort($_g, $_invSort); $_sdGroups[$_k] = $_g; }
usort($_sdOther, $_invSort);
$_sdOpen = !empty($_GET['sdOpen']) && $_GET['sdOpen'] === '1';
?>
<div class="card mt-2">
    <div class="card-header d-flex justify-between align-center" style="cursor:pointer" onclick="toggleSection('salesDetailBody','salesDetailArrow')">
        <span>
            <span id="salesDetailArrow" style="display:inline-block;transition:transform .2s;<?= $_sdOpen ? 'transform:rotate(90deg)' : '' ?>">▶</span>
            銷項發票明細（已確認 <?= count($salesDetail) ?> 筆） — 依聯式分組，依日期/發票號碼排序
        </span>
        <span style="display:inline-flex;gap:8px;align-items:center">
            <a href="/tax_report.php?action=export_sales&period=<?= e($period) ?>&company_tax_id=<?= e($companyTaxId) ?>"
               class="btn btn-sm" style="background:#16a34a;color:#fff;font-size:.78rem;padding:3px 10px"
               onclick="event.stopPropagation()" title="下載銷項發票明細 CSV（含代碼小計，Excel 可直接開啟）">📥 下載 Excel</a>
            <span style="font-size:.85rem;color:#888">點擊展開/收合</span>
        </span>
    </div>
    <div id="salesDetailBody" style="<?= $_sdOpen ? '' : 'display:none' ?>">
        <?php if (empty($salesDetail)): ?>
            <p class="text-muted text-center mt-2">此期間無銷項發票</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:32px"></th>
                        <th style="width:120px">發票號碼</th>
                        <th style="width:100px">日期</th>
                        <th style="width:90px">申報期間</th>
                        <th>客戶</th>
                        <th style="width:100px">統編</th>
                        <th style="width:60px">類型</th>
                        <th class="text-right">未稅金額</th>
                        <th class="text-right">稅額</th>
                        <th class="text-right">含稅金額</th>
                        <th style="width:80px">狀態</th>
                    </tr>
                </thead>
                <?php
                $_fmtReportPeriod = function($rp, $period_fallback = '') {
                    if (!empty($rp) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rp, $_mm)) {
                        return $_mm[1] . '/' . (int)$_mm[2] . '-' . (int)$_mm[3] . '月';
                    }
                    if (!empty($rp) && preg_match('/^(\d{4})-(\d{2})$/', $rp, $_mm)) {
                        return $_mm[1] . '/' . $_mm[2];
                    }
                    if (!empty($period_fallback) && strlen($period_fallback) >= 6) {
                        return substr($period_fallback, 0, 4) . '/' . substr($period_fallback, 4, 2);
                    }
                    return '-';
                };
                $_grandU = 0; $_grandT = 0; $_grandA = 0; $_grandC = 0;
                $_renderSalesGroup = function($code, $label, $rows, $isAllowance) use (&$_grandU, &$_grandT, &$_grandA, &$_grandC, $_fmtReportPeriod) {
                    if (empty($rows)) return;
                    $sU = 0; $sT = 0; $sA = 0; $sC = 0;
                    $so = InvoiceModel::invoiceStatusOptions();
                    $amtStyle = $isAllowance ? ' style="color:var(--danger,#c5221f)"' : '';
                    $anchorId = 'sf-grp-' . ($code !== '' ? $code : 'other');
                    echo '<tbody id="' . htmlspecialchars($anchorId) . '">';
                    echo '<tr style="background:#eef2ff;font-weight:600">';
                    $hdr = $code !== '' ? '代碼 ' . $code . '：' . $label : $label;
                    echo '<td colspan="11">' . htmlspecialchars($hdr) . ' (' . count($rows) . ' 筆)</td>';
                    echo '</tr>';
                    foreach ($rows as $r) {
                        $isVoided = $r['status'] === 'voided';
                        $voidedStyle = $isVoided ? ' style="opacity:.5;text-decoration:line-through"' : '';
                        if (!$isVoided) { $sU += (int)$r['amount_untaxed']; $sT += (int)$r['tax_amount']; $sA += (int)$r['total_amount']; $sC++; }
                        $statusLabel = isset($so[$r['status']]) ? $so[$r['status']] : $r['status'];
                        $badgeClass = $r['status'] === 'voided' ? 'danger' : ($r['status'] === 'confirmed' ? 'success' : 'warning');
                        $isStar = !empty($r['is_starred_tax']);
                        $rpDisp = $_fmtReportPeriod(!empty($r['report_period']) ? $r['report_period'] : '', !empty($r['period']) ? $r['period'] : '');
                        echo '<tr' . $voidedStyle . '>';
                        echo '<td class="text-center"><span class="star-toggle ' . ($isStar ? 'is-on' : '') . '" data-id="' . (int)$r['id'] . '" onclick="toggleStarTaxSales(this)" title="標記">&#9733;</span></td>';
                        echo '<td><a href="/sales_invoices.php?action=edit&id=' . (int)$r['id'] . '">' . htmlspecialchars(!empty($r['invoice_number']) ? $r['invoice_number'] : '-') . '</a></td>';
                        echo '<td>' . htmlspecialchars(!empty($r['invoice_date']) ? $r['invoice_date'] : '') . '</td>';
                        echo '<td style="color:#1565c0">' . htmlspecialchars($rpDisp) . '</td>';
                        echo '<td>' . htmlspecialchars(!empty($r['customer_name']) ? $r['customer_name'] : '-') . '</td>';
                        echo '<td>' . htmlspecialchars(!empty($r['customer_tax_id']) ? $r['customer_tax_id'] : '-') . '</td>';
                        echo '<td>' . htmlspecialchars(!empty($r['invoice_type']) ? $r['invoice_type'] : '-') . '</td>';
                        echo '<td class="text-right"' . $amtStyle . '>$' . number_format(!empty($r['amount_untaxed']) ? $r['amount_untaxed'] : 0) . '</td>';
                        echo '<td class="text-right"' . $amtStyle . '>$' . number_format(!empty($r['tax_amount']) ? $r['tax_amount'] : 0) . '</td>';
                        echo '<td class="text-right"' . $amtStyle . '>$' . number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) . '</td>';
                        echo '<td><span class="badge badge-' . $badgeClass . '">' . htmlspecialchars($statusLabel) . '</span></td>';
                        echo '</tr>';
                    }
                    $sign = $isAllowance ? -1 : 1;
                    $allowNote = $isAllowance ? '（折讓扣除）' : '';
                    $prefix = $isAllowance ? '-' : '';
                    echo '<tr style="font-weight:600;background:#fafafa">';
                    echo '<td colspan="7">小計 ' . htmlspecialchars($code !== '' ? $code : '-') . $allowNote . ' (' . $sC . ' 筆)</td>';
                    echo '<td class="text-right"' . $amtStyle . '>' . ($sU > 0 ? $prefix : '') . '$' . number_format($sU) . '</td>';
                    echo '<td class="text-right"' . $amtStyle . '>' . ($sT > 0 ? $prefix : '') . '$' . number_format($sT) . '</td>';
                    echo '<td class="text-right"' . $amtStyle . '>' . ($sA > 0 ? $prefix : '') . '$' . number_format($sA) . '</td>';
                    echo '<td></td>';
                    echo '</tr>';
                    echo '</tbody>';
                    $_grandU += $sign * $sU; $_grandT += $sign * $sT; $_grandA += $sign * $sA; $_grandC += $sC;
                };
                foreach ($_sdGroups as $_k => $_g) {
                    $_isRefund = in_array((string)$_k, array('33', '34'), true);
                    $_renderSalesGroup($_k, isset($_sfLabels[$_k]) ? $_sfLabels[$_k] : $_k, $_g, $_isRefund);
                }
                if (!empty($_sdOther)) {
                    $_renderSalesGroup('', '未標註聯式', $_sdOther, false);
                }
                ?>
                <tfoot>
                    <tr style="font-weight:700;background:var(--gray-50,#f8f9fa);border-top:2px solid #aaa">
                        <td colspan="7">總計（已確認，33/34 已扣除）<?= $_grandC ?> 筆</td>
                        <td class="text-right">$<?= number_format($_grandU) ?></td>
                        <td class="text-right">$<?= number_format($_grandT) ?></td>
                        <td class="text-right">$<?= number_format($_grandA) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 進項發票聯式彙總 -->
<?php
$_pfLabels = array(
    '21' => '21：進項三聯式、電子計算機統一發票',
    '22' => '22：進項二聯式收銀機統一發票、載有稅額之其他憑證',
    '23' => '23：三聯式進貨退出或折讓證明單',
    '24' => '24：二聯式進貨退出或折讓證明單',
    '25' => '25：進項三聯式收銀機統一發票、電子發票',
);
$_pfStats = array();
foreach ($_pfLabels as $_k => $_v) {
    $_pfStats[$_k] = array('count' => 0, 'untaxed' => 0, 'tax' => 0, 'total' => 0);
}
$_pfOther = array('count' => 0, 'untaxed' => 0, 'tax' => 0, 'total' => 0);
$_pfPendingRefund = 0; // 待處理的進項折讓 23/24 計數
foreach ($purchaseDetail as $_r) {
    if ($_r['status'] === 'voided') continue;
    // 僅計入「可扣抵」的進項發票，與頂部卡片一致
    if (($_r['deduction_type'] ?? '') !== 'deductible') continue;
    $_fmt = !empty($_r['invoice_format']) ? $_r['invoice_format'] : '';
    // 折讓 23/24 必須開立確認後才計入 401
    if (in_array((string)$_fmt, array('23', '24'), true) && ($_r['status'] ?? '') !== 'confirmed') {
        $_pfPendingRefund++;
        continue;
    }
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
    <div class="card-header">進項發票聯式彙總（僅計可扣抵且開立已確認）</div>
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
                    $_clickable = $_s['count'] > 0;
                    $_rowStyle = $_s['count'] === 0 ? 'color:#bbb' : ($_isRefund ? 'color:var(--danger)' : '');
                    if ($_clickable) $_rowStyle .= ($_rowStyle ? ';' : '') . 'cursor:pointer';
                    $_onClick = $_clickable ? "onclick=\"jumpToFmtGroup('purchaseDetailBody','purchaseDetailArrow','pf-grp-{$_k}')\" title=\"點擊跳到此聯式的明細\"" : '';
                ?>
                <tr style="<?= $_rowStyle ?>" <?= $_onClick ?>>
                    <td><strong><?= e($_k) ?></strong><?= $_clickable ? ' <span style="color:#888;font-size:.75rem">▸</span>' : '' ?></td>
                    <td><?= e($_pfLabels[$_k]) ?><?= $_isRefund ? '（退出折讓，扣除）' : '' ?></td>
                    <td class="text-right"><?= number_format($_s['count']) ?></td>
                    <td class="text-right"><?= $_prefix ?>$<?= number_format($_s['untaxed']) ?></td>
                    <td class="text-right"<?= $_isRefund ? '' : ' style="color:var(--success)"' ?>><?= $_prefix ?>$<?= number_format($_s['tax']) ?></td>
                    <td class="text-right"><?= $_prefix ?>$<?= number_format($_s['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($_pfOther['count'] > 0): ?>
                <tr style="cursor:pointer" onclick="jumpToFmtGroup('purchaseDetailBody','purchaseDetailArrow','pf-grp-other')" title="點擊跳到此分組的明細">
                    <td><strong>-</strong> <span style="color:#888;font-size:.75rem">▸</span></td>
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

<!-- 進項發票明細（依聯式分組，可收合） -->
<?php
$_pdGroups = array();
foreach ($_pfLabels as $_k => $_v) { $_pdGroups[$_k] = array(); }
$_pdOther = array();
foreach ($purchaseDetail as $_r) {
    $_fmt = !empty($_r['invoice_format']) ? (string)$_r['invoice_format'] : '';
    if (isset($_pdGroups[$_fmt])) { $_pdGroups[$_fmt][] = $_r; } else { $_pdOther[] = $_r; }
}
foreach ($_pdGroups as $_k => $_g) { usort($_g, $_invSort); $_pdGroups[$_k] = $_g; }
usort($_pdOther, $_invSort);
$_pdOpen = !empty($_GET['pdOpen']) && $_GET['pdOpen'] === '1';
?>
<div class="card mt-2">
    <div class="card-header d-flex justify-between align-center" style="cursor:pointer" onclick="toggleSection('purchaseDetailBody','purchaseDetailArrow')">
        <span>
            <span id="purchaseDetailArrow" style="display:inline-block;transition:transform .2s;<?= $_pdOpen ? 'transform:rotate(90deg)' : '' ?>">▶</span>
            進項發票明細（已確認 <?= count($purchaseDetail) ?> 筆） — 依聯式分組，依日期/發票號碼排序
        </span>
        <span style="display:inline-flex;gap:8px;align-items:center">
            <a href="/tax_report.php?action=export_purchase&period=<?= e($period) ?>&company_tax_id=<?= e($companyTaxId) ?>"
               class="btn btn-sm" style="background:#16a34a;color:#fff;font-size:.78rem;padding:3px 10px"
               onclick="event.stopPropagation()" title="下載進項發票明細 CSV（Excel 可直接開啟）">📥 下載 Excel</a>
            <span style="font-size:.85rem;color:#888">點擊展開/收合</span>
        </span>
    </div>
    <div id="purchaseDetailBody" style="<?= $_pdOpen ? '' : 'display:none' ?>">
        <?php if (empty($purchaseDetail)): ?>
            <p class="text-muted text-center mt-2">此期間無進項發票</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:32px"></th>
                        <th style="width:120px">發票號碼</th>
                        <th style="width:100px">日期</th>
                        <th style="width:90px">申報期間</th>
                        <th>供應商</th>
                        <th style="width:100px">統編</th>
                        <th style="width:60px">類型</th>
                        <th style="width:80px">扣抵</th>
                        <th class="text-right">未稅金額</th>
                        <th class="text-right">稅額</th>
                        <th class="text-right">含稅金額</th>
                        <th style="width:80px">狀態</th>
                    </tr>
                </thead>
                <?php
                $_pgrandU = 0; $_pgrandT = 0; $_pgrandA = 0; $_pgrandC = 0;
                $_renderPurchGroup = function($code, $label, $rows, $isAllowance) use (&$_pgrandU, &$_pgrandT, &$_pgrandA, &$_pgrandC, $_fmtReportPeriod) {
                    if (empty($rows)) return;
                    $sU = 0; $sT = 0; $sA = 0; $sC = 0;
                    $so = InvoiceModel::invoiceStatusOptions();
                    $amtStyle = $isAllowance ? ' style="color:var(--danger,#c5221f)"' : '';
                    $anchorId = 'pf-grp-' . ($code !== '' ? $code : 'other');
                    echo '<tbody id="' . htmlspecialchars($anchorId) . '">';
                    echo '<tr style="background:#e8f5e9;font-weight:600">';
                    $hdr = $code !== '' ? '代碼 ' . $code . '：' . $label : $label;
                    echo '<td colspan="12">' . htmlspecialchars($hdr) . ' (' . count($rows) . ' 筆)</td>';
                    echo '</tr>';
                    foreach ($rows as $r) {
                        $isVoided = $r['status'] === 'voided';
                        $voidedStyle = $isVoided ? ' style="opacity:.5;text-decoration:line-through"' : '';
                        if (!$isVoided) { $sU += (int)$r['amount_untaxed']; $sT += (int)$r['tax_amount']; $sA += (int)$r['total_amount']; $sC++; }
                        $statusLabel = isset($so[$r['status']]) ? $so[$r['status']] : $r['status'];
                        $badgeClass = $r['status'] === 'voided' ? 'danger' : ($r['status'] === 'confirmed' ? 'success' : 'warning');
                        $deduct = (!empty($r['deduction_type']) && $r['deduction_type'] === 'deductible') ? '<span style="color:var(--success)">可扣抵</span>' : '<span style="color:var(--danger)">不可扣抵</span>';
                        $isStar = !empty($r['is_starred_tax']);
                        $rpDisp = $_fmtReportPeriod(!empty($r['report_period']) ? $r['report_period'] : '', !empty($r['period']) ? $r['period'] : '');
                        echo '<tr' . $voidedStyle . '>';
                        echo '<td class="text-center"><span class="star-toggle ' . ($isStar ? 'is-on' : '') . '" data-id="' . (int)$r['id'] . '" onclick="toggleStarTaxPurchase(this)" title="標記">&#9733;</span></td>';
                        echo '<td><a href="/purchase_invoices.php?action=edit&id=' . (int)$r['id'] . '">' . htmlspecialchars(!empty($r['invoice_number']) ? $r['invoice_number'] : '-') . '</a></td>';
                        echo '<td>' . htmlspecialchars(!empty($r['invoice_date']) ? $r['invoice_date'] : '') . '</td>';
                        echo '<td style="color:#1565c0">' . htmlspecialchars($rpDisp) . '</td>';
                        echo '<td>' . htmlspecialchars(!empty($r['vendor_name']) ? $r['vendor_name'] : '-') . '</td>';
                        echo '<td>' . htmlspecialchars(!empty($r['vendor_tax_id']) ? $r['vendor_tax_id'] : '-') . '</td>';
                        echo '<td>' . htmlspecialchars(!empty($r['invoice_type']) ? $r['invoice_type'] : '-') . '</td>';
                        echo '<td>' . $deduct . '</td>';
                        echo '<td class="text-right"' . $amtStyle . '>$' . number_format(!empty($r['amount_untaxed']) ? $r['amount_untaxed'] : 0) . '</td>';
                        echo '<td class="text-right"' . $amtStyle . '>$' . number_format(!empty($r['tax_amount']) ? $r['tax_amount'] : 0) . '</td>';
                        echo '<td class="text-right"' . $amtStyle . '>$' . number_format(!empty($r['total_amount']) ? $r['total_amount'] : 0) . '</td>';
                        echo '<td><span class="badge badge-' . $badgeClass . '">' . htmlspecialchars($statusLabel) . '</span></td>';
                        echo '</tr>';
                    }
                    $sign = $isAllowance ? -1 : 1;
                    $allowNote = $isAllowance ? '（折讓扣除）' : '';
                    $prefix = $isAllowance ? '-' : '';
                    echo '<tr style="font-weight:600;background:#fafafa">';
                    echo '<td colspan="8">小計 ' . htmlspecialchars($code !== '' ? $code : '-') . $allowNote . ' (' . $sC . ' 筆)</td>';
                    echo '<td class="text-right"' . $amtStyle . '>' . ($sU > 0 ? $prefix : '') . '$' . number_format($sU) . '</td>';
                    echo '<td class="text-right"' . $amtStyle . '>' . ($sT > 0 ? $prefix : '') . '$' . number_format($sT) . '</td>';
                    echo '<td class="text-right"' . $amtStyle . '>' . ($sA > 0 ? $prefix : '') . '$' . number_format($sA) . '</td>';
                    echo '<td></td>';
                    echo '</tr>';
                    echo '</tbody>';
                    $_pgrandU += $sign * $sU; $_pgrandT += $sign * $sT; $_pgrandA += $sign * $sA; $_pgrandC += $sC;
                };
                foreach ($_pdGroups as $_k => $_g) {
                    $_isRefund = in_array((string)$_k, array('23', '24'), true);
                    $_renderPurchGroup($_k, isset($_pfLabels[$_k]) ? $_pfLabels[$_k] : $_k, $_g, $_isRefund);
                }
                if (!empty($_pdOther)) {
                    $_renderPurchGroup('', '未標註聯式', $_pdOther, false);
                }
                ?>
                <tfoot>
                    <tr style="font-weight:700;background:var(--gray-50,#f8f9fa);border-top:2px solid #aaa">
                        <td colspan="8">總計（已確認，23/24 已扣除）<?= $_pgrandC ?> 筆</td>
                        <td class="text-right">$<?= number_format($_pgrandU) ?></td>
                        <td class="text-right">$<?= number_format($_pgrandT) ?></td>
                        <td class="text-right">$<?= number_format($_pgrandA) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
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
.star-toggle { display:inline-block; cursor:pointer; font-size:1.2rem; color:#d0d0d0; transition:color .15s,transform .15s; user-select:none; line-height:1; }
.star-toggle:hover { color:#f1c40f; transform:scale(1.15); }
.star-toggle.is-on { color:#f1c40f; }
.star-toggle.saving { opacity:.5; pointer-events:none; }
@media (max-width: 767px) {
    .tax-summary-grid { grid-template-columns: 1fr; }
}
</style>
<script>
function _taxStarToggle(el, url) {
    if (el.classList.contains('saving')) return;
    var id = el.getAttribute('data-id'); if (!id) return;
    el.classList.add('saving');
    var fd = new FormData(); fd.append('id', id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url);
    xhr.onload = function() {
        el.classList.remove('saving');
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.error) { alert(res.error); return; }
            el.classList.toggle('is-on', !!res.starred);
        } catch (e) { alert('回應錯誤'); }
    };
    xhr.onerror = function() { el.classList.remove('saving'); alert('網路錯誤'); };
    xhr.send(fd);
}
function toggleStarTaxSales(el)    { _taxStarToggle(el, '/sales_invoices.php?action=toggle_star_tax'); }
function toggleStarTaxPurchase(el) { _taxStarToggle(el, '/purchase_invoices.php?action=toggle_star_tax'); }
</script>
