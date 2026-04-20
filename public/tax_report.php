<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('finance.manage') && !Auth::hasPermission('finance.view')) {
    Session::flash('error', '無權限存取');
    redirect('/');
}
require_once __DIR__ . '/../modules/accounting/InvoiceModel.php';

$model = new InvoiceModel();

// 預設當前雙月期間
$currentYear = (int) date('Y');
$currentMonth = (int) date('m');
$bimonthMap = array(
    1 => '01-02', 2 => '01-02',
    3 => '03-04', 4 => '03-04',
    5 => '05-06', 6 => '05-06',
    7 => '07-08', 8 => '07-08',
    9 => '09-10', 10 => '09-10',
    11 => '11-12', 12 => '11-12',
);
$defaultPeriod = $currentYear . '-' . $bimonthMap[$currentMonth];

$period = !empty($_GET['period']) ? $_GET['period'] : $defaultPeriod;
$taxPeriodOptions = $model->getTaxPeriodOptions();

// 公司統編過濾（預設禾順 94081455；選「全部」會送空字串）
$companyTaxId = isset($_GET['company_tax_id']) ? $_GET['company_tax_id'] : '94081455';

$summary = $model->getTaxSummary($period, $companyTaxId);
$purchaseDetail = $model->getTaxDetail($period, 'purchase', $companyTaxId);
$salesDetail = $model->getTaxDetail($period, 'sales', $companyTaxId);

// 上期累積留抵（格108）：依 period 存/讀 system_settings
$_prevCreditKey = 'tax_prev_credit_' . $period;
$_db = Database::getInstance();
if (isset($_GET['prev_credit']) && $_GET['prev_credit'] !== '') {
    $_pv = max(0, (int)$_GET['prev_credit']);
    $_stmt = $_db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $_stmt->execute(array($_prevCreditKey, (string)$_pv));
    $prevCredit = $_pv;
} else {
    $_stmt = $_db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $_stmt->execute(array($_prevCreditKey));
    $prevCredit = (int)($_stmt->fetchColumn() ?: 0);
}
// 回寫頂部「應繳稅額」卡片：實際應繳 = 銷項 - (進項 + 上期留抵)
$_finalPayable = (int)$summary['sales_tax'] - ((int)$summary['purchase_deductible_tax'] + $prevCredit);
$summary['tax_payable_final'] = $_finalPayable;
$summary['prev_credit'] = $prevCredit;

$pageTitle = '401 營業稅申報';
$currentPage = 'tax_report';
require __DIR__ . '/../templates/layouts/header.php';
require __DIR__ . '/../templates/accounting/tax_report.php';
require __DIR__ . '/../templates/layouts/footer.php';
