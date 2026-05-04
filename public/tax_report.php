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

// === 下載進項發票明細 CSV ===
if (isset($_GET['action']) && $_GET['action'] === 'export_purchase') {
    // 過濾：僅「開立已確認」（與報表彙總一致）
    $rows = array_values(array_filter($purchaseDetail, function($r) {
        return ($r['status'] ?? '') === 'confirmed';
    }));
    // 排序：依聯式 → 日期 → 發票號碼
    usort($rows, function($a, $b) {
        $fa = (string)($a['invoice_format'] ?? '');
        $fb = (string)($b['invoice_format'] ?? '');
        $c = strcmp($fa, $fb);
        if ($c !== 0) return $c;
        $c = strcmp((string)($a['invoice_date'] ?? ''), (string)($b['invoice_date'] ?? ''));
        if ($c !== 0) return $c;
        return strcmp((string)($a['invoice_number'] ?? ''), (string)($b['invoice_number'] ?? ''));
    });

    $fmtLabels = array(
        '21' => '21 進項三聯式、電子計算機統一發票',
        '22' => '22 進項二聯式收銀機、載有稅額之其他憑證',
        '23' => '23 三聯式進貨退出或折讓',
        '24' => '24 二聯式進貨退出或折讓',
        '25' => '25 進項三聯式收銀機、公用事業憑證',
    );
    $deductLabels = array(
        'deductible_purchase' => '進項之費用',
        'deductible_asset'    => '固定資產',
        'non_deductible'      => '不可扣抵',
    );

    $fmtRP = function($rp, $period_fb = '') {
        if (!empty($rp) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rp, $m)) {
            return $m[1] . '/' . (int)$m[2] . '-' . (int)$m[3] . '月';
        }
        if (!empty($rp) && preg_match('/^(\d{4})-(\d{2})$/', $rp, $m)) {
            return $m[1] . '/' . $m[2];
        }
        if (!empty($period_fb) && strlen($period_fb) >= 6) {
            return substr($period_fb, 0, 4) . '/' . substr($period_fb, 4, 2);
        }
        return '';
    };

    $fname = '401進項發票明細_' . $period . '_' . ($companyTaxId ?: 'all') . '_' . date('YmdHis') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $fp = fopen('php://output', 'w');
    // UTF-8 BOM 讓 Excel 識別中文
    fwrite($fp, "\xEF\xBB\xBF");

    // 第 1 列：標題說明
    fputcsv($fp, array('401 進項發票明細（開立已確認）'));
    fputcsv($fp, array('申報期間', $period, '公司統編', $companyTaxId ?: '全部', '匯出時間', date('Y-m-d H:i:s')));
    fputcsv($fp, array());

    // 表頭
    fputcsv($fp, array('代碼', '聯式', '發票號碼', '日期', '申報期間', '供應商', '統編', '類型', '扣抵', '未稅金額', '稅額', '含稅金額', '備註'));

    $sumU = 0; $sumT = 0; $sumA = 0; $count = 0;
    foreach ($rows as $r) {
        $code = !empty($r['invoice_format']) ? $r['invoice_format'] : '';
        $deduct = !empty($r['deduction_category']) && isset($deductLabels[$r['deduction_category']])
            ? $deductLabels[$r['deduction_category']]
            : (($r['deduction_type'] ?? '') === 'deductible' ? '可扣抵' : ($r['deduction_type'] === 'non_deductible' ? '不可扣抵' : ''));
        $isAllow = in_array($code, array('23', '24'), true);
        $sign = $isAllow ? -1 : 1;
        $u = (int)($r['amount_untaxed'] ?? 0);
        $t = (int)($r['tax_amount'] ?? 0);
        $a = (int)($r['total_amount'] ?? 0);

        fputcsv($fp, array(
            $code,
            $code !== '' && isset($fmtLabels[$code]) ? $fmtLabels[$code] : $code,
            $r['invoice_number'] ?? '',
            $r['invoice_date'] ?? '',
            $fmtRP($r['report_period'] ?? '', $r['period'] ?? ''),
            $r['vendor_name'] ?? '',
            $r['vendor_tax_id'] ?? '',
            $r['invoice_type'] ?? '',
            $deduct,
            $sign * $u,
            $sign * $t,
            $sign * $a,
            $r['note'] ?? '',
        ));
        $sumU += $sign * $u;
        $sumT += $sign * $t;
        $sumA += $sign * $a;
        $count++;
    }

    // 合計列（23/24 已扣除）
    fputcsv($fp, array());
    fputcsv($fp, array('合計（已確認，23/24 退折已扣除）', '', '', '', '', '', '', '', $count . ' 筆', $sumU, $sumT, $sumA, ''));

    fclose($fp);
    exit;
}

$pageTitle = '401 營業稅申報';
$currentPage = 'tax_report';
require __DIR__ . '/../templates/layouts/header.php';
require __DIR__ . '/../templates/accounting/tax_report.php';
require __DIR__ . '/../templates/layouts/footer.php';
