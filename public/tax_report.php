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

$summary = $model->getTaxSummary($period);
$purchaseDetail = $model->getTaxDetail($period, 'purchase');
$salesDetail = $model->getTaxDetail($period, 'sales');

$pageTitle = '401 營業稅申報';
$currentPage = 'tax_report';
require __DIR__ . '/../templates/layouts/header.php';
require __DIR__ . '/../templates/accounting/tax_report.php';
require __DIR__ . '/../templates/layouts/footer.php';
