<?php
$chineseNums = array('一','二','三','四','五','六','七','八','九','十');
$salesName = $quote['sales_name'] ?: '';

// 從 DB 讀取報價單設定
require_once __DIR__ . '/../../modules/settings/DropdownModel.php';
$_settingsModel = new DropdownModel();
$_allQs = $_settingsModel->getSettings('quotation');

// 根據報價公司載入對應設定
$_qCompany = isset($quote['quote_company']) ? $quote['quote_company'] : 'hershun';
$qs = $_allQs;
if ($_qCompany === 'lichuang') {
    // 理創：把 lc_ 前綴的設定映射回原本 key 名
    foreach ($_allQs as $k => $v) {
        if (strpos($k, 'lc_quote_') === 0) {
            $qs[substr($k, 3)] = $v; // lc_quote_xxx → quote_xxx
        }
    }
}
// 優先用報價單自己的保固月數，沒有則用系統預設
$warrantyMonths = !empty($quote['warranty_months']) ? $quote['warranty_months'] : (isset($qs['quote_warranty_months']) ? $qs['quote_warranty_months'] : '12');

// 依報價單所屬分公司取「公司抬頭 / 地址 / 電話 / 傳真」
// 規則：quote_xxx_b{branch_id} > quote_xxx（預設 fallback） > 寫死預設
$_quoteBranchId = (int)(isset($quote['branch_id']) ? $quote['branch_id'] : 0);
$_getQsBranch = function($baseKey, $default = '') use ($qs, $_quoteBranchId) {
    if ($_quoteBranchId > 0) {
        $bk = $baseKey . '_b' . $_quoteBranchId;
        if (isset($qs[$bk]) && $qs[$bk] !== '') return $qs[$bk];
    }
    if (isset($qs[$baseKey]) && $qs[$baseKey] !== '') return $qs[$baseKey];
    return $default;
};
$_qsCompanyTitle   = $_getQsBranch('quote_company_title',   '禾順監視數位科技-台中分公司');
$_qsContactAddress = $_getQsBranch('quote_contact_address', '台中市潭子區環中路一段138巷1之5號');
$_qsContactPhone   = $_getQsBranch('quote_contact_phone',   '04-2534-7007');
$_qsContactFax     = $_getQsBranch('quote_contact_fax',     '04-2534-7661');
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<title>報價單 <?= e($quote['quotation_number']) ?></title>
<style>
@page { size: A4; margin: 10mm 12mm; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: "Microsoft JhengHei", "微軟正黑體", Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }

.header { text-align: center; margin-bottom: 8px; }
.header .logo-row { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 2px; }
.header h1 { font-size: 22px; font-weight: bold; margin: 0; }
.header .subtitle { font-size: 11px; color: #666; margin-bottom: 6px; }

.info-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: 12px; table-layout: fixed; }
.info-table td { padding: 3px 5px; vertical-align: top; overflow: hidden; text-overflow: ellipsis; }
.info-table .label { color: #666; white-space: nowrap; width: 70px; }
.info-table .info-right.label { width: 70px; }
.info-right { text-align: right; }

.items-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; font-size: 10.5px; }
.items-table th { background: #e8f0e8; border: 1px solid #ccc; padding: 4px 6px; text-align: center; font-weight: bold; }
.items-table td { border: 1px solid #ccc; padding: 3px 6px; }
.items-table .text-right { text-align: right; }
.items-table .text-center { text-align: center; }
.items-table .section-header { background: #f5f5f5; font-weight: bold; }
.items-table .subtotal-row { background: #fafafa; }
.items-table .subtotal-row td { border-top: 2px solid #999; }

.totals-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 11px; }
.totals-table td { padding: 2px 6px; border: 1px solid #ccc; }
.totals-table .label { text-align: right; width: 75%; }
.totals-table .amount { text-align: right; width: 25%; font-weight: bold; }

.payment-row { font-size: 10.5px; margin-bottom: 6px; }
.payment-row strong { color: #333; }

.footer-section { margin-top: 8px; font-size: 9.5px; }
.bank-info { display: flex; gap: 30px; margin-bottom: 6px; }
.bank-info div { flex: 1; }
.bank-info .bank-label { color: #666; display: inline-block; width: 45px; }
.bank-notice { color: #c00; font-size: 9px; margin-bottom: 4px; }

.warranty { font-size: 9px; margin-bottom: 8px; line-height: 1.5; }
.warranty p { margin-bottom: 3px; }

.sign-area { display: flex; justify-content: flex-end; gap: 40px; margin-top: 10px; }
.sign-block { text-align: center; }
.sign-block .sign-title { font-weight: bold; font-size: 10.5px; margin-bottom: 20px; }
.sign-line { border-bottom: 1px solid #333; width: 120px; margin: 0 auto 4px; }

.line-id { text-align: center; font-size: 10px; margin-top: 8px; color: #666; }

@media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
}
@media screen {
    body { max-width: 210mm; margin: 10mm auto; padding: 10mm; background: #f5f5f5; }
    body > * { background: #fff; }
}
</style>
</head>
<body>

<button class="no-print" onclick="window.print()" style="position:fixed;top:10px;right:10px;padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;z-index:100">列印 / 存PDF</button>

<!-- 公司抬頭 -->
<div class="header">
    <h1><?= e($_qsCompanyTitle) ?> 報價單</h1>
    <div class="subtitle"><?= e(isset($qs['quote_company_subtitle']) && $qs['quote_company_subtitle'] ? $qs['quote_company_subtitle'] : '監控系統/電話總機/影視對講/門禁管制/商用音響/網路工程') ?></div>
</div>

<!-- 客戶及報價資訊 -->
<?php
$printCustNo = '';
$printCaseNo = '';
if (!empty($quote['customer_id'])) {
    try {
        $cnoStmt = Database::getInstance()->prepare('SELECT customer_no FROM customers WHERE id = ?');
        $cnoStmt->execute(array($quote['customer_id']));
        $cnoRow = $cnoStmt->fetch(PDO::FETCH_ASSOC);
        if ($cnoRow && $cnoRow['customer_no']) $printCustNo = $cnoRow['customer_no'];
    } catch (Exception $ex) {}
}
if (!empty($quote['case_id'])) {
    try {
        $caseStmt = Database::getInstance()->prepare('SELECT case_number FROM cases WHERE id = ?');
        $caseStmt->execute(array($quote['case_id']));
        $caseRow = $caseStmt->fetch(PDO::FETCH_ASSOC);
        if ($caseRow) $printCaseNo = $caseRow['case_number'];
    } catch (Exception $ex) {}
}
?>
<table class="info-table">
    <colgroup>
        <col style="width:10%"><col style="width:23%"><col style="width:10%"><col style="width:23%"><col style="width:10%"><col style="width:24%">
    </colgroup>
    <tr>
        <td class="label">客戶名稱：</td>
        <td><?= e($quote['customer_name']) ?></td>
        <td class="label">發票資訊</td>
        <td></td>
        <td class="label">報價日期：</td>
        <td><?= e($quote['quote_date']) ?></td>
    </tr>
    <tr>
        <td class="label">案件名稱：</td>
        <td><?= e($quote['site_name'] ?: '') ?></td>
        <td class="label">抬　頭：</td>
        <td><?= e($quote['invoice_title'] ?: '') ?></td>
        <td class="label">有效日期：</td>
        <td><?= e($quote['valid_date']) ?></td>
    </tr>
    <tr>
        <td class="label">連絡對象：</td>
        <td><?= e($quote['contact_person'] ?: '') ?></td>
        <td class="label">統　編：</td>
        <td><?= e($quote['invoice_tax_id'] ?: '') ?></td>
        <td class="label">承辦業務：</td>
        <td><?= e($salesName) ?></td>
    </tr>
    <tr>
        <td class="label">連絡電話：</td>
        <td><?= e($quote['contact_phone'] ?: '') ?></td>
        <td class="label">進件編號：</td>
        <td><?= e($printCaseNo) ?></td>
        <td class="label">服務專線：</td>
        <td><?= e(isset($qs['quote_service_phone']) && $qs['quote_service_phone'] ? $qs['quote_service_phone'] : '0800-008-859') ?></td>
    </tr>
    <tr>
        <td class="label">施工地址：</td>
        <td colspan="5"><?= e($quote['site_address'] ?: '') ?></td>
    </tr>
</table>

<!-- 報價明細 -->
<table class="items-table">
    <thead>
        <tr>
            <th style="width:22px">序</th>
            <th style="width:455px">品名/型號</th>
            <th style="width:40px">數量</th>
            <th style="width:40px">單位</th>
            <th style="width:58px">單價</th>
            <th style="width:65px">小計</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($quote['sections'] as $sIdx => $sec): ?>
        <?php if ($quote['format'] === 'project'): ?>
        <tr class="section-header">
            <td><?= isset($chineseNums[$sIdx]) ? $chineseNums[$sIdx] : ($sIdx + 1) ?></td>
            <td colspan="5"><?= e($sec['title'] ?: '') ?></td>
        </tr>
        <?php endif; ?>
        <?php foreach ($sec['items'] as $iIdx => $item): ?>
        <tr>
            <td class="text-center"><?= $iIdx + 1 ?></td>
            <td>
                <?= e($item['item_name']) ?><?php if (empty($quote['hide_model_on_print']) && !empty($item['model_number'])): ?> / <?= e($item['model_number']) ?><?php endif; ?>
                <?php if (!empty($item['remark'])): ?>
                <div style="color:#555;font-size:.9em;margin-top:2px;white-space:pre-line"><?= e($item['remark']) ?></div>
                <?php endif; ?>
            </td>
            <td class="text-right"><?= rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.') ?></td>
            <td class="text-center"><?= e($item['unit']) ?></td>
            <td class="text-right"><?= number_format((int)$item['unit_price']) ?></td>
            <td class="text-right"><?= number_format((int)$item['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($quote['format'] === 'project'): ?>
        <?php
            $secHasDisc = isset($sec['discount_amount']) && $sec['discount_amount'] !== null && $sec['discount_amount'] !== '';
            $secHasNote = !empty($sec['notes']);
        ?>
        <tr class="subtotal-row">
            <?php if ($secHasNote): ?>
                <td colspan="4" style="background:#e8f5e9;color:#1b5e20;font-size:.9em;padding:4px 10px;white-space:pre-line"><?= e($sec['notes']) ?></td>
                <td class="text-right"><strong>小計</strong></td>
            <?php else: ?>
                <td colspan="5" class="text-right"><strong>小計</strong></td>
            <?php endif; ?>
            <td class="text-right">
                <?php if ($secHasDisc): ?>
                    <span style="text-decoration:line-through;color:#999;font-weight:400;font-size:.85em"><?= number_format((int)$sec['subtotal']) ?></span>
                    <strong style="color:#c62828;margin-left:4px"><?= number_format((int)$sec['discount_amount']) ?></strong>
                <?php else: ?>
                    <strong><?= number_format((int)$sec['subtotal']) ?></strong>
                <?php endif; ?>
            </td>
        </tr>
        <?php elseif (!empty($sec['notes'])): ?>
        <tr class="section-notes-row">
            <td colspan="6" style="background:#e8f5e9;padding:6px 10px;font-size:.9em;color:#1b5e20;white-space:pre-line"><?= e($sec['notes']) ?></td>
        </tr>
        <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- 合計 -->
<table class="totals-table">
    <?php if (empty($quote['tax_free'])): ?>
    <tr><td class="label">未稅合計：</td><td class="amount">$<?= number_format((int)$quote['subtotal']) ?></td></tr>
    <tr><td class="label">營業稅 (<?= rtrim(rtrim(number_format($quote['tax_rate'], 2), '0'), '.') ?>%)：</td><td class="amount">$<?= number_format((int)$quote['tax_amount']) ?></td></tr>
    <tr><td class="label"><strong>合計新台幣：</strong></td><td class="amount"><strong>$<?= number_format((int)$quote['total_amount']) ?></strong></td></tr>
    <?php else: ?>
    <tr><td class="label"><strong>合計新台幣：</strong></td><td class="amount"><strong>$<?= number_format((int)$quote['subtotal']) ?></strong></td></tr>
    <?php endif; ?>
    <?php if (!empty($quote['has_discount']) && !empty($quote['discount_amount'])): ?>
    <tr><td class="label" style="color:red"><strong>優惠價：</strong></td><td class="amount" style="color:red"><strong>$<?= number_format((int)$quote['discount_amount']) ?></strong></td></tr>
    <?php endif; ?>
</table>

<!-- 收款條件 -->
<?php if ($quote['payment_terms']): ?>
<div class="payment-row"><strong>收款條件：</strong><?= e($quote['payment_terms']) ?></div>
<?php endif; ?>
<?php if ($quote['notes']): ?>
<div class="payment-row"><strong>附註說明：</strong><?= e($quote['notes']) ?></div>
<?php endif; ?>

<!-- 匯款資料 & 連絡資訊 -->
<div class="footer-section">
    <div class="bank-info">
        <div>
            <strong>匯款資料</strong><br>
            <span class="bank-label">戶名：</span><?= e(isset($qs['quote_bank_name']) ? $qs['quote_bank_name'] : '禾順監視數位科技有限公司') ?><br>
            <span class="bank-label">銀行：</span><?= e(isset($qs['quote_bank_branch']) ? $qs['quote_bank_branch'] : '中國信託銀行(822) 豐原分行') ?><br>
            <span class="bank-label">帳號：</span><?= e(isset($qs['quote_bank_account']) ? $qs['quote_bank_account'] : '3925-4087-3162') ?>
        </div>
        <div>
            <strong>連絡資訊</strong><br>
            <span class="bank-label">地址：</span><?= e($_qsContactAddress) ?><br>
            <span class="bank-label">電話：</span><?= e($_qsContactPhone) ?><br>
            <span class="bank-label">傳真：</span><?= e($_qsContactFax) ?>
        </div>
    </div>
    <div class="bank-notice"><?= e(isset($qs['quote_bank_reminder']) ? $qs['quote_bank_reminder'] : '溫馨提醒:匯款後記得告知匯款帳號4-6碼及金額') ?></div>
    <div class="bank-notice"><?= e(isset($qs['quote_deposit_notice']) ? $qs['quote_deposit_notice'] : '') ?></div>
</div>

<!-- 保固條款 -->
<div class="warranty">
    <p>一、 <?= e(str_replace('{months}', $warrantyMonths, isset($qs['quote_warranty_text_1']) ? $qs['quote_warranty_text_1'] : '產品安裝日【設備(含變壓器)保固' . $warrantyMonths . '個月，消耗品除外】')) ?></p>
    <p>二、 <?= e(isset($qs['quote_warranty_text_2']) ? $qs['quote_warranty_text_2'] : '') ?></p>
    <p>三、<?= e(isset($qs['quote_warranty_text_3']) ? $qs['quote_warranty_text_3'] : '') ?></p>
</div>

<!-- 簽名區 -->
<table style="width:100%;margin-top:10px;border:none;border-collapse:collapse">
    <tr>
        <td style="vertical-align:bottom;border:none;padding:0" rowspan="2">
            <div style="display:flex;gap:10px;align-items:flex-end">
                <?php if (!empty($qs['quote_stamp_image'])): ?>
                <img src="/<?= e($qs['quote_stamp_image']) ?>" style="max-height:90px">
                <?php endif; ?>
                <?php if (!empty($qs['quote_qrcode_image'])): ?>
                <div style="text-align:center">
                    <img src="/<?= e($qs['quote_qrcode_image']) ?>" style="max-height:80px"><br>
                    <span style="font-size:8px">ID:<?= e(isset($qs['quote_line_id']) ? $qs['quote_line_id'] : '') ?></span>
                </div>
                <?php endif; ?>
            </div>
        </td>
        <td style="text-align:center;border:none;padding:4px 15px;width:150px;font-weight:bold;font-size:10.5px">業務確認</td>
        <td style="text-align:center;border:none;padding:4px 15px;width:150px;font-weight:bold;font-size:10.5px">客戶確認</td>
    </tr>
    <tr>
        <td style="text-align:left;border:none;padding:8px 15px;height:40px;font-size:10px">簽名：<?= e($salesName) ?></td>
        <td style="text-align:left;border:none;padding:8px 15px;height:40px;font-size:10px">簽名：</td>
    </tr>
    <tr>
        <td style="border:none;padding:0"></td>
        <td style="text-align:center;border:none;padding:4px 15px;font-weight:bold;font-size:10.5px">施工人員</td>
        <td style="text-align:center;border:none;padding:4px 15px;font-weight:bold;font-size:10.5px">完工日期</td>
    </tr>
    <tr>
        <td style="border:none;padding:0"></td>
        <td style="text-align:left;border:none;padding:8px 15px;height:40px;font-size:10px">簽名：</td>
        <td style="text-align:left;border:none;padding:8px 15px;height:40px;font-size:10px"></td>
    </tr>
</table>

<?php if (empty($qs['quote_stamp_image']) && empty($qs['quote_qrcode_image'])): ?>
<div class="line-id">官方LINE ID : <?= e(isset($qs['quote_line_id']) ? $qs['quote_line_id'] : '@hs0425347007') ?></div>
<?php endif; ?>

</body>
</html>
