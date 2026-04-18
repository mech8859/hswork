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
    <div class="card tax-card">
        <div class="card-header" style="background:<?= (int)$summary['tax_payable'] >= 0 ? 'var(--danger)' : 'var(--info, #17a2b8)' ?>;color:#fff">
            <?= (int)$summary['tax_payable'] >= 0 ? '應繳稅額' : '可退稅額' ?>
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
            <div class="tax-row" style="border-top:2px solid var(--gray-200);padding-top:8px;margin-top:8px">
                <span style="font-size:1.1rem;font-weight:600"><?= (int)$summary['tax_payable'] >= 0 ? '應繳營業稅' : '可退營業稅' ?></span>
                <strong style="font-size:1.3rem;color:<?= (int)$summary['tax_payable'] >= 0 ? 'var(--danger)' : 'var(--success)' ?>">
                    $<?= number_format(abs((int)$summary['tax_payable'])) ?>
                </strong>
            </div>
        </div>
    </div>
</div>

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
foreach ($_sfStats as $s) { $_sfTot['count']+=$s['count']; $_sfTot['untaxed']+=$s['untaxed']; $_sfTot['tax']+=$s['tax']; $_sfTot['total']+=$s['total']; }
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
                <?php foreach ($_sfStats as $_k => $_s): ?>
                <tr<?= $_s['count'] === 0 ? ' style="color:#bbb"' : '' ?>>
                    <td><strong><?= e($_k) ?></strong></td>
                    <td><?= e($_sfLabels[$_k]) ?></td>
                    <td class="text-right"><?= number_format($_s['count']) ?></td>
                    <td class="text-right">$<?= number_format($_s['untaxed']) ?></td>
                    <td class="text-right" style="color:var(--danger)">$<?= number_format($_s['tax']) ?></td>
                    <td class="text-right">$<?= number_format($_s['total']) ?></td>
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
    $_fmt = !empty($_r['invoice_format']) ? $_r['invoice_format'] : '';
    $_target = isset($_pfStats[$_fmt]) ? $_pfStats[$_fmt] : $_pfOther;
    $_target['count']   += 1;
    $_target['untaxed'] += (int)$_r['amount_untaxed'];
    $_target['tax']     += (int)$_r['tax_amount'];
    $_target['total']   += (int)$_r['total_amount'];
    if (isset($_pfStats[$_fmt])) { $_pfStats[$_fmt] = $_target; } else { $_pfOther = $_target; }
}
$_pfTot = array('count' => 0, 'untaxed' => 0, 'tax' => 0, 'total' => 0);
foreach ($_pfStats as $s) { $_pfTot['count']+=$s['count']; $_pfTot['untaxed']+=$s['untaxed']; $_pfTot['tax']+=$s['tax']; $_pfTot['total']+=$s['total']; }
$_pfTot['count']+=$_pfOther['count']; $_pfTot['untaxed']+=$_pfOther['untaxed']; $_pfTot['tax']+=$_pfOther['tax']; $_pfTot['total']+=$_pfOther['total'];
?>
<div class="card mt-2">
    <div class="card-header">進項發票聯式彙總（不含作廢）</div>
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
                <?php foreach ($_pfStats as $_k => $_s): ?>
                <tr<?= $_s['count'] === 0 ? ' style="color:#bbb"' : '' ?>>
                    <td><strong><?= e($_k) ?></strong></td>
                    <td><?= e($_pfLabels[$_k]) ?></td>
                    <td class="text-right"><?= number_format($_s['count']) ?></td>
                    <td class="text-right">$<?= number_format($_s['untaxed']) ?></td>
                    <td class="text-right" style="color:var(--success)">$<?= number_format($_s['tax']) ?></td>
                    <td class="text-right">$<?= number_format($_s['total']) ?></td>
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
