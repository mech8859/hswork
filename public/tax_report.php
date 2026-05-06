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

// === 下載發票明細 CSV（進項/銷項共用邏輯，依代碼分組+小計）===
if (isset($_GET['action']) && in_array($_GET['action'], array('export_purchase', 'export_sales'), true)) {
    $isPurchase = ($_GET['action'] === 'export_purchase');
    $detail = $isPurchase ? $purchaseDetail : $salesDetail;

    // 過濾：僅「開立已確認」
    $rows = array_values(array_filter($detail, function($r) {
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

    $purchaseFmtLabels = array(
        '21' => '21 進項三聯式、電子計算機統一發票',
        '22' => '22 進項二聯式收銀機、載有稅額之其他憑證',
        '23' => '23 三聯式進貨退出或折讓',
        '24' => '24 二聯式進貨退出或折讓',
        '25' => '25 進項三聯式收銀機、電子發票',
    );
    $salesFmtLabels = array(
        '31' => '31 銷項三聯式、電子計算機統一發票',
        '32' => '32 銷項二聯式、二聯式收銀機統一發票',
        '33' => '33 三聯式銷貨退回或折讓證明單',
        '34' => '34 二聯式銷貨退回或折讓證明單',
        '35' => '35 銷項三聯式收銀機、電子發票',
    );
    $fmtLabels = $isPurchase ? $purchaseFmtLabels : $salesFmtLabels;
    $allowanceCodes = $isPurchase ? array('23', '24') : array('33', '34');
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

    $partyLabel = $isPurchase ? '供應商' : '客戶';
    $partyKey   = $isPurchase ? 'vendor_name' : 'customer_name';
    $partyTaxKey = $isPurchase ? 'vendor_tax_id' : 'customer_tax_id';
    $reportLabel = $isPurchase ? '進項' : '銷項';

    // 公司簡稱
    $companyShort = '全部';
    if ($companyTaxId === '94081455') $companyShort = '禾順';
    else if ($companyTaxId === '97002927') $companyShort = '政遠';

    // 申報期間格式化（2026-03-04 → 115年3-4月 / 2026-03 → 2026-03）
    $periodLabel = $period;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $period, $m)) {
        $rocYear = (int)$m[1] - 1911;
        $periodLabel = $rocYear . '年' . (int)$m[2] . '-' . (int)$m[3] . '月';
    }

    $fname = $companyShort . '_' . $periodLabel . '_' . $reportLabel . '發票明細.csv';
    header('Content-Type: text/csv; charset=utf-8');
    // RFC 5987：中文檔名要 URL encode
    header("Content-Disposition: attachment; filename=\"" . rawurlencode($fname) . "\"; filename*=UTF-8''" . rawurlencode($fname));
    $fp = fopen('php://output', 'w');
    fwrite($fp, "\xEF\xBB\xBF");

    fputcsv($fp, array('401 ' . $reportLabel . '發票明細（開立已確認）'));
    fputcsv($fp, array('申報期間', $period, '公司統編', $companyTaxId ?: '全部', '匯出時間', date('Y-m-d H:i:s')));
    fputcsv($fp, array());

    if ($isPurchase) {
        $headers = array('代碼', '聯式', '發票號碼', '日期', '申報期間', $partyLabel, '統編', '類型', '扣抵', '未稅金額', '稅額', '含稅金額', '備註');
    } else {
        $headers = array('代碼', '聯式', '發票號碼', '日期', '申報期間', $partyLabel, '統編', '類型', '未稅金額', '稅額', '含稅金額', '備註');
    }
    fputcsv($fp, $headers);

    // 依代碼分組
    $byCode = array();
    foreach ($rows as $r) {
        $code = !empty($r['invoice_format']) ? (string)$r['invoice_format'] : '_other';
        if (!isset($byCode[$code])) $byCode[$code] = array();
        $byCode[$code][] = $r;
    }
    // 代碼順序：依 fmtLabels 順序，未知代碼放最後
    $codeOrder = array_keys($fmtLabels);
    uksort($byCode, function($a, $b) use ($codeOrder) {
        $ia = array_search($a, $codeOrder); if ($ia === false) $ia = 999;
        $ib = array_search($b, $codeOrder); if ($ib === false) $ib = 999;
        return $ia - $ib;
    });

    $grandU = 0; $grandT = 0; $grandA = 0; $grandC = 0;
    foreach ($byCode as $code => $codeRows) {
        $codeLabel = isset($fmtLabels[$code]) ? $fmtLabels[$code] : ($code === '_other' ? '未標註聯式' : $code);
        $isAllow = in_array($code, $allowanceCodes, true);
        $sign = $isAllow ? -1 : 1;

        // 分組標題列
        fputcsv($fp, array_merge(array('▼ ' . $codeLabel . '（' . count($codeRows) . ' 筆）'), array_fill(0, count($headers) - 1, '')));

        $cU = 0; $cT = 0; $cA = 0;
        foreach ($codeRows as $r) {
            $u = (int)($r['amount_untaxed'] ?? 0);
            $t = (int)($r['tax_amount'] ?? 0);
            $a = (int)($r['total_amount'] ?? 0);
            $cU += $sign * $u; $cT += $sign * $t; $cA += $sign * $a;

            $codeOut = $code === '_other' ? '' : $code;
            $rowVals = array(
                $codeOut,
                $code !== '_other' && isset($fmtLabels[$code]) ? $fmtLabels[$code] : '',
                $r['invoice_number'] ?? '',
                $r['invoice_date'] ?? '',
                $fmtRP($r['report_period'] ?? '', $r['period'] ?? ''),
                $r[$partyKey] ?? '',
                $r[$partyTaxKey] ?? '',
                $r['invoice_type'] ?? '',
            );
            if ($isPurchase) {
                $deduct = !empty($r['deduction_category']) && isset($deductLabels[$r['deduction_category']])
                    ? $deductLabels[$r['deduction_category']]
                    : (($r['deduction_type'] ?? '') === 'deductible' ? '可扣抵' : (($r['deduction_type'] ?? '') === 'non_deductible' ? '不可扣抵' : ''));
                $rowVals[] = $deduct;
            }
            $rowVals[] = number_format($sign * $u);
            $rowVals[] = number_format($sign * $t);
            $rowVals[] = number_format($sign * $a);
            $rowVals[] = $r['note'] ?? '';
            fputcsv($fp, $rowVals);
        }

        // 代碼小計列（橫跨欄位）
        $subtotalRow = array('  └─ 小計（' . $codeLabel . '）', '', '', '', '', '', '', '');
        if ($isPurchase) $subtotalRow[] = '';  // 扣抵欄位
        $subtotalRow[] = number_format($cU);
        $subtotalRow[] = number_format($cT);
        $subtotalRow[] = number_format($cA);
        $subtotalRow[] = '';
        fputcsv($fp, $subtotalRow);
        fputcsv($fp, array());  // 空行分隔

        $grandU += $cU; $grandT += $cT; $grandA += $cA;
        $grandC += count($codeRows);
    }

    // 總合計
    $allowText = $isPurchase ? '23/24 退折已扣除' : '33/34 退折已扣除';
    $totalRow = array('合計（' . $allowText . '）', '', '', '', '', '', '', '');
    if ($isPurchase) $totalRow[] = '';
    $totalRow[] = number_format($grandU);
    $totalRow[] = number_format($grandT);
    $totalRow[] = number_format($grandA);
    $totalRow[] = $grandC . ' 筆';
    fputcsv($fp, $totalRow);

    fclose($fp);
    exit;
}

$pageTitle = '401 營業稅申報';
$currentPage = 'tax_report';
require __DIR__ . '/../templates/layouts/header.php';
require __DIR__ . '/../templates/accounting/tax_report.php';
require __DIR__ . '/../templates/layouts/footer.php';
